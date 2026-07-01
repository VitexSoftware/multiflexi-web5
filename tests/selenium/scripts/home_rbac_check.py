#!/usr/bin/env python3
"""Test RBAC privileges display on home.php page.

This script logs in to MultiFlexi and verifies that the RBAC Privileges & Access
section is displayed correctly on the home page, showing:
- Access Control Status (Active/No Access)
- Number of assigned companies
- Current role information
- Data filtering status
- List of assigned companies (if any)

Usage:
    MF_LOGIN=vitex MF_PASSWORD=secret \\
        python3 home_rbac_check.py

Environment variables:
    MF_BASE_URL   Base URL of the /src/ folder
                  (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN      Login name        (required)
    MF_PASSWORD   Password          (required)
    MF_HEADLESS   "0" to show the browser window (default: headless)
    GECKODRIVER   geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = RBAC section found and valid; non-zero = missing or invalid.
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
    print("RBAC PRIVILEGES CHECK - HOME.PHP")
    print("=" * 80)

    # --- login ---
    print("\n[1] Logging in...")
    drv.get(f"{BASE}/login.php")
    time.sleep(1)
    
    # Find the visible login form (there may be multiple)
    btn = drv.find_element(By.ID, "signinbutton")
    form = btn.find_element(By.XPATH, "./ancestor::form[1]")
    form.find_element(By.NAME, "login").send_keys(LOGIN)
    form.find_element(By.NAME, "password").send_keys(PASSWORD)
    drv.execute_script("arguments[0].submit();", form)
    time.sleep(2)
    
    print(f"    ✓ Login completed")
    print(f"    Current URL: {drv.current_url}")

    # --- navigate to home.php ---
    print("\n[2] Navigating to home.php...")
    drv.get(f"{BASE}/home.php")
    time.sleep(2)
    
    print(f"    ✓ Home page loaded")
    print(f"    Current URL: {drv.current_url}")

    # --- check for RBAC card ---
    print("\n[3] Checking for RBAC Privileges & Access section...")
    
    # Get all text on page for debugging
    page_text = drv.find_element(By.TAG_NAME, "body").text
    
    # Save screenshot for visual inspection
    drv.save_screenshot("/tmp/home_rbac_check.png")
    print(f"    Screenshot saved to /tmp/home_rbac_check.png")
    
    # Check for RBAC in page text
    rbac_found = False
    if "RBAC" in page_text or "Privileges" in page_text or "Access Control Status" in page_text:
        rbac_found = True
        print("    ✓ RBAC section found in page content")
    else:
        print("    ⚠️  RBAC text not found in page")
        print(f"\n    Page contains {len(page_text)} characters")
        print("    First 500 characters of page:")
        print("    " + page_text[:500].replace("\n", "\n    "))
        print("    ...")
    
    if not rbac_found:
        print("\n    Detailed debugging:")
        # Check all cards on page
        cards = drv.find_elements(By.CSS_SELECTOR, ".card")
        print(f"    Found {len(cards)} card elements")
        for i, card in enumerate(cards, 1):
            card_text = card.text[:100] if card.text else "(empty)"
            print(f"      Card {i}: {card_text}")
        sys.exit("✗ RBAC section NOT found - check screenshot at /tmp/home_rbac_check.png")

    # --- extract RBAC information ---
    print("\n[4] Extracting RBAC information...")
    
    # Check for key RBAC elements
    checks = {
        "Access Control Status": "Access Control Status" in page_text,
        "Companies Assigned": "Companies Assigned" in page_text,
        "Current Role": "Current Role" in page_text or "Viewer" in page_text,
        "Data Filtering": "Data Filtering" in page_text,
    }
    
    for check_name, found in checks.items():
        status = "✓" if found else "✗"
        print(f"    {status} {check_name}")
    
    # Check if any companies are assigned
    company_count = 0
    try:
        # Look for company list items
        company_items = drv.find_elements(By.XPATH, "//a[contains(@href, 'company.php')]")
        company_count = len(company_items)
        print(f"\n[5] Company Assignments Found: {company_count}")
        
        if company_count > 0:
            print("    Companies:")
            for i, company_item in enumerate(company_items, 1):
                company_name = company_item.text
                company_url = company_item.get_attribute("href")
                print(f"      {i}. {company_name}")
                print(f"         URL: {company_url}")
        else:
            # Check if there's a "no access" message
            if "not been assigned" in page_text or "No companies" in page_text or "No Access" in page_text:
                print("    ⚠️  No companies assigned (expected for new/restricted users)")
            else:
                print("    ℹ️  No companies found in list (may indicate restricted access)")
    except Exception as e:
        print(f"    ⚠️  Could not enumerate companies: {e}")

    # --- check for badges/status indicators ---
    print("\n[6] Checking for status indicators...")
    
    badges = drv.find_elements(By.CSS_SELECTOR, ".badge")
    print(f"    Found {len(badges)} badge elements")
    
    for badge in badges:
        text = badge.text.strip()
        if text:
            print(f"      • {text}")

    # --- final summary ---
    print("\n" + "=" * 80)
    print("SUMMARY")
    print("=" * 80)
    
    all_passed = all(checks.values())
    
    if all_passed:
        print("✓ RBAC section is properly displayed on home.php")
        print("✓ All required elements found:")
        print("  - Access Control Status indicator")
        print("  - Company assignment count")
        print("  - Current role information")
        print("  - Data filtering status")
        if company_count > 0:
            print(f"✓ User has access to {company_count} company(ies)")
        else:
            print("⚠️  User has no company assignments (this may be expected)")
        print("\nExit code: 0 (SUCCESS)")
    else:
        print("✗ RBAC section is incomplete or missing required elements:")
        for check_name, found in checks.items():
            if not found:
                print(f"  ✗ Missing: {check_name}")
        print("\nExit code: 1 (FAILURE)")
        sys.exit(1)

    print("=" * 80)
    
except Exception as e:
    print(f"\n✗ Unexpected error: {e}")
    import traceback
    traceback.print_exc()
    drv.save_screenshot("/tmp/home_rbac_error.png")
    sys.exit(1)

finally:
    drv.quit()
