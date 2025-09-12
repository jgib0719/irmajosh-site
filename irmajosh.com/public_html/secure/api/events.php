<?php
// api/events.php — full CRUD + date filters

require_once __DIR__ . '/_util.php';
require_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
 Table (live):
  id (uint PK), title, date (NOT NULL), time (NULL), notes,
  is_done TINYINT(1) DEFAULT 0, is_urgent TINYINT(1) DEFAULT 0,
  source_type ENUM('request','job') NULL, source_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
*/

// ---------- GET: list with optional ?date=YYYY-MM-DD or ?month=YYYY-MM ----------
if ($method === 'GET') {
    $date  = $_GET['date']  ?? null;
    $month = $_GET['month'] ?? null;

    if ($date) {
        $st = $pdo->prepare("SELECT * FROM events WHERE date = ? ORDER BY time ASC");
        $st->execute([$date]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'items' => $rows]);
    }

    if ($month) {
        // month like 2025-08
        $st = $pdo->prepare("
            SELECT * FROM events
            WHERE DATE_FORMAT(date, '%Y-%m') = ?
            ORDER BY date ASC, time ASC
        ");
        $st->execute([$month]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'items' => $rows]);
    }

    // default: all (careful in prod—consider pagination)
    $rows = $pdo->query("SELECT * FROM events ORDER BY date DESC, time ASC")->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok' => true, 'items' => $rows]);
}

// ---------- POST: create ----------
if ($method === 'POST') {
    try {
        $data = get_json();

        if (!isset($data['title']) || trim($data['title']) === '') {
            error_out('Missing event title', 400);
        }
        if (!isset($data['date']) || trim($data['date']) === '') {
            error_out('Missing event date (YYYY-MM-DD)', 400);
        }

    $st = $pdo->prepare("\n            INSERT INTO events (title, notes, date, time, is_done, is_urgent, source_type, source_id, created_at)\n            VALUES (?, ?, ?, ?, 0, 0, ?, ?, NOW())\n        ");
        $st->execute([
            $data['title'],
            $data['notes']       ?? null,
            $data['date'],
            $data['time']        ?? null,
            $data['source_type'] ?? null,
            $data['source_id']   ?? null
        ]);

        json_out(['ok' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Exception $ex) {
        // Return JSON error (include message for debugging). Remove in prod.
        error_out('Server error: ' . $ex->getMessage(), 500);
    }
}

// ---------- PATCH: update fields by id ----------
if ($method === 'PATCH') {
    $data = get_json();
    $id = $data['id'] ?? null;
    if (!$id) error_out('Missing id', 400);

    $allowed = [
        'title',
        'notes',
        'date',
        'time',
        // 'is_done' must be explicitly confirmed by the UI to avoid automated/cron changes
        'is_done',
        'is_urgent',
        'source_type',
        'source_id'
    ];

    $sets = [];
    $vals = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $data)) {
            // Protect against automated marking: only allow is_done -> 1 when client provided
            // a confirming flag 'complete_via_ui' in the JSON payload.
            if ($col === 'is_done' && (int)$data['is_done'] === 1) {
                $confirmed = $data['complete_via_ui'] ?? false;
                // accept true, '1', or 1
                if (!($confirmed === true || $confirmed === 1 || $confirmed === '1')) {
                    error_out('Changing is_done requires explicit UI confirmation', 400);
                }
            }
            $sets[] = "`$col` = ?";
            $vals[] = $data[$col];
        }
    }
    if (empty($sets)) error_out('No updatable fields provided', 400);

    $sets[] = "updated_at = NOW()";
    $vals[] = $id;

    $sql = "UPDATE events SET " . implode(", ", $sets) . " WHERE id = ?";
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

    $st = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $st->execute([$id]);

    json_out(['ok' => true, 'deleted' => $st->rowCount()]);
}

// Fallback
error_out('Method not allowed', 405);
