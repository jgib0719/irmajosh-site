<?php
// api/requests.php — full CRUD

require_once __DIR__ . '/_util.php';
require_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------- GET: list (optional ?status=) ----------
if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    if ($status) {
        $st = $pdo->prepare("SELECT * FROM requests WHERE status = ? ORDER BY created_at DESC");
        $st->execute([$status]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'items' => $rows]);
    } else {
        $st = $pdo->query("SELECT * FROM requests ORDER BY created_at DESC");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'items' => $rows]);
    }
}

// ---------- POST: create ----------
if ($method === 'POST') {
    $data = get_json();

    if (!isset($data['title']) || trim($data['title']) === '') {
        error_out('Missing request title', 400);
    }

    $st = $pdo->prepare("
        INSERT INTO requests (title, notes, date, time, is_done, is_urgent, created_at)
        VALUES (?, ?, ?, ?, 0, 0, NOW())
    ");
    $st->execute([
        $data['title'],
        $data['notes'] ?? null,
        $data['date']  ?? null,
        $data['time']  ?? null
    ]);

    json_out(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

// ---------- PATCH: update selected fields by id ----------
if ($method === 'PATCH') {
    $data = get_json();
    $id = $data['id'] ?? null;
    if (!$id) error_out('Missing id', 400);

    // Allow updates to these columns only
    $allowed = [
        'title',
        'notes',
        'date',
        'time',
        'is_done',
        'is_urgent',
        'status'
    ];

    $sets = [];
    $vals = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $data)) {
            $sets[] = "`$col` = ?";
            $vals[] = $data[$col];
        }
    }
    if (empty($sets)) error_out('No updatable fields provided', 400);

    // Touch updated_at if column exists
    $sets[] = "updated_at = NOW()";

    $vals[] = $id;
    $sql = "UPDATE requests SET " . implode(", ", $sets) . " WHERE id = ?";
    $st = $pdo->prepare($sql);
    $st->execute($vals);

    json_out(['ok' => true, 'updated' => $st->rowCount()]);
}

// ---------- DELETE: by id ----------
if ($method === 'DELETE') {
    // Prefer JSON body, fallback to ?id=
    $data = [];
    $raw = file_get_contents('php://input');
    if ($raw) {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) $data = $tmp;
    }
    $id = $data['id'] ?? ($_GET['id'] ?? null);
    if (!$id) error_out('Missing id', 400);

    $st = $pdo->prepare("DELETE FROM requests WHERE id = ?");
    $st->execute([$id]);

    json_out(['ok' => true, 'deleted' => $st->rowCount()]);
}

// Fallback
error_out('Method not allowed', 405);
