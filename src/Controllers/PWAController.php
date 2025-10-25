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
     * Serve manifest.json
     */
    public function manifest(): void
    {
        header('Content-Type: application/json');
        
        $manifest = [
            'name' => env('APP_NAME', 'IrmaJosh'),
            'short_name' => env('APP_NAME', 'IrmaJosh'),
            'description' => 'Personal calendar and task management system',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#4285f4',
            'orientation' => 'portrait-primary',
            'icons' => [
                [
                    'src' => '/assets/icons/icon-72x72.png',
                    'sizes' => '72x72',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-96x96.png',
                    'sizes' => '96x96',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-128x128.png',
                    'sizes' => '128x128',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-144x144.png',
                    'sizes' => '144x144',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-152x152.png',
                    'sizes' => '152x152',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-384x384.png',
                    'sizes' => '384x384',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/icons/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ],
            'categories' => ['productivity', 'utilities'],
            'screenshots' => []
        ];
        
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Serve service worker
     */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        
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
