<?php
/**
 * Header Component
 * 
 * @var array|null $user - Current user data
 */
?>
<header class="header">
    <div class="header-container">
        <div class="header-top">
            <a href="<?= isAuthenticated() ? '/dashboard' : '/' ?>" class="brand-link">
                <span class="brand-icon">📅</span>
                <span class="brand-name"><?= env('APP_NAME', 'IrmaJosh Calendar') ?></span>
            </a>
            
            <?php if (isAuthenticated()): ?>
                <div class="user-menu">
                    <button class="user-button" id="userMenuButton">
                        <?php if (!empty($user['picture_url'])): ?>
                            <img src="<?= htmlspecialchars($user['picture_url']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="user-avatar">
                        <?php else: ?>
                            <span class="user-avatar-text"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    </button>
                    
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-info">
                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="/auth/logout" class="logout-form">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit" class="dropdown-item">
                                <span class="dropdown-icon">🚪</span>
                                <?= t('logout') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (isAuthenticated()): ?>
            <nav class="header-nav">
                <a href="/dashboard" class="nav-link <?= ($_SERVER['REQUEST_URI'] === '/dashboard') ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text"><?= t('dashboard') ?></span>
                </a>
                <a href="/calendar" class="nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/calendar') === 0) ? 'active' : '' ?>">
                    <span class="nav-icon">📅</span>
                    <span class="nav-text"><?= t('calendar') ?></span>
                </a>
                <a href="/tasks/shared" class="nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/tasks') === 0) ? 'active' : '' ?>">
                    <span class="nav-icon">✓</span>
                    <span class="nav-text"><?= t('tasks') ?></span>
                </a>
                <a href="/schedule" class="nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/schedule') === 0) ? 'active' : '' ?>">
                    <span class="nav-icon">📧</span>
                    <span class="nav-text"><?= t('schedule') ?></span>
                </a>
                <button class="nav-link quick-add-btn" id="quickAddBtn" title="Quick Add (Ctrl+K)">
                    <span class="nav-icon">➕</span>
                    <span class="nav-text">Quick Add</span>
                </button>
            </nav>
        <?php endif; ?>
    </div>
</header>

<!-- Quick Add Modal (Global) -->
<?php if (isAuthenticated()): ?>
<div class="modal" id="quickAddModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Quick Add</h2>
            <button class="modal-close" data-close-modal="quickAddModal">&times;</button>
        </div>
        <form id="quickAddForm" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            
            <div class="form-group">
                <label for="quickAddTitle">What do you need to do? *</label>
                <input type="text" id="quickAddTitle" name="title" required class="form-control" 
                       placeholder="e.g., Team meeting, Buy groceries..." autofocus>
            </div>
            
            <div class="form-group">
                <label for="quickAddDescription">Details (optional)</label>
                <textarea id="quickAddDescription" name="description" class="form-control" rows="3"
                          placeholder="Add any additional notes..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="quickAddDateTime">When? (optional)</label>
                <input type="datetime-local" id="quickAddDateTime" name="date_time" class="form-control">
                <small class="form-hint">Leave empty to create a task. Add time to create calendar event.</small>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="quickAddShared" name="is_shared" value="1">
                    <span>Share with others</span>
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="quickAddModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script nonce="<?= cspNonce() ?>">
    // User menu dropdown
    document.addEventListener('DOMContentLoaded', function() {
        const userButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userButton && userDropdown) {
            userButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!userButton.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });
        }
        
        // Quick Add button
        const quickAddBtn = document.getElementById('quickAddBtn');
        if (quickAddBtn) {
            quickAddBtn.addEventListener('click', function() {
                openModal('quickAddModal');
            });
        }
        
        // Quick Add keyboard shortcut (Ctrl+K or Cmd+K)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openModal('quickAddModal');
            }
        });
        
        // Quick Add form submission
        const quickAddForm = document.getElementById('quickAddForm');
        if (quickAddForm) {
            quickAddForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const dateTime = formData.get('date_time');
                const isShared = formData.get('is_shared') === '1';
                
                const data = {
                    csrf_token: formData.get('csrf_token'),
                    title: formData.get('title'),
                    description: formData.get('description'),
                    is_shared: isShared ? 1 : 0
                };
                
                // Determine endpoint based on whether date/time is provided
                let endpoint = '/tasks'; // Default to task creation
                
                if (dateTime) {
                    // Has date/time - create calendar event
                    endpoint = '/calendar/events';
                    data.start = dateTime;
                    // Calculate end time (1 hour later by default)
                    const startDate = new Date(dateTime);
                    const endDate = new Date(startDate.getTime() + 60 * 60 * 1000);
                    data.end = endDate.toISOString().slice(0, 16);
                } else {
                    // No date/time - create task
                    data.due_date = null;
                }
                
                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': data.csrf_token
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok) {
                        closeModal('quickAddModal');
                        this.reset();
                        
                        if (dateTime) {
                            showAlert('Calendar event created successfully', 'success');
                            // Redirect to calendar if not already there
                            if (!window.location.pathname.includes('/calendar')) {
                                window.location.href = '/calendar';
                            } else {
                                window.location.reload();
                            }
                        } else {
                            showAlert('Task created successfully', 'success');
                            // Redirect to appropriate tasks page
                            const tasksPath = isShared ? '/tasks/shared' : '/tasks/private';
                            if (!window.location.pathname.includes('/tasks')) {
                                window.location.href = tasksPath;
                            } else {
                                window.location.reload();
                            }
                        }
                    } else {
                        showAlert(result.error || 'Failed to create item', 'error');
                    }
                } catch (error) {
                    console.error('Quick Add error:', error);
                    showAlert('Failed to create item', 'error');
                }
            });
            
            // Modal close buttons
            document.querySelectorAll('[data-close-modal="quickAddModal"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    closeModal('quickAddModal');
                });
            });
        }
    });
</script>
