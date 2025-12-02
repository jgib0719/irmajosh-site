<?php
require 'src/bootstrap.php';
use App\Models\CalendarEvent;

// Simulate FullCalendar date format
$start = '2025-12-01T00:00:00-05:00';
$end = '2026-01-01T00:00:00-05:00';

echo "Testing with ISO dates:\n";
try {
    $events = CalendarEvent::getAllByDateRange($start, $end);
    echo "Events found: " . count($events) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test with MySQL format
$start = '2025-12-01 00:00:00';
$end = '2026-01-01 00:00:00';

echo "Testing with MySQL dates:\n";
try {
    $events = CalendarEvent::getAllByDateRange($start, $end);
    echo "Events found: " . count($events) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
