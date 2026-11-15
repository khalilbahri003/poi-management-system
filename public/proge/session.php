<?php
require_once __DIR__ . '/config.php';
require_method('GET');
$user = null;
if (!empty($_SESSION['user_id'])) {
    try { $user = require_auth(); }
    catch (Throwable $e) { error_log('Session lookup failed: ' . $e->getMessage()); fail(503, 'Could not verify session.'); }
}
json_response(200, ['status' => 'success', 'user' => $user ? ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']] : null, 'csrf_token' => $_SESSION['csrf_token']]);
