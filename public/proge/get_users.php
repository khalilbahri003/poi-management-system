<?php
require_once __DIR__ . '/config.php';
require_method('GET');
require_admin();
try { $rows = $pdo->query('SELECT id, username, role, status, created_at FROM users ORDER BY created_at DESC')->fetchAll(); foreach ($rows as &$row) $row['id'] = (int)$row['id']; json_response(200, ['status' => 'success', 'data' => $rows]); }
catch (PDOException $e) { error_log('User list failed: ' . $e->getMessage()); fail(500, 'Could not load users.'); }
