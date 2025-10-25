<?php
/**
 * Bootstrap Email Allowlist
 * Creates initial email allowlist from command line input
 * 
 * Usage: php bootstrap_whitelist.php email1@example.com email2@example.com
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if ($argc < 2) {
    echo "Usage: php bootstrap_whitelist.php email1@example.com email2@example.com ...\n";
    exit(1);
}

// Collect email arguments
$emails = array_slice($argv, 1);

// Validate all emails
$validEmails = [];
foreach ($emails as $email) {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Error: Invalid email address: {$email}\n";
        exit(1);
    }
    $validEmails[] = $email;
}

if (empty($validEmails)) {
    echo "Error: No valid email addresses provided\n";
    exit(1);
}

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

// Create comma-separated list
$allowlistValue = implode(',', $validEmails);

// Read current .env content
$envContent = file_get_contents($envFile);

// Update or add EMAIL_ALLOWLIST
if (preg_match('/^EMAIL_ALLOWLIST=/m', $envContent)) {
    // Get current value
    preg_match('/^EMAIL_ALLOWLIST=(.*)$/m', $envContent, $matches);
    $currentValue = $matches[1] ?? '';
    
    echo "Current EMAIL_ALLOWLIST:\n";
    echo "  {$currentValue}\n\n";
    
    echo "New EMAIL_ALLOWLIST will be:\n";
    echo "  {$allowlistValue}\n\n";
    
    echo "This will REPLACE the current allowlist.\n";
    echo "Type 'yes' to continue: ";
    $confirmation = trim(fgets(STDIN));
    
    if (strtolower($confirmation) !== 'yes') {
        echo "Cancelled\n";
        exit(0);
    }
    
    // Replace existing EMAIL_ALLOWLIST
    $envContent = preg_replace(
        '/^EMAIL_ALLOWLIST=.*$/m',
        "EMAIL_ALLOWLIST={$allowlistValue}",
        $envContent
    );
} else {
    // Add EMAIL_ALLOWLIST at the end
    if (!str_ends_with($envContent, "\n")) {
        $envContent .= "\n";
    }
    $envContent .= "EMAIL_ALLOWLIST={$allowlistValue}\n";
}

// Write updated .env
if (!file_put_contents($envFile, $envContent)) {
    echo "Error: Failed to write to .env file\n";
    exit(1);
}

echo "\n========================================\n";
echo "Email Allowlist Updated\n";
echo "========================================\n";
echo "Allowed emails:\n";
foreach ($validEmails as $email) {
    echo "  - {$email}\n";
}
echo "\nOnly these email addresses can authenticate via Google OAuth.\n";
echo "========================================\n";

exit(0);
