<?php
declare(strict_types=1);

/**
 * Application Bootstrap
 * 
 * Initializes the application environment:
 * - PHP version check
 * - Environment loading
 * - Error reporting
 * - Session security
 * - Security headers (CSP, HSTS, etc.)
 * - Autoloading
 */

// PHP version check (MUST be first)
if (PHP_VERSION_ID < 80200) {
    die('PHP 8.2 or higher required. Current version: ' . PHP_VERSION);
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Load helper functions
require_once __DIR__ . '/helpers.php';

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

// Ensure error log directory exists
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Session security (BEFORE session_start)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1'); // HTTPS only
ini_set('session.cookie_samesite', 'Lax'); // Lax allows OAuth redirects
ini_set('session.use_strict_mode', '1');
session_name('__Host-irmajosh_session'); // __Host- prefix (must use session_name, not ini_set)
ini_set('session.gc_maxlifetime', '28800'); // 8 hours
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_character', '6');

// Start session
session_start();

// Regenerate session ID on privilege elevation
if (isset($_SESSION['regenerate']) && $_SESSION['regenerate']) {
    session_regenerate_id(true);
    unset($_SESSION['regenerate']);
}

// Generate CSP nonce (per-request, NOT in session)
$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
$GLOBALS['csp_nonce'] = $nonce;

// CSP Reporting endpoints (all three formats for compatibility)
$reportEndpoint = '/csp-report';
$reportingEndpoints = "default=\"{$reportEndpoint}\"";
$reportTo = json_encode([
    'group' => 'default',
    'max_age' => 10800,
    'endpoints' => [['url' => $reportEndpoint]]
], JSON_THROW_ON_ERROR);

// Content Security Policy
$csp = "default-src 'self'; " .
       "base-uri 'self'; " .
       "object-src 'none'; " .
       "frame-ancestors 'none'; " .
       "script-src 'self' 'nonce-{$nonce}'; " .
       "style-src 'self' 'unsafe-inline'; " .
       "img-src 'self' data:; " .
       "connect-src 'self'; " .
       "font-src 'self'; " .
       "media-src 'self'; " .
       "form-action 'self'; " .
       "upgrade-insecure-requests; " .
       "report-uri {$reportEndpoint}; " .
       "report-to default;";

// Security headers
header("Content-Security-Policy: {$csp}");
header("Reporting-Endpoints: {$reportingEndpoints}");
header("Report-To: {$reportTo}");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// HSTS (only if HTTPS)
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'http') === 'https') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Set timezone to UTC
date_default_timezone_set('UTC');

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
