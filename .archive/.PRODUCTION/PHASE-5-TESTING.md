# PHASE 5: TESTING

**Estimated Time:** 5-10 hours

**Purpose:** Comprehensive testing of security, functionality, PWA features, and recovery procedures

---

## Overview

Thorough testing before going live:
- Pre-deployment validation
- Security header verification
- OAuth and authentication flow
- Application functionality
- PWA features
- Performance testing
- Backup and recovery procedures

**Critical:** Test CSP nonce implementation to ensure NO duplicate headers and proper inline script protection.

---

## Testing Checklist

### Pre-Deployment Tests (30 minutes)
- [ ] Test Apache config syntax: `apachectl configtest`
- [ ] Verify PHP-FPM running: `systemctl status php8.2-fpm`
- [ ] Test database connection with PDO
- [ ] Verify MySQL timezone set to UTC
- [ ] Verify file permissions on storage directories (755, writable by www-data)
- [ ] Confirm .env file exists and has correct permissions (640)
- [ ] Verify .env ownership: `ls -l .env` (www-data:www-data or deployer:www-data)
- [ ] Check logs directory writable: `ls -ld storage/logs/`
- [ ] Verify Composer vendor directory exists
- [ ] Confirm all migrations applied: `SELECT * FROM _migrations;`
- [ ] Verify HTMX and FullCalendar self-hosted (check public_html/assets/js/vendor/)
- [ ] Test allowlist setup: `php scripts/bootstrap_allowlist.php test@example.com`
- [ ] Verify robots.txt exists with "Disallow: /"
- [ ] Check time sync active: `timedatectl status`

### Security Testing (2-3 hours)

#### HTTPS and Redirect
- [ ] Verify homepage loads over HTTPS
- [ ] Test HTTP to HTTPS redirect
- [ ] Verify SSL certificate valid (green padlock)
- [ ] Check certificate expiry date (should be 90 days)

#### Security Headers (CRITICAL)
Use browser DevTools → Network tab → Select any page load → Check Response Headers:

- [ ] **Strict-Transport-Security** present (HSTS)
  - Value: `max-age=31536000; includeSubDomains`
- [ ] **X-Frame-Options** present
  - Value: `SAMEORIGIN`
- [ ] **X-Content-Type-Options** present
  - Value: `nosniff`
- [ ] **X-Robots-Tag** present
  - Value: `noindex, nofollow`
- [ ] **Referrer-Policy** present
  - Value: `strict-origin-when-cross-origin`
- [ ] **Permissions-Policy** present
  - Value: `camera=(), microphone=(), geolocation=()`
- [ ] **Content-Security-Policy** present
  - MUST include: `script-src 'self' 'nonce-XXXXX'`
  - MUST include: `style-src 'self' 'nonce-XXXXX'`
  - MUST include: `report-uri /csp-report`
  - **CRITICAL: Verify CSP appears ONLY ONCE** (not duplicated from Apache + PHP)
- [ ] **Report-To** header present for CSP reporting

#### CSP Nonce Testing (CRITICAL)
- [ ] View page source (Ctrl+U)
- [ ] Find all inline `<script>` tags
- [ ] Verify EACH has `nonce="XXXXX"` attribute
- [ ] Find all inline `<style>` tags
- [ ] Verify EACH has `nonce="XXXXX"` attribute
- [ ] Copy nonce value from an inline script
- [ ] Compare with CSP header nonce value (should match exactly)
- [ ] Open browser console
- [ ] Verify no CSP violation errors for legitimate scripts
- [ ] Test: Add inline script WITHOUT nonce (should be blocked)
  - Expected: Console error "Refused to execute inline script..."

#### Session Security
- [ ] Open browser DevTools → Application → Cookies
- [ ] Verify cookie name is `__Host-PHPSESSID`
- [ ] Verify Secure flag is TRUE
- [ ] Verify HttpOnly flag is TRUE
- [ ] Verify SameSite is Lax
- [ ] Verify Path is `/`
- [ ] Verify Domain is BLANK (no domain attribute)

