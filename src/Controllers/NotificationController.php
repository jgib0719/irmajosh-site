<?php

namespace App\Controllers;

use App\Models\PushSubscription;

class NotificationController extends BaseController
{
    private PushSubscription $pushSubscription;
    
    public function __construct()
    {
        $this->pushSubscription = new PushSubscription(db());
    }
    
    /**
     * Subscribe to push notifications
     * POST /notifications/subscribe
     */
    public function subscribe(): void
    {
        if (!isAuthenticated()) {
            json(['error' => 'Unauthorized'], 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['subscription'])) {
            json(['error' => 'Missing subscription data'], 400);
        }
        
        $subscription = $data['subscription'];
        
        if (!isset($subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'])) {
            json(['error' => 'Invalid subscription format'], 400);
        }
        
        $userId = currentUser()['id'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        try {
            $this->pushSubscription->subscribe($userId, $subscription, $userAgent);
            
            logInfo("User $userId subscribed to push notifications");
            
            json([
                'success' => true,
                'message' => 'Successfully subscribed to notifications'
            ]);
        } catch (\Exception $e) {
            logError("Failed to save push subscription: " . $e->getMessage());
            json(['error' => 'Failed to save subscription'], 500);
        }
    }
    
    /**
     * Unsubscribe from push notifications
     * DELETE /notifications/unsubscribe
     */
    public function unsubscribe(): void
    {
        if (!isAuthenticated()) {
            json(['error' => 'Unauthorized'], 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['endpoint'])) {
            json(['error' => 'Missing endpoint'], 400);
        }
        
        $userId = currentUser()['id'];
        
        try {
            $this->pushSubscription->unsubscribe($userId, $data['endpoint']);
            
            logInfo("User $userId unsubscribed from push notifications");
            
            json([
                'success' => true,
                'message' => 'Successfully unsubscribed'
            ]);
        } catch (\Exception $e) {
            logError("Failed to remove push subscription: " . $e->getMessage());
            json(['error' => 'Failed to unsubscribe'], 500);
        }
    }
    
    /**
     * Get subscription status
     * GET /notifications/status
     */
    public function status(): void
    {
        if (!isAuthenticated()) {
            json(['error' => 'Unauthorized'], 401);
        }
        
        $userId = currentUser()['id'];
        $count = $this->pushSubscription->getSubscriptionCount($userId);
        
        json([
            'subscribed' => $count > 0,
            'subscription_count' => $count
        ]);
    }
}
