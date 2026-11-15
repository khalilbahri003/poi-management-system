<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/proge/config.php';
$username = trim(getenv('ADMIN_USERNAME') ?: '');
$password = getenv('ADMIN_PASSWORD') ?: '';
if (!preg_match('/^[\p{L}\p{N}_.-]{3,32}$/u', $username)) { fwrite(STDERR, "Set ADMIN_USERNAME to 3–32 valid characters.\n"); exit(1); }
if (strlen($password) < 12 || strlen($password) > 72) { fwrite(STDERR, "Set ADMIN_PASSWORD to a 12–128 character secret.\n"); exit(1); }
$count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($count !== 0) { fwrite(STDERR, "An administrator already exists; use the application to create another account.\n"); exit(1); }
$stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, \'admin\')');
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "First administrator created. Clear ADMIN_PASSWORD from the shell environment now.\n");
