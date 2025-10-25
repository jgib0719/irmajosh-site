<?php
declare(strict_types=1);

/**
 * Router
 * 
 * Handles HTTP request routing to controllers
 */

// Load routes
$routes = require __DIR__ . '/../config/routes.php';

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
// Treat HEAD as GET for routing purposes
if ($method === 'HEAD') {
    $method = 'GET';
}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove trailing slash (except for root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// Find matching route
$handler = null;
$params = [];

foreach ($routes as $key => $route) {
    // Skip non-route items (e.g., _middleware)
    if (!is_array($route) || !isset($route['method'])) {
        continue;
    }
    
    // Check if method matches
    if ($route['method'] !== $method) {
        continue;
    }
    
    // Convert route pattern to regex
    $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
    $pattern = '#^' . $pattern . '$#';
    
    // Check if URI matches pattern
    if (preg_match($pattern, $uri, $matches)) {
        $handler = $route['handler'];
        
        // Extract named parameters
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        
        break;
    }
}

// If no route found, return 404
if ($handler === null) {
    http_response_code(404);
    if (file_exists(__DIR__ . '/../public_html/views/errors/404.php')) {
        require __DIR__ . '/../public_html/views/errors/404.php';
    } else {
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
    }
    exit;
}

// Apply middleware
require_once __DIR__ . '/middleware.php';

// Apply global middleware
foreach ($routes['_middleware'] ?? [] as $middleware) {
    $middleware();
}

// Apply route-specific middleware
if (isset($route['middleware'])) {
    foreach ($route['middleware'] as $middleware) {
        // If middleware is a string (function name), call it
        if (is_string($middleware) && function_exists($middleware)) {
            $middleware();
        } elseif (is_callable($middleware)) {
            $middleware();
        }
    }
}

// Try to execute the handler

// Execute handler
try {
    if (is_callable($handler)) {
        // Closure handler
        $handler($params);
    } elseif (is_array($handler) && count($handler) === 2) {
        // Controller action handler [ControllerClass, 'method']
        [$controllerClass, $action] = $handler;
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: {$controllerClass}");
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $action)) {
            throw new Exception("Controller action not found: {$controllerClass}::{$action}");
        }
        
        $controller->$action($params);
    } else {
        throw new Exception('Invalid route handler');
    }
} catch (Exception $e) {
    // Log error
    logMessage('Router error: ' . $e->getMessage(), 'ERROR');
    
    // Show 500 error page
    http_response_code(500);
    if (file_exists(__DIR__ . '/../public_html/views/errors/500.php')) {
        require __DIR__ . '/../public_html/views/errors/500.php';
    } else {
        if (env('APP_ENV') === 'production') {
            echo '<!DOCTYPE html><html><head><title>500 Internal Server Error</title></head><body><h1>500 Internal Server Error</h1></body></html>';
        } else {
            echo '<!DOCTYPE html><html><head><title>500 Internal Server Error</title></head><body><h1>500 Internal Server Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre></body></html>';
        }
    }
    exit;
}
