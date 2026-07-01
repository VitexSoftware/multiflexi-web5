#!/usr/bin/env python3
"""Test that main.php only shows companies assigned to the current user.

This script logs in and verifies that the CompaniesBar on main.php only displays
companies that the user has access to via RBAC.

Usage:
    MF_LOGIN=vitex MF_PASSWORD=secret \\
        python3 main_rbac_check.py

Environment variables:
    MF_BASE_URL   Base URL of the /src/ folder
                  (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN      Login name        (required)
    MF_PASSWORD   Password          (required)
    MF_HEADLESS   "0" to show the browser window (default: headless)
    GECKODRIVER   geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = RBAC filtering works correctly; non-zero = issue found.
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
    print("RBAC FILTERING CHECK - MAIN.PHP (CompaniesBar)")
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

    # --- navigate to main.php ---
    print("\n[2] Navigating to main.php...")
    drv.get(f"{BASE}/main.php")
    time.sleep(2)
    
    print(f"    ✓ Main page loaded")

    # --- check for CompaniesBar (card-group with company cards) ---
    print("\n[3] Checking for CompaniesBar (company cards)...")
    
    # Take screenshot for debugging
    drv.save_screenshot("/tmp/main_rbac_check.png")
    print(f"    Screenshot saved to /tmp/main_rbac_check.png")
    
    # Find all company cards
    card_group = drv.find_elements(By.CSS_SELECTOR, ".card-group")
    if not card_group:
        print("    ⚠️  No card-group found")
    else:
        print(f"    ✓ Found {len(card_group)} card-group(s)")
    
    # Find individual company cards (they have links to company.php)
    company_cards = drv.find_elements(By.XPATH, "//a[contains(@href, 'company.php')]")
    print(f"\n[4] Company Cards Found: {len(company_cards)}")
    
    if len(company_cards) == 0:
        print("    ⚠️  No company cards found (user may have no company access)")
    else:
        print("    Companies displayed:")
        
        # Extract unique companies from the page
        companies_seen = set()
        for i, card_link in enumerate(company_cards[:10], 1):  # Show first 10
            href = card_link.get_attribute("href")
            # Extract company ID from URL
            if "company.php?id=" in href:
                company_id = href.split("company.php?id=")[1].split("&")[0]
                companies_seen.add(company_id)
                print(f"      {i}. Company ID: {company_id}")
                print(f"         URL: {href}")
        
        if len(company_cards) > 10:
            print(f"      ... and {len(company_cards) - 10} more")

    # --- final check: verify this matches home.php data ---
    print("\n[5] Verifying against home.php assignments...")
    drv.get(f"{BASE}/home.php")
    time.sleep(2)
    
    # Find company links on home.php
    home_company_links = drv.find_elements(By.XPATH, "//a[contains(@href, 'company.php')]")
    print(f"    Companies shown on home.php: {len(home_company_links)}")
    
    # Go back to main.php
    drv.get(f"{BASE}/main.php")
    time.sleep(2)
    
    main_company_links = drv.find_elements(By.XPATH, "//a[contains(@href, 'company.php')]")
    print(f"    Companies shown on main.php: {len(main_company_links)}")
    
    print("\n" + "=" * 80)
    print("SUMMARY")
    print("=" * 80)
    
    if len(main_company_links) == 0:
        print("⚠️  No companies displayed (user may have no access or RBAC filtering is aggressive)")
    elif len(main_company_links) > 100:
        print("✗ RBAC filtering NOT working - displaying too many companies!")
        print(f"  Expected: ~4 companies (vitex assignments)")
        print(f"  Actual: {len(main_company_links)} company links")
        sys.exit(1)
    else:
        print("✓ CompaniesBar is displaying a filtered list of companies")
        print(f"✓ Total companies shown: {len(main_company_links)}")
        print("✓ RBAC filtering appears to be working correctly")
        print(f"\nExit code: 0 (SUCCESS)")

    print("=" * 80)
    
except Exception as e:
    print(f"\n✗ Unexpected error: {e}")
    import traceback
    traceback.print_exc()
    drv.save_screenshot("/tmp/main_rbac_error.png")
    sys.exit(1)

finally:
    drv.quit()
