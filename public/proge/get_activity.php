<?php
require_once __DIR__ . '/config.php';
require_method('GET');
require_admin();
try {
    $rows = $pdo->query("SELECT a.id, a.action_type, a.description, a.created_at, COALESCE(u.username, '[deleted user]') AS username FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 100")->fetchAll();
    json_response(200, ['status' => 'success', 'data' => $rows]);
} catch (PDOException $e) { error_log('Activity read failed: ' . $e->getMessage()); fail(500, 'Could not load activity.'); }
