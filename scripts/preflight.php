<?php
/**
 * Pre-deployment verification script
 * Run before migrations to catch configuration issues early
 */

$errors = [];
$warnings = [];

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = "PHP 8.2+ required, found " . PHP_VERSION;
}

// Check required PHP extensions
$requiredExtensions = ['pdo_mysql', 'sodium', 'intl', 'curl', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Missing PHP extension: {$ext}";
    }
}

// Check .env exists and is readable
if (!file_exists(__DIR__ . '/../.env')) {
    $errors[] = ".env file not found";
} elseif (!is_readable(__DIR__ . '/../.env')) {
    $errors[] = ".env file not readable";
}

// Check vendor directory exists (composer install ran)
if (!is_dir(__DIR__ . '/../vendor')) {
    $errors[] = "vendor/ directory not found - run 'composer install'";
}

// Check storage directory exists and is writable
if (!is_dir(__DIR__ . '/../storage')) {
    $errors[] = "storage/ directory not found";
} elseif (!is_writable(__DIR__ . '/../storage')) {
    $errors[] = "storage/ directory not writable";
}

// Check database connection (if .env exists)
if (file_exists(__DIR__ . '/../.env')) {
    require __DIR__ . '/../vendor/autoload.php';
    
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        
        // Check critical environment variables
        $required = ['APP_ENV', 'APP_SECRET_CURR', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($required as $var) {
            if (empty($_ENV[$var])) {
                $errors[] = "Missing required environment variable: {$var}";
            }
        }
        
        // Check APP_ENV is production
        if (!empty($_ENV['APP_ENV']) && $_ENV['APP_ENV'] !== 'production') {
            $warnings[] = "APP_ENV is not set to 'production' (current: {$_ENV['APP_ENV']})";
        }
        
        // Check SESSION_SECURE is true
        if (empty($_ENV['SESSION_SECURE']) || $_ENV['SESSION_SECURE'] !== 'true') {
            $errors[] = "SESSION_SECURE must be 'true' in production";
        }
        
        // Check SESSION_COOKIE_NAME starts with __Host-
        if (!empty($_ENV['SESSION_COOKIE_NAME']) && strpos($_ENV['SESSION_COOKIE_NAME'], '__Host-') !== 0) {
            $errors[] = "SESSION_COOKIE_NAME must start with '__Host-' in production";
        }
        
        // Check APP_SECRET length
        if (!empty($_ENV['APP_SECRET_CURR']) && strlen($_ENV['APP_SECRET_CURR']) !== 64) {
            $errors[] = "APP_SECRET_CURR must be 64 characters (current: " . strlen($_ENV['APP_SECRET_CURR']) . ")";
        }
        
        // Test database connection
        if (!empty($_ENV['DB_HOST']) && !empty($_ENV['DB_NAME']) && !empty($_ENV['DB_USER']) && !empty($_ENV['DB_PASS'])) {
            try {
                $db = new PDO(
                    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
                    $_ENV['DB_USER'],
                    $_ENV['DB_PASS']
                );
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                $errors[] = "Database connection failed: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Failed to load .env: " . $e->getMessage();
    }
}

// Output results
echo "\n=== PRE-FLIGHT VERIFICATION ===\n\n";

if (!empty($errors)) {
    echo "ERRORS (must fix before deployment):\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS (should review):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "✓ All checks passed! Ready for deployment.\n\n";
    exit(0);
} elseif (!empty($errors)) {
    echo "❌ Pre-flight failed. Fix errors above before deploying.\n\n";
    exit(1);
} else {
    echo "⚠ Pre-flight passed with warnings. Review before deploying.\n\n";
    exit(0);
}
