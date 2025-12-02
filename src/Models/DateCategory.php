<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * DateCategory Model
 */
class DateCategory
{
    /**
     * Get all categories
     */
    public static function all(): array
    {
        $stmt = db()->query('SELECT * FROM date_categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Find category by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM date_categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        return $category ?: null;
    }
}
