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
        <!-- Upcoming Tasks -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="card-icon">✓</span>
                    <?= t('upcoming_tasks') ?>
                </h2>
                <a href="/tasks/shared" class="card-action">View all</a>
            </div>
            <div class="card-content">
                <?php if (empty($upcomingTasks)): ?>
                    <p class="empty-state">No upcoming tasks</p>
                <?php else: ?>
                    <ul class="task-list">
                        <?php foreach ($upcomingTasks as $task): ?>
                            <li class="task-item">
                                <div class="task-info">
                                    <span class="task-title"><?= htmlspecialchars($task['title']) ?></span>
                                    <?php if ($task['due_date']): ?>
                                        <span class="task-date"><?= formatDate($task['due_date'], 'M j') ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="task-status status-<?= $task['status'] ?>"><?= $task['status'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Overdue Tasks -->
        <?php if (!empty($overdueTasks)): ?>
        <div class="dashboard-card card-warning">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="card-icon">⚠</span>
                    Overdue Tasks
                </h2>
            </div>
            <div class="card-content">
                <ul class="task-list">
                    <?php foreach (array_slice($overdueTasks, 0, 5) as $task): ?>
                        <li class="task-item">
                            <div class="task-info">
                                <span class="task-title"><?= htmlspecialchars($task['title']) ?></span>
                                <span class="task-date task-date-overdue"><?= formatDate($task['due_date'], 'M j') ?></span>
                            </div>
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
                            <li class="request-item">
                                <div class="request-info">
                                    <div class="request-title"><?= htmlspecialchars($request['subject']) ?></div>
                                    <div class="request-recipient"><?= htmlspecialchars($request['recipient_email']) ?></div>
                                </div>
                                <span class="request-status status-<?= $request['status'] ?>"><?= $request['status'] ?></span>
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
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
