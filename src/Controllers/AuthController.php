<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

/**
 * AuthController
 * 
 * Handles OAuth authentication with complete PKCE flow
 */
class AuthController extends BaseController
{
    private AuthService $authService;
    
    public function __construct()
    {
        $this->authService = new AuthService();
    }
    
    /**
     * Show landing page
     */
    public function index(): void
    {
        // Redirect to dashboard if already authenticated
        if (isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $this->view('landing', [
            'pageTitle' => t('welcome') . ' - ' . env('APP_NAME')
        ]);
    }
    
    /**
     * Initiate OAuth login with PKCE
     */
    public function login(): void
    {
        try {
            $authUrl = $this->authService->initiateLogin();
            $this->redirect($authUrl);
        } catch (\Exception $e) {
            logMessage('Login initiation failed: ' . $e->getMessage(), 'ERROR');
            $this->setFlash('error', 'Failed to initiate login. Please try again.');
            $this->redirect('/');
        }
    }
    
    /**
     * Handle OAuth callback
     */
    public function callback(): void
    {
        // Check for error from OAuth provider
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
            $errorDescription = $_GET['error_description'] ?? 'Unknown error';
            
            logMessage("OAuth error: {$error} - {$errorDescription}", 'WARNING');
            $this->setFlash('error', 'Authentication failed: ' . htmlspecialchars($errorDescription));
            $this->redirect('/');
        }
        
        // Get authorization code and state
        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        
        if (!$code || !$state) {
            logMessage('OAuth callback missing code or state', 'WARNING');
            $this->setFlash('error', 'Invalid authentication response');
            $this->redirect('/');
        }
        
        try {
            // Handle complete OAuth flow
            $user = $this->authService->handleCallback($code, $state);
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['regenerate'] = true;
            
            $this->setFlash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
            $this->redirect('/dashboard');
        } catch (\Exception $e) {
            logMessage('OAuth callback failed: ' . $e->getMessage(), 'ERROR');
            
            // Check if it's an authorization error
            if (strpos($e->getMessage(), 'not authorized') !== false) {
                $this->setFlash('error', $e->getMessage());
            } else {
                $this->setFlash('error', 'Authentication failed. Please try again.');
            }
            
            $this->redirect('/');
        }
    }
    
    /**
     * Log out current user
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            $this->authService->logout($userId);
        } else {
            logout();
        }
        
        $this->setFlash('success', 'You have been logged out');
        $this->redirect('/');
    }
}
