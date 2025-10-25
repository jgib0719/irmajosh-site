<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * UserToken Model
 * 
 * Stores encrypted OAuth tokens for Google API access
 * Uses libsodium for encryption with APP_SECRET as key
 * Supports key rotation with APP_SECRET_PREV
 */
class UserToken
{
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24 bytes
    
    /**
     * Encrypt plaintext using libsodium
     */
    private static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        
        // Return base64-encoded nonce + ciphertext
        return base64_encode($nonce . $ciphertext);
    }
    
    /**
     * Decrypt ciphertext using libsodium
     * Supports APP_SECRET_PREV for key rotation
     */
    private static function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted);
        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $ciphertext = substr($decoded, self::NONCE_BYTES);
        
        // Try current key first
        $key = self::getEncryptionKey();
        $plaintext = @sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        
        // If decryption fails, try previous key (for rotation window)
        if ($plaintext === false && env('APP_SECRET_PREV')) {
            $prevKey = self::getEncryptionKey(true);
            $plaintext = @sodium_crypto_secretbox_open($ciphertext, $nonce, $prevKey);
            
            if ($plaintext !== false) {
                logMessage('Token decrypted with APP_SECRET_PREV - consider re-encrypting', 'WARNING');
            }
        }
        
        if ($plaintext === false) {
            throw new \Exception('Failed to decrypt token');
        }
        
        return $plaintext;
    }
    
    /**
     * Get encryption key from APP_SECRET
     */
    private static function getEncryptionKey(bool $usePrevious = false): string
    {
        $appSecret = $usePrevious ? env('APP_SECRET_PREV') : env('APP_SECRET_CURR');
        
        if (empty($appSecret)) {
            throw new \Exception('APP_SECRET not configured');
        }
        
        // Use first 32 bytes of hex-decoded APP_SECRET
        $key = hex2bin($appSecret);
        
        if (strlen($key) < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \Exception('APP_SECRET too short - must be at least 64 hex characters');
        }
        
        return substr($key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
    
    /**
     * Save encrypted tokens for a user
     */
    public static function saveTokens(int $userId, array $tokens): void
    {
        $accessToken = $tokens['access_token'] ?? null;
        $refreshToken = $tokens['refresh_token'] ?? null;
        
        if (!$accessToken) {
            throw new \Exception('Access token is required');
        }
        
        // Store tokens as JSON in encrypted_tokens column
        $tokenData = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => isset($tokens['expires_in']) 
                ? date('Y-m-d H:i:s', time() + $tokens['expires_in'])
                : null,
        ];
        
        // Encrypt the entire JSON blob
        $encryptedTokens = self::encrypt(json_encode($tokenData));
        
        // Check if tokens already exist for this user
        $existing = self::getTokenRow($userId);
        
        if ($existing) {
            // Update existing tokens
            $stmt = db()->prepare('
                UPDATE user_tokens
                SET encrypted_tokens = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ');
            
            $stmt->execute([
                $encryptedTokens,
                $userId
            ]);
        } else {
            // Insert new tokens
            $stmt = db()->prepare('
                INSERT INTO user_tokens (user_id, encrypted_tokens, key_version, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW())
            ');
            
            $stmt->execute([
                $userId,
                $encryptedTokens
            ]);
        }
        
        logMessage("Tokens saved for user {$userId}", 'INFO');
    }
    
    /**
     * Get decrypted tokens for a user
     */
    public static function getTokens(int $userId): ?array
    {
        $row = self::getTokenRow($userId);
        
        if (!$row) {
            return null;
        }
        
        try {
            // Decrypt the JSON blob
            $decrypted = self::decrypt($row['encrypted_tokens']);
            $tokenData = json_decode($decrypted, true);
            
            if (!$tokenData) {
                throw new \Exception('Invalid token data');
            }
            
            return [
                'access_token' => $tokenData['access_token'] ?? null,
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => $tokenData['expires_at'] ?? null,
                'is_expired' => isset($tokenData['expires_at']) && strtotime($tokenData['expires_at']) < time()
            ];
        } catch (\Exception $e) {
            logMessage("Failed to decrypt tokens for user {$userId}: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Get encrypted token row from database
     */
    private static function getTokenRow(int $userId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM user_tokens WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        
        return $row ?: null;
    }
    
    /**
     * Check if user has valid tokens
     */
    public static function hasValidTokens(int $userId): bool
    {
        $tokens = self::getTokens($userId);
        
        return $tokens && !$tokens['is_expired'];
    }
    
    /**
     * Delete tokens for a user
     */
    public static function deleteTokens(int $userId): void
    {
        $stmt = db()->prepare('DELETE FROM user_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        logMessage("Tokens deleted for user {$userId}", 'INFO');
    }
    
    /**
     * Re-encrypt tokens with new key (for key rotation)
     */
    public static function reencryptTokens(int $userId): bool
    {
        try {
            // Get tokens (will decrypt with current or previous key)
            $tokens = self::getTokens($userId);
            
            if (!$tokens) {
                return false;
            }
            
            // Re-encrypt with current key
            $stmt = db()->prepare('
                UPDATE user_tokens
                SET access_token = ?,
                    refresh_token = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ');
            
            $stmt->execute([
                self::encrypt($tokens['access_token']),
                $tokens['refresh_token'] ? self::encrypt($tokens['refresh_token']) : null,
                $userId
            ]);
            
            logMessage("Tokens re-encrypted for user {$userId}", 'INFO');
            
            return true;
        } catch (\Exception $e) {
            logMessage("Failed to re-encrypt tokens for user {$userId}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
