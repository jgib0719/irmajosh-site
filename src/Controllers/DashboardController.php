<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;

/**
 * DashboardController
 * 
 * Handles dashboard view with overview of tasks
 */
class DashboardController extends BaseController
{
    /**
     * Show dashboard
     */
    public function index(): void
    {
        try {
            \logMessage('Dashboard index called', 'DEBUG');
            $this->requireAuth();
            
            \logMessage('Auth check passed', 'DEBUG');
            $user = $this->getCurrentUser();
            
            if (!$user) {
                logout();
                $this->redirect('/');
                return;
            }
            
            \logMessage('User loaded: ' . $user['id'], 'DEBUG');
            $userId = $user['id'];
            
            // Get recent tasks (pending and in-progress)
            \logMessage('Fetching pending tasks', 'DEBUG');
            $recentTasks = array_slice(Task::getByStatus($userId, 'pending'), 0, 5);
            \logMessage('Fetched ' . count($recentTasks) . ' pending tasks', 'DEBUG');
            
            \logMessage('Fetching in-progress tasks', 'DEBUG');
            $inProgressTasks = array_slice(Task::getByStatus($userId, 'in_progress'), 0, 5);
            \logMessage('Fetched ' . count($inProgressTasks) . ' in-progress tasks', 'DEBUG');
            
            \logMessage('Rendering dashboard view', 'DEBUG');
            $this->view('dashboard', [
                'pageTitle' => \t('dashboard') . ' - ' . \env('APP_NAME'),
                'user' => $user,
                'recentTasks' => $recentTasks,
                'inProgressTasks' => $inProgressTasks,
            ]);
        } catch (\Throwable $e) {
            \logMessage('DASHBOARD ERROR: ' . $e->getMessage(), 'ERROR');
            \logMessage('DASHBOARD STACK: ' . $e->getTraceAsString(), 'ERROR');
            \logMessage('DASHBOARD FILE: ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
            throw $e;
        }
    }
}
