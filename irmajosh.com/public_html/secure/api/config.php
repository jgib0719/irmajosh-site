<?php
// api/config.php
// Copy this to config.local.php and fill in real values.
// config.local.php overrides this file and should NOT be publicly readable.
return [
  'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'irmajosh',
    'user' => 'owner',
    'pass' => 'CHANGE_ME',
    'charset' => 'utf8mb4'
  ],
  // Google Identity Services Web Client ID (NO client secret needed for ID-token flow)
  'google_client_id' => 'NEW_WEB_CLIENT_ID.apps.googleusercontent.com',
  // CORS: if frontend is same-origin, keep null. If different origin, set exact origin, e.g. "https://irmajosh.com"
  'allowed_origin' => null,
  // Optional: restrict who can sign in (lowercase emails). Empty array = allow any Google account
  'allowed_emails' => [
    // 'you@example.com',
    // 'irma@example.com'
  ],
  'timezone' => 'America/Chicago'
];
