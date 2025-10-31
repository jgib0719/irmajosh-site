# Codebase Reference - IrmaJosh.com

**Purpose:** Comprehensive reference for functions, helpers, patterns, and naming conventions.  
**Audience:** AI assistants and developers working on this codebase.

---

## Architecture Overview

**Framework:** Custom PHP MVC (no Laravel/Symfony)  
**PHP Version:** 8.4  
**Database:** MySQL (irmajosh_db)  
**Server:** Apache 2.4.65 + PHP-FPM  
**Frontend:** HTMX, vanilla JavaScript, FullCalendar  
**PWA:** Service worker with offline support + push notifications

---

## Critical Global Functions (src/helpers.php)

### Database Access
```php
db(): PDO  // Get PDO database connection - USE THIS, not new PDO()
```

### Environment & Configuration
```php
env(string $key, mixed $default = null): mixed  // Get .env variable
config(string $key, mixed $default = null): mixed  // Get config value
getAppLocale(): string  // Current locale (en/id)
```

### Asset Management
```php
getAssetVersion(): string  // Cache-busting version (git hash based)
// USE: /assets/css/style.css?v=<?= getAssetVersion() ?>
// DON'T USE: CACHE_VERSION constant (doesn't exist)
```

### Security & Sessions
```php
csrfToken(): string  // Generate/get CSRF token
verifyCsrfToken(string $token): bool  // Validate CSRF token
cspNonce(): string  // Get CSP nonce for current request
sanitizeInput(string $input): string  // HTML escape user input
validateEmail(string $email): bool  // Email format validation
hashPassword(string $password): string  // Password hashing
verifyPassword(string $password, string $hash): bool  // Password verification
```

### Authentication
```php
isAuthenticated(): bool  // Check if user logged in
requireAuth(): void  // Redirect to login if not authenticated
currentUser(): ?array  // Get current user data or null
setCurrentUser(array $user): void  // Set user in session
clearCurrentUser(): void  // Logout user
```

### Session Management
```php
setFlash(string $type, string $message): void  // Set flash message
getFlash(string $type): ?string  // Get and clear flash message
hasFlash(string $type): bool  // Check if flash exists
```

### Response Helpers
```php
view(string $template, array $data = []): void  // Render view template
json(mixed $data, int $status = 200): never  // Send JSON response and EXIT
redirect(string $url, int $status = 302): never  // Redirect and EXIT
```

### Logging
```php
logMessage(string $message, string $level = 'INFO'): void  // Write to log
logError(string $message): void  // Log error level
logWarning(string $message): void  // Log warning level
logInfo(string $message): void  // Log info level
redactPII(string $text): string  // Redact emails/sensitive data
```

### Translation
```php
t(string $key, array $params = []): string  // Translate key to current locale
// USE: t('task_created')
// Files: locales/en/messages.php, locales/id/messages.php
```

### Utility
```php
generateRandomString(int $length = 32): string  // Cryptographically secure random
encryptData(string $data, string $key): string  // Encrypt with AES-256-GCM
decryptData(string $encrypted, string $key): string|false  // Decrypt data
```

---

## Model Patterns

### Static Method Models (Majority)
```php
// Task, User, ScheduleRequest, ScheduleRequestSlot, UserToken, AuditLog
Task::create(array $data): array
Task::find(int $id): ?array
Task::update(int $id, array $data): bool
Task::delete(int $id): bool
Task::getByUser(int $userId, ?string $type = null): array
```

**Pattern:** Static methods that call `db()` internally. No instantiation needed.

### Instance Method Models (Exception)
```php
// PushSubscription only
$model = new PushSubscription(db());
$model->subscribe(int $userId, array $subscription): bool
$model->unsubscribe(int $userId, string $endpoint): bool
$model->getSubscriptionsByUser(int $userId): array
```

**Pattern:** Used when model maintains state (like WebPush connection).

### Rule: Use Static Methods Unless State Required
- **DO:** Follow existing static pattern for new models
- **DON'T:** Mix patterns without good reason
- **WHY:** Consistency, simplicity, no PDO injection needed

---

## Controller Patterns

### Base Controller (src/Controllers/BaseController.php)
```php
abstract class BaseController {
    protected function view(string $template, array $data = []): void
    protected function json(mixed $data, int $status = 200): void
    protected function redirect(string $url, int $status = 302): void
    protected function getCurrentUser(): ?array
    protected function requireAuth(): void
    protected function validateRequired(array $required, array $data): bool
    protected function getPost(string $key, mixed $default = null): mixed
    protected function getQuery(string $key, mixed $default = null): mixed
    protected function isHtmx(): bool
    protected function setFlash(string $type, string $message): void
}
```

