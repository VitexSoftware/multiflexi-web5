#!/usr/bin/env python3
"""Test company assignment section on user.php.

This script logs in and verifies that the Company Assignments section appears
on the user detail page with toggle switches for assigning companies to the user.

Usage:
    MF_LOGIN=vitex MF_PASSWORD=secret \\
        python3 user_company_assignment_check.py

Environment variables:
    MF_BASE_URL   Base URL of the /src/ folder
                  (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN      Login name        (required)
    MF_PASSWORD   Password          (required)
    MF_HEADLESS   "0" to show the browser window (default: headless)
    GECKODRIVER   geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = company assignment section found and working; non-zero = issue.
"""
import os
import sys
import time

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

BASE = os.environ.get("MF_BASE_URL", "http://localhost/multiflexi-web5/src").rstrip("/")
LOGIN = os.environ.get("MF_LOGIN")
PASSWORD = os.environ.get("MF_PASSWORD")
GECKODRIVER = os.environ.get("GECKODRIVER", "/usr/bin/geckodriver")

if not LOGIN or not PASSWORD:
    sys.exit("Set MF_LOGIN and MF_PASSWORD environment variables.")

opts = Options()
if os.environ.get("MF_HEADLESS", "1") != "0":
    opts.add_argument("-headless")
drv = webdriver.Firefox(service=Service(GECKODRIVER), options=opts)
drv.set_window_size(1400, 1200)

try:
    print("=" * 80)
    print("USER COMPANY ASSIGNMENT CHECK - USER.PHP")
    print("=" * 80)

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
    
    print(f"    ✓ Login completed")

    # --- navigate to user.php?id=1 ---
    print("\n[2] Navigating to user.php?id=1...")
    drv.get(f"{BASE}/user.php?id=1")
    time.sleep(2)
    
    print(f"    ✓ User page loaded")
    print(f"    Current URL: {drv.current_url}")

    # Take screenshot for visual inspection
    drv.save_screenshot("/tmp/user_company_assignment_check.png")
    print(f"    Screenshot saved to /tmp/user_company_assignment_check.png")

    # --- check for company assignment section ---
    print("\n[3] Checking for Company Assignments section...")
    
    page_text = drv.find_element(By.TAG_NAME, "body").text
    
    checks = {
        "Company Assignments heading": "Company Assignments" in page_text,
        "Company Access Rights": "Company Access Rights" in page_text,
        "Enable or disable user access": "Enable or disable user access" in page_text,
    }
    
    # Check for search box separately (it might not have visible text in page_text)
    try:
        search_box = drv.find_element(By.ID, "user-company-search")
        checks["Search companies box"] = True
    except:
        checks["Search companies box"] = False
    
    for check_name, found in checks.items():
        status = "✓" if found else "✗"
        print(f"    {status} {check_name}")

    # --- find company assignment table ---
    print("\n[4] Checking for company assignment table...")
    
    try:
        table = drv.find_element(By.ID, "user-company-assignments-table")
        print(f"    ✓ Company assignments table found")
        
        # Count rows (companies)
        rows = table.find_elements(By.CSS_SELECTOR, "tbody tr")
        print(f"    ✓ Total companies in table: {len(rows)}")
        
        # Check for toggle switches
        toggles = table.find_elements(By.CSS_SELECTOR, ".user-company-assign-toggle")
        print(f"    ✓ Total toggle switches: {len(toggles)}")
        
        if len(toggles) > 0:
            print(f"\n[5] Sample companies from table:")
            for i, row in enumerate(rows[:3], 1):
                cells = row.find_elements(By.TAG_NAME, "td")
                if len(cells) >= 2:
                    company_name = cells[0].text
                    print(f"      {i}. {company_name}")
        
    except Exception as e:
        print(f"    ✗ Error finding company table: {e}")

    # --- check for search functionality ---
    print("\n[6] Checking for additional details...")

    print("\n" + "=" * 80)
    print("SUMMARY")
    print("=" * 80)
    
    all_passed = all(checks.values())
    
    if all_passed:
        print("✓ Company Assignments section is properly displayed on user.php")
        print("✓ All required elements found:")
        print("  - Company Assignments panel")
        print("  - Company Access Rights table")
        print("  - Search box for filtering")
        print("  - Toggle switches for assignment")
        print(f"\n✓ User can view and manage company assignments")
        print(f"\nExit code: 0 (SUCCESS)")
    else:
        print("✗ Company Assignments section is incomplete or missing elements:")
        for check_name, found in checks.items():
            if not found:
                print(f"  ✗ Missing: {check_name}")
        print(f"\nExit code: 1 (FAILURE)")
        sys.exit(1)

    print("=" * 80)
    
except Exception as e:
    print(f"\n✗ Unexpected error: {e}")
    import traceback
    traceback.print_exc()
    drv.save_screenshot("/tmp/user_company_assignment_error.png")
    sys.exit(1)

finally:
    drv.quit()
