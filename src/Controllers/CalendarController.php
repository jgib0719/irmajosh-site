<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CalendarEvent;
use App\Services\NotificationService;

/**
 * CalendarController
 * 
 * Handles calendar view (local calendar events)
 */
class CalendarController extends BaseController
{
    private NotificationService $notificationService;
    
    public function __construct()
    {
        $this->notificationService = new NotificationService(\db());
    }
    
    /**
     * Show calendar view
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        // Get optional date parameter for navigation
        $initialDate = $_GET['date'] ?? null;
        
        $this->view('calendar', [
            'pageTitle' => \t('calendar') . ' - ' . \env('APP_NAME'),
            'user' => $user,
            'initialDate' => $initialDate,
        ]);
    }
    
    /**
     * Get events (API endpoint - returns events from local database)
     */
    public function getEvents(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        // Get date range from query params (FullCalendar sends start/end)
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        
        // Get events for the user
        if ($start && $end) {
            $events = CalendarEvent::getByUserAndDateRange($user['id'], $start, $end);
        } else {
            $events = CalendarEvent::getByUser($user['id']);
        }
        
        // Format events for FullCalendar
        $formattedEvents = array_map(function($event) {
            return [
                'id' => $event['id'],
                'title' => $event['title'],
                'start' => $event['start_at'],
                'end' => $event['end_at'],
                'description' => $event['description'] ?? '',
                'extendedProps' => [
                    'recurrence_type' => $event['recurrence_type'],
                    'recurrence_interval' => $event['recurrence_interval'],
                    'recurrence_end' => $event['recurrence_end'],
                    'schedule_request_id' => $event['schedule_request_id']
                ]
            ];
        }, $events);
        
        $this->json($formattedEvents);
    }
    
    /**
     * Create event - saves to local database
     */
    public function createEvent(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        // Validate required fields
        if (empty($_POST['title']) || empty($_POST['start'])) {
            $this->json(['error' => 'Title and start date are required'], 400);
        }
        
        // Convert datetime-local format to MySQL DATETIME
        $start = str_replace('T', ' ', $_POST['start']) . ':00';
        $end = !empty($_POST['end']) ? str_replace('T', ' ', $_POST['end']) . ':00' : null;
        
        // If no end time, default to 1 hour after start
        if (!$end) {
            $endDate = new \DateTime($start);
            $endDate->modify('+1 hour');
            $end = $endDate->format('Y-m-d H:i:s');
        }
        
        // Create event
        $event = CalendarEvent::create([
            'user_id' => $user['id'],
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? null,
            'start_at' => $start,
            'end_at' => $end,
            'recurrence_type' => $_POST['recurrence_type'] ?? null,
            'recurrence_interval' => !empty($_POST['recurrence_interval']) ? (int)$_POST['recurrence_interval'] : null,
            'recurrence_end' => $_POST['recurrence_end'] ?? null,
            'schedule_request_id' => !empty($_POST['request_id']) ? (int)$_POST['request_id'] : null
        ]);
        
        \logMessage("Calendar event {$event['id']} created by user {$user['id']}", 'INFO');
        
        // Send notifications
        try {
            // Push notification
            $this->notificationService->sendToUser(
                $user['id'],
                'Event Created',
                $event['title'],
                [
                    'type' => 'calendar_event',
                    'eventId' => $event['id'],
                    'url' => '/calendar'
                ]
            );
            
            // Email notifications
            $emailService = new \App\Services\EmailService();
            if ($emailService->isConfigured()) {
                $startDate = date('F j, Y g:i A', strtotime($event['start_at']));
                $endDate = date('F j, Y g:i A', strtotime($event['end_at']));
                
                // Send to user
                $emailService->sendNotification(
                    $user['email'],
                    'Calendar Event Created',
                    "You created a new calendar event:\n\n" .
                    "Title: {$event['title']}\n" .
                    (!empty($event['description']) ? "Description: {$event['description']}\n" : '') .
                    "Start: {$startDate}\n" .
                    "End: {$endDate}\n\n" .
                    "View at: " . \env('APP_URL') . "/calendar"
                );
                
                // Send to admins
                $emailService->sendAdminNotification(
                    'Calendar Event Created',
                    "User: {$user['name']} ({$user['email']})\n\n" .
                    "Title: {$event['title']}\n" .
                    (!empty($event['description']) ? "Description: {$event['description']}\n" : '') .
                    "Start: {$startDate}\n" .
                    "End: {$endDate}\n\n" .
                    "View at: " . \env('APP_URL') . "/calendar"
                );
            }
        } catch (\Exception $e) {
            \logError("Failed to send notification: " . $e->getMessage());
        }
        
        $this->json([
            'success' => true,
            'id' => $event['id'],
            'message' => 'Event created successfully',
            'event' => $event
        ]);
    }
    
    /**
     * Update event
     */
    public function updateEvent(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $eventId = (int)($params['id'] ?? 0);
        
        if (!$eventId) {
            $this->json(['error' => 'Event ID required'], 400);
        }
        
        // Check ownership
        if (!CalendarEvent::userOwns($eventId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        // Prepare update data
        $updateData = [];
        
        if (isset($_POST['title'])) {
            $updateData['title'] = $_POST['title'];
        }
        
        if (isset($_POST['description'])) {
            $updateData['description'] = $_POST['description'];
        }
        
        if (isset($_POST['start'])) {
            $updateData['start_at'] = str_replace('T', ' ', $_POST['start']) . ':00';
        }
        
        if (isset($_POST['end'])) {
            $updateData['end_at'] = str_replace('T', ' ', $_POST['end']) . ':00';
        }
        
        if (empty($updateData)) {
            $this->json(['error' => 'No data to update'], 400);
        }
        
        $success = CalendarEvent::update($eventId, $updateData);
        
        if (!$success) {
            $this->json(['error' => 'Failed to update event'], 500);
        }
        
        \logMessage("Calendar event {$eventId} updated by user {$user['id']}", 'INFO');
        
        $this->json([
            'success' => true,
            'message' => 'Event updated successfully'
        ]);
    }
    
    /**
     * Delete event
     */
    public function deleteEvent(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $eventId = (int)($params['id'] ?? 0);
        
        if (!$eventId) {
            $this->json(['error' => 'Event ID required'], 400);
        }
        
        // Check ownership
        if (!CalendarEvent::userOwns($eventId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $success = CalendarEvent::delete($eventId);
        
        if (!$success) {
            $this->json(['error' => 'Failed to delete event'], 500);
        }
        
        \logMessage("Calendar event {$eventId} deleted by user {$user['id']}", 'INFO');
        
        $this->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
    
    /**
     * Sync calendar - stubbed (returns success but does nothing)
     */
    public function sync(): void
    {
        $this->requireAuth();
        
        // Accept the request but don't sync anything
        $this->json([
            'success' => true,
            'message' => 'Calendar sync not yet implemented'
        ]);
    }
}
