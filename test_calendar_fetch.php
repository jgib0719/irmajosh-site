<?php
require_once __DIR__ . '/src/bootstrap.php';

use App\Models\CalendarEvent;
use App\Models\Task;

// Simulate a date range
$start = date('Y-m-d', strtotime('-1 month'));
$end = date('Y-m-d', strtotime('+1 month'));

echo "Testing CalendarEvent::getAllByDateRange($start, $end)...\n";
$events = CalendarEvent::getAllByDateRange($start, $end);
echo "Found " . count($events) . " events.\n";
foreach ($events as $event) {
    echo "- [{$event['id']}] {$event['title']} ({$event['start_at']}) User: {$event['user_id']}\n";
}

echo "\nTesting Task::getSharedAndUserByDateRange(1, $start, $end)...\n";
// Assuming user ID 1 exists
$tasks = Task::getSharedAndUserByDateRange(1, $start, $end);
echo "Found " . count($tasks) . " tasks.\n";
foreach ($tasks as $task) {
    echo "- [{$task['id']}] {$task['title']} (Shared: {$task['is_shared']}) User: {$task['user_id']}\n";
}
