<?php
/**
 * APP_SECRET Rotation Script
 * Rotates APP_SECRET while maintaining APP_SECRET_PREV for 8-day grace period
 * 
 * Usage: php rotate_app_secret.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    echo "Error: .env file not found\n";
    exit(1);
}

if (!is_writable($envFile)) {
    echo "Error: .env file is not writable\n";
    exit(1);
}

// Generate new APP_SECRET (64 hex characters = 32 bytes)
$newSecret = bin2hex(random_bytes(32));

// Get current APP_SECRET
$currentSecret = $_ENV['APP_SECRET'] ?? null;

if (!$currentSecret) {
    echo "Error: APP_SECRET not found in .env\n";
    exit(1);
}

if (strlen($currentSecret) < 64) {
    echo "Error: Current APP_SECRET is too short (must be at least 64 hex characters)\n";
    exit(1);
}

// Read current .env content
$envContent = file_get_contents($envFile);

// Update APP_SECRET and set APP_SECRET_PREV
if (preg_match('/^APP_SECRET_PREV=/m', $envContent)) {
    // Replace existing APP_SECRET_PREV
    $envContent = preg_replace(
        '/^APP_SECRET_PREV=.*$/m',
        "APP_SECRET_PREV={$currentSecret}",
        $envContent
    );
} else {
    // Add APP_SECRET_PREV after APP_SECRET
    $envContent = preg_replace(
        '/^APP_SECRET=.*$/m',
        "APP_SECRET={$currentSecret}\nAPP_SECRET_PREV={$currentSecret}",
        $envContent
    );
}

// Replace APP_SECRET with new value
$envContent = preg_replace(
    '/^APP_SECRET=.*$/m',
    "APP_SECRET={$newSecret}",
    $envContent
);

// Set rotation timestamp
$rotationDate = date('Y-m-d H:i:s');
if (preg_match('/^APP_SECRET_ROTATED=/m', $envContent)) {
    $envContent = preg_replace(
        '/^APP_SECRET_ROTATED=.*$/m',
        "APP_SECRET_ROTATED={$rotationDate}",
        $envContent
    );
} else {
    $envContent .= "\nAPP_SECRET_ROTATED={$rotationDate}\n";
}

// Write updated .env
if (!file_put_contents($envFile, $envContent)) {
    echo "Error: Failed to write to .env file\n";
    exit(1);
}

echo "========================================\n";
echo "APP_SECRET Rotation Complete\n";
echo "========================================\n";
echo "New APP_SECRET: {$newSecret}\n";
echo "Previous secret saved to APP_SECRET_PREV\n";
echo "Rotated at: {$rotationDate}\n";
echo "\n";
echo "IMPORTANT:\n";
echo "- APP_SECRET_PREV will work for 8 days\n";
echo "- After 8 days, remove APP_SECRET_PREV from .env\n";
echo "- All encrypted tokens will be re-encrypted on next use\n";
echo "========================================\n";

exit(0);
