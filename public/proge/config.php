<?php
declare(strict_types=1);

$envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
        if ($key !== '' && getenv($key) === false) putenv($key . '=' . $value);
    }
}

function json_response(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(int $status, string $message): never {
    json_response($status, ['status' => 'error', 'message' => $message]);
}

$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', getenv('APP_ORIGINS') ?: 'http://localhost:3000,http://127.0.0.1:3000,http://localhost:8000,http://127.0.0.1:8000'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
    fail(403, 'Request origin is not allowed.');
}
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    if ($origin === '') fail(403, 'Origin header required.');
    http_response_code(204);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    $secureCookie = (getenv('SESSION_SECURE') === '1') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secureCookie, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'poi_management_system';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    if ($user === '') throw new RuntimeException('DB_USER is not configured.');
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    fail(503, 'Database is unavailable. Check the server configuration.');
}

function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method . ', OPTIONS');
        fail(405, 'Method not allowed.');
    }
}

function json_input(): array {
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') fail(415, 'Content-Type must be application/json.');
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if (strlen($raw) > 65536) fail(413, 'Request body is too large.');
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) fail(400, 'Invalid JSON body.');
    return $data;
}

function require_auth(): array {
    if (empty($_SESSION['user_id'])) fail(401, 'Sign in required.');
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, role, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_regenerate_id(true);
        fail(401, 'Sign in required.');
    }
    return $user;
}

function require_admin(): array {
    $user = require_auth();
    if ($user['role'] !== 'admin') fail(403, 'Administrator access required.');
    return $user;
}

function require_csrf(): void {
    if (($_SERVER['HTTP_ORIGIN'] ?? '') === '') fail(403, 'Origin header required.');
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) fail(403, 'Invalid CSRF token. Refresh and try again.');
}

function validate_origin_for_write(): void {
    if (($_SERVER['HTTP_ORIGIN'] ?? '') === '') fail(403, 'Origin header required.');
}

function record_activity(int $userId, string $action, string $description): void {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, mb_substr($description, 0, 500)]);
}
