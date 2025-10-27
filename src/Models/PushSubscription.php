<?php

namespace App\Models;

use PDO;

class PushSubscription
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Save a push subscription for a user
     */
    public function subscribe(int $userId, array $subscription, ?string $userAgent = null): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
            VALUES (:user_id, :endpoint, :p256dh, :auth, :user_agent)
            ON DUPLICATE KEY UPDATE
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                user_agent = VALUES(user_agent),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            'user_id' => $userId,
            'endpoint' => $subscription['endpoint'],
            'p256dh' => $subscription['keys']['p256dh'],
            'auth' => $subscription['keys']['auth'],
            'user_agent' => $userAgent
        ]);
    }
    
    /**
     * Remove a push subscription
     */
    public function unsubscribe(int $userId, string $endpoint): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM push_subscriptions
            WHERE user_id = :user_id AND endpoint = :endpoint
        ");
        
        return $stmt->execute([
            'user_id' => $userId,
            'endpoint' => $endpoint
        ]);
    }
    
    /**
     * Get all subscriptions for a user
     */
    public function getUserSubscriptions(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM push_subscriptions
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");
        
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get subscription count for a user
     */
    public function getSubscriptionCount(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM push_subscriptions
            WHERE user_id = :user_id
        ");
        
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Clean up old/invalid subscriptions
     */
    public function cleanup(int $daysOld = 90): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM push_subscriptions
            WHERE updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }
}
