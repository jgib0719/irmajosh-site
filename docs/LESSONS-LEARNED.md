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

### 13. Fetch API Without Credentials Broke Session-Based Auth

**Problem:** JavaScript fetch() requests didn't include session cookies, causing authentication failures on all AJAX operations.

**Specific Issues:**
- Fetch requests to `/tasks` API endpoints returned 302 redirects to login
- Browser sent POST/PUT/DELETE requests but didn't include PHPSESSID cookie
- Server's `isAuthenticated()` check failed because `$_SESSION['user_id']` was empty
- Users appeared logged in (page loaded) but all API calls failed with "unauthorized"

**Root Cause:** fetch() API doesn't send cookies by default unless `credentials: 'same-origin'` or `credentials: 'include'` is specified in the request options.

**Impact:** Complete inability to create, edit, or delete tasks despite being logged in. UI showed success (200 status from redirect) but task operations silently failed.

**Lesson:** ALWAYS include `credentials: 'same-origin'` in fetch() options for same-origin requests that need session cookies. XMLHttpRequest sends cookies by default, but fetch() does not. This is a breaking difference when migrating from older AJAX approaches.

---

### 14. JSON Request Bodies Not Automatically Parsed by PHP

**Problem:** PHP doesn't populate `$_POST` from JSON request bodies like it does with form-encoded data.

**Specific Issues:**
- JavaScript sent `Content-Type: application/json` with JSON body
- Controller code expected data in `$_POST` array
- `$_POST` was empty, causing "missing required fields" validation errors
- CSRF token in JSON body wasn't accessible for middleware validation

**Root Cause:** PHP only auto-populates `$_POST` for `application/x-www-form-urlencoded` and `multipart/form-data`. JSON bodies require manual parsing from `php://input`.

**Impact:** All JSON API endpoints broken. Task creation, updates, deletes all failed validation.

**Lesson:** Add global middleware to parse JSON request bodies into `$_POST` when `Content-Type: application/json` is detected. Read from `php://input`, decode JSON, and merge into `$_POST` for compatibility with existing code that expects form data.

---

### 15. Controller Returned Both JSON and Redirect in Same Request

**Problem:** Controller executed both JSON response AND redirect, causing browser to follow redirect instead of processing JSON.

**Specific Issues:**
- Task creation succeeded in database
- Controller sent JSON success response
- Code continued executing after JSON response
- Controller then called `redirect()` to tasks page
- Browser followed redirect, ignored JSON, showed cached page
- JavaScript received 200 status but HTML content instead of JSON

**Root Cause:** Controller code checked for `isHtmx()` to decide between JSON or redirect, but JSON response didn't exit. Both code paths executed.

**Impact:** Tasks were created successfully but UI showed "Failed to create task" error because response was HTML instead of JSON.

**Lesson:** ALWAYS exit after sending JSON responses. Use `json()` helper that calls exit internally, or explicitly call `exit()` after any response. Never allow code to continue executing after sending a response.

---

### 16. Service Worker Cached Old Page HTML Preventing Code Updates

**Problem:** Service worker aggressively cached `/tasks/shared` HTML responses, serving old page content even after code updates.

**Specific Issues:**
- Updated JavaScript in view files (added `credentials: 'same-origin'`)
- Browser continued loading old cached HTML with old JavaScript
- Hard refresh didn't help because service worker intercepted fetch
- Had to manually unregister service worker to see changes
- Even after service worker update, cache persisted old responses

**Root Cause:** Service worker had `/tasks` routes in `NETWORK_FIRST_ROUTES` which cached responses. Cache was validated by cache version, but version number wasn't incremented. Browser cached HTML pages with inline JavaScript that couldn't be invalidated separately.

**Impact:** Bug fixes invisible to users. Debugging nightmare because "View Source" showed old code that wasn't on the server anymore.

**Lesson:** Move dynamic application routes (like `/tasks`) to `NETWORK_ONLY_ROUTES` in service worker. Only cache truly static assets. Always increment service worker `CACHE_VERSION` with timestamp on every deployment. For pages with inline JavaScript, never cache the HTML - always fetch fresh.

---

### 17. PHP OPcache Served Stale Controller Code After Updates

**Problem:** With `opcache.validate_timestamps=0`, PHP continued executing old controller code after file updates.

