<?php
/**
 * Private Tasks View
 * 
 * @var array $user - Current user
 * @var array $tasks - Private tasks array
 */

ob_start();
?>

<div class="tasks-page">
    <div class="page-header">
        <h1 class="page-title"><?= t('tasks') ?></h1>
    </div>
    
    <!-- Task Tabs -->
    <div class="tabs">
        <a href="/tasks/shared" class="tab">
            Shared Tasks
        </a>
        <a href="/tasks/private" class="tab active">
            My Tasks
        </a>
    </div>
    
    <div class="tasks-container">
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <p><?= t('no_tasks_yet') ?></p>
                <button class="btn btn-primary" onclick="openModal('addTaskModal')">
                    Create Your First Task
                </button>
            </div>
        <?php else: ?>
            <div class="tasks-list" id="privateTasksList">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item <?= $task['completed'] ? 'completed' : '' ?>" data-task-id="<?= $task['id'] ?>">
                        <div class="task-checkbox">
                            <input type="checkbox" 
                                   id="task-<?= $task['id'] ?>" 
                                   <?= $task['completed'] ? 'checked' : '' ?>
                                   onchange="toggleTaskComplete(<?= $task['id'] ?>, this.checked)">
                        </div>
                        <div class="task-content">
                            <label for="task-<?= $task['id'] ?>" class="task-title">
                                <?= htmlspecialchars($task['title']) ?>
                            </label>
                            <?php if (!empty($task['description'])): ?>
                                <p class="task-description"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                            <?php endif; ?>
                            <?php if ($task['due_date']): ?>
                                <span class="task-due-date <?= strtotime($task['due_date']) < time() && !$task['completed'] ? 'overdue' : '' ?>">
                                    Due: <?= date('M j, Y g:i A', strtotime($task['due_date'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="task-actions">
                            <button class="btn-icon" onclick="editTask(<?= htmlspecialchars(json_encode($task)) ?>)" title="Edit">
                                ✏️
                            </button>
                            <button class="btn-icon" onclick="deleteTask(<?= $task['id'] ?>)" title="Delete">
                                🗑️
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Task Modal -->
    <div class="modal" id="addTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Private Task</h2>
                <button class="modal-close" onclick="closeModal('addTaskModal')">&times;</button>
            </div>
            <form id="addTaskForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="type" value="private">
                
                <div class="form-group">
                    <label for="taskTitle">Title *</label>
                    <input type="text" id="taskTitle" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="taskDueDate">Due Date</label>
                    <input type="datetime-local" id="taskDueDate" name="due_date" class="form-control">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Task Modal -->
    <div class="modal" id="editTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Task</h2>
                <button class="modal-close" onclick="closeModal('editTaskModal')">&times;</button>
            </div>
            <form id="editTaskForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="task_id" id="editTaskId">
                <input type="hidden" name="type" value="private">
                
                <div class="form-group">
                    <label for="editTaskTitle">Title *</label>
                    <input type="text" id="editTaskTitle" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editTaskDescription">Description</label>
                    <textarea id="editTaskDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editTaskDueDate">Due Date</label>
                    <input type="datetime-local" id="editTaskDueDate" name="due_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editTaskCompleted" name="completed">
                        Mark as completed
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
// Add task form submission
document.getElementById('addTaskForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/tasks', {
            credentials: 'same-origin',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': data.csrf_token
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            closeModal('addTaskModal');
            showAlert('Task created successfully', 'success');
            this.reset();
            window.location.reload();
        } else {
            showAlert(result.error || 'Failed to create task', 'error');
        }
    } catch (error) {
        showAlert('Failed to create task', 'error');
    }
});

// Edit task form submission
document.getElementById('editTaskForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    const taskId = data.task_id;
    delete data.task_id;
    
    try {
        const response = await fetch(`/tasks/${taskId}`, {
            credentials: 'same-origin',
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': data.csrf_token
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            closeModal('editTaskModal');
            showAlert('Task updated successfully', 'success');
            window.location.reload();
        } else {
            showAlert(result.error || 'Failed to update task', 'error');
        }
    } catch (error) {
        showAlert('Failed to update task', 'error');
    }
});

// Modal functions are now in modal.js (loaded globally)

function editTask(task) {
    document.getElementById('editTaskId').value = task.id;
    document.getElementById('editTaskTitle').value = task.title;
    document.getElementById('editTaskDescription').value = task.description || '';
    
    if (task.due_date) {
        const dueDate = new Date(task.due_date);
        document.getElementById('editTaskDueDate').value = dueDate.toISOString().slice(0, 16);
    } else {
        document.getElementById('editTaskDueDate').value = '';
    }
    
    document.getElementById('editTaskCompleted').checked = task.completed;
    
    openModal('editTaskModal');
}

async function toggleTaskComplete(taskId, completed) {
    try {
        const response = await fetch(`/tasks/${taskId}`, {
            credentials: 'same-origin',
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ completed })
        });
        
        if (response.ok) {
            const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
            if (completed) {
                taskEl.classList.add('completed');
            } else {
                taskEl.classList.remove('completed');
            }
        } else {
            showAlert('Failed to update task', 'error');
            // Revert checkbox
            const checkbox = document.querySelector(`#task-${taskId}`);
            checkbox.checked = !completed;
        }
    } catch (error) {
        showAlert('Failed to update task', 'error');
        // Revert checkbox
        const checkbox = document.querySelector(`#task-${taskId}`);
        checkbox.checked = !completed;
    }
}

async function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }
    
    try {
        const response = await fetch(`/tasks/${taskId}`, {
            credentials: 'same-origin',
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (response.ok) {
            showAlert('Task deleted successfully', 'success');
            const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
            taskEl.remove();
        } else {
            showAlert('Failed to delete task', 'error');
        }
    } catch (error) {
        showAlert('Failed to delete task', 'error');
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
