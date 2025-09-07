<?php
// ===== Session + headers bootstrap (run before any output) =====
if (PHP_SESSION_ACTIVE !== session_status()) {
  // Robust defaults for first-party app
  $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
  $params = [
    'lifetime' => 0,           // session cookie
    'path'     => '/',         // <— ensure available to / and /secure
    'domain'   => '',          // default host-only cookie
    'secure'   => $secure,     // only over HTTPS
    'httponly' => true,        // no JS access
    'samesite' => 'Lax',       // works with top-level POST from same site
  ];
  // PHP 7.3+ signature
  session_set_cookie_params($params);
  session_name('PHPSESSID');   // keep default if you like
  session_start();
}

// (keep your existing CORS/security headers here if you had them)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
// Google Identity Services uses cross-origin iframes and postMessage during
// the sign-in flow.  We handle COOP centrally in the Apache vhost so we
// don't set it here to avoid duplicate/conflicting headers.

// --- Config ---
function cfg() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    // Application-level defaults
    $defaults = [
        'db' => [
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'charset' => 'utf8mb4'
        ],
        // Default Google Client ID (if missing from .ini)
        'google' => [
            'client_id' => '665405798855-0ft0sqf4qr9mcte09tpciu4j287uenvc.apps.googleusercontent.com'
        ],
        'auth' => [
            'allowed_emails' => ['jgib0719@gmail.com', 'irmakusuma200@gmail.com'],
            'shared_secret' => '' // Should be loaded from .ini
        ],
        'timezone' => 'America/Chicago',
        'allowed_origin' => null // CORS: null = allow same-origin
    ];

    // Load production config from private .ini
    $ini_path = '/var/www/irmajosh.com/private/cal.ini';
    if (is_readable($ini_path)) {
        $ini_values = @parse_ini_file($ini_path, true); // true = process sections
        if ($ini_values) {
            // array_replace_recursive overwrites default keys with .ini keys
            $config = array_replace_recursive($defaults, $ini_values);
        } else {
            $config = $defaults; // Fallback if .ini is unreadable
        }
    } else {
        $config = $defaults; // Fallback if .ini is missing
    }

    return $config;
}

// --- Timezone ---
date_default_timezone_set(cfg()['timezone']);

// --- CORS helper ---
function cors_headers() {
  $cfg = cfg();
  $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
  if ($cfg['allowed_origin']) {
    header('Access-Control-Allow-Origin: ' . $cfg['allowed_origin']);
  } elseif ($origin) {
    // allow same-origin; tighten if needed
    header('Access-Control-Allow-Origin: ' . $origin);
  }
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Headers: Content-Type, X-App-Secret');
  header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  cors_headers();
  http_response_code(204);
  exit;
}

// --- JSON helpers ---
function json_out($data, $code=200) {
  http_response_code($code);
  cors_headers();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}
function error_out($msg, $code=400) {
  json_out(['ok'=>false,'error'=>$msg], $code);
}
function method($m) {
  if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($m)) {
    error_out("Method not allowed", 405);
  }
}
function get_json() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];
  return $data;
}

// --- DB ---
function db() {
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = cfg()['db'];
  $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
  $pdo = new PDO($dsn, $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  return $pdo;
}

// --- Auth gate ---
// (Helper function to get the secret from config)
function get_app_secret() {
    $cfg = cfg();
    return $cfg['auth']['shared_secret'] ?? null;
}

// (Helper function to check header against config)
function has_app_secret() {
  $hdr = $_SERVER['HTTP_X_APP_SECRET'] ?? null;
  if (!$hdr) return false;
  $sec = get_app_secret();
  return $sec && hash_equals($sec, $hdr);
}

// --- Auth gate ---
// Accept either: valid session user, or a valid X-App-Secret header
function require_auth() {
  if (has_app_secret()) {
    // System identity for secret-based calls (limited trust)
    return ['system' => 'appsecret'];
  }
  if (!empty($_SESSION['user'])) {
    return $_SESSION['user'];
  }
  error_out('Not authenticated', 401);
}
