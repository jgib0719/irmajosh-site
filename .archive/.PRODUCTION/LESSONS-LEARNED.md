# Lessons Learned - IrmaJosh.com

**Project:** IrmaJosh Calendar  
**Date Range:** October 22-25, 2025  
**Phase:** Phase 5 Testing & OAuth Implementation + Post-Launch Fixes

---

## Critical Issues Discovered During Phase 5 Testing

### 1. Database Schema vs Code Mismatches

**Problem:** Multiple instances where code expected different column names than what existed in the database schema.

**Specific Issues:**
- **User Model:** Code used `google_sub` but database had `google_user_id`
- **User Model:** Code used `picture_url` but database had `picture`
- **User Model:** Code tried to update `last_login_at` column that didn't exist in schema
- **UserToken Model:** Code tried to insert separate `access_token`, `refresh_token`, `expires_at` columns, but schema had single `encrypted_tokens` JSON blob
- **ScheduleRequest Model:** Code used `user_id` in WHERE clause but schema had `sender_id` and `recipient_id`

**Root Cause:** Database migrations were created but code models weren't updated to match, or vice versa. No automated validation between schema and model expectations.

**Impact:** OAuth login failed repeatedly with SQL errors until each mismatch was manually discovered and fixed.

**Lesson:** Always verify that model code matches the actual database schema. Migration files are the source of truth. When schema changes, all related models must be updated immediately.

---

### 2. Environment Variable Naming Inconsistencies

**Problem:** Code expected `APP_SECRET` but .env file had `APP_SECRET_CURR` (for key rotation support).

**Specific Issue:**
- UserToken encryption looked for `env('APP_SECRET')` 
- But .env only had `APP_SECRET_CURR=...`
- Error: "APP_SECRET not configured" despite key being present

**Root Cause:** Key rotation feature added `_CURR` and `_PREV` suffixes but code wasn't updated to use new naming convention.

**Impact:** OAuth callback failed at token encryption stage after user creation succeeded.

**Lesson:** When implementing feature changes (like key rotation), update ALL code references. Search entire codebase for the variable name before making changes.

---

### 3. Session Cookie SameSite=Strict Broke OAuth Flow

**Problem:** Session cookie with `SameSite=Strict` prevented OAuth state from being available during callback.

**Specific Issue:**
- OAuth login stored `oauth_state` in session
- Redirected user to Google
- Google redirected back to callback URL
- Browser didn't send session cookie because SameSite=Strict blocks cross-site redirects
- Session was empty, no oauth_state found, validation failed

**Root Cause:** Overly strict security setting without understanding OAuth redirect flow requirements.

**Impact:** OAuth flow failed with "OAuth state not found in session" error until SameSite changed to Lax.

**Lesson:** SameSite=Strict is too restrictive for OAuth flows. Use SameSite=Lax which allows cookies on safe top-level navigations (like OAuth redirects) while still protecting against CSRF.

---

### 4. Apache DirectoryIndex + RewriteRule Conflict

**Problem:** Apache was trying index.html, index.cgi, index.pl before index.php, each triggering RewriteRule and creating internal redirects that resulted in 404 status.

**Specific Issue:**
- DirectoryIndex tried multiple files
- Each non-existent file matched RewriteCond `!-f`
- RewriteRule sent to index.php with `[PT]` flag
- Multiple internal redirects confused Apache's status handling
- Final HTTP status was 404 despite content rendering correctly

**Root Cause:** Using both DirectoryIndex and RewriteRule in same context created conflicting behaviors.

**Impact:** All routed pages returned HTTP 404 status while still rendering content correctly - confusing for search engines and debugging.

**Lesson:** For front controller pattern, use FallbackResource instead of RewriteRule. It's simpler and avoids conflicts with DirectoryIndex.

---

### 5. HEAD Request Method Not Handled

**Problem:** Routes only defined GET methods, so HEAD requests returned 404 from router.

**Specific Issue:**
- Browser/tools send HEAD requests to check resources
- Router only matched GET, POST, PUT, DELETE methods
- HEAD requests fell through to 404 handler

**Root Cause:** Didn't account for HEAD method which should be handled identically to GET.

**Impact:** HEAD requests returned 404, affecting browser preflight checks and monitoring tools.

**Lesson:** Always treat HEAD requests as GET in router logic. HEAD is semantically identical to GET but returns only headers.

---

### 6. .env File Permissions Too Restrictive

**Problem:** .env file with 600 permissions (owner-only) prevented Apache (www-data group) from reading it.

**Specific Issue:**
- .env owned by `owner:owner` with 600 permissions
- Apache runs as `www-data` user
- PHP-FPM couldn't read environment variables
- Site returned 500 errors

**Root Cause:** Security best practice of restrictive permissions applied without considering www-data group access needs.

**Impact:** Entire site down with 500 errors.

**Lesson:** .env should be 640 with www-data group ownership for Apache/PHP-FPM to read while keeping it private from other users.

---

### 7. OPcache validate_timestamps in Production

**Problem:** With `opcache.validate_timestamps=0` in production, code changes require PHP-FPM reload to take effect.

**Specific Issue:**
- Made code changes
- Changes didn't apply
- Forgot OPcache was caching old code
- Wasted time debugging "old" code

**Root Cause:** Production performance optimization (no timestamp checking) means manual cache invalidation required.

