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
        
        // Get upcoming tasks
        $upcomingTasks = Task::getUpcoming($userId, 7);
        
        // Get overdue tasks
        $overdueTasks = Task::getOverdue($userId);
        
        // Get recent schedule requests
        $scheduleRequests = array_slice(ScheduleRequest::getByUser($userId), 0, 5);
        
        $this->view('dashboard', [
            'pageTitle' => t('dashboard') . ' - ' . env('APP_NAME'),
            'user' => $user,
            'upcomingTasks' => $upcomingTasks,
            'overdueTasks' => $overdueTasks,
            'scheduleRequests' => $scheduleRequests,
        ]);
    }
}
