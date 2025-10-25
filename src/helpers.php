<?php
declare(strict_types=1);

/**
 * Helper Functions
 * 
 * Global utility functions used throughout the application
 */

// ============================================================================
// Configuration
// ============================================================================

/**
 * Get environment variable
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string booleans to actual booleans
    if (in_array(strtolower($value), ['true', 'false'], true)) {
        return strtolower($value) === 'true';
    }
    
    return $value;
}

/**
 * Get configuration value (alias for env)
 */
function config(string $key, mixed $default = null): mixed
{
    return env($key, $default);
}

// ============================================================================
// Database
// ============================================================================

/**
 * Get database connection (non-persistent, per-request reuse)
 */
function db(): PDO
{
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
            if (file_exists(__DIR__ . '/../public_html/views/errors/503.php')) {
                require __DIR__ . '/../public_html/views/errors/503.php';
            } else {
                echo '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body><h1>Service Unavailable</h1><p>Database connection failed. Please try again later.</p></body></html>';
            }
            exit;
        }
        
        // In CLI mode, re-throw
        throw $e;
    }
}

/**
 * Execute a query and return results
 */
function query(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ============================================================================
// Security
// ============================================================================

/**
 * Get CSRF token
 */
function csrfToken(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSP nonce for inline scripts/styles
 */
function cspNonce(): string
{
    return $GLOBALS['csp_nonce'] ?? '';
}

/**
 * Hash a password
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verify a password against a hash
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// ============================================================================
// Session & Flash Messages
// ============================================================================

/**
 * Set a flash message
 */
function setFlash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

/**
 * Get and clear a flash message
 */
function getFlash(string $key): ?string
{
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

/**
 * Check if a flash message exists
 */
function hasFlash(string $key): bool
{
    return isset($_SESSION['flash'][$key]);
}

// ============================================================================
// Authentication
// ============================================================================

/**
 * Check if user is authenticated
 */
function isAuthenticated(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user
 */
function currentUser(): ?array
{
    if (!isAuthenticated()) {
        return null;
    }
    
    static $user = null;
    
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    
    return $user ?: null;
}

/**
 * Require authentication (redirect to login if not authenticated)
 */
function requireAuth(): void
{
    if (!isAuthenticated()) {
        setFlash('error', 'Please log in to continue');
        redirect('/');
    }
}

/**
 * Log out current user
 */
function logout(): void
{
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

// ============================================================================
// Logging
// ============================================================================

/**
 * Log a message to file
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $logFile = __DIR__ . '/../storage/logs/app.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Redact PII from messages
 */
function redactPII(string $message): string
{
    $patterns = [
        // Email addresses (redact local part)
        '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/' => '***@$2',
        // Phone numbers
        '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/' => '***-***-****',
        // APP_SECRET in logs
        '/APP_SECRET\s*=\s*[^\s]+/' => 'APP_SECRET=***REDACTED***',
        '/APP_SECRET_CURR\s*=\s*[^\s]+/' => 'APP_SECRET_CURR=***REDACTED***',
        '/APP_SECRET_PREV\s*=\s*[^\s]+/' => 'APP_SECRET_PREV=***REDACTED***',
    ];
    
    return preg_replace(array_keys($patterns), array_values($patterns), $message);
}

// ============================================================================
// Response
// ============================================================================

/**
 * Send JSON response
 */
function json(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Redirect to URL
 */
function redirect(string $url, int $status = 302): void
{
    http_response_code($status);
    header("Location: {$url}");
    exit;
}

/**
 * Render a view template
 */
function view(string $template, array $data = []): void
{
    extract($data);
    
    $templatePath = __DIR__ . '/../public_html/views/' . $template . '.php';
    
    if (!file_exists($templatePath)) {
        http_response_code(500);
        echo "View template not found: {$template}";
        exit;
    }
    
    http_response_code(200);
    require $templatePath;
    exit;
}

// ============================================================================
// Asset Management
// ============================================================================

/**
 * Get asset URL with cache busting
 */
function asset(string $path): string
{
    $version = getAssetVersion();
    return rtrim(env('APP_URL'), '/') . '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

/**
 * Get asset version for cache busting
 */
function getAssetVersion(): string
{
    static $version = null;
    
    if ($version === null) {
        // Try git commit hash first
        $gitHash = @shell_exec('git rev-parse --short HEAD 2>/dev/null');
        if ($gitHash) {
            $version = trim($gitHash);
        } else {
            // Fall back to deploy timestamp or default
            $version = env('DEPLOY_TIMESTAMP', 'v1');
        }
    }
    
    return $version;
}

// ============================================================================
// Translation
// ============================================================================

/**
 * Translate a key
 */
function t(string $key, array $replacements = []): string
{
    static $translations = null;
    
    if ($translations === null) {
        $locale = getAppLocale();
        $translationFile = __DIR__ . "/../locales/{$locale}/messages.php";
        
        if (file_exists($translationFile)) {
            $translations = require $translationFile;
        } else {
            // Fallback to English
            $translationFile = __DIR__ . '/../locales/en/messages.php';
            if (file_exists($translationFile)) {
                $translations = require $translationFile;
            } else {
                $translations = [];
            }
        }
    }
    
    $translation = $translations[$key] ?? $key;
    
    // Replace placeholders
    foreach ($replacements as $placeholder => $value) {
        $translation = str_replace("{{$placeholder}}", $value, $translation);
    }
    
    return $translation;
}

/**
 * Set current application locale
 */
function setAppLocale(string $locale): void
{
    $_SESSION['locale'] = $locale;
}

/**
 * Get current application locale
 */
function getAppLocale(): string
{
    return $_SESSION['locale'] ?? env('APP_LOCALE', 'en');
}

// ============================================================================
// Validation
// ============================================================================

/**
 * Validate email address
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function validateUrl(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Sanitize input string
 */
function sanitizeInput(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ============================================================================
// Date/Time
// ============================================================================

/**
 * Format a datetime string
 */
function formatDate(string $datetime, string $format = 'Y-m-d H:i'): string
{
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        logMessage("Date formatting error: " . $e->getMessage(), 'ERROR');
        return $datetime;
    }
}

/**
 * Convert datetime to UTC
 */
function toUtc(string $datetime, string $fromTimezone = 'UTC'): string
{
    try {
        $dt = new DateTime($datetime, new DateTimeZone($fromTimezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        logMessage("Date conversion error: " . $e->getMessage(), 'ERROR');
        return $datetime;
    }
}

/**
 * Convert datetime from UTC to specific timezone
 */
function fromUtc(string $datetime, string $toTimezone = 'UTC'): string
{
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($toTimezone));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        logMessage("Date conversion error: " . $e->getMessage(), 'ERROR');
        return $datetime;
    }
}

// ============================================================================
// Utility
// ============================================================================

/**
 * Generate a random string
 */
function randomString(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if request is AJAX/HTMX
 */
function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
           !empty($_SERVER['HTTP_HX_REQUEST']);
}

/**
 * Get client IP address
 */
function getClientIp(): string
{
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (get first one)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    
    return '0.0.0.0';
}