**Specific Issues:**
- Fixed controller to return JSON instead of redirect
- PHP continued executing old code that did both JSON and redirect
- Changes to `TaskController.php` had no effect
- Appeared like edits weren't saved, but file on disk was correct

**Root Cause:** Production OPcache configuration disables timestamp checking for performance. Changes require manual cache invalidation via PHP-FPM reload.

**Impact:** Wasted time debugging "old code" that was no longer in the file. Made multiple redundant fixes thinking previous edits failed.

**Lesson:** IMMEDIATELY reload PHP-FPM after any code changes in production: `sudo systemctl reload php8.4-fpm`. Make this muscle memory. Consider enabling `opcache.validate_timestamps=1` with a revalidation frequency (like 2 seconds) for easier debugging, accepting slight performance cost.

---

### 18. Calendar Page FullCalendar Library 404 Errors

**Problem:** Calendar page referenced FullCalendar library at wrong asset paths, causing 404 errors and broken calendar display.

**Specific Issues:**
- View referenced `/assets/vendor/fullcalendar/index.global.min.js`
- Actual file location was `/assets/js/vendor/fullcalendar.min.js`
- CSS path was also incorrect: `/assets/vendor/fullcalendar/index.global.min.css` vs actual `/assets/css/vendor/fullcalendar.min.css`
- Service worker precached old paths, perpetuating the error
- Calendar page loaded but showed blank container with no calendar widget

**Root Cause:** Asset reorganization during development without updating view file references. Service worker cached old incorrect paths.

**Impact:** Calendar feature completely non-functional. Users saw empty page with no calendar display.

**Lesson:** When reorganizing assets, use grep to find ALL references: `grep -r "old-path" public_html/`. Update service worker precache list immediately when asset paths change. Test in incognito mode after asset moves to verify paths without cache interference.

---

### 19. Undefined Constant Caused 500 Error on Calendar Page

**Problem:** Calendar view used undefined `CACHE_VERSION` constant for asset cache busting.

**Specific Issues:**
- Code used `?v=<?= CACHE_VERSION ?>` for CSS/JS asset URLs
- No `CACHE_VERSION` constant defined anywhere
- PHP threw undefined constant error, converted to string 'CACHE_VERSION'
- When strict error reporting enabled, this caused 500 error

**Root Cause:** Using wrong constant name - should have been `getAssetVersion()` helper function.

**Impact:** Calendar page returned 500 error until constant usage replaced with function call.

**Lesson:** Use existing helper functions for common operations. Search codebase for similar patterns before creating new approaches. Test pages with error_reporting=E_ALL to catch undefined constants.

---

### 20. FullCalendar Icon Fonts Blocked by CSP

**Problem:** FullCalendar navigation arrows didn't display because CSP blocked embedded icon fonts.

**Specific Issues:**
- FullCalendar uses data URLs for embedded icon fonts
- CSP `font-src 'self'` blocked data URLs
- Calendar displayed but navigation arrows were missing
- Console showed CSP violation: "Refused to load font from data: because it violates font-src directive"

**Root Cause:** Overly restrictive CSP font-src directive didn't account for embedded fonts in third-party libraries.

**Impact:** Calendar appeared broken with no way to navigate between months/weeks.

**Lesson:** When integrating third-party UI libraries, review their asset requirements. Add `data:` to CSP font-src for libraries that use embedded fonts. Test all interactive elements after CSP changes.

---

### 21. Calendar API Endpoints Returned 501 Errors

**Problem:** Calendar event endpoints returned 501 Not Implemented errors, breaking calendar functionality.

**Specific Issues:**
- `/calendar/events` GET endpoint returned 501
- Frontend JavaScript expected JSON array of events
- 501 error prevented calendar from initializing properly
- Calendar widget loaded but couldn't fetch events

**Root Cause:** Google Calendar integration was removed but endpoints left in error state instead of being stubbed properly.

**Impact:** Calendar displayed but appeared empty with no events, even though UI suggested it should work.

**Lesson:** When removing external service integrations, stub endpoints to return empty successful responses rather than error codes. Return `[]` for list endpoints and `{success: true}` for action endpoints. This allows frontend to work while backend implementation is pending.

---

### 22. Missing Global Content Padding Across All Pages

