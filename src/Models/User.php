<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * User Model
 * 
 * Represents users authenticated via Google OAuth
 * Primary identifier is `sub` claim, NOT email (emails can change)
 */
class User
{
    /**
     * Find user by Google sub claim
     */
    public static function findBySub(string $sub): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE google_user_id = ? LIMIT 1');
        $stmt->execute([$sub]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }
    
    /**
     * Find user by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    /**
     * Find user by iCal token
     */
    public static function findByIcalToken(string $token): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE ical_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    /**
     * Refresh iCal token for a user
     */
    public static function refreshIcalToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        
        $stmt = db()->prepare('UPDATE users SET ical_token = ? WHERE id = ?');
        $stmt->execute([$token, $userId]);
        
        return $token;
    }
    
    /**
     * Create or update user (upsert)
     * Uses google_user_id as unique identifier
     */
    public static function upsert(array $userInfo): array
    {
        $existing = self::findBySub($userInfo['sub']);
        
        $name = $userInfo['name'] ?? '';
        $picture = $userInfo['picture'] ?? null;

        if ($existing) {
            // Update existing user
            $stmt = db()->prepare('
                UPDATE users 
                SET email = ?, 
                    name = ?, 
                    picture = ?,
                    updated_at = NOW()
                WHERE google_user_id = ?
            ');
            
            $stmt->execute([
                $userInfo['email'],
                $name,
                $picture,
                $userInfo['sub']
            ]);
            
            return self::findBySub($userInfo['sub']);
        } else {
            // Create new user
            $db = db();
            $stmt = $db->prepare('
                INSERT INTO users (google_user_id, email, name, picture, ical_token, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ');
            
            $stmt->execute([
                $userInfo['sub'],
                $userInfo['email'],
                $name,
                $picture,
                bin2hex(random_bytes(32))
            ]);
            
            $userId = $db->lastInsertId();
            
            // Log user creation
            logMessage("New user created: " . redactPII($userInfo['email']) . " (sub: {$userInfo['sub']})", 'INFO');
            
            return self::find((int)$userId);
        }
    }
    
    /**
     * Update user's last login timestamp
     * Note: Column removed from schema - method kept for backward compatibility
     */
    public static function updateLastLogin(int $userId): void
    {
        // last_login_at column not in current schema
        // Could be tracked via audit_logs if needed
        return;
    }
    
    /**
     * Get all users (admin function)
     */
    public static function all(): array
    {
        $stmt = db()->query('SELECT * FROM users ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }
    
    /**
     * Delete user and all associated data
     */
    public static function delete(int $userId): bool
    {
        try {
            db()->beginTransaction();
            
            // Delete user tokens
            $stmt = db()->prepare('DELETE FROM user_tokens WHERE user_id = ?');
            $stmt->execute([$userId]);
            
            // Delete tasks
            $stmt = db()->prepare('DELETE FROM tasks WHERE user_id = ?');
            $stmt->execute([$userId]);
            

            // Delete user
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            
            db()->commit();
            
            logMessage("User deleted: ID {$userId}", 'INFO');
            
            return true;
        } catch (\Exception $e) {
            db()->rollBack();
            logMessage("Failed to delete user {$userId}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
