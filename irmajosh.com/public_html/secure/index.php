<?php
// Enforce session-based access to the secure UI. Unauthenticated users are redirected to /
require_once __DIR__ . '/api/_util.php';

// Use require_auth() style check but avoid sending JSON error. Redirect instead.
// Allow a localhost-only debug bypass when _dbg=1 is present. This lets server-side headless
// tools load the page for diagnostics without changing the production auth guard.
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (empty($_SESSION['user'])) {
  if (($remoteIp === '127.0.0.1' || $remoteIp === '::1') && isset($_GET['_dbg']) && $_GET['_dbg'] == '1') {
    // proceed for local debug
  } else {
    header('Location: /', true, 302);
    exit;
  }
}
// If authenticated, output the existing index HTML (kept minimal and safe)
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scheduler</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/secure/favicon.ico">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <main class="container">
    <h1>Scheduler</h1>
    <div id="app"></div>
  </main>

  <!-- App entry (modules at end so DOM is ready) -->
  <script type="module" src="js/bridge.js?v=20250905035221"></script>
  <script type="module" src="js/main.js?v=20250905035221"></script>
</body>
</html>
