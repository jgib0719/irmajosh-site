<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    require '../src/bootstrap.php';
    echo "Bootstrap OK\n";
    
    $_SESSION['user_id'] = 1;
    echo "Session set\n";
    
    $user = \App\Models\User::find(1);
    echo "User loaded\n";
    
    $tasks = \App\Models\Task::getByStatus(1, 'pending');
    echo "Tasks loaded: " . count($tasks) . "\n";
    
    echo "SUCCESS - No errors!";
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "TRACE:\n" . $e->getTraceAsString() . "\n";
}
