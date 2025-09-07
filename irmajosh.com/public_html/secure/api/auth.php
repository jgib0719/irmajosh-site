<?php
// api/auth.php
require_once __DIR__.'/_util.php';

/**
 * Verifies a Google ID token using the cURL library.
 * @param string $id_token The token to verify.
 * @return array|null The decoded payload if valid, otherwise null.
 */
/**
 * Fetch Google's JWKS and cache it on disk for a short time.
 * Returns decoded JWKS array or null on failure.
 */
function fetch_google_jwks() {
  $cache_file = sys_get_temp_dir() . '/google_jwks.json';
  $cache_ttl = 3600; // 1 hour

  if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    $raw = file_get_contents($cache_file);
    return json_decode($raw, true);
  }

  $url = 'https://www.googleapis.com/oauth2/v3/certs';
  $opts = ['http' => ['timeout' => 5]];
  $ctx = stream_context_create($opts);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;

  file_put_contents($cache_file, $raw);
  return json_decode($raw, true);
}

/**
 * Verify a JWT ID token locally using Google's JWKS.
 * Returns the decoded payload array on success or null on failure.
 */
function verify_google_id_token_locally($id_token) {
  // Split token
  $parts = explode('.', $id_token);
  if (count($parts) !== 3) return null;

  list($h64, $p64, $sig64) = $parts;
  $header = json_decode(base64_decode(strtr($h64, '-_', '+/')), true);
  $payload = json_decode(base64_decode(strtr($p64, '-_', '+/')), true);
  if (!$header || !$payload) return null;

  $jwks = fetch_google_jwks();
  if (!is_array($jwks) || empty($jwks['keys'])) return null;

  // Find key by kid
  $kid = $header['kid'] ?? null;
  $key = null;
  foreach ($jwks['keys'] as $k) {
    if (($k['kid'] ?? '') === $kid) { $key = $k; break; }
  }
  if (!$key) return null;

  // Build PEM from x5c if present
  $pem = null;
  if (!empty($key['x5c'][0])) {
    $cert = $key['x5c'][0];
    $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64, "\n") . "-----END CERTIFICATE-----\n";
    $pubkey = openssl_pkey_get_public($pem);
  } else {
    return null; // no x5c available
  }

  if (!$pubkey) return null;

  $data = $h64 . '.' . $p64;
  $sig = base64_decode(strtr($sig64, '-_', '+/'));

  $verified = openssl_verify($data, $sig, $pubkey, OPENSSL_ALGO_SHA256);
  openssl_free_key($pubkey);
  if ($verified !== 1) return null;

  return $payload;
}

/**
 * Fallback: Verifies a Google ID token using the tokeninfo endpoint.
 */
function verify_google_token_with_curl($id_token) {
  $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $resp = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpcode !== 200 || $resp === false) {
    return null;
  }
  return json_decode($resp, true);
}

$action = $_GET['action'] ?? '';

switch ($action) {
  case 'login':
    method('POST');
    // FIX: use get_json() from _util.php
    $data = get_json();
    $id_token = $data['id_token'] ?? '';
    if (!$id_token) error_out('Missing id_token', 400);

  // Prefer local verification using Google's JWKS. Fall back to tokeninfo endpoint.
  $payload = verify_google_id_token_locally($id_token);
  if ($payload === null) {
    $payload = verify_google_token_with_curl($id_token);
  }
  if ($payload === null) {
    error_out('Google verification failed', 401);
  }

    // The Google ID token's audience (aud) must match our configured
    // OAuth client ID.  cfg() returns configuration grouped into sections
    // so the client ID lives under the ['google']['client_id'] key.
    //
    // Previously this code checked cfg()['google_client_id'], but the
    // configuration structure was refactored and that key no longer
    // exists.  As a result the comparison always failed and every login
    // attempt returned "Invalid id_token".
    $aud_ok = isset($payload['aud']) && $payload['aud'] === (cfg()['google']['client_id'] ?? null);
    $iss_ok = isset($payload['iss']) && in_array($payload['iss'], ['https://accounts.google.com', 'accounts.google.com'], true);
  $email_verified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
  $is_expired = isset($payload['exp']) && (int)$payload['exp'] < time();
  if (!$aud_ok || !$iss_ok || !$email_verified || $is_expired) {
        error_out('Invalid id_token', 401);
    }

    $email = strtolower($payload['email'] ?? '');
    if (!$email) error_out('Missing email in token', 401);
    $allow = cfg()['allowed_emails'];
    if (is_array($allow) && !empty($allow) && !in_array($email, $allow, true)) {
        error_out('User not allowed', 403);
    }

    $sub = $payload['sub'];
    $name = $payload['name'] ?? ($payload['given_name'] ?? 'User');

    $pdo = db();
    $pdo->prepare("INSERT INTO users (google_sub, email, name, created_at)
                   VALUES (?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE email=VALUES(email), name=VALUES(name)")
        ->execute([$sub, $email, $name]);

    $st = $pdo->prepare("SELECT id, google_sub, email, name FROM users WHERE google_sub=?");
    $st->execute([$sub]);
  // Prevent session fixation
  session_regenerate_id(true);
  $_SESSION['user'] = $st->fetch();

    json_out(['ok'=>true,'user'=>$_SESSION['user']]);
    break;

  case 'me':
    method('GET');
    json_out(['ok'=>true,'user'=>($_SESSION['user'] ?? null)]);
    break;

  case 'logout':
    method('POST');
  // Clear session data and cookie, then destroy session
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'] ?? '/',
      $params['domain'] ?? '',
      $params['secure'] ?? false,
      $params['httponly'] ?? false
    );
  }
  session_destroy();
    json_out(['ok'=>true]);
    break;

  default:
    error_out('Unknown action', 404);
}
