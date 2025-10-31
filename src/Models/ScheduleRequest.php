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
                sender_id,
                recipient_id,
                title,
                description,
                status,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ');
        
        $stmt->execute([
            $data['sender_id'],
            $data['recipient_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['status'] ?? 'pending'
        ]);
        
        $requestId = db()->lastInsertId();
        
        logMessage("Schedule request created: ID {$requestId}", 'INFO');
        
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
     * Get actionable schedule requests (not yet scheduled to calendar)
     * Excludes:
     * - Declined requests
     * - Requests with accepted_slot_id set (scheduled via slot workflow)
     * - Requests linked to calendar_events (scheduled via direct calendar workflow)
     */
    public static function getActionable(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT sr.* FROM schedule_requests sr
            LEFT JOIN calendar_events ce ON sr.id = ce.schedule_request_id
            WHERE (sr.sender_id = ? OR sr.recipient_id = ?)
            AND sr.status != ?
            AND sr.accepted_slot_id IS NULL
            AND ce.id IS NULL
            ORDER BY sr.created_at DESC
        ');
        $stmt->execute([$userId, $userId, 'declined']);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get schedule requests by status
     */
    public static function getByStatus(int $userId, string $status): array
    {
        $stmt = db()->prepare('
            SELECT * FROM schedule_requests 
            WHERE (sender_id = ? OR recipient_id = ?) AND status = ? 
            ORDER BY created_at DESC
        ');
        $stmt->execute([$userId, $userId, $status]);
        
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
        
        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $values[] = $data['title'];
        }
        
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $values[] = $data['description'];
        }
        
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $values[] = $data['status'];
        }
        
        if (isset($data['accepted_slot_id'])) {
            $fields[] = 'accepted_slot_id = ?';
            $values[] = $data['accepted_slot_id'];
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
     * Check if user owns the schedule request (sender or recipient)
     */
    public static function userOwns(int $requestId, int $userId): bool
    {
        $stmt = db()->prepare('
            SELECT COUNT(*) as count 
            FROM schedule_requests 
            WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
        ');
        $stmt->execute([$requestId, $userId, $userId]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
}
