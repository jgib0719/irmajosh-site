<?php
declare(strict_types=1);

namespace App\Models;

class ShoppingItem
{
    /**
     * Create a new shopping item
     */
    public static function create(int $userId, string $name): int
    {
        $db = db();
        $stmt = $db->prepare('INSERT INTO shopping_items (user_id, item_name) VALUES (?, ?)');
        $stmt->execute([$userId, $name]);
        
        $id = (int)$db->lastInsertId();
        
        if ($id === 0) {
            // Fallback: try to get the ID from the database directly
            $fallbackStmt = $db->query('SELECT LAST_INSERT_ID() as id');
            $fallbackResult = $fallbackStmt->fetch();
            $id = (int)($fallbackResult['id'] ?? 0);
            
            if ($id > 0) {
                \logMessage("Retrieved shopping item ID via LAST_INSERT_ID() query: {$id}", 'WARNING');
            }
        }
        
        return $id;
    }

    /**
     * Get all active (uncompleted) items
     */
    public static function getAllActive(): array
    {
        $stmt = db()->query('SELECT * FROM shopping_items WHERE is_completed = 0 ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    /**
     * Get recently completed items
     */
    public static function getRecentCompleted(int $limit = 20): array
    {
        $stmt = db()->prepare('SELECT * FROM shopping_items WHERE is_completed = 1 ORDER BY completed_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Toggle completion status
     */
    public static function toggle(int $id, bool $completed): bool
    {
        $completedAt = $completed ? date('Y-m-d H:i:s') : null;
        $stmt = db()->prepare('UPDATE shopping_items SET is_completed = ?, completed_at = ? WHERE id = ?');
        return $stmt->execute([$completed ? 1 : 0, $completedAt, $id]);
    }
    
    /**
     * Delete an item
     */
    public static function delete(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM shopping_items WHERE id = ?');
        return $stmt->execute([$id]);
    }
    
    /**
     * Find an item by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM shopping_items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }
}
