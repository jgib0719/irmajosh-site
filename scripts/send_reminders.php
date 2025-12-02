<?php
/**
 * Send Reminders Script
 * 
 * Checks for upcoming events and sends push notifications.
 * Intended to be run via cron every 5 minutes.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Models\User;
use App\Models\CalendarEvent;
use App\Services\NotificationService;

// Configuration
$reminderWindowMinutes = 15;
$now = new DateTime();
$windowEnd = (clone $now)->modify("+{$reminderWindowMinutes} minutes");

echo "--- Starting Reminder Check: " . $now->format('Y-m-d H:i:s') . " ---\n";

// Initialize services
$db = db();
$notificationService = new NotificationService($db);

// Get all users
$users = User::all();

foreach ($users as $user) {
    echo "Checking user: {$user['email']} (ID: {$user['id']})\n";
    
    // Get events for today (covers the 15 min window)
    // We fetch a bit wider range to handle edge cases around midnight
    $start = (clone $now)->modify('-1 hour')->format('Y-m-d H:i:s');
    $end = (clone $now)->modify('+2 hours')->format('Y-m-d H:i:s');
    
    $events = CalendarEvent::getByUserAndDateRange((int)$user['id'], $start, $end);
    
    foreach ($events as $event) {
        $eventStart = new DateTime($event['start_at']);
        
        // Check if event is within the reminder window (now <= start <= windowEnd)
        // And hasn't already started (optional, but good for "upcoming")
        // Actually, if it started 1 min ago, we might still want to remind if we missed it?
        // Let's stick to: start_time is in the future, but less than 15 mins away.
        
        if ($eventStart > $now && $eventStart <= $windowEnd) {
            $eventId = (int)($event['parent_event_id'] ?? $event['id']);
            $occurrenceStart = $event['start_at'];
            
            // Check if reminder already sent
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM event_reminders 
                WHERE event_id = ? AND occurrence_start = ?
            ");
            $stmt->execute([$eventId, $occurrenceStart]);
            
            if ($stmt->fetchColumn() == 0) {
                // Send reminder
                echo "  -> Sending reminder for: {$event['title']} at {$occurrenceStart}\n";
                
                try {
                    $notificationService->sendToUser(
                        (int)$user['id'],
                        'Upcoming Event',
                        "Starting in " . $reminderWindowMinutes . " mins: " . $event['title'],
                        [
                            'type' => 'calendar_event',
                            'eventId' => $eventId,
                            'url' => '/calendar'
                        ]
                    );
                    
                    // Mark as sent
                    $insert = $db->prepare("
                        INSERT INTO event_reminders (event_id, occurrence_start, sent_at)
                        VALUES (?, ?, NOW())
                    ");
                    $insert->execute([$eventId, $occurrenceStart]);
                    
                    echo "     [OK] Sent and recorded.\n";
                    
                } catch (Exception $e) {
                    echo "     [ERROR] Failed to send: " . $e->getMessage() . "\n";
                }
            } else {
                // echo "  -> Reminder already sent for: {$event['title']}\n";
            }
        }
    }
}

echo "--- Done ---\n";
