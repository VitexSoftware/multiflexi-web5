#!/usr/bin/env python3
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
    drv.get(f"{BASE}/login.php")
    btn = drv.find_element(By.ID, "signinbutton")
    form = btn.find_element(By.XPATH, "./ancestor::form[1]")
    form.find_element(By.NAME, "login").send_keys(LOGIN)
    form.find_element(By.NAME, "password").send_keys(PASSWORD)
    drv.execute_script("arguments[0].submit();", form)
    time.sleep(2)

    # user.php check
    drv.get(f"{BASE}/user.php?id=1")
    time.sleep(2)
    user_new = len(drv.find_elements(By.NAME, "new_password")) > 0
    user_confirm = len(drv.find_elements(By.NAME, "new_password_confirm")) > 0

    # profile.php check
    drv.get(f"{BASE}/profile.php")
    time.sleep(2)
    profile_current = len(drv.find_elements(By.NAME, "current_password")) > 0
    profile_new = len(drv.find_elements(By.NAME, "new_password")) > 0
    profile_confirm = len(drv.find_elements(By.NAME, "new_password_confirm")) > 0

    print("user.php new_password:", user_new)
    print("user.php new_password_confirm:", user_confirm)
    print("profile.php current_password:", profile_current)
    print("profile.php new_password:", profile_new)
    print("profile.php new_password_confirm:", profile_confirm)

    ok = user_new and user_confirm and profile_current and profile_new and profile_confirm
    sys.exit(0 if ok else 1)
finally:
    drv.quit()
