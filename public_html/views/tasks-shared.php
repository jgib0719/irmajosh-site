<?php
/**
 * Shared Tasks View
 * 
 * @var array $user - Current user
 * @var array $tasks - Shared tasks array
 */

ob_start();
?>

<div class="tasks-page">
    <div class="page-header">
        <h1 class="page-title"><?= t('shared_tasks') ?></h1>
        <div class="page-actions">
            <button class="btn btn-primary" id="openAddTaskModal">
                <span>+</span> Add Task
            </button>
        </div>
    </div>
    
    <div class="tasks-container">
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <p><?= t('no_tasks_yet') ?></p>
                <button class="btn btn-primary" id="openAddTaskModalEmpty">
                    Create Your First Task
                </button>
            </div>
        <?php else: ?>
            <div class="tasks-list" id="sharedTasksList">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item <?= $task['status'] === 'completed' ? 'completed' : '' ?>" data-task-id="<?= $task['id'] ?>">
                        <div class="task-checkbox">
                            <input type="checkbox" 
                                   id="task-<?= $task['id'] ?>" 
                                   class="task-complete-checkbox"
                                   data-task-id="<?= $task['id'] ?>"
                                   <?= $task['status'] === 'completed' ? 'checked' : '' ?>>
                        </div>
                        <div class="task-content">
                            <label for="task-<?= $task['id'] ?>" class="task-title">
                                <?= htmlspecialchars($task['title']) ?>
                            </label>
                            <?php if (!empty($task['description'])): ?>
                                <p class="task-description"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                            <?php endif; ?>
                            <?php if ($task['due_date']): ?>
                                <span class="task-due-date <?= strtotime($task['due_date']) < time() && $task['status'] !== 'completed' ? 'overdue' : '' ?>">
                                    Due: <?= date('M j, Y g:i A', strtotime($task['due_date'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="task-actions">
                            <button class="btn-icon task-edit-btn" data-task='<?= htmlspecialchars(json_encode($task)) ?>' title="Edit">
                                ✏️
                            </button>
                            <button class="btn-icon task-delete-btn" data-task-id="<?= $task['id'] ?>" title="Delete">
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
                <h2 class="modal-title">Add Shared Task</h2>
                <button class="modal-close" data-close-modal="addTaskModal">&times;</button>
            </div>
            <form id="addTaskForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="is_shared" value="1">
                
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
                    <button type="button" class="btn btn-secondary" data-close-modal="addTaskModal">Cancel</button>
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
                <button class="modal-close" data-close-modal="editTaskModal">&times;</button>
            </div>
            <form id="editTaskForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="task_id" id="editTaskId">
                <input type="hidden" name="is_shared" value="1">
                
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
                    <button type="button" class="btn btn-secondary" data-close-modal="editTaskModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
    // Open add task modal buttons
    const openAddTaskBtns = [
        document.getElementById('openAddTaskModal'),
        document.getElementById('openAddTaskModalEmpty')
    ];
    
    openAddTaskBtns.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', () => openModal('addTaskModal'));
        }
    });
    
    // Close modal buttons
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.getAttribute('data-close-modal'));
        });
    });
    
    // Task edit buttons
    document.querySelectorAll('.task-edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const task = JSON.parse(this.getAttribute('data-task'));
            editTask(task);
        });
    });
    
    // Task delete buttons
    document.querySelectorAll('.task-delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteTask(parseInt(this.getAttribute('data-task-id')));
        });
    });
    
    // Task complete checkboxes
    document.querySelectorAll('.task-complete-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleTaskComplete(parseInt(this.getAttribute('data-task-id')), this.checked);
        });
    });
});

// Add task form submission
document.getElementById('addTaskForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/tasks', {
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
    
    // Convert checkbox to status
    if (data.completed === 'on') {
        data.status = 'completed';
    }
    delete data.completed;
    
    try {
        const response = await fetch(`/tasks/${taskId}`, {
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

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

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
    
    document.getElementById('editTaskCompleted').checked = task.status === 'completed';
    
    openModal('editTaskModal');
}

async function toggleTaskComplete(taskId, completed) {
    const status = completed ? 'completed' : 'pending';
    
    try {
        const response = await fetch(`/tasks/${taskId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ status })
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
