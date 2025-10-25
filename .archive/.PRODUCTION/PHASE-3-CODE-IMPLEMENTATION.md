# PHASE 3: CODE IMPLEMENTATION

**Estimated Time:** 39-66 hours (revised from agent reviews)

**Purpose:** Build the complete application codebase (MVC structure, helpers, views, assets)

---

## Overview

This is the largest phase, implementing all application code:
- Front controller and bootstrap
- 25+ helper functions
- Router and middleware
- 8 controllers, 5 services, 6 models
- View templates with CSP nonce support
- Frontend assets (CSS, JavaScript, PWA)
- 10+ utility scripts

**CRITICAL IMPLEMENTATION NOTES:**
- This document incorporates security specifications from agent reviews
- All Critical and High-severity implementation details MUST be followed
- Code examples provide exact patterns - do not deviate without review
- Security features (PKCE, ID token verification, encryption) are MANDATORY

---

## Pre-Implementation Checklist

**MUST verify before starting Phase 3:**

- [ ] Phase 2 completed (all config files created)
- [ ] Database migrations run successfully (all 7 tables created)
- [ ] Composer dependencies installed (`google/apiclient`, `vlucas/phpdotenv`, `phpmailer/phpmailer`)
- [ ] APP_SECRET generated (64-char hex) and stored in password manager
- [ ] Local development environment tested (Apache + PHP-FPM + MySQL)
- [ ] .env file populated with all required values
- [ ] Git repository initialized
- [ ] Development branch created
- [ ] PHP 8.2+ verified: `php -v`

---

## Implementation Order

### 1. Core Foundation (4-6 hours)
- [ ] public_html/index.php (front controller with HTTPS enforcement)
- [ ] src/bootstrap.php (CSP nonce, session security, headers)
- [ ] src/helpers.php (25+ utility functions - see complete list below)
- [ ] src/router.php (dynamic route matching with parameters)
- [ ] src/middleware.php (auth, CSRF, rate limiting)
- [ ] .gitignore (vendor/, storage/, .env)

**Key Deliverables:**
- CSP nonce generated per-request in `$GLOBALS` (NOT session)
- Session cookies use `__Host-` prefix with Secure, HttpOnly, SameSite=Strict
- Error reporting configured (off in production, on in development)
- PHP version check (8.2+ required)

### 2. Models (3-5 hours)
- [ ] src/Models/User.php (upsert by Google sub, not email)
- [ ] src/Models/UserToken.php (libsodium encryption/decryption)
- [ ] src/Models/Task.php (CRUD with user ownership)
- [ ] src/Models/ScheduleRequest.php
- [ ] src/Models/ScheduleRequestSlot.php
- [ ] src/Models/AuditLog.php (PII redaction on write)

**Key Deliverables:**
- Token encryption uses `APP_SECRET` as encryption key (32 bytes)
- Support `APP_SECRET_PREV` for 8-day rotation window
- All database operations use prepared statements
- User identified by `sub` claim (NOT email)

### 3. Services (6-10 hours)
- [ ] src/Services/GoogleClientFactory.php (OAuth scopes including 'openid')
- [ ] src/Services/AuthService.php (PKCE flow, ID token verification, email allowlist)
- [ ] src/Services/CalendarService.php (API wrapper with retry logic, error handling)
- [ ] src/Services/EmailService.php (PHPMailer configuration, rate limiting)
- [ ] src/Services/TranslationService.php (fallback to English, error handling)

**Key Deliverables:**
- **CRITICAL:** `openid` scope MUST be included for ID token
- ID token verification using Google JWKS endpoint
- PKCE code_verifier/code_challenge generation (S256 method)
- Google Calendar API error handling (429 rate limits, 403 quota, 5xx retries)
- Exponential backoff for transient errors

### 4. Controllers (8-12 hours)
- [ ] src/Controllers/BaseController.php
- [ ] src/Controllers/AuthController.php (OAuth flow with complete PKCE implementation)
- [ ] src/Controllers/DashboardController.php
- [ ] src/Controllers/CalendarController.php (CRUD, sync, error handling)
- [ ] src/Controllers/TaskController.php
- [ ] src/Controllers/ScheduleController.php (email notifications with rate limiting)
- [ ] src/Controllers/LocaleController.php
- [ ] src/Controllers/PWAController.php
- [ ] src/Controllers/HealthController.php
- [ ] src/Controllers/SecurityController.php (CSP reporting endpoint)

