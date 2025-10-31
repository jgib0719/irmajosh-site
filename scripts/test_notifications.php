#!/usr/bin/env php
<?php
/**
 * Test Push Notifications
 * 
 * This script tests the push notification system
 * Usage: php scripts/test_notifications.php [user_id]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use App\Services\NotificationService;
use App\Models\PushSubscription;

// Get user ID from command line or use default
$userId = isset($argv[1]) ? (int)$argv[1] : null;

if (!$userId) {
    echo "Usage: php scripts/test_notifications.php [user_id]\n";
    echo "Example: php scripts/test_notifications.php 1\n";
    exit(1);
}

try {
    $db = db();
    $pushModel = new PushSubscription($db);
    
    // Check if user has subscriptions
    $subscriptions = $pushModel->getUserSubscriptions($userId);
    
    if (empty($subscriptions)) {
        echo "❌ User $userId has no push subscriptions.\n";
        echo "   User needs to subscribe via the dashboard first.\n";
        exit(1);
    }
    
    echo "✓ Found " . count($subscriptions) . " subscription(s) for user $userId\n";
    
    // Create notification service
    $notificationService = new NotificationService($db);
    
    // Send test notification
    echo "📤 Sending test notification...\n";
    
    $results = $notificationService->sendToUser(
        $userId,
        'Test Notification',
        'This is a test push notification from IrmaJosh!',
        [
            'url' => '/dashboard'
        ]
    );
    
    echo "✅ Sent: {$results['sent']}, Failed: {$results['failed']}\n";
    
    if ($results['sent'] > 0) {
        echo "✅ Test notification sent successfully!\n";
        echo "   Check your device/browser for the notification.\n";
    } else {
        echo "⚠️  No notifications were sent. Check logs for details.\n";
        echo "   Log file: storage/logs/app.log\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
