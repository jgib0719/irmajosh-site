<?php
/**
 * Application Routes
 * 
 * Format: 'METHOD /path' => [ControllerClass::class, 'method']
 * Supports dynamic segments: /path/{id}
 */

return [
    // Global middleware applied to all routes
    '_middleware' => ['parseJsonBodyMiddleware'],
    
    // ========================================================================
    // Public Routes
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => [\App\Controllers\AuthController::class, 'index'],
        'middleware' => ['guestMiddleware']
    ],
    
    // ========================================================================
    // Authentication Routes (rate-limited)
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/auth/login',
        'handler' => [\App\Controllers\AuthController::class, 'login'],
        'middleware' => ['rateLimitMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/auth/callback',
        'handler' => [\App\Controllers\AuthController::class, 'callback'],
        'middleware' => ['rateLimitMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/auth/logout',
        'handler' => [\App\Controllers\AuthController::class, 'logout'],
        'middleware' => ['authMiddleware', 'rateLimitMiddleware']
    ],
    
    // ========================================================================
    // Dashboard (authenticated)
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/dashboard',
        'handler' => [\App\Controllers\DashboardController::class, 'index'],
        'middleware' => ['authMiddleware']
    ],
    
    // ========================================================================
    // Calendar (authenticated)
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/calendar',
        'handler' => [\App\Controllers\CalendarController::class, 'index'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/calendar/events',
        'handler' => [\App\Controllers\CalendarController::class, 'getEvents'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/calendar/events',
        'handler' => [\App\Controllers\CalendarController::class, 'createEvent'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'PUT',
        'path' => '/calendar/events/{id}',
        'handler' => [\App\Controllers\CalendarController::class, 'updateEvent'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'DELETE',
        'path' => '/calendar/events/{id}',
        'handler' => [\App\Controllers\CalendarController::class, 'deleteEvent'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/calendar/sync',
        'handler' => [\App\Controllers\CalendarController::class, 'sync'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],

    [
        'method' => 'GET',
        'path' => '/calendar/feed/{token}',
        'handler' => [\App\Controllers\CalendarController::class, 'feed'],
        'middleware' => ['rateLimitMiddleware']
    ],

    [
        'method' => 'POST',
        'path' => '/calendar/feed/refresh',
        'handler' => [\App\Controllers\CalendarController::class, 'refreshFeedToken'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/calendar/search',
        'handler' => [\App\Controllers\CalendarController::class, 'search'],
        'middleware' => ['authMiddleware']
    ],
    
    // ========================================================================
    // Tasks (authenticated)
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/tasks/shared',
        'handler' => [\App\Controllers\TaskController::class, 'shared'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/tasks/private',
        'handler' => [\App\Controllers\TaskController::class, 'private'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/tasks/api',
        'handler' => [\App\Controllers\TaskController::class, 'getTasks'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/tasks/{id}',
        'handler' => [\App\Controllers\TaskController::class, 'get'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/tasks',
        'handler' => [\App\Controllers\TaskController::class, 'create'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'PUT',
        'path' => '/tasks/{id}',
        'handler' => [\App\Controllers\TaskController::class, 'update'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'DELETE',
        'path' => '/tasks/{id}',
        'handler' => [\App\Controllers\TaskController::class, 'delete'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    // ========================================================================
    // Date Night (authenticated)
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/date-night',
        'handler' => [\App\Controllers\DateNightController::class, 'index'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/date-night/complete',
        'handler' => [\App\Controllers\DateNightController::class, 'complete'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/date-night/create',
        'handler' => [\App\Controllers\DateNightController::class, 'create'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    // ========================================================================
    // Locale (language switching)
    // ========================================================================
    
    [
        'method' => 'POST',
        'path' => '/locale/switch',
        'handler' => [\App\Controllers\LocaleController::class, 'switch'],
        'middleware' => ['csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    // ========================================================================
    // Push Notifications (authenticated)
    // ========================================================================
    
    [
        'method' => 'POST',
        'path' => '/notifications/subscribe',
        'handler' => [\App\Controllers\NotificationController::class, 'subscribe'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'DELETE',
        'path' => '/notifications/unsubscribe',
        'handler' => [\App\Controllers\NotificationController::class, 'unsubscribe'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'GET',
        'path' => '/notifications/status',
        'handler' => [\App\Controllers\NotificationController::class, 'status'],
        'middleware' => ['authMiddleware']
    ],
    
    // ========================================================================
    // PWA Endpoints
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/service-worker.js',
        'handler' => [\App\Controllers\PWAController::class, 'serviceWorker'],
        'middleware' => []
    ],
    
    [
        'method' => 'GET',
        'path' => '/offline.html',
        'handler' => [\App\Controllers\PWAController::class, 'offline'],
        'middleware' => []
    ],
    
    // ========================================================================
    // Health Check
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/health',
        'handler' => [\App\Controllers\HealthController::class, 'index'],
        'middleware' => []
    ],
    
    [
        'method' => 'GET',
        'path' => '/health/detailed',
        'handler' => [\App\Controllers\HealthController::class, 'detailed'],
        'middleware' => []
    ],
    
    // ========================================================================
    // Security (CSP reporting - rate-limited)
    // ========================================================================
    
    [
        'method' => 'POST',
        'path' => '/csp-report',
        'handler' => [\App\Controllers\SecurityController::class, 'cspReport'],
        'middleware' => ['rateLimitMiddleware']
    ],
    
    // ========================================================================
    // Shopping List (authenticated)
    // ========================================================================
    
    [
        'method' => 'GET',
        'path' => '/shopping-list',
        'handler' => [\App\Controllers\ShoppingController::class, 'index'],
        'middleware' => ['authMiddleware']
    ],
    
    [
        'method' => 'POST',
        'path' => '/shopping-list',
        'handler' => [\App\Controllers\ShoppingController::class, 'create'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'PUT',
        'path' => '/shopping-list/{id}/toggle',
        'handler' => [\App\Controllers\ShoppingController::class, 'toggle'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
    
    [
        'method' => 'DELETE',
        'path' => '/shopping-list/{id}',
        'handler' => [\App\Controllers\ShoppingController::class, 'delete'],
        'middleware' => ['authMiddleware', 'csrfMiddleware', 'rateLimitMiddleware']
    ],
];