**Key Deliverables:**
- OAuth callback sequence: state validation → PKCE verification → token exchange → ID token verification → email allowlist check → user creation
- CSRF protection on all mutations (POST/PUT/DELETE)
- Rate limiting ONLY on: `/auth/login`, `/auth/callback`, `/csp-report`, `/schedule/send-request`
- Email allowlist checked AFTER ID token verification, BEFORE database write

### 5. Views (6-10 hours)
- [ ] public_html/views/layout.php (CSP nonce, CSRF meta tag, cache busting)
- [ ] public_html/views/landing.php
- [ ] public_html/views/dashboard.php
- [ ] public_html/views/calendar.php (FullCalendar integration)
- [ ] public_html/views/tasks-shared.php
- [ ] public_html/views/tasks-private.php
- [ ] public_html/views/schedule-requests.php
- [ ] public_html/views/404.php
- [ ] public_html/views/500.php
- [ ] public_html/views/503.php (database error page)
- [ ] public_html/views/offline.html (standalone, no PHP)
- [ ] public_html/views/components/header.php
- [ ] public_html/views/components/nav.php
- [ ] public_html/views/components/footer.php
- [ ] public_html/views/components/alerts.php

**Key Deliverables:**
- ALL inline scripts use `nonce="<?= cspNonce() ?>"`
- CSRF token in meta tag: `<meta name="csrf-token" content="<?= csrfToken() ?>">`
- Alert container div: `<div id="alert-container"></div>`
- offline.html is standalone HTML/CSS only (served by service worker)
- Components receive data via variables, not global state

### 6. Frontend Assets (8-15 hours)
- [ ] public_html/assets/css/style.css (custom CSS, <50KB)
- [ ] public_html/assets/js/app.js (HTMX CSRF config, service worker registration)
- [ ] public_html/service-worker.js (cache-first for static, network-first for API)
- [ ] public_html/manifest.json (complete PWA metadata with all icon sizes)
- [ ] public_html/assets/icons/ (PWA icons: 72, 96, 128, 144, 152, 192, 384, 512)
- [ ] Self-hosted vendor files (htmx.min.js, fullcalendar)

**Key Deliverables:**
- HTMX configured to send CSRF token in `X-CSRF-Token` header
- HTMX error handler for 403 CSRF failures (auto-refresh page)
- Service worker: cache-first for static assets, network-first for API/auth
- Service worker: NEVER cache API responses or auth endpoints
- Manifest includes all required PWA fields (name, short_name, icons, etc.)
- All assets self-hosted (NO CDNs)

### 7. Utility Scripts (4-8 hours)
- [ ] scripts/generate_key.php (32-byte hex generator)
- [ ] scripts/migrate.php (migration runner with tracking table)
- [ ] scripts/backup.sh (GPG-encrypted backups)
- [ ] scripts/restore_backup.sh
- [ ] scripts/deploy.sh (git pull, composer install, migrate)
- [ ] scripts/rotate_app_secret.php (handles APP_SECRET_PREV)
- [ ] scripts/bootstrap_whitelist.php
- [ ] scripts/verify_backup.sh (validates GPG encryption)
- [ ] scripts/check_permissions.sh (storage/ directory permissions)
- [ ] public_html/robots.txt (block ALL search engines)

**Key Deliverables:**
- robots.txt blocks all crawlers (User-agent: * / Disallow: /)
- Backup encryption uses GPG with AES256
- APP_SECRET rotation supports 8-day migration window
- Deploy script sets DEPLOY_TIMESTAMP for cache busting fallback

---

## Critical Implementation Details

### 1. PHP Version Check (bootstrap.php)

**CRITICAL:** Add version check as first line after `<?php`:

```php
<?php
declare(strict_types=1);

// PHP version check (MUST be first)
if (PHP_VERSION_ID < 80200) {
    die('PHP 8.2 or higher required. Current version: ' . PHP_VERSION);
}
```

### 2. CSP Nonce (CRITICAL)

### 2. CSP Nonce (CRITICAL)

