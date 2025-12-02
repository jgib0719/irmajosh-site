<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;

/**
 * SecurityController
 * 
 * Handles security-related endpoints (CSP reporting)
 */
class SecurityController extends BaseController
{
    /**
     * Handle CSP violation reports
     */
    public function cspReport(): void
    {
        // Get raw POST body (CSP reports are sent as JSON)
        $body = file_get_contents('php://input');
        
        if (empty($body)) {
            http_response_code(204);
            exit;
        }
        
        try {
            $report = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                \logMessage('Invalid CSP report JSON: ' . json_last_error_msg(), 'WARNING');
                http_response_code(400);
                exit;
            }
            
            // Extract CSP violation details
            $cspReport = $report['csp-report'] ?? $report;
            
            $violation = [
                'document_uri' => $cspReport['document-uri'] ?? $cspReport['documentURL'] ?? 'unknown',
                'violated_directive' => $cspReport['violated-directive'] ?? $cspReport['violatedDirective'] ?? 'unknown',
                'blocked_uri' => $cspReport['blocked-uri'] ?? $cspReport['blockedURL'] ?? 'unknown',
                'source_file' => $cspReport['source-file'] ?? $cspReport['sourceFile'] ?? null,
                'line_number' => $cspReport['line-number'] ?? $cspReport['lineNumber'] ?? null,
            ];
            
            // Log CSP violation
            $message = sprintf(
                'CSP violation: %s blocked on %s (directive: %s)',
                $violation['blocked_uri'],
                $violation['document_uri'],
                $violation['violated_directive']
            );
            
            \logMessage($message, 'WARNING');
            
            // Store in audit log
            AuditLog::logSecurity('csp_violation', $message);
            
            http_response_code(204);
        } catch (\Exception $e) {
            \logMessage('Error processing CSP report: ' . $e->getMessage(), 'ERROR');
            http_response_code(500);
        }
        
        exit;
    }
}
