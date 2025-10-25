<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Task Model
 * 
 * Represents tasks with Google Calendar integration
 */
class Task
{
    /**
     * Create a new task
     */
    public static function create(array $data): array
    {
        $stmt = db()->prepare('
            INSERT INTO tasks (
                user_id, 
                title, 
                description, 
                is_shared, 
                status, 
                due_date, 
                google_event_id,
                created_at, 
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['is_shared'] ?? false,
            $data['status'] ?? 'pending',
            $data['due_date'] ?? null,
            $data['google_event_id'] ?? null
        ]);
        
        $taskId = db()->lastInsertId();
        
        logMessage("Task created: ID {$taskId} by user {$data['user_id']}", 'INFO');
        
        return self::find((int)$taskId);
    }
    
    /**
     * Find task by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        return $task ?: null;
    }
    
    /**
     * Find task by Google Event ID
     */
    public static function findByEventId(string $eventId, int $userId): ?array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE google_event_id = ? AND user_id = ? 
            LIMIT 1
        ');
        $stmt->execute([$eventId, $userId]);
        $task = $stmt->fetch();
        
        return $task ?: null;
    }
    
    /**
     * Get all tasks for a user
     */
    public static function getByUser(int $userId, ?string $type = null): array
    {
        if ($type) {
            $isShared = ($type === 'shared') ? 1 : 0;
            $stmt = db()->prepare('
                SELECT * FROM tasks 
                WHERE user_id = ? AND is_shared = ? 
                ORDER BY due_date ASC, created_at DESC
            ');
            $stmt->execute([$userId, $isShared]);
        } else {
            $stmt = db()->prepare('
                SELECT * FROM tasks 
                WHERE user_id = ? 
                ORDER BY due_date ASC, created_at DESC
            ');
            $stmt->execute([$userId]);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get tasks by status
     */
    public static function getByStatus(int $userId, string $status): array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE user_id = ? AND status = ? 
            ORDER BY due_date ASC, created_at DESC
        ');
        $stmt->execute([$userId, $status]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get upcoming tasks (due within next 7 days)
     */
    public static function getUpcoming(int $userId, int $days = 7): array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE user_id = ? 
            AND status != ? 
            AND due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
            ORDER BY due_date ASC
        ');
        $stmt->execute([$userId, 'completed', $days]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get overdue tasks
     */
    public static function getOverdue(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE user_id = ? 
            AND status != ? 
            AND due_date < NOW()
            ORDER BY due_date DESC
        ');
        $stmt->execute([$userId, 'completed']);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update a task
     */
    public static function update(int $id, array $data): bool
    {
        $task = self::find($id);
        if (!$task) {
            return false;
        }
        
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
        
        if (isset($data['is_shared'])) {
            $fields[] = 'is_shared = ?';
            $values[] = $data['is_shared'];
        }
        
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $values[] = $data['status'];
        }
        
        if (isset($data['due_date'])) {
            $fields[] = 'due_date = ?';
            $values[] = $data['due_date'];
        }
        
        if (isset($data['google_event_id'])) {
            $fields[] = 'google_event_id = ?';
            $values[] = $data['google_event_id'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        
        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = db()->prepare($sql);
        
        $result = $stmt->execute($values);
        
        logMessage("Task updated: ID {$id}", 'INFO');
        
        return $result;
    }
    
    /**
     * Delete a task
     */
    public static function delete(int $id): bool
    {
        $task = self::find($id);
        if (!$task) {
            return false;
        }
        
        $stmt = db()->prepare('DELETE FROM tasks WHERE id = ?');
        $result = $stmt->execute([$id]);
        
        logMessage("Task deleted: ID {$id} by user {$task['user_id']}", 'INFO');
        
        return $result;
    }
    
    /**
     * Check if user owns the task
     */
    public static function userOwns(int $taskId, int $userId): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) as count FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
}
