#!/usr/bin/env python3
"""Test file uploading on the schedule.php form.

Navigates to schedule.php for RunTemplate 1 (multiflexi-probe), uploads a JSON
file via the FILE_UPLOAD field, submits the form, and verifies the job is
created without a PHP warning about "Undefined array key".

Usage:
    MF_LOGIN=vitex MF_PASSWORD=u3fZzwfkQ59hcST \\
        python3 file_upload_check.py

Environment variables:
    MF_BASE_URL       Base URL of the /src/ folder
                      (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN          Login name        (required)
    MF_PASSWORD       Password          (required)
    MF_RUNTEMPLATE_ID RunTemplate id with a FILE_UPLOAD field (default: 1)
    MF_HEADLESS       "0" to show the browser window (default: headless)
    GECKODRIVER       geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = file uploaded and job scheduled without PHP warnings.
"""
import json
import os
import sys
import tempfile
import time

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

BASE = os.environ.get("MF_BASE_URL", "http://localhost/multiflexi-web5/src").rstrip("/")
LOGIN = os.environ.get("MF_LOGIN")
PASSWORD = os.environ.get("MF_PASSWORD")
RT_ID = os.environ.get("MF_RUNTEMPLATE_ID", "1")
GECKODRIVER = os.environ.get("GECKODRIVER", "/usr/bin/geckodriver")

if not LOGIN or not PASSWORD:
    sys.exit("Set MF_LOGIN and MF_PASSWORD environment variables.")

opts = Options()
if os.environ.get("MF_HEADLESS", "1") != "0":
    opts.add_argument("-headless")
drv = webdriver.Firefox(service=Service(GECKODRIVER), options=opts)
drv.set_window_size(1400, 1200)

# Create a temporary JSON file to upload
upload_file = tempfile.NamedTemporaryFile(
    suffix=".json", prefix="selenium_upload_", delete=False, mode="w"
)
json.dump({"test": "selenium_file_upload", "timestamp": time.time()}, upload_file)
upload_file.close()
UPLOAD_PATH = upload_file.name

errors = []

try:
    print("=" * 80)
    print("FILE UPLOAD CHECK - SCHEDULE.PHP")
    print("=" * 80)
    print(f"  Base URL      : {BASE}")
    print(f"  RunTemplate   : {RT_ID}")
    print(f"  Upload file   : {UPLOAD_PATH}")

    # --- login ---
    print("\n[1] Logging in...")
    drv.get(f"{BASE}/login.php")
    time.sleep(1)

    btn = drv.find_element(By.ID, "signinbutton")
    form = btn.find_element(By.XPATH, "./ancestor::form[1]")
    form.find_element(By.NAME, "login").send_keys(LOGIN)
    form.find_element(By.NAME, "password").send_keys(PASSWORD)
    drv.execute_script("arguments[0].submit();", form)
    time.sleep(2)
    print(f"    Current URL: {drv.current_url}")
    print("    ✓ Login completed")

    # --- open the schedule form ---
    print(f"\n[2] Opening schedule form for RunTemplate {RT_ID}...")
    drv.get(f"{BASE}/schedule.php?id={RT_ID}")
    time.sleep(2)
    drv.save_screenshot("/tmp/file_upload_check_form.png")
    print("    Screenshot saved to /tmp/file_upload_check_form.png")

    # --- look for a file input ---
    print("\n[3] Locating file input field...")
    file_inputs = drv.find_elements(By.CSS_SELECTOR, "input[type='file']")

    if not file_inputs:
        print("    ⚠  No file input found on schedule form — RunTemplate may not "
              "have a file-path field. Checking page text...")
        page_text = drv.find_element(By.TAG_NAME, "body").text
        print("    First 400 chars:", page_text[:400])
        errors.append("No file input found on schedule form")
    else:
        file_input = file_inputs[0]
        field_name = file_input.get_attribute("name") or "(no name)"
        print(f"    ✓ Found file input, name='{field_name}'")

        # --- send the file path ---
        print(f"\n[4] Uploading file: {UPLOAD_PATH}")
        file_input.send_keys(UPLOAD_PATH)
        time.sleep(1)

        # set the datetime field to "now" to avoid validation issues
        try:
            dt_input = drv.find_element(By.NAME, "when")
            now_str = time.strftime("%Y-%m-%dT%H:%M")
            drv.execute_script(
                "arguments[0].value = arguments[1];", dt_input, now_str
            )
        except Exception:
            pass

        # --- submit ---
        print("\n[5] Submitting the form...")
        submit_btn = drv.find_element(By.CSS_SELECTOR, "button[type='submit'], input[type='submit']")
        submit_btn.click()
        time.sleep(3)

        drv.save_screenshot("/tmp/file_upload_check_result.png")
        print("    Screenshot saved to /tmp/file_upload_check_result.png")
        print(f"    Current URL: {drv.current_url}")

        page_src = drv.page_source
        page_text = drv.find_element(By.TAG_NAME, "body").text

        # --- check for PHP warnings ---
        php_warning_markers = [
            "Warning:",
            "Undefined array key",
            "Fatal error",
            "Parse error",
        ]
        found_warnings = [m for m in php_warning_markers if m in page_src]

        if found_warnings:
            print(f"\n    ✗ PHP warning(s) detected in response: {found_warnings}")
            errors.append(f"PHP warnings present: {found_warnings}")
        else:
            print("\n    ✓ No PHP warnings detected")

        # --- check for success indicators ---
        success_markers = ["job.php", "Job details", "SandClock", "schedule", "Start after"]
        found_success = [m for m in success_markers if m in page_src]

        if found_success:
            print(f"    ✓ Job scheduling indicators found: {found_success}")
        else:
            print("    ⚠  No job-scheduling success indicators found")
            print("    Page text (first 600 chars):")
            print("    " + page_text[:600].replace("\n", "\n    "))
            errors.append("No success indicators after form submission")

finally:
    drv.quit()
    try:
        os.unlink(UPLOAD_PATH)
    except OSError:
        pass

print("\n" + "=" * 80)
if errors:
    print("RESULT: FAIL")
    for e in errors:
        print(f"  ✗ {e}")
    sys.exit(1)
else:
    print("RESULT: PASS — file uploaded and job scheduled without PHP warnings")
    sys.exit(0)
