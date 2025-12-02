<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * PWAController
 * 
 * Handles PWA-related endpoints
 */
class PWAController extends BaseController
{
    /**
     * Serve service worker
     */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $swFile = __DIR__ . '/../../public_html/service-worker.js';
        
        if (file_exists($swFile)) {
            readfile($swFile);
        } else {
            http_response_code(404);
            echo '// Service worker not found';
        }
        
        exit;
    }
    
    /**
     * Serve offline page
     */
    public function offline(): void
    {
        $offlineFile = __DIR__ . '/../../public_html/views/offline.html';
        
        if (file_exists($offlineFile)) {
            readfile($offlineFile);
        } else {
            echo '<!DOCTYPE html><html><head><title>Offline</title></head><body><h1>You are offline</h1><p>Please check your internet connection.</p></body></html>';
        }
        
        exit;
    }
}