**In bootstrap.php:**
```php
// Generate per-request (NOT in session)
$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

// Store in $GLOBALS for views
$GLOBALS['csp_nonce'] = $nonce;

// CSP Reporting (all three formats for compatibility)
$reportEndpoint = '/csp-report';
$reportingEndpoints = "default=\"{$reportEndpoint}\"";
$reportTo = json_encode([
    'group' => 'default',
    'max_age' => 10800,
    'endpoints' => [['url' => $reportEndpoint]]
]);

// Send CSP header
$csp = "default-src 'self'; " .
       "base-uri 'self'; " .
       "object-src 'none'; " .
       "frame-ancestors 'none'; " .
       "script-src 'self' 'nonce-{$nonce}'; " .
       "style-src 'self' 'unsafe-inline'; " .
       "img-src 'self' data:; " .
       "connect-src 'self'; " .
       "report-uri {$reportEndpoint}; " .
       "report-to default;";

header("Content-Security-Policy: {$csp}");
header("Reporting-Endpoints: {$reportingEndpoints}");
header("Report-To: {$reportTo}");
```

**In all views:**
```php
<script nonce="<?= cspNonce() ?>">
    // Inline JavaScript
</script>
```

**Helper function in helpers.php:**
```php
function cspNonce(): string {
    return $GLOBALS['csp_nonce'] ?? '';
}
```

### 3. Session Security (bootstrap.php)

### 3. Session Security (bootstrap.php)

**CRITICAL:** Configure session before `session_start()`:

```php
// Session security (BEFORE session_start)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1'); // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_name', '__Host-irmajosh_session'); // __Host- prefix
ini_set('session.gc_maxlifetime', '28800'); // 8 hours

session_start();

// Regenerate session ID on privilege elevation
if (isset($_SESSION['regenerate']) && $_SESSION['regenerate']) {
    session_regenerate_id(true);
    unset($_SESSION['regenerate']);
}
```

**Note:** `__Host-` prefix requires HTTPS and no Domain attribute (automatically enforced).

### 4. Error Display Configuration (bootstrap.php)

```php
// Error reporting based on environment
if (env('APP_ENV') === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Log all errors
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-errors.log');
```

### 5. Non-Persistent Database Connection (helpers.php)
    ### 5. Non-Persistent Database Connection (helpers.php)

**CRITICAL:** Per-request reuse, NOT persistent connections:

```php
function db(): PDO {
    static $pdo = null; // Per-request reuse
    
    // Return existing connection if available
    if ($pdo !== null) {
        return $pdo;
    }
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Non-persistent (2-user scale)
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+00:00', sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
    ];
    
    try {
        $pdo = new PDO(
            "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
            env('DB_USER'),
            env('DB_PASS'),
            $options
        );
        
        return $pdo;
    } catch (PDOException $e) {
        // Log error with sanitized message (no credentials)
        error_log('[CRITICAL] Database connection failed: ' . $e->getMessage());
        
        // Check if we're in a web request
        if (php_sapi_name() !== 'cli') {
            // Show user-friendly error page
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            
            if (file_exists(__DIR__ . '/../public_html/views/503.php')) {
                require __DIR__ . '/../public_html/views/503.php';
            } else {
                echo '<h1>Service Temporarily Unavailable</h1>';
                echo '<p>We\'re experiencing technical difficulties. Please try again later.</p>';
            }
            exit;
        }
        
        // In CLI mode, re-throw
        throw $e;
    }
}
```

### 6. Complete OAuth PKCE Flow (AuthController.php)

