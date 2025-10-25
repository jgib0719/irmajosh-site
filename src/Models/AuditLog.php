<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AuditLog Model
 * 
 * Stores security and operational audit logs
 * Automatically redacts PII on write
 */
class AuditLog
{
    /**
     * Create a new audit log entry
     */
    public static function create(array $data): array
    {
        // Redact PII from details before storing
        $details = $data['details'] ?? '';
        $redactedDetails = redactPII($details);
        
        $stmt = db()->prepare('
            INSERT INTO audit_logs (
                user_id,
                event_type,
                ip_address,
                user_agent,
                details,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['event_type'] ?? $data['action'] ?? 'unknown',
            $data['ip_address'] ?? getClientIp(),
            $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            $redactedDetails
        ]);
        
        $logId = db()->lastInsertId();
        
        return self::find((int)$logId);
    }
    
    /**
     * Log a user action
     */
    public static function log(string $action, string $message, ?int $userId = null): void
    {
        self::create([
            'user_id' => $userId,
            'event_type' => $action,
            'details' => $message
        ]);
    }
    
    /**
     * Log authentication event
     */
    public static function logAuth(string $action, string $message, ?int $userId = null): void
    {
        self::create([
            'user_id' => $userId,
            'event_type' => 'auth.' . $action,
            'details' => $message
        ]);
    }
    
    /**
     * Log security event
     */
    public static function logSecurity(string $action, string $message, ?int $userId = null): void
    {
        self::create([
            'user_id' => $userId,
            'event_type' => 'security.' . $action,
            'details' => $message
        ]);
    }
    
    /**
     * Find audit log by ID
     */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM audit_logs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $log = $stmt->fetch();
        
        return $log ?: null;
    }
    
    /**
     * Get audit logs for a user
     */
    public static function getByUser(int $userId, int $limit = 100): array
    {
        $stmt = db()->prepare('
            SELECT * FROM audit_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get audit logs by action
     */
    public static function getByAction(string $action, int $limit = 100): array
    {
        $stmt = db()->prepare('
            SELECT * FROM audit_logs 
            WHERE event_type = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ');
        $stmt->execute([$action, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get audit logs by action pattern (LIKE search)
     */
    public static function getByActionPattern(string $pattern, int $limit = 100): array
    {
        $stmt = db()->prepare('
            SELECT * FROM audit_logs 
            WHERE event_type LIKE ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ');
        $stmt->execute([$pattern, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get audit logs by IP address
     */
    public static function getByIp(string $ipAddress, int $limit = 100): array
    {
        $stmt = db()->prepare('
            SELECT * FROM audit_logs 
            WHERE ip_address = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ');
        $stmt->execute([$ipAddress, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent audit logs
     */
    public static function getRecent(int $limit = 100): array
    {
        $stmt = db()->prepare('
            SELECT * FROM audit_logs 
            ORDER BY created_at DESC 
            LIMIT ?
        ');
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get audit logs within date range
     */
    public static function getByDateRange(string $startDate, string $endDate, int $limit = 1000): array
    {
        $stmt = db()->prepare('
            SELECT * FROM audit_logs 
            WHERE created_at BETWEEN ? AND ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ');
        $stmt->execute([$startDate, $endDate, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Delete old audit logs (retention policy)
     */
    public static function deleteOlderThan(int $days): int
    {
        $stmt = db()->prepare('
            DELETE FROM audit_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        
        $deleted = $stmt->rowCount();
        
        logMessage("Deleted {$deleted} audit logs older than {$days} days", 'INFO');
        
        return $deleted;
    }
    
    /**
     * Get count of audit logs by action
     */
    public static function countByAction(string $action): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) as count FROM audit_logs WHERE event_type = ?');
        $stmt->execute([$action]);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
    
    /**
     * Get count of audit logs by user
     */
    public static function countByUser(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) as count FROM audit_logs WHERE user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
}
