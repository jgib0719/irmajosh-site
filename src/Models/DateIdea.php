<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * DateIdea Model
 */
class DateIdea
{
    /**
     * Find idea by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM date_ideas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $idea = $stmt->fetch();
        
        return $idea ?: null;
    }

    /**
     * Get all ideas with latest completion status for a user (Unique ideas)
     */
    public static function getAll(int $userId, array $filters = []): array
    {
        $sql = '
            SELECT d.*, c.name as category_name, c.icon as category_icon, c.color as category_color,
                   MAX(cd.completed_at) as completed_at, MAX(cd.rating) as rating, MAX(cd.points_awarded) as points_awarded
            FROM date_ideas d
            LEFT JOIN date_categories c ON d.category_id = c.id
            LEFT JOIN completed_dates cd ON d.id = cd.date_idea_id AND cd.user_id = ?
            WHERE d.is_active = 1
        ';
        
        $params = [$userId];
        
        if (!empty($filters['category_id'])) {
            $sql .= ' AND d.category_id = ?';
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['cost_level'])) {
            $sql .= ' AND d.cost_level = ?';
            $params[] = $filters['cost_level'];
        }

        if (!empty($filters['season']) && $filters['season'] !== 'Any') {
            $sql .= ' AND (d.season = ? OR d.season = "Any")';
            $params[] = $filters['season'];
        }
        
        $sql .= ' GROUP BY d.id ORDER BY d.title ASC';
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Get completion history
     */
    public static function getHistory(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT cd.*, d.title, d.description, c.name as category_name, c.color as category_color
            FROM completed_dates cd
            JOIN date_ideas d ON cd.date_idea_id = d.id
            LEFT JOIN date_categories c ON d.category_id = c.id
            WHERE cd.user_id = ?
            ORDER BY cd.completed_at DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Mark a date as completed
     */
    public static function complete(int $userId, int $ideaId, array $data): bool
    {
        $idea = self::find($ideaId);
        if (!$idea) return false;

        // Check if already completed? Maybe allow multiple completions?
        // For now, let's allow multiple completions but maybe only award points once?
        // Or just simple insert.
        
        $points = $idea['points_value'] ?? 100;
        
        $stmt = db()->prepare('
            INSERT INTO completed_dates (user_id, date_idea_id, completed_at, rating, notes, photo_url, points_awarded)
            VALUES (?, ?, NOW(), ?, ?, ?, ?)
        ');
        
        return $stmt->execute([
            $userId,
            $ideaId,
            $data['rating'] ?? null,
            $data['notes'] ?? null,
            $data['photo_url'] ?? null,
            $points
        ]);
    }

    /**
     * Get total couples points
     * (Sum of all points from all users, or just the current user? 
     * "Couples Points" implies a shared score. 
     * If the app is single-user per login but represents a couple, then user's points are fine.
     * If multiple users share data, we might need to sum all users or link them.
     * Based on the app structure, it seems to be single-user auth but maybe shared tasks?
     * I'll just sum for the current user for now, or maybe all users if it's a shared instance.)
     * 
     * Let's assume it's per-user for now, or maybe I'll sum everything in the table if it's a shared instance.
     * Given "Couples Points", I'll sum ALL points in the completed_dates table regardless of user, 
     * assuming the instance is for the couple.
     */
    public static function getTotalCouplesPoints(): int
    {
        $stmt = db()->query('SELECT SUM(points_awarded) as total FROM completed_dates');
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Create a new date idea
     */
    public static function create(array $data): int
    {
        $db = db();
        $stmt = $db->prepare('
            INSERT INTO date_ideas (category_id, title, description, url, cost_level, season, points_value)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $data['category_id'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['url'] ?? null,
            $data['cost_level'] ?? 1,
            $data['season'] ?? 'Any',
            $data['points_value'] ?? 100
        ]);
        
        return (int)$db->lastInsertId();
    }
}
