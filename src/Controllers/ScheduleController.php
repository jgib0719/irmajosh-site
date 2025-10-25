<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScheduleRequest;
use App\Models\ScheduleRequestSlot;
use App\Services\EmailService;

/**
 * ScheduleController
 * 
 * Handles schedule requests with email notifications
 */
class ScheduleController extends BaseController
{
    private EmailService $emailService;
    
    public function __construct()
    {
        $this->emailService = new EmailService();
    }
    
    /**
     * Show schedule requests
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $requests = ScheduleRequest::getByUser($user['id']);
        
        $this->view('schedule-requests', [
            'pageTitle' => t('schedule_requests') . ' - ' . env('APP_NAME'),
            'user' => $user,
            'requests' => $requests,
        ]);
    }
    
    /**
     * Create and send schedule request
     */
    public function sendRequest(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        $required = ['recipient_email', 'subject'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->json(['error' => 'Missing required fields'], 400);
        }
        
        $recipientEmail = trim($this->getPost('recipient_email'));
        
        // Validate email
        if (!validateEmail($recipientEmail)) {
            $this->json(['error' => 'Invalid email address'], 400);
        }
        
        // Create schedule request
        $requestData = [
            'user_id' => $user['id'],
            'recipient_email' => $recipientEmail,
            'subject' => sanitizeInput($this->getPost('subject')),
            'message' => sanitizeInput($this->getPost('message', '')),
            'status' => 'pending',
        ];
        
        $request = ScheduleRequest::create($requestData);
        
        // Create time slots
        $slots = json_decode($this->getPost('slots', '[]'), true);
        
        if (!empty($slots)) {
            ScheduleRequestSlot::createMultiple($request['id'], $slots);
        }
        
        // Send email notification
        $slotsForEmail = ScheduleRequestSlot::getByRequest($request['id']);
        
        if (!empty($slotsForEmail)) {
            $emailSent = $this->emailService->sendScheduleRequest(
                $recipientEmail,
                $slotsForEmail,
                $requestData['subject'],
                $requestData['message']
            );
            
            if ($emailSent) {
                ScheduleRequest::updateStatus($request['id'], 'sent');
                
                $this->json([
                    'success' => true,
                    'request' => $request,
                    'message' => t('request_sent')
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'error' => 'Failed to send email notification'
                ], 500);
            }
        } else {
            $this->json([
                'success' => true,
                'request' => $request,
                'message' => 'Schedule request created (no slots provided)'
            ]);
        }
    }
    
    /**
     * Get schedule request details
     */
    public function getRequest(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $requestId = (int)($params['id'] ?? 0);
        
        if (!$requestId) {
            $this->json(['error' => 'Request ID required'], 400);
        }
        
        // Check ownership
        if (!ScheduleRequest::userOwns($requestId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $request = ScheduleRequest::find($requestId);
        $slots = ScheduleRequestSlot::getByRequest($requestId);
        
        $this->json([
            'request' => $request,
            'slots' => $slots
        ]);
    }
    
    /**
     * Update schedule request status
     */
    public function updateStatus(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $requestId = (int)($params['id'] ?? 0);
        
        if (!$requestId) {
            $this->json(['error' => 'Request ID required'], 400);
        }
        
        // Check ownership
        if (!ScheduleRequest::userOwns($requestId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $status = $this->getPost('status');
        
        if (!in_array($status, ['pending', 'sent', 'accepted', 'declined', 'cancelled'])) {
            $this->json(['error' => 'Invalid status'], 400);
        }
        
        $success = ScheduleRequest::updateStatus($requestId, $status);
        
        if (!$success) {
            $this->json(['error' => 'Failed to update status'], 500);
        }
        
        $this->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }
    
    /**
     * Delete schedule request
     */
    public function deleteRequest(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $requestId = (int)($params['id'] ?? 0);
        
        if (!$requestId) {
            $this->json(['error' => 'Request ID required'], 400);
        }
        
        // Check ownership
        if (!ScheduleRequest::userOwns($requestId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $success = ScheduleRequest::delete($requestId);
        
        if (!$success) {
            $this->json(['error' => 'Failed to delete request'], 500);
        }
        
        $this->json([
            'success' => true,
            'message' => 'Schedule request deleted successfully'
        ]);
    }
}
