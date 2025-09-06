<?php
header('Content-Type: application/json');
echo json_encode([
  'ok' => true,
  'php_version' => PHP_VERSION,
  'sapi' => PHP_SAPI,
]);
