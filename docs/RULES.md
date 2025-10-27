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
- **Don't create indexes on foreign key columns** - MySQL auto-creates them, manual index causes duplicate key error
- **Follow existing model patterns** - Static methods for most models, instance methods only if maintaining state
- **Use db() helper for PDO access** - Don't store PDO as instance property in controllers

---

## Frontend Assets & Styling

- **Verify asset paths match file structure** - Search all views when reorganizing assets
- **Create CSS for all view components** - Never add HTML classes without corresponding styles
- **Test visual appearance immediately** - Don't commit HTML/CSS changes without viewing in browser
- **Use consistent asset version strategy** - Update cache-busting parameter on every deployment
- **Check browser DevTools for missing styles** - Verify all classes have CSS definitions
- **Use helper functions for cache busting** - `getAssetVersion()` not undefined constants
- **Update service worker precache on asset moves** - Keep precache list in sync with actual paths
- **Test asset loading in incognito mode** - Verify paths work without cache interference
- **Implement global layout padding** - Use .content-wrapper with CSS variables for consistent spacing
- **Use CSS variables for theming** - Enables easy theme switching and consistent colors
- **Design mobile-first or mobile-responsive** - Add @media breakpoints for screens under 768px
- **Prevent horizontal overflow on mobile** - Use overflow-x: hidden on body and main containers
- **Make tabs scrollable on mobile** - Horizontal scroll with -webkit-overflow-scrolling: touch
- **Reduce padding on mobile** - Use smaller spacing values for constrained viewports

---

## JavaScript & AJAX

- **ALWAYS include credentials in fetch() calls** - Use `credentials: 'same-origin'` for session-based auth
- **Never rely on default fetch() behavior** - Unlike XMLHttpRequest, fetch() doesn't send cookies by default
- **Parse JSON request bodies in middleware** - PHP doesn't auto-populate `$_POST` from JSON
- **Add middleware to handle Content-Type: application/json** - Read `php://input`, decode, merge into `$_POST`
- **Exit after sending JSON responses** - Use `json()` helper that calls exit, or manually call `exit()`
- **Never execute code after response sent** - One request = one response (either JSON or redirect, never both)
- **Test AJAX endpoints with browser DevTools Network tab** - Verify cookies sent, response type, status codes

---

## Content Security Policy (CSP)

## Code Review Checklist
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
- [ ] Service worker cache version updated when adding scripts to layout
- [ ] Removed code doesn't reference deleted features/services
- [ ] All fetch() calls include `credentials: 'same-origin'`
- [ ] JSON request body parsing middleware in place
- [ ] All JSON response functions call exit() after sending
- [ ] Dynamic routes excluded from service worker caching
- [ ] Constants used in views are defined or replaced with helper functions
- [ ] CSP directives allow third-party library assets (fonts, styles)
- [ ] API endpoints return valid responses (not 501 errors)
- [ ] Global layout padding applied via .content-wrapper
- [ ] Mobile responsive CSS with @media breakpoints at 768px
- [ ] Horizontal overflow prevented with overflow-x: hidden
- [ ] Tab navigation implemented for related list views
- [ ] Dark mode colors provide sufficient contrast
- [ ] Foreign key columns don't have redundant manual indexes
- [ ] VAPID keys generated using library function, not OpenSSL
- [ ] Web Push features tested on HTTPS domain, not localhost
- [ ] Composer autoload regenerated after adding new classes
- **Use addEventListener instead** - Attach events in script tags with nonce or external JS
- **Pass data via data attributes** - Use `data-*` attributes instead of inline function calls
- **CSP nonces only work for script/style tags** - They don't apply to inline attributes
- **Test all interactive elements after CSP changes** - Buttons, forms, checkboxes must work
- **Add data: to font-src for embedded fonts** - Required for libraries like FullCalendar with data URLs
- **Review third-party library CSP requirements** - Check what directives they need before integration
- **Test with CSP enabled from the start** - Don't add it late in development

---

## Service Worker & Caching

- **Update CACHE_VERSION on every deployment** - Use timestamp format like `v4-20251025-0646`
- **Update cache version when adding scripts to layout** - New script tags in cached HTML require version bump
- **Hard refresh required after service worker changes** - Document this for users/testers
- **Precache paths must match actual files** - Verify all paths exist before deployment
- **Consider cache strategy per asset type** - Static assets can cache longer than HTML
- **Test in incognito mode to verify updates** - Bypasses service worker cache
- **Put dynamic routes in NETWORK_ONLY_ROUTES** - Never cache HTML with inline JavaScript or session data
- **Only cache truly static assets** - CSS, JS files, images - not HTML pages with dynamic content
- **Service worker can't be bypassed by hard refresh** - Must manually unregister or use incognito for testing
- **Increment cache version with every code change** - Even small CSS/JS tweaks need new version
- **Move application routes to NETWORK_ONLY** - Routes like /tasks, /calendar should never be cached
- **Make cache version part of feature checklist** - Not an afterthought, update it during implementation

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

## API Endpoints & Controllers

- **Stub removed integrations with success responses** - Return `[]` for lists, `{success: true}` for actions, not 501 errors
- **Allow frontend to work without full backend** - Empty successful responses enable UI development
- **Don't leave endpoints in error states** - 501/500 breaks frontend, stubs allow graceful degradation
- **Return appropriate response types** - JSON for AJAX, HTML for browser requests, never both
- **Exit after sending responses** - One request = one response

---

## User Experience & Navigation

- **Implement unified Quick Add for multi-entity apps** - Single global button/modal with smart routing
- **Add keyboard shortcuts for power users** - Ctrl+K for quick add, Esc to close modals, etc.
- **Use tab navigation for related list views** - Shared/private tasks, different calendars, etc.
- **Highlight active tab based on current route** - Users need to know where they are
- **Remove duplicate per-page action buttons** - Consolidate to global navigation actions
- **Consider context-aware routing** - Route based on input (datetime → event, no datetime → task)
- **Test all navigation patterns** - Links, tabs, buttons, keyboard shortcuts, back button

---

## Error Handling & Logging

- **Check logs FIRST when debugging** - tail -f storage/logs/app.log
- **Log SQL errors with full query context** - Include parameters when safe
- **Redact PII in all logs** - Use redactPII() helper consistently
- **Return 500 errors gracefully** - Never expose stack traces in production

---

## Web Push Notifications

- **Use library key generation for VAPID** - `VAPID::createVapidKeys()`, never OpenSSL manually
- **VAPID public key must be exactly 65 bytes** - Verify with `strlen(base64_decode($key))`
- **Don't base64_decode VAPID private key** - Library-generated keys are already in correct format
- **Store VAPID keys without quotes in .env** - No quotes around base64url-encoded strings
- **Test NotificationService instantiation** - Fails fast if VAPID keys are wrong format
- **Push notifications require HTTPS** - Won't work on localhost, must test on production domain
- **Regenerate keys if changing Web Push library** - Different libraries may have different format requirements
