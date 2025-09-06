<?php
// api/jobs.php — full CRUD

require_once __DIR__ . '/_util.php';
require_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
 Table (live):
  id (uint PK), title, notes,
  status ENUM('available','accepted','scheduled','cancelled') DEFAULT 'available',
  posted_by VARCHAR(64) NULL,
  accepted_by VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
*/

// ---------- GET: list (optional ?status=) ----------
if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    if ($status) {
        $st = $pdo->prepare("SELECT * FROM jobs WHERE status = ? ORDER BY created_at DESC");
        $st->execute([$status]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'items' => $rows]);
    } else {
        $st = $pdo->query("SELECT * FROM jobs ORDER BY created_at DESC");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'items' => $rows]);
    }
}

// ---------- POST: create ----------
if ($method === 'POST') {
    $data = get_json();

    if (!isset($data['title']) || trim($data['title']) === '') {
        error_out('Missing job title', 400);
    }

    // *** START FIX ***
    if (empty($user['google_sub'])) {
         error_out('Cannot create job: Invalid user session data', 401);
    }

    $st = $pdo->prepare("
        INSERT INTO jobs (title, notes, posted_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $st->execute([
        $data['title'],
        $data['notes'] ?? null,
        $user['google_sub'] // <-- PASS THE USER SUB ID HERE
    ]);
    // *** END FIX ***

    json_out(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

// ---------- PATCH: update fields by id ----------
if ($method === 'PATCH') {
    $user = require_auth();
    $data = get_json();
    $id = $data['id'] ?? null;
    if (!$id) error_out('Missing id', 400);

    // *** START FIX ***
    // If status is being set to 'accepted', force 'accepted_by' to be the current user's Google SUB.
    // This aligns with your live schema (cal.sql) which uses a VARCHAR(64) for this column.
    if (isset($data['status']) && $data['status'] === 'accepted') {
        if (empty($user['google_sub'])) {
            error_out('Cannot accept job: Invalid user session data', 401);
        }
        // Force this key into the data array so the loop below picks it up.
        $data['accepted_by'] = $user['google_sub'];
    }
    // *** END FIX ***

    // Allow updates to these columns only
    $allowed = [
        'title',
        'notes',
        'status',
        'posted_by',
        'accepted_by'
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

    $sets[] = "updated_at = NOW()";
    $vals[] = $id;

    $sql = "UPDATE jobs SET " . implode(", ", $sets) . " WHERE id = ?";
    $st = $pdo->prepare($sql);
    $st->execute($vals);

    json_out(['ok' => true, 'updated' => $st->rowCount()]);
}

// ---------- DELETE: by id ----------
if ($method === 'DELETE') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    $id = $data['id'] ?? ($_GET['id'] ?? null);
    if (!$id) error_out('Missing id', 400);

    $st = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
    $st->execute([$id]);

    json_out(['ok' => true, 'deleted' => $st->rowCount()]);
}

// Fallback
error_out('Method not allowed', 405);
