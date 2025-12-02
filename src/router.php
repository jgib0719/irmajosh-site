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
    logMessage("Router executing: {$method} {$uri}", 'DEBUG');
    logMessage("Handler: " . json_encode($handler), 'DEBUG');
    logMessage("Params: " . json_encode($params), 'DEBUG');
    
    if (is_callable($handler)) {
        // Closure handler
        logMessage("Executing closure handler", 'DEBUG');
        $handler($params);
    } elseif (is_array($handler) && count($handler) === 2) {
        // Controller action handler [ControllerClass, 'method']
        [$controllerClass, $action] = $handler;
        
        logMessage("Attempting to load controller: {$controllerClass}::{$action}", 'DEBUG');
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: {$controllerClass}");
        }
        
        logMessage("Controller class loaded: {$controllerClass}", 'DEBUG');
        
        $controller = new $controllerClass();
        
        logMessage("Controller instantiated: {$controllerClass}", 'DEBUG');
        
        if (!method_exists($controller, $action)) {
            throw new Exception("Controller action not found: {$controllerClass}::{$action}");
        }
        
        logMessage("Executing controller action: {$controllerClass}::{$action}", 'DEBUG');
        $controller->$action($params);
        logMessage("Controller action completed: {$controllerClass}::{$action}", 'DEBUG');
    } else {
        throw new Exception('Invalid route handler');
    }
} catch (Throwable $e) {
    // Log and show error with query if available
    $error = $e->getMessage();
    $file = $e->getFile() . ':' . $e->getLine();
    $trace = $e->getTraceAsString();
    
    // Try to extract SQL from PDOException
    if ($e instanceof PDOException) {
        logMessage('PDO ERROR: ' . $error, 'ERROR');
        logMessage('SQL ERROR FILE: ' . $file, 'ERROR');
        logMessage('TRACE: ' . $trace, 'ERROR');
    } else {
        logMessage('Router error: ' . $error, 'ERROR');
    }
    
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>500 Error</title></head><body>';
    echo '<h1>Error</h1>';
    echo '<p>' . htmlspecialchars($error) . '</p>';
    echo '<h2>File</h2><p>' . htmlspecialchars($file) . '</p>';
    echo '<h2>Stack Trace</h2><pre>' . htmlspecialchars($trace) . '</pre>';
    echo '</body></html>';
    exit;
}
