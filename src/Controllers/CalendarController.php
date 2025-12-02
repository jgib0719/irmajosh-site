<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
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
            // Show ALL calendar events (shared calendar)
            $events = CalendarEvent::getAllByDateRange($start, $end);
            // Show user's tasks AND shared tasks
            $tasks = Task::getSharedAndUserByDateRange($user['id'], $start, $end);
        } else {
            $events = CalendarEvent::getAll();
            $tasks = Task::getSharedAndUser($user['id']);
        }
        
        // Format events for FullCalendar
        $formattedEvents = array_map(function($event) {
            return [
                'id' => $event['id'],
                'title' => $event['title'],
                'start' => $event['start_at'],
                'end' => $event['end_at'],
                'allDay' => (bool)$event['is_all_day'],
                'backgroundColor' => $event['color'] ?? '#3b82f6',
                'borderColor' => $event['color'] ?? '#3b82f6',
                'description' => $event['description'] ?? '',
                'extendedProps' => [
                    'type' => 'event',
                    'color' => $event['color'] ?? '#3b82f6',
                    'recurrence_type' => $event['recurrence_type'],
                    'recurrence_interval' => $event['recurrence_interval'],
                    'recurrence_end' => $event['recurrence_end'],
                    'is_all_day' => (bool)$event['is_all_day']
                ]
            ];
        }, $events);

        // Format tasks for FullCalendar
        $formattedTasks = array_map(function($task) {
            $isCompleted = $task['status'] === 'completed';
            return [
                'id' => 'task_' . $task['id'],
                'title' => ($isCompleted ? '✓ ' : '☐ ') . $task['title'],
                'start' => $task['due_date'],
                'backgroundColor' => $isCompleted ? '#9ca3af' : '#10b981', // Gray if done, Green if pending
                'borderColor' => $isCompleted ? '#9ca3af' : '#10b981',
                'description' => $task['description'] ?? '',
                'extendedProps' => [
                    'type' => 'task',
                    'status' => $task['status'],
                    'original_id' => $task['id'],
                    'is_shared' => (bool)$task['is_shared']
                ]
            ];
        }, $tasks);
        
        $this->json(array_merge($formattedEvents, $formattedTasks));
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
        // Use loose comparison for is_all_day as JSON might send integer 1
        $isAllDay = !empty($_POST['is_all_day']);
        
        if ($isAllDay) {
            // For all day, we expect YYYY-MM-DD
            // Strip any time component if present (e.g. from datetime-local input)
            $startDate = substr($_POST['start'], 0, 10);
            $endDate = !empty($_POST['end']) ? substr($_POST['end'], 0, 10) : $startDate;
            
            $start = $startDate . ' 00:00:00';
            $end = $endDate . ' 23:59:59';
        } else {
            $start = str_replace('T', ' ', $_POST['start']) . ':00';
            $end = !empty($_POST['end']) ? str_replace('T', ' ', $_POST['end']) . ':00' : null;
            
            // If no end time, default to 1 hour after start
            if (!$end) {
                $endDate = new \DateTime($start);
                $endDate->modify('+1 hour');
                $end = $endDate->format('Y-m-d H:i:s');
            }
        }
        
        // Only set recurrence if explicitly checked
        $isRecurring = !empty($_POST['is_recurring']) && $_POST['is_recurring'] === '1';
        
        $event = CalendarEvent::create([
            'user_id' => $user['id'],
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? null,
            'color' => $_POST['color'] ?? '#3b82f6',
            'start_at' => $start,
            'end_at' => $end,
            'is_all_day' => $isAllDay,
            'recurrence_type' => $isRecurring && !empty($_POST['recurrence_type']) ? $_POST['recurrence_type'] : null,
            'recurrence_interval' => $isRecurring && !empty($_POST['recurrence_interval']) ? (int)$_POST['recurrence_interval'] : null,
            'recurrence_end' => $isRecurring && !empty($_POST['recurrence_end']) ? $_POST['recurrence_end'] : null
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
            
            // Email notifications removed per user request
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
        
        if (isset($_POST['color'])) {
            $updateData['color'] = $_POST['color'];
        }

        // Handle is_all_day update
        if (isset($_POST['is_all_day'])) {
            $updateData['is_all_day'] = !empty($_POST['is_all_day']) ? 1 : 0;
        }
        
        $isAllDay = isset($updateData['is_all_day']) ? $updateData['is_all_day'] : 
                   (CalendarEvent::find($eventId)['is_all_day'] ?? 0);

        if (isset($_POST['start'])) {
            if ($isAllDay) {
                $startDate = substr($_POST['start'], 0, 10);
                $updateData['start_at'] = $startDate . ' 00:00:00';
            } else {
                $updateData['start_at'] = str_replace('T', ' ', $_POST['start']) . ':00';
            }
        }
        
        if (isset($_POST['end'])) {
            if ($isAllDay) {
                $endDate = substr($_POST['end'], 0, 10);
                $updateData['end_at'] = $endDate . ' 23:59:59';
            } else {
                $updateData['end_at'] = str_replace('T', ' ', $_POST['end']) . ':00';
            }
        }
        
        // Check if we should update all occurrences of a recurring event
        $updateAll = isset($_POST['update_all']) && $_POST['update_all'] === true;
        
        // Handle recurrence fields - only if updating all occurrences
        if ($updateAll) {
            if (isset($_POST['recurrence_type'])) {
                $updateData['recurrence_type'] = $_POST['recurrence_type'];
            }
            
            if (isset($_POST['recurrence_interval'])) {
                $updateData['recurrence_interval'] = (int)$_POST['recurrence_interval'];
            }
            
            if (isset($_POST['recurrence_end'])) {
                $updateData['recurrence_end'] = !empty($_POST['recurrence_end']) ? $_POST['recurrence_end'] : null;
            }
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
        
        $mode = $_GET['mode'] ?? 'all';
        $dateStr = $_GET['date'] ?? null;
        
        // If simple delete or no date provided, delete the whole event
        if ($mode === 'all' || !$dateStr) {
            $success = CalendarEvent::delete($eventId);
            
            if (!$success) {
                $this->json(['error' => 'Failed to delete event'], 500);
            }
            
            \logMessage("Calendar event {$eventId} deleted by user {$user['id']}", 'INFO');
            
            $this->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);
            return;
        }
        
        // Handle recurring event deletion
        $event = CalendarEvent::find($eventId);
        if (!$event) {
            $this->json(['error' => 'Event not found'], 404);
        }
        
        // Parse the instance date
        try {
            $instanceDate = new \DateTime($dateStr);
        } catch (\Exception $e) {
            $this->json(['error' => 'Invalid date format'], 400);
        }
        
        if ($mode === 'future') {
            // End the recurrence before this instance
            // Set recurrence_end to the day before the instance date
            $endDate = clone $instanceDate;
            $endDate->modify('-1 day');
            
            $success = CalendarEvent::update($eventId, [
                'recurrence_end' => $endDate->format('Y-m-d')
            ]);
            
            if (!$success) {
                $this->json(['error' => 'Failed to update event'], 500);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Future occurrences deleted successfully'
            ]);
            return;
        }
        
        if ($mode === 'single') {
            // Split the event into two:
            // 1. Original event ends before this instance
            // 2. New event starts after this instance
            
            // Calculate new start date for the second part
            $nextStart = clone $instanceDate;
            $interval = $event['recurrence_interval'] ?? 1;
            
            switch ($event['recurrence_type']) {
                case 'daily':
                    $nextStart->modify("+{$interval} day");
                    break;
                case 'weekly':
                    $nextStart->modify("+{$interval} week");
                    break;
                case 'monthly':
                    $nextStart->modify("+{$interval} month");
                    break;
                case 'yearly':
                    $nextStart->modify("+{$interval} year");
                    break;
            }
            
            // Create the second part (continuation)
            // Only if the next start is before the original recurrence end (if set)
            $shouldCreateNext = true;
            if (!empty($event['recurrence_end'])) {
                $originalEnd = new \DateTime($event['recurrence_end']);
                if ($nextStart > $originalEnd) {
                    $shouldCreateNext = false;
                }
            }
            
            if ($shouldCreateNext) {
                // We need to calculate the end time for the new event
                // Duration of the original event
                $origStart = new \DateTime($event['start_at']);
                $origEnd = new \DateTime($event['end_at']);
                $duration = $origStart->diff($origEnd);
                
                $nextEnd = clone $nextStart;
                $nextEnd->add($duration);
                
                CalendarEvent::create([
                    'user_id' => $user['id'],
                    'title' => $event['title'],
                    'description' => $event['description'],
                    'color' => $event['color'],
                    'start_at' => $nextStart->format('Y-m-d H:i:s'),
                    'end_at' => $nextEnd->format('Y-m-d H:i:s'),
                    'is_all_day' => $event['is_all_day'],
                    'recurrence_type' => $event['recurrence_type'],
                    'recurrence_interval' => $event['recurrence_interval'],
                    'recurrence_end' => $event['recurrence_end']
                ]);
            }
            
            // Update the original event to end before this instance
            $endDate = clone $instanceDate;
            $endDate->modify('-1 day');
            
            $success = CalendarEvent::update($eventId, [
                'recurrence_end' => $endDate->format('Y-m-d')
            ]);
            
            if (!$success) {
                $this->json(['error' => 'Failed to update event'], 500);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Single occurrence deleted successfully'
            ]);
            return;
        }
        
        $this->json(['error' => 'Invalid delete mode'], 400);
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
    
    /**
     * Generate iCal feed
     */
    public function feed(array $params): void
    {
        $token = $params['token'] ?? '';
        
        if (empty($token)) {
            http_response_code(404);
            die('Calendar not found');
        }
        
        $user = User::findByIcalToken($token);
        
        if (!$user) {
            http_response_code(404);
            die('Calendar not found');
        }
        
        // Fetch events (past 30 days and all future)
        $start = date('Y-m-d', strtotime('-30 days'));
        $end = date('Y-m-d', strtotime('+2 years'));
        
        // Use shared fetch methods to match web view
        $events = CalendarEvent::getAllByDateRange($start, $end);
        $tasks = Task::getSharedAndUserByDateRange($user['id'], $start, $end);
        
        // Start iCal output
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="calendar.ics"');
        
        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//IrmaJosh//Calendar//EN\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "X-WR-CALNAME:" . $this->escapeIcs($user['name'] . "'s Calendar") . "\r\n";
        echo "X-WR-TIMEZONE:UTC\r\n";
        
        // Output events
        foreach ($events as $event) {
            $this->outputIcsEvent($event);
        }
        
        // Output tasks as events
        foreach ($tasks as $task) {
            $this->outputIcsTask($task);
        }
        
        echo "END:VCALENDAR\r\n";
        exit;
    }
    
    /**
     * Refresh iCal token
     */
    public function refreshFeedToken(): void
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        $newToken = User::refreshIcalToken($user['id']);
        
        $this->json([
            'success' => true,
            'token' => $newToken,
            'url' => \env('APP_URL') . '/calendar/feed/' . $newToken
        ]);
    }
    
    /**
     * Search events and tasks
     */
    public function search(): void
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            $this->json([]);
        }
        
        $events = CalendarEvent::search($query, $user['id']);
        $tasks = Task::search($query, $user['id']);
        
        // Format results
        $results = [];
        
        foreach ($events as $event) {
            $results[] = [
                'id' => $event['id'],
                'title' => $event['title'],
                'date' => $event['start_at'],
                'type' => 'event',
                'color' => $event['color'] ?? '#3b82f6'
            ];
        }
        
        foreach ($tasks as $task) {
            $results[] = [
                'id' => 'task_' . $task['id'],
                'title' => $task['title'],
                'date' => $task['due_date'],
                'type' => 'task',
                'status' => $task['status'],
                'is_shared' => (bool)$task['is_shared']
            ];
        }
        
        // Sort by date descending
        usort($results, function($a, $b) {
            // Handle null dates (tasks without due date)
            if (!$a['date']) return 1;
            if (!$b['date']) return -1;
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        $this->json($results);
    }
    
    /**
     * Helper to output ICS event
     */
    private function outputIcsEvent(array $event): void
    {
        $dtStart = date('Ymd\THis\Z', strtotime($event['start_at']));
        $dtEnd = date('Ymd\THis\Z', strtotime($event['end_at']));
        $now = date('Ymd\THis\Z');
        $uid = 'event-' . $event['id'] . '@irmajosh.com';
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:{$now}\r\n";
        echo "DTSTART:{$dtStart}\r\n";
        echo "DTEND:{$dtEnd}\r\n";
        echo "SUMMARY:" . $this->escapeIcs($event['title']) . "\r\n";
        
        if (!empty($event['description'])) {
            echo "DESCRIPTION:" . $this->escapeIcs($event['description']) . "\r\n";
        }
        
        // Handle recurrence
        if (!empty($event['recurrence_type'])) {
            $rrule = "FREQ=" . strtoupper($event['recurrence_type']);
            
            if (!empty($event['recurrence_interval']) && $event['recurrence_interval'] > 1) {
                $rrule .= ";INTERVAL=" . $event['recurrence_interval'];
            }
            
            if (!empty($event['recurrence_end'])) {
                $until = date('Ymd\THis\Z', strtotime($event['recurrence_end'] . ' 23:59:59'));
                $rrule .= ";UNTIL=" . $until;
            }
            
            echo "RRULE:{$rrule}\r\n";
        }
        
        echo "END:VEVENT\r\n";
    }
    
    /**
     * Helper to output Task as ICS event
     */
    private function outputIcsTask(array $task): void
    {
        if (empty($task['due_date'])) {
            return;
        }
        
        $dtStart = date('Ymd\THis\Z', strtotime($task['due_date']));
        // Tasks are point-in-time, but for calendar view let's give them 1 hour duration or 0
        // Better to make them 0 duration or 30 mins
        $dtEnd = date('Ymd\THis\Z', strtotime($task['due_date'] . ' +30 minutes'));
        $now = date('Ymd\THis\Z');
        $uid = 'task-' . $task['id'] . '@irmajosh.com';
        
        $status = $task['status'] === 'completed' ? '✓ ' : '☐ ';
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:{$now}\r\n";
        echo "DTSTART:{$dtStart}\r\n";
        echo "DTEND:{$dtEnd}\r\n";
        echo "SUMMARY:" . $this->escapeIcs($status . $task['title']) . "\r\n";
        
        $description = "Status: " . ucfirst($task['status']);
        if (!empty($task['description'])) {
            $description .= "\n\n" . $task['description'];
        }
        echo "DESCRIPTION:" . $this->escapeIcs($description) . "\r\n";
        
        echo "END:VEVENT\r\n";
    }
    
    /**
     * Escape text for ICS format
     */
    private function escapeIcs(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\n', $text);
        return $text;
    }
}