**Impact:** Confusion during debugging, code changes appeared to not work.

**Lesson:** Always run `sudo systemctl reload php8.4-fpm` after code changes in production. Document this clearly for all developers.

---

## Post-Launch Issues (October 25, 2025)

### 8. Asset Path Mismatches Between Views and File Structure

**Problem:** HTML views referenced assets at incorrect paths that didn't match the actual file structure.

**Specific Issues:**
- View referenced `/assets/vendor/htmx/htmx.min.js` but file was at `/assets/js/vendor/htmx.min.js`
- Service worker precached wrong HTMX path, causing failed cache initialization
- Browser showed 404 errors and refused to execute scripts due to MIME type mismatch

**Root Cause:** Asset organization changed during development but view templates weren't updated to match new structure.

**Impact:** HTMX library failed to load, breaking all HTMX-dependent functionality. Service worker installation failed.

**Lesson:** When reorganizing assets, search all view files for asset references and update paths. Use asset version helpers consistently to avoid browser caching stale paths.

---

### 9. Incomplete CSS for Dashboard Components

**Problem:** Dashboard view used CSS classes that weren't defined in stylesheet, resulting in unstyled page.

**Specific Issues:**
- View used `.dashboard`, `.dashboard-grid`, `.dashboard-card` classes
- CSS only had `.dashboard-stats` which wasn't used anywhere
- Page rendered as plain unstyled HTML despite CSS file loading correctly

**Root Cause:** View templates were created/modified without corresponding CSS being written. No visual testing after changes.

**Impact:** Dashboard appeared broken with no visual hierarchy, spacing, or card layouts.

**Lesson:** When creating new views or modifying markup, ensure corresponding CSS exists. Test visual appearance immediately after any HTML/CSS changes. Use browser DevTools to verify all classes have styles.

---

### 10. Service Worker Aggressive Caching Prevented Updates

**Problem:** Service worker cached old versions of files and continued serving them even after files were updated on server.

**Specific Issues:**
- Updated CSS and JavaScript files but browser continued showing old cached versions
- Cache version bumps didn't always trigger service worker update
- Users had to manually unregister service worker to see changes

**Root Cause:** Service worker cache strategy too aggressive. Browser doesn't always detect service worker file changes immediately.

**Impact:** Bug fixes and style improvements invisible to users until manual cache clear.

**Lesson:** Increment service worker CACHE_VERSION with timestamp on every deployment. Consider adding cache-busting timestamps to asset URLs. For critical fixes, instruct users to hard refresh or clear site data.

---

### 11. Database Schema Mismatch - Task Type vs is_shared

**Problem:** Code used `type` column ('shared'/'private' string) but database schema used `is_shared` (boolean).

**Specific Issues:**
- TaskController expected 'type' parameter
- Task model tried to query `WHERE type = ?` column that didn't exist
- SQL error: "Unknown column 'type' in 'WHERE'"
- Task creation and listing completely broken

**Root Cause:** Database migration created `is_shared BOOLEAN` but code was written expecting `type` enum. Models and controllers out of sync with actual schema.

**Impact:** Tasks feature completely non-functional. All task operations failed with SQL errors.

**Lesson:** This is a repeat of Issue #1. ALWAYS verify code matches migrations before deployment. Run DESCRIBE on tables and compare to model queries. Consider automated schema validation tests.

---

### 12. Inline Event Handlers Violated Content Security Policy

**Problem:** Views used inline `onclick` attributes which violated CSP `script-src` directive.

**Specific Issues:**
- Task buttons had `onclick="functionName()"` attributes
- CSP blocked inline event handlers even with nonce
- Console showed "Refused to execute inline event handler" errors
- Add/Edit/Delete task buttons completely non-functional

**Root Cause:** Using inline event handlers (`onclick`, `onchange`) which CSP blocks to prevent XSS attacks. CSP nonces only work for `<script>` tags, not inline attributes.

**Impact:** Critical task management functionality broken. Users couldn't interact with tasks at all.

**Lesson:** Never use inline event handlers (onclick, onchange, etc.). Always use addEventListener in separate script tags or external JS files with CSP nonce. Use data attributes to pass data to event handlers instead of inline function calls.

---

## Process Improvements Needed

### Pre-Deployment Validation
- **Need:** Automated schema validation against model expectations
- **Current:** Manual discovery of mismatches during runtime
- **Better:** Script that compares DESCRIBE table output with model queries

### Testing Strategy
- **Need:** Integration tests for OAuth flow in test environment
- **Current:** Only manual browser testing in production
- **Better:** Automated tests using OAuth mock/sandbox

### Documentation
- **Need:** Keep RULES.md updated with lessons learned
- **Current:** Knowledge in developer's head only
- **Better:** Documented rules prevent repeat mistakes

### Code Review Checklist
- [ ] Database schema matches model code
- [ ] Environment variables match code expectations
- [ ] Session cookie settings appropriate for use case
- [ ] OAuth scopes minimal and non-restricted
- [ ] File permissions allow www-data group access
- [ ] OPcache invalidation plan documented
- [ ] Asset paths in views match actual file structure
- [ ] All CSS classes used in views have corresponding styles
- [ ] No inline event handlers (onclick, onchange, etc.)
- [ ] Service worker cache version updated on every deployment
- [ ] Removed code doesn't reference deleted features/services

---

**Last Updated:** October 25, 2025