### Controller Constructor Pattern
```php
// DON'T store PDO as instance property
class TaskController extends BaseController {
    public function __construct() {
        // Nothing needed, use db() when needed
    }
    
    public function create(): void {
        // Use db() directly when needed
        $userId = $this->getCurrentUser()['id'];
        $task = Task::create($data);  // Model calls db() internally
    }
}

// DO store service instances if needed
class ScheduleController extends BaseController {
    private EmailService $emailService;
    
    public function __construct() {
        $this->emailService = new EmailService();
    }
}
```

### Controller Response Rules
```php
// ALWAYS exit after JSON response
$this->json(['success' => true]);  // json() helper exits automatically

// ALWAYS exit after redirect
$this->redirect('/dashboard');  // redirect() helper exits automatically

// NEVER do both in same request
// BAD:
$this->json(['success' => true]);
$this->redirect('/tasks');  // This executes! Browser follows redirect, ignores JSON

// GOOD:
if ($this->isHtmx()) {
    $this->json(['success' => true]);
} else {
    $this->redirect('/tasks');
}
```

---

## Service Patterns

### Service Construction
```php
// Services that need database
class NotificationService {
    public function __construct(PDO $db) {
        $this->db = $db;
    }
}

// Usage in controllers
$service = new NotificationService(db());
$service->sendToUser($userId, $data);
```

### Existing Services
- **EmailService:** `new EmailService()` - no args, uses env() for SMTP config
- **AuthService:** `new AuthService()` - no args, handles OAuth
- **NotificationService:** `new NotificationService(db())` - needs PDO for subscriptions
- **TranslationService:** Used via `t()` helper, don't instantiate directly
- **GoogleClientFactory:** Static factory for Google API client

---

## Route Definition (config/routes.php)

```php
return [
    '_middleware' => ['parseJsonBodyMiddleware'],  // Global middleware
    
    [
        'method' => 'GET',
        'path' => '/tasks/shared',
        'handler' => [\App\Controllers\TaskController::class, 'shared'],
        'middleware' => ['authMiddleware']
    ],
    
    // Dynamic segments
    [
        'method' => 'PUT',
        'path' => '/tasks/{id}',  // {id} captured in $params
        'handler' => [\App\Controllers\TaskController::class, 'update'],
        'middleware' => ['authMiddleware', 'csrfMiddleware']
    ]
];
```

### Route Handler Method Signature
```php
// No params
public function index(): void {
    // Access via $this->getQuery('param')
}

// With params
public function update(array $params): void {
    $taskId = (int)$params['id'];  // From /tasks/{id}
}
```

### Available Middleware
- `authMiddleware` - Requires authentication
- `guestMiddleware` - Requires NOT authenticated
- `csrfMiddleware` - Validates CSRF token
- `rateLimitMiddleware` - Rate limiting
- `parseJsonBodyMiddleware` - Parses JSON into $_POST

---

## Database Schema Conventions

### Tables
- `users` - NOT `user`
- `tasks` - NOT `task`
- `push_subscriptions` - NOT `push_subscription`

### Columns
- Use actual schema column names in queries
- Check with: `DESCRIBE table_name`
- Common mismatches to avoid:
  - Schema has `is_shared` (boolean), NOT `type` (string)
  - Schema has `google_user_id`, NOT `google_sub`
  - Schema has `picture`, NOT `picture_url`
  - Schema has `sender_id`/`recipient_id`, NOT just `user_id`

### Foreign Keys
- Foreign keys automatically create indexes
- DON'T manually create index on FK column (duplicate key error)

---

## View Patterns (public_html/views/)

### View Files
- **Layout:** `layout.php` - Main wrapper with header/footer
- **Pages:** `dashboard.php`, `tasks-shared.php`, `tasks-private.php`, `calendar.php`, `schedule-requests.php`
- **Components:** `components/header.php`, `components/footer.php`, `components/alerts.php`
- **Emails:** `emails/schedule-request.php`

### View Variables Available
```php
// Automatically available in all views via layout.php
$user         // Current user array or null
$pageTitle    // Page title string
$locale       // Current locale (en/id)

// Passed from controller
$content      // From ob_get_clean() in view files
```

### View Pattern
```php
<?php ob_start(); ?>
<!-- Page content here -->
<h1><?= htmlspecialchars($pageTitle) ?></h1>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
```

