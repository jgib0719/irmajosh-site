<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use App\Services\NotificationService;

/**
 * TaskController
 * 
 * Handles task CRUD operations with ownership validation
 */
class TaskController extends BaseController
{
    /**
     * Show shared tasks
     */
    public function shared(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $tasks = Task::getByUser($user['id'], 'shared');
        
        $this->view('tasks-shared', [
            'pageTitle' => \t('shared_tasks') . ' - ' . \env('APP_NAME'),
            'user' => $user,
            'tasks' => $tasks,
        ]);
    }
    
    /**
     * Show private tasks
     */
    public function private(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $tasks = Task::getByUser($user['id'], 'private');
        
        $this->view('tasks-private', [
            'pageTitle' => \t('private_tasks') . ' - ' . \env('APP_NAME'),
            'user' => $user,
            'tasks' => $tasks,
        ]);
    }
    
    /**
     * Get a single task by ID (JSON)
     */
    public function get(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $taskId = (int)($params['id'] ?? 0);
        
        if (!$taskId) {
            $this->json(['error' => 'Task ID required'], 400);
        }
        
        // Check ownership
        if (!Task::userOwns($taskId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $task = Task::find($taskId);
        
        if (!$task) {
            $this->json(['error' => 'Task not found'], 404);
        }
        
        // Add completed boolean for easier frontend handling
        $task['completed'] = ($task['status'] === 'completed');
        
        $this->json($task);
    }
    
    /**
     * Create a task
     */
    public function create(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        $required = ['title'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->json(['error' => 'Missing required fields'], 400);
        }
        
        // Validate and sanitize is_shared input
        $isShared = false;
        if (isset($_POST['is_shared'])) {
            $isSharedRaw = $_POST['is_shared'];
            if (in_array($isSharedRaw, ['1', '0', 1, 0, true, false], true)) {
                $isShared = in_array($isSharedRaw, ['1', 1, true], true);
            } else {
                if ($this->isHtmx()) {
                    $this->json(['error' => 'Invalid is_shared value'], 400);
                }
                $this->setFlash('error', 'Invalid input');
                $this->redirect('/tasks/shared');
            }
        }
        
        // Validate status if provided
        $status = $this->getPost('status', 'pending');
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
            $status = 'pending';
        }
        
        $taskData = [
            'user_id' => $user['id'],
            'title' => \sanitizeInput($this->getPost('title')),
            'description' => \sanitizeInput($this->getPost('description', '')),
            'is_shared' => $isShared ? 1 : 0,
            'status' => $status,
        ];
        
        try {
            $task = Task::create($taskData);
            
            // Send notifications if task is shared
            if ($isShared) {
                try {
                    // Push notification
                    $notificationService = new NotificationService(\db());
                    $notificationService->notifyTaskCreated($user['id'], $task);
                    
                    // Email notifications
                    $emailService = new \App\Services\EmailService();
                    if ($emailService->isConfigured()) {
                        // Send to user
                        $emailService->sendNotification(
                            $user['email'],
                            'New Shared Task Created',
                            "You created a new shared task:\n\n" .
                            "Title: {$task['title']}\n" .
                            (!empty($task['description']) ? "Description: {$task['description']}\n" : '') .
                            "\nView it at: " . \env('APP_URL') . "/tasks/shared"
                        );
                        
                        // Send to admins
                        $emailService->sendAdminNotification(
                            'Shared Task Created',
                            "User: {$user['name']} ({$user['email']})\n\n" .
                            "Title: {$task['title']}\n" .
                            (!empty($task['description']) ? "Description: {$task['description']}\n" : '') .
                            "\nView it at: " . \env('APP_URL') . "/tasks/shared"
                        );
                    }
                } catch (\Exception $e) {
                    // Log notification failure but don't fail the task creation
                    \logMessage("Failed to send task notification: " . $e->getMessage(), 'WARNING');
                }
            }
        } catch (\Exception $e) {
            \logMessage("Failed to create task: " . $e->getMessage(), 'ERROR');
            $this->json(['error' => 'Failed to create task'], 500);
        }
        
        $this->json([
            'success' => true,
            'task' => $task,
            'message' => 'Task created successfully'
        ]);
    }
    
    /**
     * Update a task
     */
    public function update(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $taskId = (int)($params['id'] ?? 0);
        
        if (!$taskId) {
            $this->json(['error' => 'Task ID required'], 400);
        }
        
        // Check ownership
        if (!Task::userOwns($taskId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $taskData = [];
        
        if ($this->getPost('title')) {
            $taskData['title'] = \sanitizeInput($this->getPost('title'));
        }
        
        if ($this->getPost('description') !== null) {
            $taskData['description'] = \sanitizeInput($this->getPost('description'));
        }
        
        // Handle completed toggle (from checkbox)
        if ($this->getPost('completed') !== null) {
            $completed = $this->getPost('completed');
            $taskData['status'] = $completed ? 'completed' : 'pending';
        }
        // Validate status if provided
        else if ($this->getPost('status')) {
            $status = $this->getPost('status');
            if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
                $this->json(['error' => 'Invalid status value'], 400);
            }
            $taskData['status'] = $status;
        }
        
        if (empty($taskData)) {
            $this->json(['error' => 'No data to update'], 400);
        }
        
        $success = Task::update($taskId, $taskData);
        
        if (!$success) {
            $this->json(['error' => 'Failed to update task'], 500);
        }
        
        $task = Task::find($taskId);
        
        $this->json([
            'success' => true,
            'task' => $task,
            'message' => 'Task updated successfully'
        ]);
    }
    
    /**
     * Delete a task
     */
    public function delete(array $params): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $taskId = (int)($params['id'] ?? 0);
        
        if (!$taskId) {
            $this->json(['error' => 'Task ID required'], 400);
        }
        
        // Check ownership
        if (!Task::userOwns($taskId, $user['id'])) {
            $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $success = Task::delete($taskId);
        
        if (!$success) {
            $this->json(['error' => 'Failed to delete task'], 500);
        }
        
        $this->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    }
    
    /**
     * Get tasks (API endpoint)
     */
    public function getTasks(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $type = $this->getQuery('type');
        $status = $this->getQuery('status');
        
        if ($status) {
            $tasks = Task::getByStatus($user['id'], $status);
        } elseif ($type) {
            $tasks = Task::getByUser($user['id'], $type);
        } else {
            $tasks = Task::getByUser($user['id']);
        }
        
        $this->json($tasks);
    }
}
