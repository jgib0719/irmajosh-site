<?php
/**
 * Dashboard View
 * 
 * @var array $user - Current user
 * @var array $upcomingTasks - Upcoming tasks
 * @var array $overdueTasks - Overdue tasks
 * @var array $scheduleRequests - Recent schedule requests
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
        
        <!-- Schedule Requests -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="card-icon">📧</span>
                    <?= t('schedule_requests') ?>
                </h2>
                <a href="/schedule" class="card-action">View all</a>
            </div>
            <div class="card-content">
                <?php if (empty($scheduleRequests)): ?>
                    <p class="empty-state">No schedule requests</p>
                <?php else: ?>
                    <ul class="request-list">
                        <?php foreach ($scheduleRequests as $request): ?>
                            <li class="request-item clickable" data-request-id="<?= $request['id'] ?>" style="cursor: pointer;">
                                <div class="request-info">
                                    <div class="request-title"><?= htmlspecialchars($request['title'] ?? 'Untitled Request') ?></div>
                                    <?php if (!empty($request['description'])): ?>
                                        <div class="request-description"><?= htmlspecialchars(substr($request['description'], 0, 50)) ?><?= strlen($request['description']) > 50 ? '...' : '' ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="request-status status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
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
            <a href="/schedule" class="action-card">
                <span class="action-icon">📧</span>
                <span class="action-text">Send Request</span>
            </a>
        </div>
    </div>
    
    <!-- Notification Settings -->
    <div class="dashboard-card" style="margin-top: 2rem;">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">🔔</span>
                Push Notifications
            </h2>
        </div>
        <div class="card-content">
            <div id="notification-status" class="notification-status">
                <p class="notification-text">Loading notification status...</p>
            </div>
            <button id="notification-toggle" class="btn btn-primary" style="margin-top: 1rem; display: none;">
                Enable Notifications
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

<!-- Schedule Request Detail Modal -->
<div class="modal" id="requestDetailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="requestDetailTitle">Request Details</h2>
            <button class="modal-close" data-close-modal="requestDetailModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="scheduleEventForm">
                <input type="hidden" id="scheduleRequestId" name="request_id">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label>Title</label>
                    <p id="requestDetailTitleText"></p>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <p id="requestDetailDescription"></p>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <p id="requestDetailStatus"></p>
                </div>
                <div class="form-group">
                    <label>From</label>
                    <p id="requestDetailSender"></p>
                </div>
                <div class="form-group">
                    <label>Created</label>
                    <p id="requestDetailCreated"></p>
                </div>
                <div class="form-group" id="requestSlotsContainer" style="display: none;">
                    <label>Proposed Times</label>
                    <ul id="requestDetailSlots"></ul>
                </div>
                
                <!-- Scheduling Section (only visible for Pending Schedule status) -->
                <div id="scheduleSection" style="display: none; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--color-border, #333);">
                    <div class="form-group">
                        <label for="scheduleStart">Date/Time*</label>
                        <input type="datetime-local" id="scheduleStart" name="start" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="scheduleMultiDay" name="multi_day">
                            Multi-day event
                        </label>
                    </div>
                    
                    <div class="form-group" id="scheduleEndGroup" style="display: none;">
                        <label for="scheduleEnd">End Date/Time</label>
                        <input type="datetime-local" id="scheduleEnd" name="end" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="scheduleRecurring" name="recurring">
                            Recurring event
                        </label>
                    </div>
                    
                    <div id="scheduleRecurrenceGroup" style="display: none;">
                        <div class="form-group">
                            <label for="scheduleRecurrenceType">Repeat</label>
                            <select id="scheduleRecurrenceType" name="recurrence_type" class="form-control">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly (on this day)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="scheduleRecurrenceInterval">Every</label>
                            <input type="number" id="scheduleRecurrenceInterval" name="recurrence_interval" value="1" min="1" class="form-control" style="width: 80px; display: inline-block;"> 
                            <span id="recurrenceIntervalLabel">week(s)</span>
                        </div>
                        
                        <div class="form-group" id="scheduleRecurrenceEndGroup">
                            <label for="scheduleRecurrenceEnd">Ends on</label>
                            <input type="date" id="scheduleRecurrenceEnd" name="recurrence_end" class="form-control">
                            <small style="display: block; margin-top: 0.5rem; color: var(--color-text-secondary, #888);">Leave blank for no end date</small>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-success" id="requestAcceptBtn" style="display: none;">Accept</button>
            <button type="button" class="btn btn-danger" id="requestDeclineBtn" style="display: none;">Decline</button>
            <button type="button" class="btn btn-primary" id="requestScheduleSubmitBtn" style="display: none;">Schedule to Calendar</button>
            <button type="button" class="btn btn-secondary" data-close-modal="requestDetailModal">Close</button>
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

    // Schedule request click handlers
    const requestItems = document.querySelectorAll('.request-item[data-request-id]');
    console.log('Found request items:', requestItems.length);
    requestItems.forEach(item => {
        item.addEventListener('click', async function(e) {
            const requestId = this.dataset.requestId;
            console.log('Clicked request ID:', requestId);
            await showRequestDetail(requestId);
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

// Show request detail modal
async function showRequestDetail(requestId) {
    console.log('showRequestDetail called with ID:', requestId);
    try {
        console.log('Fetching request from:', `/schedule/${requestId}`);
        const response = await fetch(`/schedule/${requestId}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Response error:', errorText);
            throw new Error('Failed to fetch request details');
        }
        
        const request = await response.json();
        console.log('Request data:', request);
        
        // Populate modal
        document.getElementById('requestDetailTitleText').textContent = request.title || '';
        document.getElementById('requestDetailDescription').textContent = request.description || 'No description';
        document.getElementById('requestDetailStatus').innerHTML = `<span class="request-status status-${request.status}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span>`;
        document.getElementById('requestDetailSender').textContent = request.sender_name || 'Unknown';
        document.getElementById('requestDetailCreated').textContent = new Date(request.created_at).toLocaleString();
        
        // Show slots if available
        const slotsContainer = document.getElementById('requestSlotsContainer');
        const slotsList = document.getElementById('requestDetailSlots');
        if (request.slots && request.slots.length > 0) {
            slotsList.innerHTML = request.slots.map(slot => {
                const start = new Date(slot.start_at || slot.start_time);
                const end = new Date(slot.end_at || slot.end_time);
                return `<li>${start.toLocaleString()} - ${end.toLocaleTimeString()}</li>`;
            }).join('');
            slotsContainer.style.display = 'block';
        } else {
            slotsContainer.style.display = 'none';
        }
        
        // Show action buttons based on status
        const acceptBtn = document.getElementById('requestAcceptBtn');
        const declineBtn = document.getElementById('requestDeclineBtn');
        const scheduleBtn = document.getElementById('requestScheduleBtn');
        
        if (request.status === 'pending') {
            // Show Accept/Decline for pending requests
            acceptBtn.style.display = 'inline-block';
            declineBtn.style.display = 'inline-block';
            scheduleBtn.style.display = 'none';
            
            acceptBtn.onclick = async () => {
                await acceptRequest(requestId);
                closeModal('requestDetailModal');
                window.location.reload();
            };
            
            declineBtn.onclick = async () => {
                if (confirm('Are you sure you want to decline this request?')) {
                    await declineRequest(requestId);
                    closeModal('requestDetailModal');
                    window.location.reload();
                }
            };
        } else if (request.status === 'accepted' && !request.accepted_slot_id) {
            // Show scheduling section for accepted but not scheduled requests
            acceptBtn.style.display = 'none';
            declineBtn.style.display = 'none';
            const submitBtn = document.getElementById('requestScheduleSubmitBtn');
            submitBtn.style.display = 'inline-block';
            
            // Update status to show "Pending Schedule"
            document.getElementById('requestDetailStatus').innerHTML = `<span class="request-status status-pending">Pending Schedule</span>`;
            
            // Show the scheduling section
            const scheduleSection = document.getElementById('scheduleSection');
            scheduleSection.style.display = 'block';
            
            // Populate form with request data
            document.getElementById('scheduleRequestId').value = requestId;
            
            // If there are slots, pre-populate with first slot
            if (request.slots && request.slots.length > 0) {
                const firstSlot = request.slots[0];
                const startDate = new Date(firstSlot.start_at || firstSlot.start_time);
                const endDate = new Date(firstSlot.end_at || firstSlot.end_time);
                
                // Format for datetime-local input
                document.getElementById('scheduleStart').value = startDate.toISOString().slice(0, 16);
                document.getElementById('scheduleStart').required = true;
                
                // Check if it spans multiple days
                if (startDate.toDateString() !== endDate.toDateString()) {
                    document.getElementById('scheduleMultiDay').checked = true;
                    document.getElementById('scheduleEndGroup').style.display = 'block';
                    document.getElementById('scheduleEnd').value = endDate.toISOString().slice(0, 16);
                }
            } else {
                // Default to tomorrow at 9am
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(9, 0, 0, 0);
                document.getElementById('scheduleStart').value = tomorrow.toISOString().slice(0, 16);
                document.getElementById('scheduleStart').required = true;
            }
            
            submitBtn.onclick = async () => {
                const form = document.getElementById('scheduleEventForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                await submitScheduleEvent();
            };
        } else {
            // No actions for declined or fully scheduled requests
            acceptBtn.style.display = 'none';
            declineBtn.style.display = 'none';
            document.getElementById('requestScheduleSubmitBtn').style.display = 'none';
            document.getElementById('scheduleSection').style.display = 'none';
        }
        
        console.log('Opening request detail modal');
        openModal('requestDetailModal');
    } catch (error) {
        console.error('Error showing request detail:', error);
        alert('Failed to load request details');
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

// Accept request
async function acceptRequest(requestId) {
    try {
        const response = await fetch(`/schedule/${requestId}/accept`, {
            credentials: 'same-origin',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to accept request');
        }
    } catch (error) {
        console.error('Error accepting request:', error);
        alert('Failed to accept request');
    }
}

// Decline request
async function declineRequest(requestId) {
    try {
        const response = await fetch(`/schedule/${requestId}/decline`, {
            credentials: 'same-origin',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to decline request');
        }
    } catch (error) {
        console.error('Error declining request:', error);
        alert('Failed to decline request');
    }
}

// Handle multi-day checkbox
document.addEventListener('DOMContentLoaded', function() {
    const multiDayCheck = document.getElementById('scheduleMultiDay');
    const endGroup = document.getElementById('scheduleEndGroup');
    
    if (multiDayCheck && endGroup) {
        multiDayCheck.addEventListener('change', function() {
            endGroup.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Handle recurring checkbox
    const recurringCheck = document.getElementById('scheduleRecurring');
    const recurrenceGroup = document.getElementById('scheduleRecurrenceGroup');
    
    if (recurringCheck && recurrenceGroup) {
        recurringCheck.addEventListener('change', function() {
            recurrenceGroup.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Update recurrence interval label
    const recurrenceType = document.getElementById('scheduleRecurrenceType');
    const intervalLabel = document.getElementById('recurrenceIntervalLabel');
    
    if (recurrenceType && intervalLabel) {
        recurrenceType.addEventListener('change', function() {
            const labels = {
                'daily': 'day(s)',
                'weekly': 'week(s)',
                'monthly': 'month(s)',
                'yearly': 'year(s)'
            };
            intervalLabel.textContent = labels[this.value] || 'period(s)';
        });
    }
});

// Submit the schedule event
async function submitScheduleEvent() {
    try {
        const form = document.getElementById('scheduleEventForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Get title and description from the modal text
        data.title = document.getElementById('requestDetailTitleText').textContent;
        data.description = document.getElementById('requestDetailDescription').textContent;
        
        // If not multi-day, set end time to 1 hour after start
        if (!data.multi_day || !data.end) {
            const startDate = new Date(data.start);
            startDate.setHours(startDate.getHours() + 1);
            data.end = startDate.toISOString().slice(0, 16);
        }
        
        // Clean up data
        delete data.multi_day;
        if (!data.recurring) {
            delete data.recurrence_type;
            delete data.recurrence_interval;
            delete data.recurrence_end;
        }
        delete data.recurring;
        
        console.log('Submitting schedule event:', data);
        
        // Create the calendar event
        const response = await fetch('/calendar/events', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': data.csrf_token
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error('Failed to create calendar event');
        }
        
        const result = await response.json();
        console.log('Calendar event created:', result);
        
        // Mark the request as scheduled
        const requestId = data.request_id;
        await fetch(`/schedule/${requestId}/schedule`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': data.csrf_token
            },
            body: JSON.stringify({
                calendar_event_id: result.id
            })
        });
        
        // Close modal and navigate to calendar at the event date
        closeModal('requestDetailModal');
        const eventDate = new Date(data.start);
        window.location.href = `/calendar?date=${eventDate.toISOString().split('T')[0]}`;
        
    } catch (error) {
        console.error('Error submitting schedule event:', error);
        alert('Failed to create calendar event. Please try again.');
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
