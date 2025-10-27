<?php
/**
 * Unified Tasks View with Tabs
 * 
 * @var array $user - Current user
 * @var array $sharedTasks - Shared tasks array
 * @var array $privateTasks - Private tasks array
 * @var string $activeTab - Which tab is active ('shared' or 'private')
 */

$activeTab = $activeTab ?? 'shared';
ob_start();
?>

<div class="tasks-page">
    <div class="page-header">
        <h1 class="page-title"><?= t('tasks') ?></h1>
    </div>
    
    <!-- Task Tabs -->
    <div class="tabs">
        <button class="tab <?= $activeTab === 'shared' ? 'active' : '' ?>" data-tab="shared">
            Shared Tasks
        </button>
        <button class="tab <?= $activeTab === 'private' ? 'active' : '' ?>" data-tab="private">
            My Tasks
        </button>
    </div>
    
    <!-- Shared Tasks Tab Content -->
    <div class="tab-content <?= $activeTab === 'shared' ? 'active' : '' ?>" id="shared-tab">
        <?php 
        $tasks = $sharedTasks ?? [];
        require __DIR__ . '/components/task-list.php';
        ?>
    </div>
    
    <!-- Private Tasks Tab Content -->
    <div class="tab-content <?= $activeTab === 'private' ? 'active' : '' ?>" id="private-tab">
        <?php 
        $tasks = $privateTasks ?? [];
        require __DIR__ . '/components/task-list.php';
        ?>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            // Update active tab button
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update active tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(`${tabName}-tab`).classList.add('active');
            
            // Update URL without reload
            const newPath = `/tasks/${tabName}`;
            window.history.pushState({}, '', newPath);
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
