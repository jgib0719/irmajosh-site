<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserToken;
use App\Models\AuditLog;
use App\Services\GoogleClientFactory;
use Google_Client;

/**
 * AuthService
 * 
 * Handles OAuth authentication with PKCE flow and ID token verification
 * CRITICAL: Implements complete security requirements from Phase 3 spec
 */
class AuthService
{
    private GoogleClientFactory $clientFactory;
    
    public function __construct()
    {
        $this->clientFactory = new GoogleClientFactory();
    }
    
    /**
     * Initiate OAuth login with PKCE
     * Returns authorization URL
     */
    public function initiateLogin(): string
    {
        // Generate code verifier (43-128 chars, base64url)
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $_SESSION['oauth_code_verifier'] = $codeVerifier;
        $_SESSION['oauth_code_verifier_expiry'] = time() + 600; // 10 minutes
        
        // Generate code challenge (S256 method)
        $codeChallenge = rtrim(strtr(
            base64_encode(hash('sha256', $codeVerifier, true)),
            '+/', '-_'
        ), '=');
        
        // Generate state
        $_SESSION['oauth_state'] = bin2hex(random_bytes(32)); // 64-char hex
        $_SESSION['oauth_state_expiry'] = time() + 600;
        
        // Build authorization URL
        $params = http_build_query([
            'client_id' => \env('GOOGLE_CLIENT_ID'),
            'redirect_uri' => \env('APP_URL') . '/auth/callback',
            'response_type' => 'code',
            'scope' => implode(' ', $this->clientFactory->getRequiredScopes()),
            'state' => $_SESSION['oauth_state'],
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
        
        AuditLog::logAuth('login_initiated', 'OAuth login initiated from ' . \getClientIp());
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }
    
    /**
     * Validate OAuth state parameter
     */
    public function validateState(string $state): bool
    {
        if (!isset($_SESSION['oauth_state']) || 
            !isset($_SESSION['oauth_state_expiry'])) {
            \logMessage('OAuth state not found in session', 'WARNING');
            return false;
        }
        
        if (time() > $_SESSION['oauth_state_expiry']) {
            \logMessage('OAuth state expired', 'WARNING');
            return false;
        }
        
        if (!hash_equals($_SESSION['oauth_state'], $state)) {
            \logMessage('OAuth state mismatch', 'WARNING');
            AuditLog::logSecurity('state_mismatch', 'OAuth state parameter mismatch from ' . \getClientIp());
            return false;
        }
        
        return true;
    }
    
    /**
     * Get and validate code verifier
     */
    private function getCodeVerifier(): ?string
    {
        if (!isset($_SESSION['oauth_code_verifier']) ||
            !isset($_SESSION['oauth_code_verifier_expiry'])) {
            \logMessage('OAuth code verifier not found in session', 'WARNING');
            return null;
        }
        
        if (time() > $_SESSION['oauth_code_verifier_expiry']) {
            \logMessage('OAuth code verifier expired', 'WARNING');
            return null;
        }
        
        return $_SESSION['oauth_code_verifier'];
    }
    
    /**
     * Exchange authorization code for tokens (with PKCE verification)
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $codeVerifier = $this->getCodeVerifier();
        
        if (!$codeVerifier) {
            throw new \Exception('Code verifier not found or expired');
        }
        
        // Clear code verifier from session
        unset($_SESSION['oauth_code_verifier'], $_SESSION['oauth_code_verifier_expiry']);
        
        // Exchange code for tokens
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $data = [
            'code' => $code,
            'client_id' => \env('GOOGLE_CLIENT_ID'),
            'client_secret' => \env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => \env('APP_URL') . '/auth/callback',
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            \logMessage("Token exchange failed: HTTP {$httpCode} - {$response}", 'ERROR');
            throw new \Exception('Failed to exchange authorization code for tokens');
        }
        
        $tokens = json_decode($response, true);
        
        if (!isset($tokens['access_token']) || !isset($tokens['id_token'])) {
            \logMessage('Token exchange response missing required tokens', 'ERROR');
            throw new \Exception('Invalid token response from Google');
        }
        
        return $tokens;
    }
    
    /**
     * Verify ID token using Google JWKS endpoint
     * CRITICAL: This validates the user's identity
     */
    public function verifyIdToken(string $idToken): array
    {
        $client = $this->clientFactory->createClient();
        
        try {
            // Verify using google-api-client library
            $payload = $client->verifyIdToken($idToken);
            
            if (!$payload) {
                throw new \Exception('Invalid ID token');
            }
            
            // Verify audience (client ID)
            if ($payload['aud'] !== \env('GOOGLE_CLIENT_ID')) {
                throw new \Exception('ID token audience mismatch');
            }
            
            // Verify issuer
            $validIssuers = ['https://accounts.google.com', 'accounts.google.com'];
            if (!in_array($payload['iss'], $validIssuers)) {
                throw new \Exception('ID token issuer invalid');
            }
            
            // Verify expiration
            if (isset($payload['exp']) && time() > $payload['exp']) {
                throw new \Exception('ID token expired');
            }
            
            AuditLog::logAuth('id_token_verified', 'ID token verified for ' . \redactPII($payload['email']));
            
            return [
                'sub' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'] ?? '',
                'picture' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false
            ];
        } catch (\Exception $e) {
            \logMessage('ID token verification failed: ' . $e->getMessage(), 'ERROR');
            AuditLog::logSecurity('id_token_failed', 'ID token verification failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if email is in allowlist
     * CRITICAL: This controls access to the application
     */
    public function isEmailAllowed(string $email): bool
    {
        $allowlistStr = \env('EMAIL_ALLOWLIST', '');
        
        if (empty($allowlistStr)) {
            \logMessage('EMAIL_ALLOWLIST is empty - no access allowed', 'WARNING');
            return false;
        }
        
        $allowlist = array_map('trim', explode(',', $allowlistStr));
        $emailLower = strtolower(trim($email));
        
        foreach ($allowlist as $allowed) {
            if (strtolower(trim($allowed)) === $emailLower) {
                return true;
            }
        }
        
        AuditLog::logSecurity('unauthorized_email', 'Unauthorized email attempted login: ' . \redactPII($email));
        
        return false;
    }
    
    /**
     * Complete OAuth callback process
     * Returns authenticated user array
     */
    public function handleCallback(string $code, string $state): array
    {
        // 1. Validate state parameter
        if (!$this->validateState($state)) {
            throw new \Exception('Invalid state parameter');
        }
        
        // Clear state after validation
        unset($_SESSION['oauth_state'], $_SESSION['oauth_state_expiry']);
        
        // 2. Exchange authorization code for tokens (with PKCE)
        $tokens = $this->exchangeCodeForTokens($code);
        
        // 3. Verify ID token
        $userInfo = $this->verifyIdToken($tokens['id_token']);
        
        // 4. Check email allowlist
        if (!$this->isEmailAllowed($userInfo['email'])) {
            AuditLog::logSecurity('access_denied', 'Access denied for email: ' . \redactPII($userInfo['email']));
            throw new \Exception('Your email is not authorized to access this application');
        }
        
        // 5. Create or update user in database
        $user = User::upsert($userInfo);
        
        // 6. Save encrypted tokens
        UserToken::saveTokens($user['id'], $tokens);
        
        // 7. Update last login
        User::updateLastLogin($user['id']);
        
        // 8. Create audit log
        AuditLog::logAuth('login_success', 'User logged in successfully', $user['id']);
        
        \logMessage('User authenticated: ' . \redactPII($userInfo['email']) . ' (ID: ' . $user['id'] . ')', 'INFO');
        
        return $user;
    }
    
    /**
     * Log out user
     */
    public function logout(int $userId): void
    {
        AuditLog::logAuth('logout', 'User logged out', $userId);
        
        // Optionally revoke Google tokens
        $client = $this->clientFactory->createClientForUser($userId);
        if ($client) {
            try {
                $client->revokeToken();
            } catch (\Exception $e) {
                \logMessage("Failed to revoke Google token for user {$userId}: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // Clear session
        \logout();
    }
}
