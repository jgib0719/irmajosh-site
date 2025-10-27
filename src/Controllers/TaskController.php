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
            'pageTitle' => t('shared_tasks') . ' - ' . env('APP_NAME'),
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
            'pageTitle' => t('private_tasks') . ' - ' . env('APP_NAME'),
            'user' => $user,
            'tasks' => $tasks,
        ]);
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
            'title' => sanitizeInput($this->getPost('title')),
            'description' => sanitizeInput($this->getPost('description', '')),
            'is_shared' => $isShared,
            'status' => $status,
            'due_date' => $this->getPost('due_date'),
        ];
        
        try {
            $task = Task::create($taskData);
            
            // Send push notification if task is shared
            if ($isShared) {
                try {
                    $notificationService = new NotificationService(db());
                    $notificationService->notifyTaskCreated($user['id'], $task);
                } catch (\Exception $e) {
                    // Log notification failure but don't fail the task creation
                    logMessage("Failed to send task notification: " . $e->getMessage(), 'WARNING');
                }
            }
        } catch (\Exception $e) {
            logMessage("Failed to create task: " . $e->getMessage(), 'ERROR');
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
            $taskData['title'] = sanitizeInput($this->getPost('title'));
        }
        
        if ($this->getPost('description') !== null) {
            $taskData['description'] = sanitizeInput($this->getPost('description'));
        }
        
        // Validate status if provided
        if ($this->getPost('status')) {
            $status = $this->getPost('status');
            if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
                $this->json(['error' => 'Invalid status value'], 400);
            }
            $taskData['status'] = $status;
        }
        
        if ($this->getPost('due_date') !== null) {
            $taskData['due_date'] = $this->getPost('due_date');
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
