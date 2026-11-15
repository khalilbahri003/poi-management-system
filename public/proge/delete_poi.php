<?php
require_once __DIR__ . '/config.php';
require_method('POST');
require_csrf();
$user = require_auth();
$data = json_input();
$id = filter_var($data['poi_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) fail(400, 'Invalid location id.');
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, created_by FROM pois WHERE id = ? FOR UPDATE'); $stmt->execute([$id]); $poi = $stmt->fetch();
    if (!$poi) fail(404, 'Location not found.');
    if ($user['role'] !== 'admin' && (int)$poi['created_by'] !== (int)$user['id']) fail(403, 'You cannot delete this location.');
    $pdo->prepare('DELETE FROM pois WHERE id = ?')->execute([$id]);
    record_activity((int)$user['id'], 'delete_poi', 'Deleted a point of interest');
    $pdo->commit();
    json_response(200, ['status' => 'success', 'message' => 'Location deleted.']);
} catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('POI delete failed: ' . $e->getMessage()); fail(500, 'Could not delete this location.'); }
