<?php
/**
 * Dashboard View
 * 
 * @var array $user - Current user
 * @var array $upcomingTasks - Upcoming tasks
 * @var array $overdueTasks - Overdue tasks
 */

ob_start();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="page-title"><?= t('welcome') ?>, <?= htmlspecialchars($user['name']) ?>!</h1>
        <p class="page-subtitle">Here's what's happening with your schedule</p>
    </div>
    
    <div class="dashboard-grid">
        <!-- Recent Tasks -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="card-icon">✓</span>
                    Recent Tasks
                </h2>
                <a href="/tasks/shared" class="card-action">View all</a>
            </div>
            <div class="card-content">
                <?php if (empty($recentTasks)): ?>
                    <p class="empty-state">No recent tasks</p>
                <?php else: ?>
                    <ul class="task-list">
                        <?php foreach ($recentTasks as $task): ?>
                            <li class="task-item clickable" data-task-id="<?= $task['id'] ?>" style="cursor: pointer;">
                                <div class="task-info">
                                    <span class="task-title"><?= htmlspecialchars($task['title']) ?></span>
                                </div>
                                <span class="task-status status-<?= $task['status'] ?>"><?= ucfirst($task['status']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- In Progress Tasks -->
        <?php if (!empty($inProgressTasks)): ?>
        <div class="dashboard-card card-info">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="card-icon">🔄</span>
                    In Progress
                </h2>
            </div>
            <div class="card-content">
                <ul class="task-list">
                    <?php foreach ($inProgressTasks as $task): ?>
                        <li class="task-item clickable" data-task-id="<?= $task['id'] ?>" style="cursor: pointer;">
                            <div class="task-info">
                                <span class="task-title"><?= htmlspecialchars($task['title']) ?></span>
                            </div>
                            <span class="task-status status-<?= $task['status'] ?>"><?= ucfirst($task['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        

    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2 class="section-title">Quick Actions</h2>
        <div class="action-grid">
            <a href="/tasks/shared" class="action-card">
                <span class="action-icon">✓</span>
                <span class="action-text">Create Task</span>
            </a>
            <a href="/calendar" class="action-card">
                <span class="action-icon">📅</span>
                <span class="action-text">Add Event</span>
            </a>

        </div>
    </div>
    
    <!-- Notification Settings -->
    <div class="dashboard-card notification-card">
        <div class="card-content text-center">
            <div class="notification-header">
                <span class="notification-icon">🔔</span>
                <span class="notification-title">Push Notifications</span>
            </div>
            <div id="notification-status" class="notification-status">
                <p class="notification-text">Loading status...</p>
            </div>
            <button id="notification-toggle" class="btn btn-sm btn-primary mt-2" style="display: none;">
                Enable
            </button>
        </div>
    </div>
</div>

<!-- Task Detail Modal -->
<div class="modal" id="taskDetailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="taskDetailTitle">Task Details</h2>
            <button class="modal-close" data-close-modal="taskDetailModal">&times;</button>
        </div>
        <div class="modal-body">
            <div id="taskDetailContent">
                <div class="form-group">
                    <label>Title</label>
                    <p id="taskDetailTitleText"></p>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <p id="taskDetailDescription"></p>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <p id="taskDetailStatus"></p>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <p id="taskDetailType"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-success" id="taskCompleteBtn">Mark Complete</button>
            <button type="button" class="btn btn-primary" id="taskEditBtn">Edit</button>
            <button type="button" class="btn btn-danger" id="taskDeleteBtn">Delete</button>
            <button type="button" class="btn btn-secondary" data-close-modal="taskDetailModal">Close</button>
        </div>
    </div>
</div>



<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Dashboard DOMContentLoaded fired');
    
    const statusDiv = document.getElementById('notification-status');
    const toggleBtn = document.getElementById('notification-toggle');
    
    console.log('statusDiv:', statusDiv);
    console.log('toggleBtn:', toggleBtn);
    
    if (!statusDiv || !toggleBtn) {
        console.error('Notification elements not found');
    } else if (!window.PushNotifications) {
        statusDiv.innerHTML = '<p class="notification-text">Push notifications not supported</p>';
    } else {
        async function updateStatus() {
        const isSubscribed = await PushNotifications.isSubscribed();
        
        if (isSubscribed) {
            statusDiv.innerHTML = '<p class="notification-text" style="color: var(--color-success);">✓ Notifications enabled</p>';
            toggleBtn.textContent = 'Disable Notifications';
            toggleBtn.classList.remove('btn-primary');
            toggleBtn.classList.add('btn-secondary');
        } else {
            statusDiv.innerHTML = '<p class="notification-text">Notifications disabled</p>';
            toggleBtn.textContent = 'Enable Notifications';
            toggleBtn.classList.remove('btn-secondary');
            toggleBtn.classList.add('btn-primary');
        }
        
        toggleBtn.style.display = 'inline-block';
    }
    
    toggleBtn.addEventListener('click', async function() {
        const isSubscribed = await PushNotifications.isSubscribed();
        
        toggleBtn.disabled = true;
        
        try {
            if (isSubscribed) {
                await PushNotifications.unsubscribe();
            } else {
                await PushNotifications.subscribe();
            }
            await updateStatus();
        } catch (error) {
            console.error('Failed to toggle notifications:', error);
            alert('Failed to toggle notifications. Please try again.');
        } finally {
            toggleBtn.disabled = false;
        }
    });
    
        await updateStatus();
    }
    
    // Task click handlers
    console.log('Setting up task click handlers...');
    const taskItems = document.querySelectorAll('.task-item[data-task-id]');
    console.log('Found task items:', taskItems.length);
    taskItems.forEach(item => {
        item.addEventListener('click', async function(e) {
            const taskId = this.dataset.taskId;
            console.log('Clicked task ID:', taskId);
            await showTaskDetail(taskId);
        });
    });


    // Modal close button handlers
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.closeModal);
        });
    });
    
    console.log('Dashboard initialization complete');
});

