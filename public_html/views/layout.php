<?php
/**
 * Main Layout Template
 * 
 * @var string $pageTitle - Page title
 * @var string $content - Page content (from view)
 * @var array|null $user - Current user data
 */

$user = $user ?? currentUser();
$locale = getAppLocale();
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="Personal calendar and task management system">
    
    <title><?= htmlspecialchars($pageTitle ?? env('APP_NAME', 'IrmaJosh')) ?></title>
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= getAssetVersion() ?>">
    
    <!-- HTMX -->
    <script src="/assets/js/vendor/htmx.min.js" defer></script>
</head>
<body>
    <?php require __DIR__ . '/components/header.php'; ?>
    
    <main class="main-content">
        <!-- Alert Container -->
        <div id="alert-container" class="alert-container">
            <?php require __DIR__ . '/components/alerts.php'; ?>
        </div>
        
        <!-- Page Content -->
        <div class="content-wrapper">
            <?= $content ?? '' ?>
        </div>
    </main>
    
    <?php require __DIR__ . '/components/footer.php'; ?>
    
    <!-- Utility JavaScript -->
    <script src="/assets/js/modal.js?v=<?= getAssetVersion() ?>" nonce="<?= cspNonce() ?>"></script>
    <script src="/assets/js/form-utils.js?v=<?= getAssetVersion() ?>" nonce="<?= cspNonce() ?>"></script>
    
    <!-- Application JavaScript -->
    <script src="/assets/js/app.js?v=<?= getAssetVersion() ?>" nonce="<?= cspNonce() ?>"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?= $additionalScripts ?>
    <?php endif; ?>
</body>
</html>
