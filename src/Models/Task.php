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
        $isShared = isset($data['is_shared']) ? (int)$data['is_shared'] : 0;
        $status = $data['status'] ?? 'pending';
        $dueDate = $data['due_date'] ?? null;
        $googleEventId = $data['google_event_id'] ?? null;

        try {
            $pdo = db();
            $stmt = $pdo->prepare('
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
            
            $result = $stmt->execute([
                $data['user_id'],
                $data['title'],
                $data['description'] ?? null,
                $isShared,
                $status,
                $dueDate,
                $googleEventId
            ]);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                logMessage("Task insert failed: " . json_encode($errorInfo), 'ERROR');
                throw new \Exception("Failed to insert task");
            }
            
            // Get the last insert ID immediately after execute on the same connection
            $taskId = (int)$pdo->lastInsertId();
            
            if ($taskId === 0) {
                // Fallback: try to get the ID from the database
                $fallbackStmt = $pdo->query('SELECT LAST_INSERT_ID() as id');
                $fallbackResult = $fallbackStmt->fetch();
                $taskId = (int)($fallbackResult['id'] ?? 0);
                
                if ($taskId === 0) {
                    logMessage("Task insert succeeded but could not retrieve ID. Data: " . json_encode($data), 'ERROR');
                    throw new \Exception("Failed to get task ID after insert");
                }
                
                logMessage("Retrieved task ID via LAST_INSERT_ID() query: {$taskId}", 'WARNING');
            }
            
            logMessage("Task created: ID {$taskId} by user {$data['user_id']}", 'INFO');
            
            return self::find($taskId);
        } catch (\PDOException $e) {
            logMessage("PDO Exception creating task: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
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
     * Get tasks for a user within a date range
     */
    public static function getByUserAndDateRange(int $userId, string $start, string $end): array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE user_id = ? 
            AND due_date BETWEEN ? AND ?
            ORDER BY due_date ASC
        ');
        $stmt->execute([$userId, $start, $end]);
        
        return $stmt->fetchAll();
    }

    /**
     * Get tasks for a user OR shared tasks within a date range
     */
    public static function getSharedAndUserByDateRange(int $userId, string $start, string $end): array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE (user_id = ? OR is_shared = 1)
            AND due_date BETWEEN ? AND ?
            ORDER BY due_date ASC
        ');
        $stmt->execute([$userId, $start, $end]);
        
        return $stmt->fetchAll();
    }

    /**
     * Get tasks for a user OR shared tasks
     */
    public static function getSharedAndUser(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE (user_id = ? OR is_shared = 1)
            ORDER BY due_date ASC, created_at DESC
        ');
        $stmt->execute([$userId]);
        
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
        $values[] = $task['user_id'];
        
        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?';
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
        
        $stmt = db()->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $result = $stmt->execute([$id, $task['user_id']]);
        
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
    
    /**
     * Search tasks
     */
    public static function search(string $query, int $userId): array
    {
        $searchTerm = '%' . $query . '%';
        
        $stmt = db()->prepare('
            SELECT * FROM tasks 
            WHERE user_id = ? 
            AND (title LIKE ? OR description LIKE ?)
            ORDER BY due_date DESC
            LIMIT 20
        ');
        
        $stmt->execute([$userId, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll();
    }
}
