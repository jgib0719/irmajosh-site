<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use App\Models\ScheduleRequest;

/**
 * DashboardController
 * 
 * Handles dashboard view with overview of tasks and schedule requests
 */
class DashboardController extends BaseController
{
    /**
     * Show dashboard
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $userId = $user['id'];
        
        // Get recent tasks (pending and in-progress)
        $recentTasks = array_slice(Task::getByStatus($userId, 'pending'), 0, 5);
        $inProgressTasks = array_slice(Task::getByStatus($userId, 'in_progress'), 0, 5);
        
        // Get actionable schedule requests (not yet scheduled to calendar)
        $scheduleRequests = array_slice(ScheduleRequest::getActionable($userId), 0, 5);
        
        $this->view('dashboard', [
            'pageTitle' => \t('dashboard') . ' - ' . \env('APP_NAME'),
            'user' => $user,
            'recentTasks' => $recentTasks,
            'inProgressTasks' => $inProgressTasks,
            'scheduleRequests' => $scheduleRequests,
        ]);
    }
}
