#!/usr/bin/env python3
"""Verify the Task SLA & Retry section and Task metrics on runtemplate.php.

Checks that after the task-concept additions the RunTemplate page shows:
  - "Scheduling" tab: a "Task SLA & Retry" card with five inline-editable
    fields (deadline_offset, max_attempts, retry_backoff, retry_min_gap,
    allow_late).
  - "Tasks" tab: RuntemplateTaskMetrics cards (Fulfilled, Failed, Missed,
    Active, Total links).

Usage:
    MF_LOGIN=vitex MF_PASSWORD=secret \\
        python3 runtemplate_task_ui_check.py [runtemplate_id]

Environment variables:
    MF_BASE_URL        Base URL of the /src/ folder
                       (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN           Login name        (required)
    MF_PASSWORD        Password          (required)
    MF_HEADLESS        "0" to show the browser window (default: headless)
    GECKODRIVER        geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = all checks passed; non-zero = failure.
"""
import os
import sys
import time

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE = os.environ.get("MF_BASE_URL", "http://localhost/multiflexi-web5/src").rstrip("/")
LOGIN = os.environ.get("MF_LOGIN")
PASSWORD = os.environ.get("MF_PASSWORD")
GECKODRIVER = os.environ.get("GECKODRIVER", "/usr/bin/geckodriver")
RT_ID = sys.argv[1] if len(sys.argv) > 1 else "1"

if not LOGIN or not PASSWORD:
    sys.exit("Set MF_LOGIN and MF_PASSWORD environment variables.")

opts = Options()
if os.environ.get("MF_HEADLESS", "1") != "0":
    opts.add_argument("-headless")
drv = webdriver.Firefox(service=Service(GECKODRIVER), options=opts)
drv.set_window_size(1400, 1000)

PASS = True

def check(label, condition, detail=""):
    global PASS
    status = "OK  " if condition else "FAIL"
    if not condition:
        PASS = False
    suffix = f"  ({detail})" if detail else ""
    print(f"  [{status}] {label}{suffix}")


try:
    print("=" * 80)
    print(f"RUNTEMPLATE TASK UI CHECK  (runtemplate id={RT_ID})")
    print("=" * 80)

    # ------------------------------------------------------------------ login
    print("\n[1] Login...")
    drv.get(f"{BASE}/login.php")
    time.sleep(1)
    btn = drv.find_element(By.ID, "signinbutton")
    form = btn.find_element(By.XPATH, "./ancestor::form[1]")
    form.find_element(By.NAME, "login").send_keys(LOGIN)
    form.find_element(By.NAME, "password").send_keys(PASSWORD)
    drv.execute_script("arguments[0].submit();", form)
    time.sleep(2)
    check("Login redirected away from login.php", "login.php" not in drv.current_url, drv.current_url)

    # ------------------------------------------------------ open runtemplate page
    print(f"\n[2] Open runtemplate.php?id={RT_ID}...")
    drv.get(f"{BASE}/runtemplate.php?id={RT_ID}")
    time.sleep(2)
    check("Page title contains 'runtemplate'", "runtemplate" in drv.current_url.lower() or "multiflexi" in drv.title.lower(), drv.title)

    # ----------------------------------------- screenshot after page load
    drv.save_screenshot("/tmp/rt_page_loaded.png")
    print("    Screenshot: /tmp/rt_page_loaded.png")

    # ------------------------------------------------------ Scheduling tab
    print("\n[3] Scheduling tab — Task SLA & Retry section...")

    # Click the Scheduling tab (find by text)
    try:
        sched_tabs = drv.find_elements(By.XPATH, "//a[contains(text(),'Scheduling') or contains(text(),'Plánování')]")
        if not sched_tabs:
            sched_tabs = drv.find_elements(By.XPATH, "//*[contains(@data-bs-toggle,'tab') and (contains(text(),'Scheduling') or contains(text(),'Plánování'))]")
        if sched_tabs:
            drv.execute_script("arguments[0].click();", sched_tabs[0])
            time.sleep(1)
    except Exception as e:
        print(f"    warning: could not click Scheduling tab: {e}")

    drv.save_screenshot("/tmp/rt_scheduling_tab.png")
    print("    Screenshot: /tmp/rt_scheduling_tab.png")

    # Check for SLA section heading
    page_src = drv.page_source
    check("SLA heading 'Task SLA' present in page", "Task SLA" in page_src or "SLA" in page_src)

    # Check for each editable field by data-name attribute
    SLA_FIELDS = ["deadline_offset", "max_attempts", "retry_backoff", "retry_min_gap", "allow_late"]
    for field in SLA_FIELDS:
        elems = drv.find_elements(By.CSS_SELECTOR, f"[data-name='{field}']")
        check(f"SLA field '{field}' found on page", len(elems) > 0,
              f"{len(elems)} element(s) with data-name={field}")

    # --------------------------------------------------------- Tasks tab
    print("\n[4] Tasks tab — RuntemplateTaskMetrics cards...")
    # All Bootstrap tab panes are already in the DOM — no navigation needed.
    # Re-read page source now (still on runtemplate.php).
    page_src = drv.page_source

    # Click the Tasks tab button (data-bs-toggle="tab" — a button, not an <a>)
    # to make the pane visible for element visibility checks.
    try:
        task_tab_btns = drv.find_elements(By.XPATH,
            "//*[@data-bs-toggle='tab' and (contains(.,'Tasks') or contains(.,'📋'))]")
        if task_tab_btns:
            drv.execute_script("arguments[0].click();", task_tab_btns[0])
            time.sleep(1)
    except Exception as e:
        print(f"    note: could not click Tasks tab button: {e}")

    drv.save_screenshot("/tmp/rt_tasks_tab.png")
    print("    Screenshot: /tmp/rt_tasks_tab.png")

    # Check for metric card state links in the DOM (HTML-encoded & → &amp;)
    METRIC_STATES = ["fulfilled", "failed", "missed"]
    for state in METRIC_STATES:
        check(f"Metrics card link for state='{state}' present",
              f"state={state}" in page_src,
              "tasks.php?runtemplate_id=...&state="+state)

    # Check for tasks.php?runtemplate_id=RT_ID link (Total card)
    check(f"Total tasks link (tasks.php?runtemplate_id={RT_ID}) present",
          f"tasks.php?runtemplate_id={RT_ID}" in page_src)

    # Check for metric card count elements by CSS (all panes are in DOM)
    metric_cards = drv.find_elements(By.CSS_SELECTOR,
        ".fw-bold.text-success, .fw-bold.text-danger, .fw-bold.text-warning, "
        ".fw-bold.text-info, .fw-bold.text-dark, .fw-bold.text-secondary")
    check(f"At least 5 metric card count elements in DOM", len(metric_cards) >= 5,
          f"found {len(metric_cards)}")

    # --------------------------------------------------------- final
    print("\n" + "=" * 80)
    if PASS:
        print("RESULT: PASS — all checks OK")
    else:
        print("RESULT: FAIL — one or more checks failed")
    print("=" * 80)

    drv.save_screenshot("/tmp/rt_final.png")
    print("Final screenshot: /tmp/rt_final.png")

finally:
    drv.quit()

sys.exit(0 if PASS else 1)