#### Static Assets
- [ ] Check Cache-Control headers on CSS files (max-age=31536000, immutable)
- [ ] Check Cache-Control headers on JS files (max-age=31536000, immutable)
- [ ] Verify compression active (Content-Encoding: br or gzip)
- [ ] Verify custom CSS loads without errors
- [ ] Verify no JavaScript console errors on page load

### Authentication Testing (1-2 hours)

#### OAuth Flow
- [ ] Click "Login with Google" button
- [ ] Verify redirect to Google OAuth consent screen
- [ ] Verify consent screen shows correct app name
- [ ] Verify scopes requested (openid, email, profile, calendar.events)
- [ ] Accept consent
- [ ] Verify successful redirect back to callback URL
- [ ] Verify session created (cookie set)
- [ ] Verify redirect to dashboard

#### OAuth State Parameter
- [ ] Inspect OAuth redirect URL
- [ ] Verify `state` parameter present (64-character hex string)
- [ ] Verify `code_challenge` present (PKCE)
- [ ] Test state validation: manually tamper with state parameter in callback URL
- [ ] Expected: Error message "Invalid state"

#### Email Allowlist
- [ ] Test login with unauthorized email (not in allowlist)
- [ ] Expected: Error message "Email not authorized"
- [ ] Verify user NOT created in database
- [ ] Test login with authorized email
- [ ] Expected: Successful login
- [ ] Verify user created in `users` table
- [ ] Verify encrypted tokens stored in `user_tokens` table

#### Session Management
- [ ] Login successfully
- [ ] Verify dashboard loads
- [ ] Close browser tab
- [ ] Reopen browser and navigate to dashboard
- [ ] Expected: Still logged in (session persists)
- [ ] Test logout (POST request with CSRF token)
- [ ] Verify session destroyed
- [ ] Try to access dashboard
- [ ] Expected: Redirect to login page

### Functional Testing (2-3 hours)

#### Navigation
- [ ] Test all navigation links work
- [ ] Test breadcrumbs (if applicable)
- [ ] Test 404 page (visit non-existent URL)
- [ ] Verify 404 page renders correctly

#### Dashboard
- [ ] Dashboard loads for authenticated users
- [ ] Verify welcome message with user name
- [ ] Verify dashboard widgets/components render

#### Calendar
- [ ] Calendar page loads
- [ ] FullCalendar renders from self-hosted assets
- [ ] Verify calendar shows current month
- [ ] Test create new event
- [ ] Verify event syncs to Google Calendar
- [ ] Test edit event
- [ ] Verify changes sync to Google Calendar
- [ ] Test delete event
- [ ] Verify deletion syncs to Google Calendar
- [ ] Test error handling (disconnect internet, try to sync)
- [ ] Expected: Graceful error message

#### Tasks
- [ ] Test create shared task
- [ ] Verify task appears for both users
- [ ] Test create private task
- [ ] Verify task only appears for creator
- [ ] Test edit task
- [ ] Test delete task
- [ ] Test task filtering (shared vs private)

#### Schedule Requests
- [ ] Test create schedule request
- [ ] Verify proposed time slots stored in database
- [ ] Test send schedule request email
- [ ] Verify email received (check SMTP logs if not)
- [ ] Test accept schedule request
- [ ] Verify acceptance email sent
- [ ] Test decline schedule request
- [ ] Verify decline email sent

#### Language Switching
- [ ] Test switch language to Indonesian
- [ ] Verify UI translates to Indonesian
- [ ] Verify missing translations fall back to English
- [ ] Test switch back to English
- [ ] Verify UI translates back

#### Error Pages
- [ ] Test 500 error page (temporarily break code in controller)
- [ ] Verify user-friendly error message (no stack trace)
- [ ] Verify error logged to storage/logs/error.log
- [ ] Verify PII redacted in logs (no tokens, emails)

### CSRF Protection Testing (30 minutes)
- [ ] Test form submission without CSRF token
- [ ] Expected: 403 Forbidden error
- [ ] Test form submission with invalid CSRF token
- [ ] Expected: 403 Forbidden error
- [ ] Test form submission with valid CSRF token
- [ ] Expected: Successful submission
- [ ] Verify logout requires POST + CSRF token
- [ ] Test: Try GET request to /logout
- [ ] Expected: Method not allowed or redirect

