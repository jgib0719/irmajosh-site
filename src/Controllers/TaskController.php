<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;

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
            if ($this->isHtmx()) {
                $this->json(['error' => 'Missing required fields'], 400);
            }
            
            $this->setFlash('error', 'Missing required fields');
            $this->redirect('/tasks/shared');
        }
        
        // Convert is_shared to boolean
        $isShared = $this->getPost('is_shared') === '1' || $this->getPost('is_shared') === 1 || $this->getPost('is_shared') === true;
        
        $taskData = [
            'user_id' => $user['id'],
            'title' => sanitizeInput($this->getPost('title')),
            'description' => sanitizeInput($this->getPost('description', '')),
            'is_shared' => $isShared,
            'status' => $this->getPost('status', 'pending'),
            'due_date' => $this->getPost('due_date'),
        ];
        
        try {
            $task = Task::create($taskData);
        } catch (\Exception $e) {
            logMessage("Failed to create task: " . $e->getMessage(), 'ERROR');
            
            if ($this->isHtmx()) {
                $this->json(['error' => 'Failed to create task: ' . $e->getMessage()], 500);
            }
            
            $this->setFlash('error', 'Failed to create task');
            $this->redirect('/tasks/shared');
        }
        
        if ($this->isHtmx()) {
            $this->json([
                'success' => true,
                'task' => $task,
                'message' => 'Task created successfully'
            ]);
        }
        
        $this->setFlash('success', 'Task created successfully');
        $redirectType = $isShared ? 'shared' : 'private';
        $this->redirect('/tasks/' . $redirectType);
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
        
        if ($this->getPost('status')) {
            $taskData['status'] = $this->getPost('status');
        }
        
        if ($this->getPost('due_date') !== null) {
            $taskData['due_date'] = $this->getPost('due_date');
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
