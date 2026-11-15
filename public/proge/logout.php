<?php
require_once __DIR__ . '/config.php';
require_method('POST');
require_csrf();
$user = require_auth();
record_activity((int)$user['id'], 'logout', 'Signed out');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict']);
}
session_destroy();
json_response(200, ['status' => 'success']);
