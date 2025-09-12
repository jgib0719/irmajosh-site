<?php
// /secure/api/config.js.php
// Serves public JS config from the centralized cfg() loader.

// This MUST be required first to load the real config
require_once __DIR__ . '/_util.php';

header('Content-Type: application/javascript; charset=utf-8');

$config = cfg();

// Get the Google Client ID from the [google] section of the config
$cid = $config['google']['client_id'] ?? null;

// Final fallback (should not be needed if cal.ini is correct)
if (!$cid) {
    // Updated fallback Google Client ID (2025-09-10)
    $cid = '301304738390-svfnm48gtb29tdbc14o0tv2003sn5eao.apps.googleusercontent.com';
}

echo "export const GSI_CLIENT_ID = " . json_encode($cid) . ";\n";