# Development Rules - IrmaJosh.com

**Purpose:** Prevent common mistakes and ensure consistency across all phases.

---

## Database & Models

- **ALWAYS verify model code matches actual database schema** - Run `DESCRIBE table_name` before writing queries
- **Migration files are the source of truth** - Code must match migrations, not the other way around
- **Use exact column names from schema** - No assumptions about naming conventions (e.g. `is_shared` not `type`)
- **Test database queries independently** - Use MySQL CLI to verify query syntax before adding to models
- **Foreign key relationships must use actual column names** - Check schema for `sender_id` vs `user_id` etc.
- **Verify schema after writing migrations** - Run DESCRIBE and compare to model code before deployment

---

## Frontend Assets & Styling

- **Verify asset paths match file structure** - Search all views when reorganizing assets
- **Create CSS for all view components** - Never add HTML classes without corresponding styles
- **Test visual appearance immediately** - Don't commit HTML/CSS changes without viewing in browser
- **Use consistent asset version strategy** - Update cache-busting parameter on every deployment
- **Check browser DevTools for missing styles** - Verify all classes have CSS definitions

---

## Content Security Policy (CSP)

- **Never use inline event handlers** - No `onclick`, `onchange`, `onsubmit` in HTML
- **Use addEventListener instead** - Attach events in script tags with nonce or external JS
- **Pass data via data attributes** - Use `data-*` attributes instead of inline function calls
- **CSP nonces only work for script/style tags** - They don't apply to inline attributes
- **Test all interactive elements after CSP changes** - Buttons, forms, checkboxes must work

---

## Service Worker & Caching

- **Update CACHE_VERSION on every deployment** - Use timestamp format like `v4-20251025-0646`
- **Hard refresh required after service worker changes** - Document this for users/testers
- **Precache paths must match actual files** - Verify all paths exist before deployment
- **Consider cache strategy per asset type** - Static assets can cache longer than HTML
- **Test in incognito mode to verify updates** - Bypasses service worker cache

---

## Environment Variables

- **Search entire codebase before renaming env variables** - Use `grep -r "OLD_NAME" src/` to find all references
- **Document env variable naming conventions** - If using suffixes like `_CURR` and `_PREV`, update ALL code
- **Never assume variable names** - Always check .env file for exact spelling
- **Validate all env() calls on deployment** - Ensure every env() call has corresponding .env entry

---

## Session & Cookies

- **Use SameSite=Lax for OAuth applications** - Strict breaks OAuth redirect flows
- **Test session persistence across redirects** - Verify cookies survive OAuth roundtrip
- **Always include Secure and HttpOnly flags** - But don't make SameSite too strict
- **Use __Host- prefix for session cookie names** - Requires path=/, secure, and no domain attribute

---

## OAuth & External APIs

- **Only request necessary OAuth scopes** - Restricted scopes require Google verification
- **Test OAuth flow in private/incognito mode** - Ensures fresh session state
- **Store oauth_state in session before redirect** - Verify it persists through callback
- **Validate state parameter matches** - Security requirement, not optional

---

## Apache & Routing

- **Use FallbackResource instead of mod_rewrite** - Simpler and more reliable for front controllers
- **Don't combine DirectoryIndex with RewriteRule** - Creates conflicting internal redirects
- **Handle HEAD requests as GET** - Add `if ($method === 'HEAD') $method = 'GET';` in router
- **Test both GET and HEAD requests** - Use curl -I to verify HEAD works

---

## File Permissions

- **.env must be 640 with www-data group** - Allows Apache/PHP-FPM to read while staying private
- **storage/ directories must be writable by www-data** - For logs, cache, sessions
- **Never use 600 for files Apache needs to read** - Use 640 with proper group ownership
- **Verify permissions after deployment** - Run scripts/check_permissions.sh

---

## OPcache in Production

- **ALWAYS reload PHP-FPM after code changes** - `sudo systemctl reload php8.4-fpm`
- **Document this in deployment process** - Can't rely on automatic detection
- **With validate_timestamps=0, cache never expires** - Manual invalidation required
- **Consider timestamps=1 for development** - Only use 0 in true production

---

## Security Headers

- **Set CSP in PHP, not Apache** - Allows per-request nonces
- **Don't duplicate security headers** - Pick Apache OR PHP, not both
- **Test headers with curl -I** - Verify no conflicts or duplicates
- **Frame-ancestors 'none' blocks all embedding** - Appropriate for this app

---

## Error Handling & Logging

- **Check logs FIRST when debugging** - tail -f storage/logs/app.log
- **Log SQL errors with full query context** - Include parameters when safe
- **Redact PII in all logs** - Use redactPII() helper consistently
- **Return 500 errors gracefully** - Never expose stack traces in production

---

## Testing Checklist

Before declaring a feature "done":

- [ ] Tested with fresh session (incognito/private mode)
- [ ] Tested all HTTP methods (GET, POST, HEAD)
- [ ] Verified database queries with actual schema
- [ ] Checked logs for errors/warnings
- [ ] Tested with OPcache enabled (reload FPM after changes)
- [ ] Verified all env variables exist in .env
- [ ] Confirmed file permissions allow www-data access
- [ ] Tested on actual domain, not localhost
- [ ] Verified all asset paths are correct
- [ ] Confirmed all interactive elements work (no CSP violations)
- [ ] Checked browser DevTools console for errors
- [ ] Hard refreshed to test without cache

---

## Deployment Process

1. **Review code changes** - Check for hardcoded values, debug statements
2. **Verify .env variables** - Ensure all env() calls have corresponding entries
3. **Check database migrations** - Run migrate.php and verify schema with DESCRIBE
4. **Verify asset paths in views** - Search for /assets/ and confirm files exist
5. **Update service worker cache version** - Increment with timestamp
6. **Update file permissions** - Run check_permissions.sh
7. **Reload PHP-FPM** - `sudo systemctl reload php8.4-fpm`
8. **Test critical paths** - Login, dashboard, logout, task creation at minimum
9. **Check browser console** - Verify no CSP violations or 404s
10. **Monitor logs** - Watch for errors in first 5 minutes
11. **Create backup** - Run backup.sh AFTER verifying deployment works

---

## When Things Break

1. **Check logs first** - storage/logs/app.log and php-errors.log
2. **Check browser console** - Look for CSP violations, 404s, JavaScript errors
3. **Verify schema matches code** - DESCRIBE tables in MySQL and compare to models
4. **Check .env file** - Correct values, correct permissions (640)
5. **Reload PHP-FPM** - OPcache might be serving old code
6. **Test in incognito mode** - Eliminate cached session and service worker issues
7. **Verify asset paths** - Check that files exist where views reference them
8. **Check Apache error logs** - /var/log/apache2/irmajosh-error.log
9. **Verify services running** - Apache, PHP-FPM, MySQL, Postfix
10. **Hard refresh browser** - Ctrl+Shift+R to bypass all caches

---

## Feature Removal Checklist

When removing a feature or integration:

- [ ] Remove configuration (OAuth scopes, API keys, etc.)
- [ ] Search for all code references - `grep -r "FeatureName" src/`
- [ ] Remove service/factory classes
- [ ] Remove controller methods that call removed services
- [ ] Remove view components displaying removed feature
- [ ] Remove database migrations if safe (or document as deprecated)
- [ ] Remove from navigation/menus
- [ ] Update documentation
- [ ] Test that removal doesn't break other features

---

**Last Updated:** October 25, 2025  
**Review Frequency:** After each major incident or phase completion
