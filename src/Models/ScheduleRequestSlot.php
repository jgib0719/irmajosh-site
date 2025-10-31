<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * ScheduleRequestSlot Model
 * 
 * Represents available time slots for schedule requests
 */
class ScheduleRequestSlot
{
    /**
     * Create a new slot
     */
    public static function create(array $data): array
    {
        $stmt = db()->prepare('
            INSERT INTO schedule_request_slots (
                request_id,
                start_at,
                end_at
            )
            VALUES (?, ?, ?)
        ');
        
        $stmt->execute([
            $data['request_id'],
            $data['start_at'] ?? $data['start_time'] ?? null,
            $data['end_at'] ?? $data['end_time'] ?? null
        ]);
        
        $slotId = db()->lastInsertId();
        
        return self::find((int)$slotId);
    }
    
    /**
     * Find slot by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM schedule_request_slots WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $slot = $stmt->fetch();
        
        return $slot ?: null;
    }
    
    /**
     * Get all slots for a schedule request
     */
    public static function getByRequest(int $requestId): array
    {
        $stmt = db()->prepare('
            SELECT * FROM schedule_request_slots 
            WHERE request_id = ? 
            ORDER BY start_at ASC
        ');
        $stmt->execute([$requestId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get first slot for a schedule request (since is_selected doesn't exist)
     */
    public static function getSelectedSlot(int $requestId): ?array
    {
        $stmt = db()->prepare('
            SELECT * FROM schedule_request_slots 
            WHERE request_id = ? 
            ORDER BY start_at ASC
            LIMIT 1
        ');
        $stmt->execute([$requestId]);
        $slot = $stmt->fetch();
        
        return $slot ?: null;
    }
    
    /**
     * Mark a slot as selected (store in schedule_requests.accepted_slot_id)
     */
    public static function selectSlot(int $slotId, int $requestId): bool
    {
        try {
            $stmt = db()->prepare('
                UPDATE schedule_requests 
                SET accepted_slot_id = ? 
                WHERE id = ?
            ');
            $stmt->execute([$slotId, $requestId]);
            
            logMessage("Slot {$slotId} selected for request {$requestId}", 'INFO');
            
            return true;
        } catch (\Exception $e) {
            logMessage("Failed to select slot {$slotId}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Update a slot
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        if (isset($data['start_at']) || isset($data['start_time'])) {
            $fields[] = 'start_at = ?';
            $values[] = $data['start_at'] ?? $data['start_time'];
        }
        
        if (isset($data['end_at']) || isset($data['end_time'])) {
            $fields[] = 'end_at = ?';
            $values[] = $data['end_at'] ?? $data['end_time'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        
        $sql = 'UPDATE schedule_request_slots SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = db()->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    /**
     * Delete a slot
     */
    public static function delete(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM schedule_request_slots WHERE id = ?');
        return $stmt->execute([$id]);
    }
    
    /**
     * Delete all slots for a schedule request
     */
    public static function deleteByRequest(int $requestId): bool
    {
        $stmt = db()->prepare('DELETE FROM schedule_request_slots WHERE request_id = ?');
        return $stmt->execute([$requestId]);
    }
    
    /**
     * Create multiple slots for a request
     */
    public static function createMultiple(int $requestId, array $slots): bool
    {
        try {
            db()->beginTransaction();
            
            foreach ($slots as $slot) {
                self::create([
                    'request_id' => $requestId,
                    'start_at' => $slot['start'] ?? $slot['start_time'] ?? $slot['start_at'] ?? null,
                    'end_at' => $slot['end'] ?? $slot['end_time'] ?? $slot['end_at'] ?? null
                ]);
            }
            
            db()->commit();
            
            logMessage("Created " . count($slots) . " slots for request {$requestId}", 'INFO');
            
            return true;
        } catch (\Exception $e) {
            db()->rollBack();
            logMessage("Failed to create slots for request {$requestId}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
