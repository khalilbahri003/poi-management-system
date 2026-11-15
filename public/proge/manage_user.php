<?php
require_once __DIR__ . '/config.php';
require_method('POST');
require_csrf();
$admin = require_admin();
$data = json_input();
$action = $_GET['action'] ?? '';
try {
    if ($action === 'add') {
        $username = trim(is_string($data['username'] ?? null) ? $data['username'] : '');
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $role = $data['role'] ?? 'worker';
        if (!preg_match('/^[\p{L}\p{N}_.-]{3,32}$/u', $username)) fail(400, 'Username must be 3–32 letters, numbers, dot, dash or underscore.');
        if (strlen($password) < 12 || strlen($password) > 72) fail(400, 'Password must be 12–72 bytes.');
        if (!in_array($role, ['worker', 'admin'], true)) fail(400, 'Role is invalid.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute([$username, $hash, $role]);
        record_activity((int)$admin['id'], 'user_created', 'Created ' . $role . ' account');
        json_response(201, ['status' => 'success', 'message' => 'User created.']);
    }
    if ($action === 'delete') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === (int)$admin['id']) fail(400, 'Invalid user id.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? FOR UPDATE'); $stmt->execute([$id]); $target = $stmt->fetch();
        if (!$target) fail(404, 'User not found.');
        if ($target['role'] === 'admin' && $target['status'] === 'active') {
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' FOR UPDATE")->fetchAll();
            if (count($admins) <= 1) fail(409, 'The last active administrator cannot be deleted.');
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        record_activity((int)$admin['id'], 'user_deleted', 'Deleted a user account');
        $pdo->commit();
        json_response(200, ['status' => 'success', 'message' => 'User deleted.']);
    }
    fail(400, 'Unknown user action.');
} catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); if ((string)$e->getCode() === '23000') fail(409, 'That username is already in use.'); error_log('User management failed: ' . $e->getMessage()); fail(500, 'Could not update users.'); }