**CRITICAL:** Full PKCE implementation with S256 method:
}
```

### OAuth State Parameter Validation

### 6. Complete OAuth PKCE Flow (AuthController.php)

**CRITICAL:** Full PKCE implementation with S256 method:

**In login method (before redirect):**
```php
public function login(): void {
    // Generate code verifier (43-128 chars, base64url)
    $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    $_SESSION['oauth_code_verifier'] = $codeVerifier;
    $_SESSION['oauth_code_verifier_expiry'] = time() + 600; // 10 minutes
    
    // Generate code challenge (S256 method)
    $codeChallenge = rtrim(strtr(
        base64_encode(hash('sha256', $codeVerifier, true)),
        '+/', '-_'
    ), '=');
    
    // Generate state
    $_SESSION['oauth_state'] = bin2hex(random_bytes(32)); // 64-char hex
    $_SESSION['oauth_state_expiry'] = time() + 600;
    
    // Build authorization URL
    $params = http_build_query([
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'redirect_uri' => env('APP_URL') . '/auth/callback',
        'response_type' => 'code',
        'scope' => 'openid email profile https://www.googleapis.com/auth/calendar',
        'state' => $_SESSION['oauth_state'],
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);
    
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}
```

**In callback method:**
```php
public function callback(): void {
    // 1. Validate state parameter
    if (!isset($_SESSION['oauth_state']) || 
        !isset($_GET['state']) ||
        time() > $_SESSION['oauth_state_expiry']) {
        throw new Exception('Invalid state');
    }
    
    if (!hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
        throw new Exception('State mismatch');
    }
    
    // Clear state after use
    unset($_SESSION['oauth_state'], $_SESSION['oauth_state_expiry']);
    
    // 2. Validate code verifier
    if (!isset($_SESSION['oauth_code_verifier']) ||
        time() > $_SESSION['oauth_code_verifier_expiry']) {
        throw new Exception('Code verifier expired');
    }
    
    $codeVerifier = $_SESSION['oauth_code_verifier'];
    unset($_SESSION['oauth_code_verifier'], $_SESSION['oauth_code_verifier_expiry']);
    
    // 3. Exchange authorization code for tokens
    $tokenData = $this->authService->exchangeCodeForTokens(
        $_GET['code'],
        $codeVerifier
    );
    
    // 4. Verify ID token (CRITICAL)
    $userInfo = $this->authService->verifyIdToken($tokenData['id_token']);
    
    // 5. Check email allowlist (AFTER verification, BEFORE database)
    if (!$this->authService->isEmailAllowed($userInfo['email'])) {
        logMessage("Unauthorized email attempted login: " . redactPII($userInfo['email']), 'WARNING');
        throw new Exception('Your email is not authorized to access this application');
    }
    
    // 6. Create or update user in database
    $user = User::findBySub($userInfo['sub']) ?? User::create($userInfo);
    
    // 7. Save encrypted tokens
    UserToken::saveTokens($user['id'], $tokenData);
    
    // 8. Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['regenerate'] = true;
    
    // 9. Redirect to dashboard
    header('Location: /dashboard');
    exit;
}
```

### 7. ID Token Verification (AuthService.php)

**CRITICAL:** Verify ID tokens using Google's JWKS endpoint:

```php
// In AuthService.php
public function verifyIdToken(string $idToken): array {
    $client = $this->googleClientFactory->createClient();
    
    try {
        // Verify using google-api-client library
        $payload = $client->verifyIdToken($idToken);
        
        if (!$payload) {
            throw new Exception('Invalid ID token');
        }
        
        // Verify audience
        if ($payload['aud'] !== env('GOOGLE_CLIENT_ID')) {
            throw new Exception('Invalid audience');
        }
        
        // Verify issuer
        if (!in_array($payload['iss'], ['accounts.google.com', 'https://accounts.google.com'])) {
            throw new Exception('Invalid issuer');
        }
        
        // Verify expiration
        if ($payload['exp'] < time()) {
            throw new Exception('Token expired');
        }
        
        return [
            'sub' => $payload['sub'],        // User identifier (REQUIRED)
            'email' => $payload['email'],
            'email_verified' => $payload['email_verified'] ?? false,
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
        ];
    } catch (Exception $e) {
        logMessage('ID token verification failed: ' . $e->getMessage(), 'ERROR');
        throw $e;
    }
}
```

**CRITICAL:** Use `sub` claim as primary user identifier in `users` table, NOT email (emails can change).

### 8. OAuth Scope Configuration (GoogleClientFactory.php)

**CRITICAL:** `openid` scope is MANDATORY for ID token:

```php
// In GoogleClientFactory.php
class GoogleClientFactory {
    private const REQUIRED_SCOPES = [
        'openid',  // MANDATORY for ID token
        'email',
        'profile',
        'https://www.googleapis.com/auth/calendar',
    ];
    
    public function createClient(): Google_Client {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('APP_URL') . '/auth/callback');
        $client->setScopes(self::REQUIRED_SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        
        return $client;
    }
}
```

### 9. Token Encryption (UserToken.php)

**CRITICAL:** Encrypt tokens using libsodium with APP_SECRET:

```php
// In UserToken.php
class UserToken {
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24 bytes
    
    private static function encrypt(string $plaintext): string {
        $key = self::getEncryptionKey();
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }
    
    private static function decrypt(string $encrypted): string {
        $key = self::getEncryptionKey();
        $decoded = base64_decode($encrypted);
        
        if ($decoded === false || strlen($decoded) < self::NONCE_BYTES) {
            throw new Exception('Invalid encrypted data');
        }
        
        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $ciphertext = substr($decoded, self::NONCE_BYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        
        if ($plaintext === false) {
            throw new Exception('Decryption failed');
        }
        
        return $plaintext;
    }
    
    private static function getEncryptionKey(): string {
        $appSecret = env('APP_SECRET');
        if (strlen($appSecret) < 32) {
            throw new Exception('APP_SECRET too short');
        }
        return substr($appSecret, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
    
    public static function saveTokens(int $userId, array $tokens): void {
        $db = db();
        $stmt = $db->prepare("
            INSERT INTO user_tokens (user_id, access_token, refresh_token, expires_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $userId,
            self::encrypt($tokens['access_token']),
            isset($tokens['refresh_token']) ? self::encrypt($tokens['refresh_token']) : null,
            date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600))
        ]);
    }
    
    public static function getTokens(int $userId): ?array {
        $db = db();
        $stmt = $db->prepare("
            SELECT access_token, refresh_token, expires_at
            FROM user_tokens WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        
        if (!$row) return null;
        
        return [
            'access_token' => self::decrypt($row['access_token']),
            'refresh_token' => $row['refresh_token'] ? self::decrypt($row['refresh_token']) : null,
            'expires_at' => $row['expires_at']
        ];
    }
}
```

### 10. Rate Limiting (middleware.php)

**CRITICAL:** Rate limit ONLY specific endpoints (NOT all routes):

```php
// In middleware.php
function rateLimitMiddleware(): void {
    // ONLY rate limit these endpoints
    $rateLimitedPaths = [
        '/auth/login',
        '/auth/callback',
        '/csp-report',
        '/schedule/send-request', // Email sending
    ];
    
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if (!in_array($requestPath, $rateLimitedPaths)) {
        return; // No rate limiting for regular routes
    }
    
    $identifier = $_SERVER['REMOTE_ADDR']; // Use IP address
    $cacheKey = "rate_limit:{$requestPath}:{$identifier}";
    $limit = 100; // 100 requests
    $window = 900; // 15 minutes
    
    $cacheFile = __DIR__ . '/../storage/cache/' . md5($cacheKey);
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if ($data['expires'] < time()) {
            // Window expired, reset
            $data = ['count' => 1, 'expires' => time() + $window];
        } else {
            $data['count']++;
            
            if ($data['count'] > $limit) {
                http_response_code(429);
                header('Retry-After: ' . ($data['expires'] - time()));
                echo json_encode(['error' => 'Too many requests']);
                exit;
            }
        }
    } else {
        $data = ['count' => 1, 'expires' => time() + $window];
    }
    
    // Ensure cache directory exists
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    file_put_contents($cacheFile, json_encode($data));
}
```

### 11. HTMX CSRF Integration (app.js)

**CRITICAL:** Configure HTMX to send CSRF tokens:

```javascript
// In app.js
document.addEventListener('DOMContentLoaded', function() {
    // Configure HTMX to send CSRF token with all requests
    document.body.addEventListener('htmx:configRequest', function(event) {
        // Get CSRF token from meta tag
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        
        if (token) {
            // Add CSRF token to request headers
            event.detail.headers['X-CSRF-Token'] = token;
        }
    });
    
    // Handle CSRF failures
    document.body.addEventListener('htmx:responseError', function(event) {
        if (event.detail.xhr.status === 403) {
            const response = JSON.parse(event.detail.xhr.responseText);
            
            if (response.error && response.error.includes('CSRF')) {
                // Show user-friendly error
                showAlert('Security token expired. Please refresh the page.', 'error');
                
                // Optionally auto-refresh after 3 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        }
    });
    
    // Handle successful responses with alerts
    document.body.addEventListener('htmx:afterSwap', function(event) {
        const alertMessage = event.detail.xhr.getResponseHeader('X-Alert-Message');
        const alertType = event.detail.xhr.getResponseHeader('X-Alert-Type');
        
        if (alertMessage) {
            showAlert(alertMessage, alertType || 'info');
        }
    });
});

function showAlert(message, type) {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    
    alertContainer.appendChild(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}
```

### 12. Service Worker Caching Strategy (service-worker.js)

**CRITICAL:** Different strategies for different resources:

```javascript
// In service-worker.js
const CACHE_VERSION = 'v1';
const STATIC_CACHE = 'irmajosh-static-' + CACHE_VERSION;
const DYNAMIC_CACHE = 'irmajosh-dynamic-' + CACHE_VERSION;

const STATIC_ASSETS = [
    '/',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/vendor/htmx/htmx.min.js',
    '/assets/vendor/fullcalendar/index.global.min.js',
    '/offline.html',
];

// Install: Cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key.startsWith('irmajosh-') && key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
                    .map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch: Different strategies for different resources
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Network-first for API calls (always fresh data)
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/auth/')) {
        event.respondWith(
            fetch(request)
                .catch(() => caches.match('/offline.html'))
        );
        return;
    }
    
    // Cache-first for static assets
    if (STATIC_ASSETS.includes(url.pathname) || request.destination === 'image') {
        event.respondWith(
            caches.match(request).then((response) => {
                return response || fetch(request).then((fetchResponse) => {
                    return caches.open(DYNAMIC_CACHE).then((cache) => {
                        cache.put(request, fetchResponse.clone());
                        return fetchResponse;
                    });
                });
            }).catch(() => {
                if (request.destination === 'document') {
                    return caches.match('/offline.html');
                }
            })
        );
        return;
    }
    
    // Network-first for everything else
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok) {
                    return caches.open(DYNAMIC_CACHE).then((cache) => {
                        cache.put(request, response.clone());
                        return response;
                    });
                }
                return response;
            })
            .catch(() => {
                return caches.match(request).then((response) => {
                    return response || caches.match('/offline.html');
                });
            })
    );
});
```

**IMPORTANT:** Never cache API responses or authentication endpoints. Always use network-first or network-only for dynamic data.

### 13. offline.html Implementation

**CRITICAL:** Standalone HTML/CSS only (no PHP, no external resources):

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - IrmaJosh</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }
        
        .offline-container {
            max-width: 400px;
        }
        
        .offline-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .retry-btn {
            margin-top: 30px;
            padding: 12px 30px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .retry-btn:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">📡</div>
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection. Some features require an active connection to work properly.</p>
        <button class="retry-btn" onclick="window.location.reload()">Try Again</button>
    </div>
</body>
</html>
```

