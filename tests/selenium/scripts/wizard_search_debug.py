#!/usr/bin/env python3
"""Debug helper: drive the Activation Wizard with Selenium + geckodriver and
verify the live "Search by Name" filter on step 2 (Choose Application).

It logs in, completes step 1 (picks the first company), then types a keyword
into the name-search box and reports how many application cards stay visible.
Handy for manually reproducing/checking client-side filtering behaviour.

Usage:
    MF_LOGIN=vitex MF_PASSWORD=secret \\
        python3 wizard_search_debug.py [keyword]

Environment variables:
    MF_BASE_URL   Base URL of the /src/ folder
                  (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN      Login name        (required)
    MF_PASSWORD   Password          (required)
    MF_HEADLESS   "0" to show the browser window (default: headless)
    GECKODRIVER   geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = search box found and filter ran; non-zero = something missing.
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
KEYWORD = sys.argv[1] if len(sys.argv) > 1 else "Digest"

if not LOGIN or not PASSWORD:
    sys.exit("Set MF_LOGIN and MF_PASSWORD environment variables.")

opts = Options()
if os.environ.get("MF_HEADLESS", "1") != "0":
    opts.add_argument("-headless")
drv = webdriver.Firefox(service=Service(GECKODRIVER), options=opts)
drv.set_window_size(1400, 1200)


def visible_cards():
    """Return data-name of every .app-card whose grid column is displayed."""
    names = []
    for card in drv.find_elements(By.CSS_SELECTOR, ".app-card"):
        col = card.find_element(By.XPATH, "./ancestor::*[contains(@class,'col')][1]")
        if col.is_displayed():
            names.append(card.get_attribute("data-name"))
    return names


try:
    # --- login (the page has two login forms; target the one holding the
    #     visible "signinbutton" so we fill the right fields) ---
    drv.get(f"{BASE}/login.php")
    btn = drv.find_element(By.ID, "signinbutton")
    form = btn.find_element(By.XPATH, "./ancestor::form[1]")
    form.find_element(By.NAME, "login").send_keys(LOGIN)
    form.find_element(By.NAME, "password").send_keys(PASSWORD)
    # submit() avoids a cookie-consent banner that can intercept clicks
    drv.execute_script("arguments[0].submit();", form)
    time.sleep(2)
    print("after login URL:", drv.current_url)

    # --- step 1: the wizard keeps company_id in the session, so pick a
    #     company and POST to reach step 2 ---
    drv.get(f"{BASE}/activation-wizard.php?step=1")
    time.sleep(2)
    radios = drv.find_elements(By.CSS_SELECTOR, "input[name='company_id'][type='radio']")
    print(f"step1 company options: {len(radios)}")
    if not radios:
        sys.exit("No companies available to start the wizard.")
    drv.execute_script("arguments[0].checked = true;", radios[0])
    drv.execute_script("arguments[0].submit();", drv.find_element(By.ID, "wizardForm"))
    time.sleep(2)
    print("step2 URL:", drv.current_url)

    total = len(drv.find_elements(By.CSS_SELECTOR, ".app-card"))
    print(f"total app cards rendered: {total}")
    print(f"visible before search: {len(visible_cards())}")

    # NOTE: FormGroup overwrites the input's id via setTagID(), so the search
    # box is located by its stable "app-name-search" CSS class, not an id.
    boxes = drv.find_elements(By.CSS_SELECTOR, ".app-name-search")
    if not boxes:
        drv.save_screenshot("/tmp/wizard_search.png")
        sys.exit("'.app-name-search' input not present on step 2.")

    boxes[0].clear()
    boxes[0].send_keys(KEYWORD)
    time.sleep(1)

    vis = visible_cards()
    print(f"visible after typing '{KEYWORD}': {len(vis)}")
    for name in vis:
        print("   MATCH:", name)

    drv.save_screenshot("/tmp/wizard_search.png")
    print("screenshot saved to /tmp/wizard_search.png")
finally:
    drv.quit()
