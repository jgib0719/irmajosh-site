<?php
// /secure/api/config.js.php
// Serves public JS config for the frontend (no secrets).
header('Content-Type: application/javascript; charset=utf-8');

$cid = null;

/**
 * Try reading /var/www/irmajosh.com/private/cal.ini first
 */
$root = dirname(__DIR__, 4); // from /public_html/secure/api -> /var/www/irmajosh.com
$ini_path = $root . '/private/cal.ini';
if (is_readable($ini_path)) {
    $ini = @parse_ini_file($ini_path, true);
    if ($ini && isset($ini['google']['client_id']) && is_string($ini['google']['client_id'])) {
        $cid = trim($ini['google']['client_id']);
    }
}

/**
 * Fallback to server defaults via _util.php (if cal.ini missing)
 */
if (!$cid) {
    require __DIR__ . '/_util.php';
    if (function_exists('cfg')) {
        $cfg = @cfg();
        if (is_array($cfg) && !empty($cfg['google_client_id'])) {
            $cid = $cfg['google_client_id'];
        }
    }
}

/**
 * Final fallback (should not be needed once configured)
 */
if (!$cid) {
    $cid = '665405798855-0ft0sqf4qr9mcte09tpciu4j287uenvc.apps.googleusercontent.com';
}

echo "export const GSI_CLIENT_ID = " . json_encode($cid) . ";\n";
