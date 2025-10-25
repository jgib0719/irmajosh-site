<?php
declare(strict_types=1);

/**
 * Front Controller
 * 
 * Entry point for all HTTP requests
 * Enforces HTTPS and delegates to router
 */

// HTTPS enforcement (production only)
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'http') !== 'https' 
    && ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

// Bootstrap application
require_once __DIR__ . '/../src/bootstrap.php';

// Route the request
require_once __DIR__ . '/../src/router.php';
