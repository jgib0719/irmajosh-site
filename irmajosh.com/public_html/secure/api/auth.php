<?php
// api/auth.php
require_once __DIR__.'/_util.php';

/**
 * Verifies a Google ID token using the cURL library.
 * @param string $id_token The token to verify.
 * @return array|null The decoded payload if valid, otherwise null.
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

    $payload = verify_google_token_with_curl($id_token);
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
    $email_verified = ($payload['email_verified'] ?? 'false') === 'true';
    $expired = isset($payload['exp']) && (int)$payload['exp'] < time();
    if (!$aud_ok || !$iss_ok || !$email_verified || $expired) {
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
    $_SESSION['user'] = $st->fetch();

    json_out(['ok'=>true,'user'=>$_SESSION['user']]);
    break;

  case 'me':
    method('GET');
    json_out(['ok'=>true,'user'=>($_SESSION['user'] ?? null)]);
    break;

  case 'logout':
    method('POST');
    session_destroy();
    json_out(['ok'=>true]);
    break;

  default:
    error_out('Unknown action', 404);
}