// Show task detail modal
async function showTaskDetail(taskId) {
    console.log('showTaskDetail called with ID:', taskId);
    try {
        console.log('Fetching task from:', `/tasks/${taskId}`);
        const response = await fetch(`/tasks/${taskId}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Response error:', errorText);
            throw new Error('Failed to fetch task details');
        }
        
        const task = await response.json();
        console.log('Task data:', task);
        
        // Populate modal
        document.getElementById('taskDetailTitleText').textContent = task.title || '';
        document.getElementById('taskDetailDescription').textContent = task.description || 'No description';
        document.getElementById('taskDetailStatus').textContent = task.completed ? 'Completed' : 'In Progress';
        document.getElementById('taskDetailType').textContent = task.is_shared ? 'Shared' : 'Private';
        
        // Update complete button
        const completeBtn = document.getElementById('taskCompleteBtn');
        if (task.completed) {
            completeBtn.textContent = 'Mark Incomplete';
            completeBtn.classList.remove('btn-success');
            completeBtn.classList.add('btn-secondary');
        } else {
            completeBtn.textContent = 'Mark Complete';
            completeBtn.classList.remove('btn-secondary');
            completeBtn.classList.add('btn-success');
        }
        
        // Set up button handlers
        completeBtn.onclick = async () => {
            await toggleTaskComplete(taskId, !task.completed);
            closeModal('taskDetailModal');
            window.location.reload();
        };
        
        document.getElementById('taskEditBtn').onclick = () => {
            const taskType = task.is_shared ? 'shared' : 'private';
            window.location.href = `/tasks/${taskType}`;
        };
        
        document.getElementById('taskDeleteBtn').onclick = async () => {
            if (confirm('Are you sure you want to delete this task?')) {
                await deleteTask(taskId);
                closeModal('taskDetailModal');
                window.location.reload();
            }
        };
        
        console.log('Opening task detail modal');
        openModal('taskDetailModal');
    } catch (error) {
        console.error('Error showing task detail:', error);
        alert('Failed to load task details');
    }
}



// Toggle task completion
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
        
        if (!response.ok) {
            throw new Error('Failed to update task');
        }
    } catch (error) {
        console.error('Error toggling task:', error);
        alert('Failed to update task');
    }
}

// Delete task
async function deleteTask(taskId) {
    try {
        const response = await fetch(`/tasks/${taskId}`, {
            credentials: 'same-origin',
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to delete task');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        alert('Failed to delete task');
    }
}




</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
