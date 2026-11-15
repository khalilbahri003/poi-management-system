<?php
require_once __DIR__ . '/config.php';
require_method('POST');
validate_origin_for_write();
$data = json_input();
$username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
$password = is_string($data['password'] ?? null) ? $data['password'] : '';
if ($username === '' || strlen($username) > 50 || $password === '' || strlen($password) > 72) fail(400, 'Enter a valid username and password.');

try {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $hashKey = getenv('APP_IP_HASH_KEY') ?: '';
    if (strlen($hashKey) < 32) { error_log('APP_IP_HASH_KEY is missing or too short.'); fail(503, 'Sign-in is temporarily unavailable.'); }
    $ipHash = hash_hmac('sha256', $clientIp, $hashKey);
    $usernameHash = hash_hmac('sha256', mb_strtolower($username, 'UTF-8'), $hashKey);
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)')->execute();
    $limit = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE (ip_hash = ? OR username_hash = ?) AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)');
    $limit->execute([$ipHash, $usernameHash]);
    if ((int)$limit->fetchColumn() >= 10) fail(429, 'Too many login attempts. Try again later.');
    $pdo->prepare('INSERT INTO login_attempts (ip_hash, username_hash) VALUES (?, ?)')->execute([$ipHash, $usernameHash]);
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, status FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active' || !password_verify($password, $row['password_hash'])) fail(401, 'Invalid username or password.');
    $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash = ? OR username_hash = ?')->execute([$ipHash, $usernameHash]);
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int)$row['id'], 'csrf_token' => bin2hex(random_bytes(32))];
    record_activity((int)$row['id'], 'login', 'Signed in');
    json_response(200, ['status' => 'success', 'user' => ['id' => (int)$row['id'], 'username' => $row['username'], 'role' => $row['role']], 'csrf_token' => $_SESSION['csrf_token']]);
} catch (PDOException $e) {
    error_log('Login request failed: ' . $e->getMessage());
    fail(503, 'Sign-in is temporarily unavailable.');
}
