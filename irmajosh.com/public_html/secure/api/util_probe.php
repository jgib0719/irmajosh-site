<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

$resp = ['ok' => true];

try {
  require_once __DIR__.'/_util.php';
  $resp['require_util'] = 'ok';
  // light-touch check that functions exist
  $resp['has'] = [
    'db' => function_exists('db'),
    'json_out' => function_exists('json_out'),
    'error_out' => function_exists('error_out'),
    'get_json' => function_exists('get_json'),
    'method' => function_exists('method'),
  ];
} catch (Throwable $e) {
  $resp['require_util'] = 'error';
  $resp['error'] = $e->getMessage();
}

echo json_encode($resp);
