<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * ScheduleRequest Model
 * 
 * Represents scheduling requests sent to external parties
 */
class ScheduleRequest
{
    /**
     * Create a new schedule request
     */
    public static function create(array $data): array
    {
        $stmt = db()->prepare('
            INSERT INTO schedule_requests (
                user_id,
                recipient_email,
                subject,
                message,
                status,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ');
        
        $stmt->execute([
            $data['user_id'],
            $data['recipient_email'],
            $data['subject'],
            $data['message'] ?? null,
            $data['status'] ?? 'pending'
        ]);
        
        $requestId = db()->lastInsertId();
        
        logMessage("Schedule request created: ID {$requestId} to " . redactPII($data['recipient_email']), 'INFO');
        
        return self::find((int)$requestId);
    }
    
    /**
     * Find schedule request by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM schedule_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        return $request ?: null;
    }
    
    /**
     * Get all schedule requests for a user (sent or received)
     */
    public static function getByUser(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT * FROM schedule_requests 
            WHERE sender_id = ? OR recipient_id = ?
            ORDER BY created_at DESC
        ');
        $stmt->execute([$userId, $userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get schedule requests by status
     */
    public static function getByStatus(int $userId, string $status): array
    {
        $stmt = db()->prepare('
            SELECT * FROM schedule_requests 
            WHERE user_id = ? AND status = ? 
            ORDER BY created_at DESC
        ');
        $stmt->execute([$userId, $status]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update schedule request status
     */
    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = db()->prepare('
            UPDATE schedule_requests 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ');
        
        $result = $stmt->execute([$status, $id]);
        
        logMessage("Schedule request {$id} status updated to {$status}", 'INFO');
        
        return $result;
    }
    
    /**
     * Update schedule request
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        if (isset($data['recipient_email'])) {
            $fields[] = 'recipient_email = ?';
            $values[] = $data['recipient_email'];
        }
        
        if (isset($data['subject'])) {
            $fields[] = 'subject = ?';
            $values[] = $data['subject'];
        }
        
        if (isset($data['message'])) {
            $fields[] = 'message = ?';
            $values[] = $data['message'];
        }
        
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $values[] = $data['status'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        
        $sql = 'UPDATE schedule_requests SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = db()->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    /**
     * Delete schedule request and its slots
     */
    public static function delete(int $id): bool
    {
        try {
            db()->beginTransaction();
            
            // Delete slots first (FK constraint)
            ScheduleRequestSlot::deleteByRequest($id);
            
            // Delete request
            $stmt = db()->prepare('DELETE FROM schedule_requests WHERE id = ?');
            $stmt->execute([$id]);
            
            db()->commit();
            
            logMessage("Schedule request deleted: ID {$id}", 'INFO');
            
            return true;
        } catch (\Exception $e) {
            db()->rollBack();
            logMessage("Failed to delete schedule request {$id}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Check if user owns the schedule request
     */
    public static function userOwns(int $requestId, int $userId): bool
    {
        $stmt = db()->prepare('
            SELECT COUNT(*) as count 
            FROM schedule_requests 
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$requestId, $userId]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
}
