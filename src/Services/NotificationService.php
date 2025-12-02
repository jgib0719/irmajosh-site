<?php

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use App\Models\PushSubscription;
use PDO;

class NotificationService
{
    private PDO $db;
    private PushSubscription $pushSubscriptionModel;
    private WebPush $webPush;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->pushSubscriptionModel = new PushSubscription($db);
        
        // Initialize Web Push with VAPID keys
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => \env('VAPID_SUBJECT', 'mailto:admin@irmajosh.com'),
                'publicKey' => \env('VAPID_PUBLIC_KEY'),
                'privateKey' => \env('VAPID_PRIVATE_KEY')
            ]
        ]);
    }
    
    /**
     * Send notification to a specific user
     */
    public function sendToUser(int $userId, string $title, string $body, ?array $data = null): array
    {
        $subscriptions = $this->pushSubscriptionModel->getUserSubscriptions($userId);
        
        if (empty($subscriptions)) {
            return ['sent' => 0, 'failed' => 0];
        }
        
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/icons/icon-192x192.png',
            'badge' => '/assets/icons/icon-192x192.png',
            'data' => $data ?? []
        ]);
        
        $results = ['sent' => 0, 'failed' => 0];
        
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh'],
                        'auth' => $sub['auth']
                    ]
                ]);
                
                $this->webPush->queueNotification($subscription, $payload);
            } catch (\Exception $e) {
                $results['failed']++;
                \logError("Failed to queue notification: " . $e->getMessage());
            }
        }
        
        // Send all queued notifications
        foreach ($this->webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $results['sent']++;
            } else {
                $results['failed']++;
                // Remove invalid subscriptions
                if ($report->isSubscriptionExpired()) {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    $this->pushSubscriptionModel->unsubscribe($userId, $endpoint);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Send task notification
     */
    public function notifyTaskCreated(int $userId, array $task): array
    {
        return $this->sendToUser(
            $userId,
            'New Task',
            $task['title'],
            [
                'type' => 'task',
                'taskId' => $task['id'],
                'url' => '/tasks/' . ($task['is_shared'] ? 'shared' : 'private')
            ]
        );
    }
    
    /**
     * Send calendar event notification
     */
    public function notifyEventCreated(int $userId, array $event): array
    {
        return $this->sendToUser(
            $userId,
            'New Event',
            $event['title'],
            [
                'type' => 'event',
                'eventId' => $event['id'],
                'url' => '/calendar'
            ]
        );
    }
}
