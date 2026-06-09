#!/usr/bin/env python3
"""Debug helper: verify the runtemplate REST API works behind Apache rewrites.

Logs in via the web UI (session cookie), then requests the same JSON endpoint
that companyapp.php and runtemplate.php AJAX calls use.

Usage:
    MF_LOGIN=vitex MF_PASSWORD=secret \\
        python3 api_runtemplate_debug.py [runtemplate_id]

Environment variables:
    MF_BASE_URL        Base URL of the /src/ folder
                       (default: http://localhost/multiflexi-web5/src)
    MF_LOGIN           Login name        (required)
    MF_PASSWORD        Password          (required)
    MF_HEADLESS        "0" to show the browser window (default: headless)
    GECKODRIVER        geckodriver path  (default: /usr/bin/geckodriver)

Exit code 0 = API returned JSON for the runtemplate; non-zero = failure.
"""
import json
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
RT_ID = sys.argv[1] if len(sys.argv) > 1 else "135"

if not LOGIN or not PASSWORD:
    sys.exit("Set MF_LOGIN and MF_PASSWORD environment variables.")

API_URL = f"{BASE}/api/VitexSoftware/MultiFlexi/1.0.0/runtemplate/{RT_ID}.json"

opts = Options()
if os.environ.get("MF_HEADLESS", "1") != "0":
    opts.add_argument("-headless")
drv = webdriver.Firefox(service=Service(GECKODRIVER), options=opts)
drv.set_window_size(1400, 900)

try:
    print("=" * 80)
    print("RUNTEMPLATE API DEBUG")
    print("=" * 80)
    print(f"API URL: {API_URL}")

    # --- login (session cookie is reused for API basic-auth bypass) ---
    print("\n[1] Logging in...")
    drv.get(f"{BASE}/login.php")
    time.sleep(1)
    btn = drv.find_element(By.ID, "signinbutton")
    form = btn.find_element(By.XPATH, "./ancestor::form[1]")
    form.find_element(By.NAME, "login").send_keys(LOGIN)
    form.find_element(By.NAME, "password").send_keys(PASSWORD)
    drv.execute_script("arguments[0].submit();", form)
    time.sleep(2)
    print(f"    after login URL: {drv.current_url}")

    # --- fetch API with the authenticated session ---
    print("\n[2] Requesting runtemplate JSON via fetch()...")
    script = """
        const url = arguments[0];
        const callback = arguments[arguments.length - 1];
        fetch(url, { credentials: 'same-origin' })
            .then(async (response) => {
                const body = await response.text();
                callback({
                    ok: response.ok,
                    status: response.status,
                    contentType: response.headers.get('content-type') || '',
                    body: body,
                });
            })
            .catch((error) => callback({ ok: false, status: 0, contentType: '', body: String(error) }));
    """
    result = drv.execute_async_script(script, API_URL)
    print(f"    HTTP status: {result['status']}")
    print(f"    Content-Type: {result['contentType']}")

    if result["status"] == 403:
        print("\nFAIL: 403 Forbidden — check src/.htaccess RewriteBase and that src/api/ exists.")
        sys.exit(2)

    if not result["ok"]:
        print(f"\nFAIL: unexpected response body:\n{result['body'][:500]}")
        sys.exit(3)

    try:
        payload = json.loads(result["body"])
    except json.JSONDecodeError:
        print(f"\nFAIL: response is not JSON:\n{result['body'][:500]}")
        sys.exit(4)

    print("\n[3] Parsed JSON keys:", ", ".join(sorted(payload.keys())))
    if str(payload.get("id", payload.get("runtemplate_id", ""))) == str(RT_ID):
        print(f"    runtemplate id matches: {RT_ID}")
    else:
        print(f"    note: top-level id field is {payload.get('id', payload.get('runtemplate_id', '(missing)'))}")

    print("\nOK: API endpoint is reachable and returns JSON.")
finally:
    drv.quit()