### Asset Paths in Views
```php
// CSS
<link rel="stylesheet" href="/assets/css/style.css?v=<?= getAssetVersion() ?>">

// JavaScript (with CSP nonce)
<script src="/assets/js/app.js?v=<?= getAssetVersion() ?>" nonce="<?= cspNonce() ?>"></script>

// Images
<img src="/assets/icons/icon-192x192.png" alt="Icon">

// DON'T use undefined constants
// BAD: ?v=<?= CACHE_VERSION ?>
// GOOD: ?v=<?= getAssetVersion() ?>
```

### Inline Scripts (CSP Compliant)
```php
// ALWAYS use nonce for inline scripts
<script nonce="<?= cspNonce() ?>">
    // Inline JavaScript here
</script>

// NEVER use inline event handlers (CSP blocks them)
// BAD: <button onclick="doSomething()">
// GOOD: <button id="myButton">
// <script nonce="<?= cspNonce() ?>">
//     document.getElementById('myButton').addEventListener('click', doSomething);
// </script>
```

---

## Frontend JavaScript Patterns

### AJAX with fetch()
```javascript
// ALWAYS include credentials for session-based auth
fetch('/api/endpoint', {
    method: 'POST',
    credentials: 'same-origin',  // REQUIRED for session cookies
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(data)
})
```

### Global Objects
- `window.PushNotifications` - Push notification manager (if authenticated)
- HTMX loaded via `/assets/js/vendor/htmx.min.js`
- FullCalendar loaded on calendar page

### Service Worker
- File: `/public_html/service-worker.js`
- Served via PWAController with no-cache headers (always fresh)
- Cache version: Update on EVERY deployment
- Format: `v8-20251027-remove-quickadd` (version-date-feature)

---

## Environment Variables (.env)

### Required Variables
```bash
# Application
APP_NAME="IrmaJosh Calendar"
APP_ENV=production
APP_URL=https://irmajosh.com
APP_SECRET_CURR=<64-char-hex>  # NOT APP_SECRET

# Database
DB_HOST=localhost
DB_NAME=irmajosh_db
DB_USER=irmajosh_app
DB_PASS=<password>

# Google OAuth
GOOGLE_CLIENT_ID=<client-id>
GOOGLE_CLIENT_SECRET=<secret>
GOOGLE_REDIRECT_URI=https://irmajosh.com/auth/callback
GOOGLE_SCOPES="openid email profile https://www.googleapis.com/auth/calendar"
EMAIL_ALLOWLIST=email1@gmail.com,email2@gmail.com

# Session
SESSION_SECURE=true
SESSION_COOKIE_NAME="__Host-ij_sess"
SESSION_LIFETIME=7200

# SMTP
SMTP_HOST=localhost
SMTP_PORT=25
SMTP_FROM_EMAIL=admin@irmajosh.com

# Web Push (VAPID)
VAPID_PUBLIC_KEY=<base64url-key>  # NO quotes
VAPID_PRIVATE_KEY=<base64url-key>  # NO quotes
VAPID_SUBJECT=mailto:admin@irmajosh.com
```

### Generate VAPID Keys
```bash
php -r "require 'vendor/autoload.php'; 
use Minishlink\WebPush\VAPID; 
\$keys = VAPID::createVapidKeys(); 
echo 'VAPID_PUBLIC_KEY=' . \$keys['publicKey'] . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . \$keys['privateKey'] . PHP_EOL;"
```

---

## Common Gotchas & Solutions

### 1. Session Not Available in OAuth Callback
```php
// .env must have:
SESSION_SECURE=true
SESSION_COOKIE_NAME="__Host-ij_sess"
SESSION_SAMESITE=Lax  // NOT Strict (breaks OAuth)
```

### 2. fetch() Doesn't Send Cookies
```javascript
// ALWAYS add credentials
fetch('/api/endpoint', {
    credentials: 'same-origin'  // Required!
})
```

### 3. $_POST Empty for JSON Requests
```php
// parseJsonBodyMiddleware handles this globally
// JSON body automatically parsed into $_POST
```

### 4. Code Changes Not Visible
```bash
# Service worker cache - increment version in service-worker.js
const CACHE_VERSION = 'v8-20251027-remove-quickadd';

# Service worker served with no-cache headers via PWAController
# Browser checks for updates automatically every 24 hours

# PHP OPcache - reload PHP-FPM
sudo systemctl reload php8.4-fpm

# Browser cache - hard refresh Ctrl+Shift+R
```

