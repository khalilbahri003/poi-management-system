<?php
declare(strict_types=1);
$index = __DIR__ . '/site/index.html';
if (!is_file($index)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "L'application n'est pas construite. Lancez pnpm build.";
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
readfile($index);
