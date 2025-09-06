<?php
// ===== Session + headers bootstrap (run before any output) =====
if (PHP_SESSION_ACTIVE !== session_status()) {
  // Robust defaults for first-party app
  $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
  $params = [
    'lifetime' => 0,           // session cookie
    'path'     => '/',         // <— ensure available to / and /secure/*
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

// --- Config (edit as needed) ---
function cfg() {
  return [
    'db' => [
      'host'    => '127.0.0.1',
      'port'    => 3306,
      'name'    => 'cal',
      'user'    => 'owner',
      'pass'    => 'Awake2020!',
      'charset' => 'utf8mb4'
    ],
    // Set to your site if you want strict CORS. null allows same-origin.
    'allowed_origin'   => null,
    'google_client_id' => '665405798855-0ft0sqf4qr9mcte09tpciu4j287uenvc.apps.googleusercontent.com',
    'allowed_emails'   => ['jgib0719@gmail.com','irmakusuma200@gmail.com'],
    'timezone'         => 'America/Chicago',
    // Where to read the shared secret for X-App-Secret
    'app_ini'          => '/var/www/irmajosh.com/private/cal.ini',
    'app_ini_key'      => 'shared_secret'
  ];
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

// --- App secret loader ---
function app_secret() {
  $c = cfg();
  $file = $c['cal_ini'];
  $key  = $c['app_ini_key'];
  if (!is_readable($file)) return null;
  $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return null;
  foreach ($lines as $ln) {
    if (strpos($ln, '=') === false) continue;
    [$k,$v] = array_map('trim', explode('=', $ln, 2));
    if ($k === $key && $v !== '') return $v;
  }
  return null;
}
function has_app_secret() {
  $hdr = $_SERVER['HTTP_X_APP_SECRET'] ?? null;
  if (!$hdr) return false;
  $sec = app_secret();
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