**Problem:** Most pages had content flush against viewport edges with no breathing room.

**Specific Issues:**
- Tasks page had no left/right padding
- Schedule requests page content touched edges
- Dashboard cards extended to viewport boundaries
- Only calendar page had proper padding (added individually)

**Root Cause:** No global content wrapper padding strategy. Each view responsible for its own spacing, leading to inconsistent implementation.

**Impact:** Poor visual design, content difficult to read on edges, unprofessional appearance.

**Lesson:** Implement global layout padding via shared wrapper class (`.content-wrapper`) applied to all pages. Use CSS variables for consistent spacing values. Define spacing in layout.php, not individual views.

---

### 23. Quick Add Feature Removed - Feature Creep

**Problem:** Quick Add feature added unnecessary complexity without clear user benefit over existing page-specific actions.

**Specific Issues:**
- Global Quick Add button in navigation added screen clutter
- Modal duplicated functionality already available on task/calendar pages
- Ctrl+K keyboard shortcut conflicted with browser search shortcuts
- Smart routing logic added complexity for minimal UX improvement
- Feature required significant code: button, modal HTML, JavaScript, CSS, keyboard handler

**Root Cause:** Added feature without validating actual user need. Over-engineering simple task/event creation.

**Impact:** Removed October 27, 2025. Simplified navigation, removed ~200 lines of code across header.php, style.css, service worker.

**Lesson:** Don't implement "nice to have" features without validating user need. Page-specific "Add Task" and "Add Event" buttons are sufficient and more discoverable. Global actions should solve real pain points, not just seem convenient. Prefer simplicity over feature completeness when user benefit is unclear.

---

### 24. Task Page Lacked Navigation Between Shared/Private Views

**Problem:** Shared and private tasks were separate pages with no navigation between them.

**Specific Issues:**
- `/tasks/shared` and `/tasks/private` were separate routes
- No tabs or links to switch between views
- Users had to manually edit URL to switch
- Unclear which view they were currently on
- Duplicate "Add Task" buttons on each page (removed in Quick Add refactor)

**Root Cause:** Views created independently without considering navigation between related pages.

**Impact:** Poor UX, users couldn't easily switch between task types, discoverability of private tasks was low.

**Lesson:** For related list views, implement tab navigation: (1) Add tabs to both pages, (2) Highlight active tab based on current route, (3) Use shared layout component for consistent navigation, (4) Remove duplicate per-page action buttons in favor of global actions.

---

### 25. Light Mode Theme in Dark Mode Era

**Problem:** Site used light theme (white backgrounds, dark text) when modern applications trend toward dark mode for reduced eye strain.

**Specific Issues:**
- Bright white backgrounds at night caused eye strain
- Harsh contrast in dark environments
- No user preference detection or toggle
- Inconsistent with modern calendar/productivity app expectations

**Root Cause:** Initial design used default light theme without considering user preferences or modern design trends.

**Impact:** Poor user experience in low-light environments, reduced accessibility for light-sensitive users.