### 5. CSP Blocks Inline Scripts
```php
// Use nonce for inline scripts
<script nonce="<?= cspNonce() ?>">

// Use addEventListener, not onclick
// BAD: <button onclick="handler()">
// GOOD: <button id="btn">
// <script nonce="<?= cspNonce() ?>">
//     document.getElementById('btn').addEventListener('click', handler);
```

### 6. Model/Schema Mismatch
```bash
# Always verify schema before coding
mysql -u irmajosh_app -p irmajosh_db -e "DESCRIBE tasks"
```

### 7. VAPID Keys Wrong Format
```php
// DON'T generate with OpenSSL
// DON'T base64_decode the private key
// DO use library generator:
$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
```

---

## File Structure Reference

```
/var/www/irmajosh.com/
├── config/
│   └── routes.php                 # Route definitions
├── migrations/
│   └── *.sql                      # Database migrations (run in order)
├── public_html/
│   ├── index.php                  # Front controller
│   ├── service-worker.js          # PWA service worker
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css          # Main stylesheet
│   │   ├── js/
│   │   │   ├── app.js             # Main app JavaScript
│   │   │   ├── modal.js           # Modal utilities
│   │   │   ├── form-utils.js      # Form utilities
│   │   │   ├── push-notifications.js  # Push notification manager
│   │   │   └── vendor/
│   │   │       ├── htmx.min.js
│   │   │       └── fullcalendar.min.js
│   │   └── icons/                 # PWA icons
│   └── views/
│       ├── layout.php             # Main layout wrapper
│       ├── dashboard.php
│       ├── tasks-shared.php
│       ├── tasks-private.php
│       ├── calendar.php
│       ├── schedule-requests.php
│       ├── components/
│       │   ├── header.php
│       │   ├── footer.php
│       │   └── alerts.php
│       └── emails/
│           └── schedule-request.php
├── src/
│   ├── bootstrap.php              # App initialization
│   ├── helpers.php                # Global helper functions
│   ├── middleware.php             # Middleware functions
│   ├── router.php                 # Router logic
│   ├── Controllers/
│   │   ├── BaseController.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── TaskController.php
│   │   ├── CalendarController.php
│   │   ├── ScheduleController.php
│   │   └── NotificationController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── UserToken.php
│   │   ├── Task.php
│   │   ├── ScheduleRequest.php
│   │   ├── ScheduleRequestSlot.php
│   │   ├── AuditLog.php
│   │   └── PushSubscription.php
│   └── Services/
│       ├── AuthService.php
│       ├── EmailService.php
│       ├── NotificationService.php
│       ├── TranslationService.php
│       └── GoogleClientFactory.php
├── scripts/
│   ├── migrate.php                # Run migrations
│   ├── test_notifications.php     # Test push notifications
│   └── bust_cache.sh             # Clear browser cache headers
├── storage/
│   ├── logs/                      # Application logs
│   ├── cache/                     # Cache files
│   └── rate_limits/               # Rate limit storage
├── .env                           # Environment configuration
└── composer.json                  # PHP dependencies
```

---

## Quick Reference Commands

```bash
# Database migrations
php scripts/migrate.php

# Test push notifications
php scripts/test_notifications.php <user_id>

# Clear OPcache
sudo systemctl reload php8.4-fpm

# Check permissions
scripts/check_permissions.sh

# Regenerate Composer autoload
composer dump-autoload

# View logs
tail -f storage/logs/app.log

# Check database schema
mysql -u irmajosh_app -p irmajosh_db -e "DESCRIBE table_name"
```

---

## Critical Rules Summary

1. **USE:** `db()` function, not `new PDO()`
2. **USE:** `getAssetVersion()` function, not `CACHE_VERSION` constant
3. **USE:** `t()` for translations, not hardcoded strings
4. **USE:** `json()` and `redirect()` helpers that auto-exit
5. **USE:** `credentials: 'same-origin'` in all fetch() calls
6. **USE:** `nonce="<?= cspNonce() ?>"` for inline scripts
7. **USE:** `VAPID::createVapidKeys()` for push notification keys
8. **UPDATE:** Service worker cache version on every deployment
9. **VERIFY:** Schema with DESCRIBE before writing model queries
10. **RELOAD:** PHP-FPM after code changes in production
11. **AVOID:** Inline event handlers (onclick, onchange)
12. **AVOID:** Creating indexes on foreign key columns
13. **AVOID:** Executing code after sending responses
14. **AVOID:** Mixing static and instance model patterns

---

**Last Updated:** October 27, 2025
