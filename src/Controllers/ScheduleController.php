<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScheduleRequest;
use App\Models\ScheduleRequestSlot;
use App\Models\User;
use App\Services\EmailService;
use App\Services\NotificationService;

/**
 * ScheduleController
 * 
 * Handles schedule requests with email notifications
 */
class ScheduleController extends BaseController
{
    private EmailService $emailService;
    private NotificationService $notificationService;
    
    public function __construct()
    {
        $this->emailService = new EmailService();
        $this->notificationService = new NotificationService(\db());
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
            'pageTitle' => \t('schedule_requests') . ' - ' . \env('APP_NAME'),
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
        
        $required = ['title'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->json(['error' => 'Missing required fields'], 400);
        }
        
        // Create schedule request
        $requestData = [
            'sender_id' => $user['id'],
            'recipient_id' => $user['id'], // For now, user creates requests for themselves
            'title' => \sanitizeInput($this->getPost('title')),
            'description' => \sanitizeInput($this->getPost('description', '')),
            'status' => 'pending',
        ];
        
        $request = ScheduleRequest::create($requestData);
        
        // Create time slots (optional)
        $slots = $this->getPost('slots', []);
        
        if (!empty($slots) && is_array($slots)) {
            ScheduleRequestSlot::createMultiple($request['id'], $slots);
        }
        
        // Success - slots will be added when accepting the request
        $this->json([
            'success' => true,
            'request' => $request,
            'message' => 'Schedule request created successfully'
        ]);
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
        
        if (!$request) {
            $this->json(['error' => 'Request not found'], 404);
        }
        
        $slots = ScheduleRequestSlot::getByRequest($requestId);
        
        // Add slots to the response
        $request['slots'] = $slots;
        
        // Get sender name
        $sender = User::find($request['sender_id']);
        $request['sender_name'] = $sender ? $sender['name'] : 'Unknown';
        
        $this->json($request);
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
    
    /**
     * Accept schedule request
     */
    public function acceptRequest(array $params): void
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
        
        if (!$request) {
            $this->json(['error' => 'Request not found'], 404);
        }
        
        $success = ScheduleRequest::updateStatus($requestId, 'accepted');
        
        if (!$success) {
            $this->json(['error' => 'Failed to accept request'], 500);
        }
        
        // Send notifications
        try {
            // Push notification
            $this->notificationService->sendToUser(
                $user['id'],
                'Schedule Request Accepted',
                'You accepted: ' . $request['title'],
                [
                    'type' => 'schedule_accepted',
                    'requestId' => $requestId,
                    'url' => '/schedule'
                ]
            );
            
            // Email notifications
            if ($this->emailService->isConfigured()) {
                // Send to user
                $this->emailService->sendNotification(
                    $user['email'],
                    'Schedule Request Accepted',
                    "You accepted the schedule request:\n\n" .
                    "Title: {$request['title']}\n" .
                    (!empty($request['description']) ? "Description: {$request['description']}\n" : '') .
                    "\nNext step: Schedule it to your calendar\n" .
                    "View at: " . \env('APP_URL') . "/schedule"
                );
                
                // Send to admins
                $this->emailService->sendAdminNotification(
                    'Schedule Request Accepted',
                    "User: {$user['name']} ({$user['email']})\n\n" .
                    "Title: {$request['title']}\n" .
                    (!empty($request['description']) ? "Description: {$request['description']}\n" : '') .
                    "\nView at: " . \env('APP_URL') . "/schedule"
                );
            }
        } catch (\Exception $e) {
            \logError("Failed to send notification: " . $e->getMessage());
        }
        
        $this->json([
            'success' => true,
            'message' => 'Schedule request accepted'
        ]);
    }
    
    /**
     * Decline schedule request
     */
    public function declineRequest(array $params): void
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
        
        $success = ScheduleRequest::updateStatus($requestId, 'declined');
        
        if (!$success) {
            $this->json(['error' => 'Failed to decline request'], 500);
        }
        
        $this->json([
            'success' => true,
            'message' => 'Schedule request declined'
        ]);
    }
    
    /**
     * Schedule request to calendar (creates Google Calendar event)
     */
    public function scheduleToCalendar(array $params): void
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
        
        if (!$request) {
            $this->json(['error' => 'Request not found'], 404);
        }
        
        if ($request['status'] !== 'accepted') {
            $this->json(['error' => 'Request must be accepted first'], 400);
        }
        
        // Check if we have a slot_id (old workflow) or calendar_event_id (new workflow)
        $slotId = $this->getPost('slot_id');
        $calendarEventId = $this->getPost('calendar_event_id');
        
        if ($slotId) {
            // Old workflow: with predefined slots
            $slot = ScheduleRequestSlot::find((int)$slotId);
            
            if (!$slot) {
                $this->json(['error' => 'Slot not found'], 404);
            }
            
            // Verify slot belongs to this request
            if ($slot['request_id'] !== $requestId) {
                $this->json(['error' => 'Slot does not belong to this request'], 400);
            }
            
            // Update request with accepted slot
            $success = ScheduleRequest::update($requestId, [
                'accepted_slot_id' => $slotId
            ]);
            
            if (!$success) {
                $this->json(['error' => 'Failed to update request'], 500);
            }
            
            \logMessage("Schedule request {$requestId} scheduled with slot {$slotId}", 'INFO');
        } elseif ($calendarEventId) {
            // New workflow: calendar event already created, just log it
            // The calendar event is already linked via schedule_request_id in calendar_events table
            \logMessage("Schedule request {$requestId} scheduled with calendar event {$calendarEventId}", 'INFO');
        } else {
            $this->json(['error' => 'Either slot_id or calendar_event_id is required'], 400);
        }
        
        $this->json([
            'success' => true,
            'message' => 'Request scheduled to calendar',
            'calendar_url' => '/calendar'
        ]);
    }
}
