<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * HealthController
 * 
 * Handles health check endpoints
 */
class HealthController extends BaseController
{
    /**
     * Basic health check
     */
    public function index(): void
    {
        $this->json([
            'status' => 'ok',
            'timestamp' => date('c'),
            'version' => getAssetVersion()
        ]);
    }
    
    /**
     * Detailed health check
     */
    public function detailed(): void
    {
        $checks = [];
        
        // Database check
        try {
            db()->query('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
        }
        
        // Storage check
        $storageDirs = ['logs', 'cache', 'rate_limits'];
        $storageOk = true;
        
        foreach ($storageDirs as $dir) {
            $path = __DIR__ . "/../../storage/{$dir}";
            if (!is_dir($path) || !is_writable($path)) {
                $storageOk = false;
                break;
            }
        }
        
        $checks['storage'] = ['status' => $storageOk ? 'ok' : 'error'];
        
        // Environment check
        $requiredEnvVars = [
            'APP_SECRET',
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
        ];
        
        $envOk = true;
        foreach ($requiredEnvVars as $var) {
            if (empty(env($var))) {
                $envOk = false;
                break;
            }
        }
        
        $checks['environment'] = ['status' => $envOk ? 'ok' : 'error'];
        
        // Overall status
        $overallStatus = 'ok';
        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $overallStatus = 'error';
                break;
            }
        }
        
        $this->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => date('c'),
            'php_version' => PHP_VERSION,
            'app_version' => getAssetVersion()
        ]);
    }
}
