<?php
declare(strict_types=1);

namespace App\Services;

use Google_Client;

/**
 * GoogleClientFactory Service
 * 
 * Creates and configures Google API Client instances
 * CRITICAL: 'openid' scope is MANDATORY for ID token
 */
class GoogleClientFactory
{
    private const REQUIRED_SCOPES = [
        'openid',  // MANDATORY for ID token verification
        'email',
        'profile',
        // Calendar scope removed to avoid Google unverified app warning
        // Add back when app is verified: 'https://www.googleapis.com/auth/calendar',
    ];
    
    /**
     * Create a configured Google Client instance
     */
    public function createClient(): Google_Client
    {
        $client = new Google_Client();
        
        // OAuth 2.0 configuration
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('APP_URL') . '/auth/callback');
        
        // Set scopes (MUST include 'openid')
        $client->setScopes(self::REQUIRED_SCOPES);
        
        // Request offline access for refresh tokens
        $client->setAccessType('offline');
        
        // Always prompt for consent to get refresh token
        $client->setPrompt('consent');
        
        // Set application name
        $client->setApplicationName(env('APP_NAME', 'IrmaJosh'));
        
        return $client;
    }
    
    /**
     * Create client with user's access token
     */
    public function createClientWithToken(string $accessToken): Google_Client
    {
        $client = $this->createClient();
        $client->setAccessToken($accessToken);
        
        return $client;
    }
    
    /**
     * Create client for a specific user
     */
    public function createClientForUser(int $userId): ?Google_Client
    {
        $tokens = \App\Models\UserToken::getTokens($userId);
        
        if (!$tokens) {
            logMessage("No tokens found for user {$userId}", 'WARNING');
            return null;
        }
        
        $client = $this->createClient();
        
        // Set access token
        $client->setAccessToken([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_at'] ? strtotime($tokens['expires_at']) - time() : 3600
        ]);
        
        // Check if token is expired and refresh if needed
        if ($client->isAccessTokenExpired() && $tokens['refresh_token']) {
            try {
                $newTokens = $client->fetchAccessTokenWithRefreshToken($tokens['refresh_token']);
                
                if (isset($newTokens['error'])) {
                    logMessage("Token refresh failed for user {$userId}: " . $newTokens['error'], 'ERROR');
                    return null;
                }
                
                // Save new tokens
                \App\Models\UserToken::saveTokens($userId, $newTokens);
                
                logMessage("Refreshed access token for user {$userId}", 'INFO');
            } catch (\Exception $e) {
                logMessage("Token refresh exception for user {$userId}: " . $e->getMessage(), 'ERROR');
                return null;
            }
        }
        
        return $client;
    }
    
    /**
     * Get required scopes
     */
    public function getRequiredScopes(): array
    {
        return self::REQUIRED_SCOPES;
    }
}
