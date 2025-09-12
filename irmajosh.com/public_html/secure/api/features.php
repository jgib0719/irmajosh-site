<?php
// api/features.php — feature request tracking
require_once __DIR__ . '/_util.php';
require_auth();
$pdo = db();

// Ensure table exists (lightweight, idempotent)
$pdo->exec("CREATE TABLE IF NOT EXISTS feature_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  is_done TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $st = $pdo->query("SELECT * FROM feature_requests ORDER BY is_done ASC, created_at DESC");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok'=>true,'items'=>$rows]);
}

if ($method === 'POST') {
  $data = get_json();
  $title = trim($data['title'] ?? '');
  $notes = trim($data['notes'] ?? '');
  if ($title === '') error_out('Missing title', 400);
  $st = $pdo->prepare("INSERT INTO feature_requests (title, notes, created_at) VALUES (?,?,NOW())");
  $st->execute([$title, $notes ?: null]);
  json_out(['ok'=>true,'id'=>$pdo->lastInsertId()]);
}

if ($method === 'PATCH') {
  $data = get_json();
  $id = (int)($data['id'] ?? 0);
  if (!$id) error_out('Missing id',400);
  $sets=[]; $vals=[];
  foreach(['title','notes','is_done'] as $col){
    if(array_key_exists($col,$data)){ $sets[] = "`$col`=?"; $vals[]=$data[$col]; }
  }
  if (!$sets) error_out('No fields',400);
  $sets[] = 'updated_at=NOW()';
  $vals[]=$id;
  $sql = 'UPDATE feature_requests SET '.implode(',',$sets).' WHERE id=?';
  $st=$pdo->prepare($sql); $st->execute($vals);
  json_out(['ok'=>true,'updated'=>$st->rowCount()]);
}

if ($method === 'DELETE') {
  $data = get_json();
  $id = (int)($data['id'] ?? 0);
  if (!$id) error_out('Missing id',400);
  $st = $pdo->prepare('DELETE FROM feature_requests WHERE id=?');
  $st->execute([$id]);
  json_out(['ok'=>true,'deleted'=>$st->rowCount()]);
}

error_out('Method not allowed',405);
