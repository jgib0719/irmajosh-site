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
        <button class="tab-add-btn" id="openAddTaskModal">
            + Add Task
        </button>
    </div>
    
    <div class="tasks-container">
        <?php 
        // Separate active and completed tasks
        $activeTasks = array_filter($tasks, fn($task) => $task['status'] !== 'completed');
        $completedTasks = array_filter($tasks, fn($task) => $task['status'] === 'completed');
        ?>
        
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <p><?= t('no_tasks_yet') ?></p>
                <button class="btn btn-primary" id="createFirstTaskBtn">
                    Create Your First Task
                </button>
            </div>
        <?php else: ?>
            <!-- Active Tasks -->
            <?php if (empty($activeTasks)): ?>
                <div class="empty-state">
                    <p>No active tasks</p>
                </div>
            <?php else: ?>
                <div class="tasks-list" id="privateTasksList">
                    <?php foreach ($activeTasks as $task): ?>
                        <div class="task-item" data-task-id="<?= $task['id'] ?>">
                            <div class="task-checkbox">
                                <input type="checkbox" 
                                       id="task-<?= $task['id'] ?>" 
                                       data-task-id="<?= $task['id'] ?>"
                                       class="task-complete-checkbox">
                            </div>
                            <div class="task-content">
                                <label for="task-<?= $task['id'] ?>" class="task-title">
                                    <?= htmlspecialchars($task['title']) ?>
                                </label>
                                <?php if (!empty($task['description'])): ?>
                                    <p class="task-description"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="task-actions">
                                <button class="btn-icon edit-task-btn" data-task='<?= htmlspecialchars(json_encode($task)) ?>' title="Edit">
                                    ✏️
                                </button>
                                <button class="btn-icon delete-task-btn" data-task-id="<?= $task['id'] ?>" title="Delete">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Completed Tasks Section -->
            <?php if (!empty($completedTasks)): ?>
                <div class="completed-tasks-section">
                    <div class="completed-tasks-header" onclick="toggleCompletedTasks()">
                        <h3>Completed Tasks (<?= count($completedTasks) ?>)</h3>
                        <span class="completed-tasks-toggle" id="completedToggle">▼</span>
                    </div>
                    <div class="completed-tasks-list" id="completedTasksList">
                        <?php foreach ($completedTasks as $task): ?>
                            <div class="task-item completed" data-task-id="<?= $task['id'] ?>">
                                <div class="task-checkbox">
                                    <input type="checkbox" 
                                           id="task-<?= $task['id'] ?>" 
                                           data-task-id="<?= $task['id'] ?>"
                                           class="task-complete-checkbox"
                                           checked>
                                </div>
                                <div class="task-content">
                                    <label for="task-<?= $task['id'] ?>" class="task-title">
                                        <?= htmlspecialchars($task['title']) ?>
                                    </label>
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="task-description"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="task-actions">
                                    <button class="btn-icon edit-task-btn" data-task='<?= htmlspecialchars(json_encode($task)) ?>' title="Edit">
                                        ✏️
                                    </button>
                                    <button class="btn-icon delete-task-btn" data-task-id="<?= $task['id'] ?>" title="Delete">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Add Task Modal -->
    <div class="modal" id="addTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Private Task</h2>
                <button class="modal-close" data-modal="addTaskModal">&times;</button>
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
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal="addTaskModal" data-action="close">Cancel</button>
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
                <button class="modal-close" data-modal="editTaskModal">&times;</button>
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
                    <label>
                        <input type="checkbox" id="editTaskCompleted" name="completed">
                        Mark as completed
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal="editTaskModal" data-action="close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
// Toggle completed tasks section
function toggleCompletedTasks() {
    const list = document.getElementById('completedTasksList');
    const toggle = document.getElementById('completedToggle');
    
    if (list && toggle) {
        list.classList.toggle('collapsed');
        toggle.classList.toggle('collapsed');
    }
}

// DOM event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add task buttons
    const openAddTaskBtn = document.getElementById('openAddTaskModal');
    const createFirstTaskBtn = document.getElementById('createFirstTaskBtn');
    
    if (openAddTaskBtn) {
        openAddTaskBtn.addEventListener('click', () => openModal('addTaskModal'));
    }
    
    if (createFirstTaskBtn) {
        createFirstTaskBtn.addEventListener('click', () => openModal('addTaskModal'));
    }
    
    // Task completion checkboxes
    document.querySelectorAll('.task-complete-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const taskId = parseInt(this.dataset.taskId);
            toggleTaskComplete(taskId, this.checked);
        });
    });
    
    // Edit task buttons
    document.querySelectorAll('.edit-task-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskData = JSON.parse(this.dataset.task);
            editTask(taskData);
        });
    });
    
    // Delete task buttons
    document.querySelectorAll('.delete-task-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = parseInt(this.dataset.taskId);
            deleteTask(taskId);
        });
    });
    
    // Modal close buttons
    document.querySelectorAll('.modal-close, [data-action="close"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.dataset.modal;
            if (modalId) {
                closeModal(modalId);
            }
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
    
    document.getElementById('editTaskCompleted').checked = task.completed;
    
    openModal('editTaskModal');
}

async function toggleTaskComplete(taskId, completed) {
    const status = completed ? 'completed' : 'pending';
    
    try {
        const response = await fetch(`/tasks/${taskId}`, {
            credentials: 'same-origin',
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ status })
        });
        
        if (response.ok) {
            // Reload page to move task to appropriate section
            window.location.reload();
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
