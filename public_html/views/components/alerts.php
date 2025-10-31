<?php
/**
 * Alerts Component
 * 
 * Displays flash messages
 */

$alertTypes = ['success', 'error', 'warning', 'info'];

foreach ($alertTypes as $type) {
    if (hasFlash($type)) {
        $message = getFlash($type);
        ?>
        <div class="alert alert-<?= $type ?>" role="alert">
            <span class="alert-icon">
                <?php if ($type === 'success'): ?>✓<?php endif; ?>
                <?php if ($type === 'error'): ?>✗<?php endif; ?>
                <?php if ($type === 'warning'): ?>⚠<?php endif; ?>
                <?php if ($type === 'info'): ?>ℹ<?php endif; ?>
            </span>
            <span class="alert-message"><?= htmlspecialchars($message) ?></span>
            <button class="alert-close" data-dismiss="alert">&times;</button>
        </div>
        <?php
    }
}
?>
