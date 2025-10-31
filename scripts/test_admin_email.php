<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

echo "Testing admin email notifications...\n\n";

try {
    $emailService = new \App\Services\EmailService();
    
    if (!$emailService->isConfigured()) {
        echo "✗ Email not configured\n";
        exit(1);
    }
    
    echo "Sending test admin notification...\n";
    
    $result = $emailService->sendAdminNotification(
        'Test Admin Notification',
        "This is a test of the admin notification system.\n\n" .
        "Time: " . date('Y-m-d H:i:s') . "\n" .
        "Server: " . gethostname() . "\n\n" .
        "If you receive this email at both Gmail addresses, the admin notification system is working correctly."
    );
    
    if ($result) {
        echo "✓ Admin notification sent successfully!\n";
        echo "Check both Gmail inboxes (and spam folders):\n";
        echo "  - jgib0719@gmail.com\n";
        echo "  - irmakusuma200@gmail.com\n";
    } else {
        echo "✗ Failed to send admin notification\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
    exit(1);
}