### 14. Complete Helper Functions List (helpers.php)

**Required 25+ helper functions:**

```php
// Configuration
function env(string $key, mixed $default = null): mixed
function config(string $key, mixed $default = null): mixed

// Database
function db(): PDO
function query(string $sql, array $params = []): array

// Security
function csrfToken(): string
function verifyCsrfToken(string $token): bool
function cspNonce(): string
function hashPassword(string $password): string
function verifyPassword(string $password, string $hash): bool

// Session
function setFlash(string $key, string $message): void
function getFlash(string $key): ?string
function hasFlash(string $key): bool

// Authentication
function isAuthenticated(): bool
function currentUser(): ?array
function requireAuth(): void
function logout(): void

// Logging
function logMessage(string $message, string $level = 'INFO'): void
function redactPII(string $message): string

// Response
function json(mixed $data, int $status = 200): void
function redirect(string $url, int $status = 302): void
function view(string $template, array $data = []): void

// Asset management
function asset(string $path): string
function getAssetVersion(): string

// Translation
function t(string $key, array $replacements = []): string
function setLocale(string $locale): void
function getLocale(): string

// Validation
function validateEmail(string $email): bool
function validateUrl(string $url): bool
function sanitizeInput(string $input): string

// Date/Time
function formatDate(string $datetime, string $format = 'Y-m-d H:i'): string
function toUtc(string $datetime): string
function fromUtc(string $datetime, string $timezone = 'UTC'): string
```

