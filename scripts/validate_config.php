<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$errors = [];

// Required variables
$required = [
    'APP_NAME', 'APP_ENV', 'APP_URL', 'APP_SECRET_CURR', 'APP_LOCALE', 'APP_TIMEZONE',
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI', 'GOOGLE_SCOPES',
    'SESSION_SECURE', 'SESSION_COOKIE_NAME', 'SESSION_LIFETIME',
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME',
    'ALLOWED_EMAILS', 'RATE_LIMIT_ENABLED'
];

foreach ($required as $var) {
    if (empty($_ENV[$var])) {
        $errors[] = "Missing required variable: {$var}";
    }
}

// Validate APP_SECRET_CURR length (must be 64 hex chars)
if (isset($_ENV['APP_SECRET_CURR'])) {
    if (strlen($_ENV['APP_SECRET_CURR']) !== 64) {
        $errors[] = "APP_SECRET_CURR must be exactly 64 characters (got " . strlen($_ENV['APP_SECRET_CURR']) . ")";
    }
    if (!ctype_xdigit($_ENV['APP_SECRET_CURR'])) {
        $errors[] = "APP_SECRET_CURR must be a hexadecimal string";
    }
}

// Validate SESSION_SECURE in production
if ($_ENV['APP_ENV'] === 'production' && $_ENV['SESSION_SECURE'] !== 'true') {
    $errors[] = "CRITICAL: SESSION_SECURE must be 'true' in production (required for __Host- cookies)";
}

// Validate HTTPS in production
if ($_ENV['APP_ENV'] === 'production' && !str_starts_with($_ENV['APP_URL'], 'https://')) {
    $errors[] = "CRITICAL: APP_URL must use HTTPS in production (required for Secure cookies)";
}

// Validate OAuth scopes include 'openid'
if (isset($_ENV['GOOGLE_SCOPES'])) {
    if (!str_contains($_ENV['GOOGLE_SCOPES'], 'openid')) {
        $errors[] = "CRITICAL: GOOGLE_SCOPES must include 'openid' (required for ID token with sub claim)";
    }
}

// Validate ALLOWED_EMAILS is set
if (empty($_ENV['ALLOWED_EMAILS'])) {
    $errors[] = "CRITICAL: ALLOWED_EMAILS must be configured (security requirement)";
}

// Validate timezone
if (!in_array($_ENV['APP_TIMEZONE'] ?? '', timezone_identifiers_list())) {
    $errors[] = "Invalid APP_TIMEZONE: {$_ENV['APP_TIMEZONE']}";
}

// Validate SESSION_COOKIE_NAME uses __Host- prefix in production
if ($_ENV['APP_ENV'] === 'production' && !str_starts_with($_ENV['SESSION_COOKIE_NAME'], '__Host-')) {
    $errors[] = "CRITICAL: SESSION_COOKIE_NAME must use '__Host-' prefix in production";
}

// Report results
if ($errors) {
    echo "❌ Configuration validation failed:\n\n";
    foreach ($errors as $error) {
        echo "   ⚠️  {$error}\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "✅ Configuration validation passed!\n";
    echo "\nEnvironment: {$_ENV['APP_ENV']}\n";
    echo "App URL: {$_ENV['APP_URL']}\n";
    echo "Database: {$_ENV['DB_NAME']}\n";
    echo "Session Secure: {$_ENV['SESSION_SECURE']}\n";
}
