<?php
declare(strict_types=1);
ini_set('display_errors', '0');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (str_contains($path, "\0") || str_contains($path, '..')) {
    http_response_code(400);
    exit;
}
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}
$target = __DIR__ . $path;
if (is_file($target)) {
    if (str_ends_with(strtolower($path), '.php')) {
        if (!str_starts_with($path, '/proge/')) {
            http_response_code(404);
            return true;
        }
        require $target;
        return true;
    }
    return false;
}
if (str_starts_with($path, '/site/')) {
    http_response_code(404);
    return true;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
return true;