### Rate Limiting Testing (30 minutes)
- [ ] Test rate limiting on /auth/login
  - Make 100+ requests in 15 minutes
  - Expected: 429 Too Many Requests after 100th request
- [ ] Test rate limiting on /csp-report
  - Send 100+ CSP violation reports
  - Expected: 429 Too Many Requests
- [ ] Verify regular routes NOT rate limited
  - Make 150+ requests to /dashboard
  - Expected: All requests succeed (rate limit not applied)

### PWA Testing (1-2 hours)

#### Manifest
- [ ] Open DevTools → Application → Manifest
- [ ] Verify manifest loads without errors
- [ ] Verify app name: "IrmaJosh Calendar"
- [ ] Verify start_url: "/"
- [ ] Verify display: "standalone"
- [ ] Verify icons present (192x192, 512x512)
- [ ] Verify theme_color and background_color set

#### Service Worker
- [ ] Open DevTools → Application → Service Workers
- [ ] Verify service worker registered
- [ ] Verify service worker status: "activated and running"
- [ ] Verify cached resources in Cache Storage

#### Offline Mode
- [ ] Load application while online
- [ ] Open DevTools → Network → Set throttling to "Offline"
- [ ] Reload page
- [ ] Expected: Offline page loads from cache
- [ ] Navigate to cached pages
- [ ] Expected: Pages load from cache
- [ ] Try to access API endpoint
- [ ] Expected: Graceful error or cached data

#### Install Prompt
- [ ] Test on mobile device (Android/iOS)
- [ ] Verify "Add to Home Screen" prompt appears
- [ ] Install PWA to home screen
- [ ] Launch PWA from home screen
- [ ] Verify opens in standalone mode (no browser UI)
- [ ] Test on desktop (Chrome/Edge)
- [ ] Verify install icon in address bar
- [ ] Click install
- [ ] Verify PWA installs and opens in app window

#### Lighthouse Audit
- [ ] Open DevTools → Lighthouse
- [ ] Select "Progressive Web App" category
- [ ] Run audit
- [ ] Expected: Score 90+ (aim for 95+)
- [ ] Review and fix any failing criteria

### Performance Testing (30 minutes)
- [ ] Check page load times (<2 seconds)
- [ ] Verify OPcache active: `php -i | grep opcache`
- [ ] Test database query performance
- [ ] Check server load: `top` or `htop`
- [ ] Verify no memory leaks during extended use
- [ ] Test static asset caching (reload page, check Network tab for 304 responses)

### Responsive Design Testing (30 minutes)
- [ ] Test on mobile (320px width)
- [ ] Test on tablet (768px width)
- [ ] Test on desktop (1920px width)
- [ ] Verify navigation adapts to screen size
- [ ] Verify calendar adapts to screen size
- [ ] Verify forms are usable on mobile
- [ ] Test touch interactions on mobile

### Backup and Recovery Testing (1-2 hours)

#### Backup Creation
- [ ] Run manual backup: `bash scripts/backup.sh`
- [ ] Verify database backup created (.sql.gz.gpg)
- [ ] Verify storage backup created (.tar.gz.gpg)
- [ ] Verify backups are GPG encrypted
- [ ] Check backup file sizes (reasonable, not empty)

#### Backup Restoration
- [ ] Note current database state (row counts)
- [ ] Create test change (add a task)
- [ ] Restore from backup: `bash scripts/restore_backup.sh db.sql.gz.gpg storage.tar.gz.gpg`
- [ ] Enter GPG passphrase
- [ ] Verify restoration completes without errors
- [ ] Verify database restored to previous state (test change gone)
- [ ] Verify application still works
- [ ] Verify tokens still decrypt correctly

#### Migration Rollback
- [ ] Create pre-migration backup
- [ ] Run a test migration (add a column)
- [ ] Verify migration applied
- [ ] Simulate migration failure scenario
- [ ] Restore from pre-migration backup
- [ ] Verify database restored to pre-migration state