**Lesson:** Implement dark mode as default for productivity applications: (1) Use CSS variables for all colors, (2) Dark theme: dark backgrounds (#0f172a), light text (#f1f5f9), (3) Muted accent colors that work on dark backgrounds, (4) Test all components for sufficient contrast, (5) Consider adding theme toggle for user preference in future.

---

### 26. Mobile Horizontal Scrolling and Overflow Issues

**Problem:** Mobile viewport showed horizontal scrolling, with content and tabs extending beyond screen boundaries.

**Specific Issues:**
- Tables on task pages caused horizontal overflow
- Tabs extended beyond colored border container
- Content touched viewport edges on mobile
- No responsive padding adjustments
- Calendar container too wide for mobile screens

**Root Cause:** No mobile-responsive CSS breakpoints. Desktop padding and widths applied to all screen sizes without adjustment.

**Impact:** Broken mobile experience, unusable on phones, horizontal scrolling made navigation difficult.

**Lesson:** Implement comprehensive mobile responsive design: (1) Add `@media (max-width: 768px)` breakpoints, (2) Use `overflow-x: hidden` on body and content containers, (3) Make tabs horizontally scrollable with `-webkit-overflow-scrolling: touch`, (4) Reduce padding on mobile (var(--spacing-md) instead of --spacing-lg), (5) Set `max-width: 100%` on all elements, (6) Test on actual mobile devices, not just browser DevTools, (7) Make containers scrollable instead of breaking layout.

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

---

### 27. VAPID Key Format Requirements for Web Push

**Problem:** Generated VAPID keys using OpenSSL didn't meet Web Push library requirements for key format and length.

**Specific Issues:**
- Initially generated keys using `openssl ecparam -genkey -name prime256v1`
- Public key was 64 bytes when base64-decoded, but Web Push requires exactly 65 bytes
- Private key was in PEM format (base64-encoded with headers) but library expected raw key
- NotificationService failed to instantiate with "Public key should be 65 bytes long" error
- Attempted to base64_decode the private key but library expected it already decoded

**Root Cause:** Using generic OpenSSL commands instead of library-specific key generation. Web Push VAPID keys have specific format requirements that differ from standard EC key formats.

**Impact:** Notification system completely non-functional. Service couldn't initialize to send any notifications.

**Lesson:** Use library-provided key generation functions for specialized protocols. For Web Push, use `Minishlink\WebPush\VAPID::createVapidKeys()` which generates keys in exact format required. Don't try to generate VAPID keys manually with OpenSSL - the format is subtly different from standard EC keys.

---

### 28. Database Migration Duplicate Index on Foreign Key Column

**Problem:** Migration attempted to create index on column that already had index from foreign key constraint.

**Specific Issues:**
- Migration created `FOREIGN KEY (user_id)` which auto-creates index
- Then tried to `CREATE INDEX idx_user_id ON push_subscriptions(user_id)`
- MySQL error: "Duplicate key name 'idx_user_id'"
- Migration failed, table not created

**Root Cause:** Not understanding that MySQL automatically creates indexes for foreign key columns. Explicitly creating the same index is redundant and causes error.

**Impact:** Migration failure prevented entire notification system from working. Table didn't exist so all operations failed.

**Lesson:** Foreign keys automatically create indexes in MySQL. Never manually create index on same column as foreign key. If you need index on FK column, the FK definition is sufficient. Remove redundant CREATE INDEX statements.

---

### 29. Models Using Instance Methods vs Static Methods Pattern Inconsistency

**Problem:** Existing models used static methods, but new PushSubscription model used instance methods requiring PDO injection.

**Specific Issues:**
- Task, User, ScheduleRequest models: `Task::create()`, `User::find()` (static)
- New PushSubscription model: `new PushSubscription($db)` then `->subscribe()` (instance)
- Controllers inconsistent: some used static calls, others required instantiation
- NotificationService and NotificationController both needed PDO injection
- Confusion about which pattern to follow for new code

**Root Cause:** No established pattern documented for model architecture. Mixed approaches in codebase without clear guidance.

**Impact:** Inconsistent API, confusion during implementation, had to refactor controller constructors multiple times to get PDO dependency right.

**Lesson:** Document model architecture pattern in RULES.md. For this codebase: existing models use static methods with internal db() calls, new models can use instance methods if they need to maintain state (like WebPush connection). Controllers should use `db()` helper to get PDO when needed, never store as instance property. When in doubt, follow existing patterns unless there's good reason to deviate.

---

### 30. Service Worker Cache Version Not Updated Blocked Script Changes

**Problem:** Updated push-notifications.js and layout.php but changes weren't visible because service worker served old cached versions.

**Specific Issues:**
- Added push notification script to layout.php
- Service worker cache version was `v6` from previous mobile fix session
- Browser continued serving old layout.php without new script tag
- Push notification manager never loaded even though file existed
- Had to manually update cache version to `v7-20251027-push` to force refresh

**Root Cause:** Forgot to increment service worker cache version when adding new features. Service worker cached HTML includes script references.

**Impact:** New push notification feature invisible to users. Had to do manual service worker update mid-implementation.

**Lesson:** This is a repeat of Issues #10 and #16. ALWAYS update service worker CACHE_VERSION when: (1) Adding new scripts to layout.php, (2) Changing any asset paths, (3) Updating CSS/JS files, (4) Modifying HTML structure. Make this part of the feature implementation checklist, not an afterthought. Consider automated cache version based on git commit hash.

---

**Last Updated:** October 27, 2025 (Session 3: Push Notifications Implementation)
