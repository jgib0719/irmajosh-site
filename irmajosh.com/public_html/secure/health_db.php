<?php
// /secure/health_db.php — requires X-App-Secret header to guard the endpoint

function require_app_secret() {
  $cfg = @parse_ini_file("/var/www/irmajosh.com/private/cal.ini", true);
  $need = $cfg["auth"]["shared_secret"] ?? "";
  $hdr  = $_SERVER["HTTP_X_APP_SECRET"] ?? "";
  if (!is_string($need) || $need === "" || !hash_equals($need, $hdr)) {
    http_response_code(401);
    header("Content-Type: application/json");
    echo json_encode(["error" => "unauthorized"]);
    exit;
  }
}

function db_ok() {
  $cfg = @parse_ini_file("/var/www/irmajosh.com/private/cal.ini", true);
  if (!$cfg || empty($cfg["db"])) return ["ok"=>false, "error"=>"db_config_missing"];

  $db = $cfg["db"];
  $host = $db["host"] ?? "localhost";
  $name = $db["name"] ?? "";
  $user = $db["user"] ?? "";
  $pass = $db["pass"] ?? "";
  $charset = $db["charset"] ?? "utf8mb4";

  if ($name === "" || $user === "") return ["ok"=>false, "error"=>"db_config_incomplete"];

  $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // simple probe
    $ver = $pdo->query("SELECT VERSION() AS v")->fetchColumn();
    $pdo = null;
    return ["ok"=>true, "db"=>"OK", "version"=>$ver];
  } catch (Throwable $e) {
    return ["ok"=>false, "error"=>"db_connect_failed", "detail"=>$e->getMessage()];
  }
}

require_app_secret();
header("Content-Type: application/json");
echo json_encode(db_ok());
