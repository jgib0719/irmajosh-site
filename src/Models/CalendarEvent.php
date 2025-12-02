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
        
        $description = $data['description'] ?? null;
        $color = $data['color'] ?? '#3b82f6';
        $recurrenceType = $data['recurrence_type'] ?? null;
        $recurrenceInterval = $data['recurrence_interval'] ?? null;
        $recurrenceEnd = $data['recurrence_end'] ?? null;
        $isAllDay = isset($data['is_all_day']) ? (int)$data['is_all_day'] : 0;

        $stmt = $db->prepare("
            INSERT INTO calendar_events (
                user_id, title, description, color, start_at, end_at, is_all_day,
                recurrence_type, recurrence_interval, recurrence_end
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $description,
            $color,
            $data['start_at'],
            $data['end_at'],
            $isAllDay,
            $recurrenceType,
            $recurrenceInterval,
            $recurrenceEnd
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
                OR (recurrence_type IS NOT NULL AND start_at <= ?)
            )
            ORDER BY start_at ASC
        ");
        
        $stmt->execute([$userId, $start, $end, $start, $end, $start, $end, $end]);
        
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Expand recurring events
        return self::expandRecurringEvents($events, $start, $end);
    }
    
    /**
     * Expand recurring events into individual occurrences
     */
    private static function expandRecurringEvents(array $events, string $rangeStart, string $rangeEnd): array
    {
        $expandedEvents = [];
        
        foreach ($events as $event) {
            // Non-recurring events are added as-is
            if (empty($event['recurrence_type'])) {
                $expandedEvents[] = $event;
                continue;
            }
            
            // Expand recurring event
            $interval = $event['recurrence_interval'] ?? 1;
            $recurrenceEnd = $event['recurrence_end'] ? new \DateTime($event['recurrence_end']) : new \DateTime($rangeEnd);
            $rangeEndDate = new \DateTime($rangeEnd);
            
            // Use the earlier of recurrence_end or range end
            $effectiveEnd = $recurrenceEnd < $rangeEndDate ? $recurrenceEnd : $rangeEndDate;
            
            $eventStart = new \DateTime($event['start_at']);
            $eventEnd = new \DateTime($event['end_at']);
            $duration = $eventStart->diff($eventEnd);
            
            $currentStart = clone $eventStart;
            $occurrenceCount = 0;
            $maxOccurrences = 730; // Safety limit: 2 years of daily events
            
            while ($currentStart <= $effectiveEnd && $occurrenceCount < $maxOccurrences) {
                // Check if this occurrence falls within the requested range
                if ($currentStart >= new \DateTime($rangeStart)) {
                    $currentEnd = clone $currentStart;
                    $currentEnd->add($duration);
                    
                    // Create occurrence
                    $occurrence = $event;
                    $occurrence['id'] = $event['id'] . '_' . $currentStart->format('Ymd');
                    $occurrence['start_at'] = $currentStart->format('Y-m-d H:i:s');
                    $occurrence['end_at'] = $currentEnd->format('Y-m-d H:i:s');
                    $occurrence['is_recurring_instance'] = true;
                    $occurrence['parent_event_id'] = $event['id'];
                    
                    $expandedEvents[] = $occurrence;
                }
                
                // Move to next occurrence
                switch ($event['recurrence_type']) {
                    case 'daily':
                        $currentStart->modify("+{$interval} day");
                        break;
                    case 'weekly':
                        $currentStart->modify("+{$interval} week");
                        break;
                    case 'monthly':
                        $currentStart->modify("+{$interval} month");
                        break;
                    case 'yearly':
                        $currentStart->modify("+{$interval} year");
                        break;
                }
                
                $occurrenceCount++;
            }
        }
        
        // Sort by start time
        usort($expandedEvents, function($a, $b) {
            return strcmp($a['start_at'], $b['start_at']);
        });
        
        return $expandedEvents;
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
     * Get events for all users within a date range
     */
    public static function getAllByDateRange(string $start, string $end): array
    {
        $db = db();
        
        $stmt = $db->prepare("
            SELECT * FROM calendar_events
            WHERE (
                (start_at BETWEEN ? AND ?)
                OR (end_at BETWEEN ? AND ?)
                OR (start_at <= ? AND end_at >= ?)
                OR (recurrence_type IS NOT NULL AND start_at <= ?)
            )
            ORDER BY start_at ASC
        ");
        
        $stmt->execute([$start, $end, $start, $end, $start, $end, $end]);
        
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Expand recurring events
        return self::expandRecurringEvents($events, $start, $end);
    }

    /**
     * Get all events for all users
     */
    public static function getAll(): array
    {
        $db = db();
        
        $stmt = $db->query("
            SELECT * FROM calendar_events
            ORDER BY start_at ASC
        ");
        
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
     * Search events (all users)
     */
    public static function search(string $query, int $userId = null): array
    {
        $db = db();
        $searchTerm = '%' . $query . '%';
        
        $stmt = $db->prepare("
            SELECT * FROM calendar_events 
            WHERE (title LIKE ? OR description LIKE ?)
            ORDER BY start_at DESC
            LIMIT 20
        ");
        
        $stmt->execute([$searchTerm, $searchTerm]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
