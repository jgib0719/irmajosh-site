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
            </nav>
        <?php endif; ?>
    </div>
</header>

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
    });
</script>
