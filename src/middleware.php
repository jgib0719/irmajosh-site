<?php
declare(strict_types=1);

/**
 * Middleware Functions
 * 
 * Request processing middleware
 */

/**
 * Authentication middleware - require user to be logged in
 */
function authMiddleware(): void
{
    requireAuth();
}

/**
 * CSRF protection middleware - verify CSRF token on mutations
 */
function csrfMiddleware(): void
{
    // Only check CSRF on state-changing methods
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        return;
    }
    
    // Get token from header or POST data
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        logMessage('CSRF token validation failed for ' . $_SERVER['REQUEST_URI'], 'WARNING');
        
        if (isAjaxRequest()) {
            json(['error' => 'Invalid CSRF token. Please refresh the page.'], 403);
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Invalid CSRF token. Please refresh the page and try again.</p></body></html>';
            exit;
        }
    }
}

/**
 * Rate limiting middleware - limit requests per IP
 * ONLY applied to specific endpoints (auth, CSP reporting, email sending)
 */
function rateLimitMiddleware(): void
{
    // ONLY rate limit these endpoints
    $rateLimitedPaths = [
        '/auth/login',
        '/auth/callback',
        '/csp-report',
        '/schedule/send-request', // Email sending
    ];
    
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if (!in_array($requestPath, $rateLimitedPaths)) {
        return; // No rate limiting for regular routes
    }
    
    $identifier = getClientIp();
    $cacheKey = "rate_limit:{$requestPath}:{$identifier}";
    $limit = 100; // 100 requests
    $window = 900; // 15 minutes
    
    $cacheDir = __DIR__ . '/../storage/rate_limits';
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.json';
    
    // Ensure cache directory exists
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        // Check if window expired
        if (time() > $data['expires']) {
            // Reset counter
            $data = ['count' => 1, 'expires' => time() + $window];
        } else {
            // Increment counter
            $data['count']++;
            
            // Check if limit exceeded
            if ($data['count'] > $limit) {
                logMessage("Rate limit exceeded for {$identifier} on {$requestPath}", 'WARNING');
                
                http_response_code(429);
                header('Retry-After: ' . ($data['expires'] - time()));
                
                if (isAjaxRequest()) {
                    json(['error' => 'Too many requests. Please try again later.'], 429);
                } else {
                    echo '<!DOCTYPE html><html><head><title>429 Too Many Requests</title></head><body><h1>429 Too Many Requests</h1><p>You have exceeded the rate limit. Please try again later.</p></body></html>';
                    exit;
                }
            }
        }
    } else {
        $data = ['count' => 1, 'expires' => time() + $window];
    }
    
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}

/**
 * Guest middleware - redirect authenticated users away from auth pages
 */
function guestMiddleware(): void
{
    if (isAuthenticated()) {
        redirect('/dashboard');
    }
}

/**
 * JSON middleware - ensure request accepts JSON responses
 */
function jsonMiddleware(): void
{
    header('Content-Type: application/json');
}