### Health Check Endpoint (15 minutes)
- [ ] Test health check: `curl https://irmajosh.com/health`
- [ ] Verify JSON response
- [ ] Verify includes: `{"status":"ok","database":"connected",...}`
- [ ] Test when database is down (stop MySQL)
- [ ] Expected: `{"status":"error","database":"disconnected"}`
- [ ] Restart MySQL

### CSP Reporting Endpoint (15 minutes)
- [ ] Trigger CSP violation (add inline script without nonce)
- [ ] Verify violation logged to storage/logs/security.log
- [ ] Check log entry includes violated directive
- [ ] Verify rate limiting prevents flood (max 100 reports per 15 min)

### Log Monitoring (15 minutes)
- [ ] Check error logs: `tail -f storage/logs/error.log`
- [ ] Verify no unexpected errors
- [ ] Verify PII redacted (no tokens visible)
- [ ] Check security logs: `tail -f storage/logs/security.log`
- [ ] Verify login events logged
- [ ] Verify logout events logged
- [ ] Verify failed login attempts logged

---

## Phase 5 Completion Checklist

### Pre-Deployment
- [ ] All pre-deployment tests passed
- [ ] No configuration errors
- [ ] All services running

### Security
- [ ] HTTPS working correctly
- [ ] All security headers present
- [ ] CSP nonce implementation verified (NO duplicates)
- [ ] Session security verified (__Host- prefix, correct flags)
- [ ] Static assets cached and compressed

### Authentication
- [ ] OAuth flow working end-to-end
- [ ] PKCE and state parameter validation working
- [ ] Email allowlist enforced
- [ ] Session management working correctly

### Functionality
- [ ] All navigation working
- [ ] Dashboard loading
- [ ] Calendar CRUD operations working
- [ ] Google Calendar sync working
- [ ] Tasks CRUD operations working
- [ ] Schedule requests working
- [ ] Email notifications working
- [ ] Language switching working
- [ ] Error pages rendering correctly

### Security Features
- [ ] CSRF protection working
- [ ] Rate limiting working on auth endpoints
- [ ] Rate limiting NOT applied to regular routes
- [ ] Method override restricted to POST + CSRF

### PWA
- [ ] Manifest accessible
- [ ] Service worker registered
- [ ] Offline mode working
- [ ] Install prompt working
- [ ] Lighthouse PWA score 90+

### Performance
- [ ] Page load times acceptable (<2s)
- [ ] OPcache active
- [ ] Static assets cached
- [ ] Compression active
- [ ] Responsive design working

### Backup & Recovery
- [ ] Backup creation working
- [ ] Backup encryption working
- [ ] Backup restoration tested and working
- [ ] Migration rollback procedure documented

### Monitoring
- [ ] Health check endpoint working
- [ ] CSP reporting working
- [ ] Error logs clean (no PII)
- [ ] Security logs capturing events

---

## Common Issues and Fixes

### CSP Violations for Legitimate Scripts
**Problem:** Console errors for inline scripts with nonce
**Fix:** Verify nonce attribute is correctly formatted: `nonce="<?= cspNonce() ?>"`

### Session Not Persisting
**Problem:** User logged out after closing browser
**Fix:** Check session cookie settings (SameSite, Secure, Path, Domain)

### Google Calendar Sync Failing
**Problem:** 401 errors when syncing
**Fix:** Token expired, user needs to re-authenticate

### Email Notifications Not Sending
**Problem:** Schedule request emails not received
**Fix:** Check SMTP configuration in .env, verify DNS records (SPF, DKIM, DMARC)

### Rate Limiting Too Aggressive
**Problem:** Legitimate users getting 429 errors
**Fix:** Review rate limit threshold (100 req/15 min), adjust if needed for auth endpoints only

---

## Next Steps

✅ **Phase 5 Complete!**

Proceed to **Phase 6: Operations Setup** to:
- Configure automated maintenance
- Set up monitoring and alerting
- Document recovery procedures
- Create maintenance schedules

---

**PHASE 5 - TESTING v1.0 - October 22, 2025**
