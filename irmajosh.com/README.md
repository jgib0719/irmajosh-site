# irmajosh.com — quick dev & ops notes

Overview
- Static public site in `public_html/` and a small secure single-page app under `public_html/secure/`.
- Google Sign-In uses a client ID from `/private/cal.ini` and is exported to JS by `/public_html/secure/api/config.js.php`.

Quick fixes applied
- Inline module moved into `/public_html/js/login-init.js` to satisfy CSP.
- `login-exchange.js` (defines `window.handleCredentialResponse`) is now loaded before `login-init.js`.
- `secure/js/ui.js` now creates a minimal UI skeleton when `#app` is empty, avoiding runtime null errors.
- Basic styling added to `secure/css/styles.css` to make the generated skeleton usable.

Google OAuth notes
- OAuth client must include exact Authorized JavaScript origins. Use canonical roots only:
  - https://irmajosh.com
  - https://www.irmajosh.com (if you use it)
- Avoid port-specific entries during testing. After confirmed working, you may add ports for dev instances.
- Do not share client secrets; rotate if exposed.

Apache / CSP / COOP
- CSP header is set in `/etc/apache2/sites-enabled/irmajosh.com.conf`. It intentionally blocks inline scripts.
- COOP is set to `same-origin-allow-popups` to support Google Sign-In popups.
- To allow an inline script instead of externalizing it, add a sha256 hash to the `script-src` directive or implement nonces.

Testing
- Use Chrome Incognito to test sign-in flows after editing the OAuth origins.
- Dev: use `curl -D - https://irmajosh.com/` to inspect headers.

Next steps & growth
- Add CI to lint and run small smoke tests on deploy.
- Add a simple integration test that loads `/` and `/secure/` and asserts no CSP or origin errors.
- Consider adding a small build step to bundle/optimize JS for production.

Contact
- Keep the `private/cal.ini` and `private/` directory secure and out of VCS.
