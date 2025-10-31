<?php
declare(strict_types=1);

namespace App\Models;

/**
 * CalendarEvent Model
 * 
 * Manages calendar events stored locally
 */
class CalendarEvent
{
    /**
     * Create a new calendar event
     */
    public static function create(array $data): array
    {
        $db = db();
        
        $stmt = $db->prepare("
            INSERT INTO calendar_events (
                user_id, title, description, start_at, end_at,
                recurrence_type, recurrence_interval, recurrence_end,
                schedule_request_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['start_at'],
            $data['end_at'],
            $data['recurrence_type'] ?? null,
            $data['recurrence_interval'] ?? null,
            $data['recurrence_end'] ?? null,
            $data['schedule_request_id'] ?? null
        ]);
        
        $id = (int)$db->lastInsertId();
        
        return self::find($id);
    }
    
    /**
     * Find event by ID
     */
    public static function find(int $id): ?array
    {
        $db = db();
        
        $stmt = $db->prepare("
            SELECT * FROM calendar_events WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $event ?: null;
    }
    
    /**
     * Get events for a user within a date range
     */
    public static function getByUserAndDateRange(int $userId, string $start, string $end): array
    {
        $db = db();
        
        $stmt = $db->prepare("
            SELECT * FROM calendar_events
            WHERE user_id = ?
            AND (
                (start_at BETWEEN ? AND ?)
                OR (end_at BETWEEN ? AND ?)
                OR (start_at <= ? AND end_at >= ?)
            )
            ORDER BY start_at ASC
        ");
        
        $stmt->execute([$userId, $start, $end, $start, $end, $start, $end]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all events for a user
     */
    public static function getByUser(int $userId): array
    {
        $db = db();
        
        $stmt = $db->prepare("
            SELECT * FROM calendar_events
            WHERE user_id = ?
            ORDER BY start_at ASC
        ");
        
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Update an event
     */
    public static function update(int $id, array $data): bool
    {
        $db = db();
        
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $stmt = $db->prepare("
            UPDATE calendar_events SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($values);
    }
    
    /**
     * Delete an event
     */
    public static function delete(int $id): bool
    {
        $db = db();
        
        $stmt = $db->prepare("DELETE FROM calendar_events WHERE id = ?");
        
        return $stmt->execute([$id]);
    }
    
    /**
     * Check if user owns this event
     */
    public static function userOwns(int $eventId, int $userId): bool
    {
        $db = db();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM calendar_events WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$eventId, $userId]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get event by schedule request ID
     */
    public static function findByScheduleRequest(int $requestId): ?array
    {
        $db = db();
        
        $stmt = $db->prepare("
            SELECT * FROM calendar_events WHERE schedule_request_id = ?
        ");
        
        $stmt->execute([$requestId]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $event ?: null;
    }
}
