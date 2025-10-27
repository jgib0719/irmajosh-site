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
    $subscriptions = $pushModel->getSubscriptionsByUser($userId);
    
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
    
    $success = $notificationService->sendToUser($userId, [
        'title' => 'Test Notification',
        'body' => 'This is a test push notification from IrmaJosh!',
        'icon' => '/assets/icons/icon-192x192.png',
        'url' => '/dashboard'
    ]);
    
    if ($success) {
        echo "✅ Test notification sent successfully!\n";
        echo "   Check your mobile device for the notification.\n";
    } else {
        echo "⚠️  Notification may have failed. Check logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
