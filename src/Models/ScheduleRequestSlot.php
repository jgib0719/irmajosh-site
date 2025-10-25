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
                schedule_request_id,
                start_time,
                end_time,
                is_selected,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ');
        
        $stmt->execute([
            $data['schedule_request_id'],
            $data['start_time'],
            $data['end_time'],
            $data['is_selected'] ?? 0
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
            WHERE schedule_request_id = ? 
            ORDER BY start_time ASC
        ');
        $stmt->execute([$requestId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get selected slot for a schedule request
     */
    public static function getSelectedSlot(int $requestId): ?array
    {
        $stmt = db()->prepare('
            SELECT * FROM schedule_request_slots 
            WHERE schedule_request_id = ? AND is_selected = 1 
            LIMIT 1
        ');
        $stmt->execute([$requestId]);
        $slot = $stmt->fetch();
        
        return $slot ?: null;
    }
    
    /**
     * Mark a slot as selected (and unselect others)
     */
    public static function selectSlot(int $slotId, int $requestId): bool
    {
        try {
            db()->beginTransaction();
            
            // Unselect all slots for this request
            $stmt = db()->prepare('
                UPDATE schedule_request_slots 
                SET is_selected = 0, updated_at = NOW() 
                WHERE schedule_request_id = ?
            ');
            $stmt->execute([$requestId]);
            
            // Select the specified slot
            $stmt = db()->prepare('
                UPDATE schedule_request_slots 
                SET is_selected = 1, updated_at = NOW() 
                WHERE id = ? AND schedule_request_id = ?
            ');
            $stmt->execute([$slotId, $requestId]);
            
            db()->commit();
            
            logMessage("Slot {$slotId} selected for request {$requestId}", 'INFO');
            
            return true;
        } catch (\Exception $e) {
            db()->rollBack();
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
        
        if (isset($data['start_time'])) {
            $fields[] = 'start_time = ?';
            $values[] = $data['start_time'];
        }
        
        if (isset($data['end_time'])) {
            $fields[] = 'end_time = ?';
            $values[] = $data['end_time'];
        }
        
        if (isset($data['is_selected'])) {
            $fields[] = 'is_selected = ?';
            $values[] = $data['is_selected'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'updated_at = NOW()';
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
        $stmt = db()->prepare('DELETE FROM schedule_request_slots WHERE schedule_request_id = ?');
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
                    'schedule_request_id' => $requestId,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'is_selected' => $slot['is_selected'] ?? 0
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