### 15. PII Redaction
    $patterns = [
        '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/' => '***@$2',
        '/"(access_token|refresh_token|id_token)"\s*:\s*"[^"]+"/' => '"$1":"***REDACTED***"',
        '/APP_SECRET\s*=\s*[^\s]+/' => 'APP_SECRET=***REDACTED***',
    ];
    
    return preg_replace(array_keys($patterns), array_values($patterns), $message);
}
```

### Asset Cache Busting

```php
<?php $assetVersion = trim(shell_exec('git rev-parse --short HEAD') ?: 'v1'); ?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= $assetVersion ?>">
<script src="/assets/js/app.js?v=<?= $assetVersion ?>"></script>
```

---

## Common Implementation Mistakes to Avoid

### ❌ Don't:
- Store CSP nonce in `$_SESSION` (use `$GLOBALS`)
- Use persistent database connections (use non-persistent)
- Skip ID token verification (security vulnerability)
- Hard-code secrets in code (use environment variables)
- Cache API responses in service worker (always stale)
- Use CDNs for assets (must self-host)
- Skip CSRF tokens on mutations (security vulnerability)
- Apply rate limiting to all routes (only specific endpoints)
- Use email as primary key (use `sub` claim)
- Store tokens in plaintext (must encrypt)

### ✅ Do:
- Generate CSP nonce per-request in bootstrap.php
- Use prepared statements for all database queries
- Verify ID tokens using Google JWKS
- Store all secrets in .env
- Use network-first strategy for API calls
- Self-host all JavaScript/CSS libraries
- Add CSRF tokens to all POST/PUT/DELETE forms
- Rate limit only auth/notification endpoints
- Use `sub` claim as user identifier
- Encrypt all OAuth tokens with libsodium

---

## Testing During Implementation

Test each component as you build it:

### Helper Functions
```bash
# Test in PHP REPL
php -a
>>> require 'src/bootstrap.php';
>>> echo env('APP_NAME');
>>> $db = db();
>>> echo csrfToken();
```

### Component Testing Commands

```bash
# Test helpers
php -r "require 'src/bootstrap.php'; echo env('APP_NAME');"

# Test database
php -r "require 'src/bootstrap.php'; var_dump(db()->query('SELECT 1')->fetch());"

# Test CSRF
php -r "require 'src/bootstrap.php'; echo csrfToken();"
```

### Controllers
- Test each route as you implement it
- Use browser or curl for HTTP testing
- Verify CSRF protection works (should reject requests without token)
- Test OAuth flow end-to-end with real Google account
- Verify email allowlist blocks unauthorized users

### Views
- Check CSP nonce on ALL inline scripts (browser console will show violations)
- Verify no JavaScript console errors
- Test responsive design at different breakpoints
- Test HTMX interactions (create/update/delete operations)

### Service Worker
- Test offline mode (DevTools → Network → Offline)
- Verify cache strategies (check Network tab for cache hits)
- Check DevTools → Application → Service Workers
- Test PWA installation (Desktop/Mobile)

### Debugging Tips

**CSP Issues:**
- Check browser console for CSP violations
- Verify all inline scripts have nonce attribute
- Check nonce matches between header and script tags

**OAuth Issues:**
- Enable debug logging in GoogleClientFactory
- Verify redirect URI matches exactly (including protocol)
- Check state parameter is being validated
- Verify PKCE code_verifier/code_challenge flow

**Database Issues:**
- Check MySQL error log: `tail -f /var/log/mysql/error.log`
- Verify connection parameters in .env
- Test manual connection: `mysql -u user -p database`

---

## Phase 3 Completion Checklist

### Core Files
- [ ] public_html/index.php created (HTTPS enforcement)
- [ ] src/bootstrap.php created with:
  - [ ] PHP version check (8.2+)
  - [ ] CSP nonce generation in `$GLOBALS`
  - [ ] Session security (`__Host-` prefix, Secure, HttpOnly, SameSite=Strict)
  - [ ] Error reporting configuration
  - [ ] All security headers (CSP, X-Content-Type-Options, X-Frame-Options, etc.)
- [ ] src/helpers.php created with all 25+ functions
- [ ] src/router.php created (dynamic route matching)
- [ ] src/middleware.php created (auth, CSRF, rate limiting)
- [ ] .gitignore created (vendor/, storage/, .env)

### Models (6 total)
- [ ] All models created with database operations
- [ ] FK relationships properly handled
- [ ] UserToken: Encryption/decryption with libsodium implemented
- [ ] UserToken: APP_SECRET_PREV support for key rotation
- [ ] User: Uses `sub` claim as primary identifier (not email)
- [ ] AuditLog: PII redaction on write

### Services (5 total)
- [ ] GoogleClientFactory: Includes 'openid' scope (MANDATORY)
- [ ] AuthService: Complete ID token verification implemented
- [ ] AuthService: Email allowlist checking
- [ ] CalendarService: Error handling with retry logic (429, 5xx)
- [ ] CalendarService: Token refresh implementation
- [ ] EmailService: PHPMailer configuration with SMTP
- [ ] TranslationService: Fallback to English, error handling

### Controllers (9 total)
- [ ] BaseController created
- [ ] AuthController: Complete PKCE flow (S256 method)
- [ ] AuthController: State parameter validation
- [ ] AuthController: Correct callback sequence (state → PKCE → tokens → ID verify → allowlist → DB)
- [ ] DashboardController created
- [ ] CalendarController: CRUD operations with error handling
- [ ] TaskController: Ownership validation
- [ ] ScheduleController: Email notifications with rate limiting
- [ ] LocaleController created
- [ ] PWAController created
- [ ] HealthController created
- [ ] SecurityController: CSP reporting endpoint

### Views (15 total)
- [ ] layout.php created with:
  - [ ] CSP nonce usage
  - [ ] CSRF meta tag
  - [ ] Alert container div
  - [ ] Cache-busted asset URLs
- [ ] All page views created (landing, dashboard, calendar, tasks, schedule-requests)
- [ ] Error pages created (404, 500, 503)
- [ ] offline.html created (standalone HTML/CSS only, no PHP)
- [ ] All components created (header, nav, footer, alerts)
- [ ] Nonce attribute on ALL inline scripts/styles
- [ ] Components receive data via variables (not global state)

### Frontend Assets
- [ ] style.css created (<50KB, self-contained)
- [ ] app.js created with:
  - [ ] HTMX CSRF configuration
  - [ ] HTMX error handling (403 auto-refresh)
  - [ ] Service worker registration
  - [ ] Alert display function
- [ ] service-worker.js created with:
  - [ ] Cache-first for static assets
  - [ ] Network-first for API/auth
  - [ ] Offline fallback
  - [ ] Cache cleanup on activate
- [ ] manifest.json created with all required PWA fields
- [ ] PWA icons generated (72, 96, 128, 144, 152, 192, 384, 512)
- [ ] Vendor assets self-hosted (htmx.min.js, fullcalendar)

### Scripts (10 total)
- [ ] generate_key.php (32-byte hex)
- [ ] migrate.php (migration runner with _migrations table)
- [ ] backup.sh (GPG-encrypted with AES256)
- [ ] restore_backup.sh
- [ ] deploy.sh (git pull, composer install, migrate, DEPLOY_TIMESTAMP)
- [ ] rotate_app_secret.php (APP_SECRET_PREV support)
- [ ] bootstrap_whitelist.php
- [ ] verify_backup.sh (validates GPG encryption)
- [ ] check_permissions.sh (storage/ permissions)
- [ ] robots.txt (blocks ALL search engines)

### Security Verification
- [ ] CSP nonce generated per-request (NOT in session)
- [ ] All inline scripts use nonce attribute
- [ ] CSRF tokens on all mutations (POST/PUT/DELETE)
- [ ] Rate limiting only on specific endpoints
- [ ] OAuth tokens encrypted with libsodium
- [ ] ID token verification implemented
- [ ] PKCE flow complete (S256 method)
- [ ] Email allowlist checked before database write
- [ ] PII redaction in all logs
- [ ] Session cookies use `__Host-` prefix
- [ ] Database connection is non-persistent
- [ ] No CDN usage (all assets self-hosted)

### Code Quality
- [ ] No PHP syntax errors (run `php -l` on all files)
- [ ] All classes use proper namespaces
- [ ] All functions have return type declarations
- [ ] All database queries use prepared statements
- [ ] All user input is sanitized/validated
- [ ] All passwords use password_hash()
- [ ] All tokens use encryption
- [ ] Error reporting configured (off in production)

### Testing Completed
- [ ] Helper functions tested
- [ ] Database connection tested
- [ ] OAuth flow tested end-to-end
- [ ] CSRF protection tested (blocks invalid tokens)
- [ ] Email allowlist tested (blocks unauthorized emails)
- [ ] Rate limiting tested
- [ ] Service worker tested (offline mode works)
- [ ] PWA installable on desktop/mobile
- [ ] All routes return expected responses

### Documentation
- [ ] README.md updated with setup instructions
- [ ] .env.example created with all required variables
- [ ] All critical sections have inline comments
- [ ] All files committed to git
- [ ] Git tags created for version tracking

---

## Next Steps

✅ **Phase 3 Complete!**

Proceed to **Phase 4: Deployment** to:
- Configure production server
- Set up Apache and PHP-FPM
- Deploy application
- Configure SSL and DNS

---

**PHASE 3 - CODE IMPLEMENTATION v2.0 - October 22, 2025**

*Updated with comprehensive security specifications from agent reviews (Claude + GPT)*

**Critical Changes from v1.0:**
- Added pre-implementation checklist
- Expanded critical implementation details (19 sections vs 4)
- Added complete code examples for all security-critical components
- Added PKCE flow, ID token verification, token encryption implementations
- Added HTMX CSRF integration, service worker caching strategies
- Added complete helper functions list, common mistakes section
- Expanded testing guidance with debugging tips
- Comprehensive completion checklist (100+ items)
