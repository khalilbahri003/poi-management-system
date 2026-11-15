<?php
declare(strict_types=1);
$base = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$adminName = getenv('TEST_ADMIN_USERNAME') ?: '';
$adminPassword = getenv('TEST_ADMIN_PASSWORD') ?: '';
if ($adminName === '' || $adminPassword === '') {
    fwrite(STDERR, "Set TEST_ADMIN_USERNAME and TEST_ADMIN_PASSWORD to a local test administrator before running.\n");
    exit(2);
}
function check(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}
function request(string $path, string $method = 'GET', ?array $body = null, ?string $cookie = null, ?string $csrf = null, string $origin = 'http://localhost:3000'): array {
    $headers = ['Accept: application/json', 'Origin: ' . $origin];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    if ($cookie !== null && $cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    $options = ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 8];
    $context = stream_context_create(['http' => $options]);
    $raw = @file_get_contents($GLOBALS['base'] . $path, false, $context);
    preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0] ?? '', $matches);
    $status = (int)($matches[1] ?? 0);
    $responseHeaders = $http_response_header ?? [];
    $cookieValue = $cookie;
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) $cookieValue = explode(';', trim(substr($header, 11)))[0];
    }
    return [$status, json_decode($raw ?: '', true), $cookieValue, $responseHeaders];
}
function jsonResponseHas(array $response, string $key): bool { return isset($response[1]) && is_array($response[1]) && array_key_exists($key, $response[1]); }

[$status, $session, $cookie] = request('/proge/session.php');
check($status === 200 && array_key_exists('user', $session) && $session['user'] === null && is_string($session['csrf_token'] ?? null) && is_string($cookie) && $cookie !== '', 'anonymous session provides a CSRF token and session cookie');
$csrf = $session['csrf_token'];
check(request('/proge/get_pois.php', 'GET', null, $cookie)[0] === 401, 'protected POI endpoint rejects anonymous callers');
check(request('/proge/get_users.php', 'GET', null, $cookie)[0] === 401, 'administrator endpoint rejects anonymous callers');
check(request('/proge/manage_user.php?action=add', 'POST', ['username'=>'attacker','password'=>'test-not-a-real-password','role'=>'admin'], $cookie, $csrf)[0] === 401, 'anonymous callers cannot create users or grant roles');
check(request('/proge/login.php', 'POST', ['username'=>'admin','password'=>'admin'])[0] === 401, 'old seeded admin credentials do not work');
check(request('/proge/login.php', 'POST', ['username'=>$adminName,'password'=>$adminPassword], null, null, 'https://evil.example')[0] === 403, 'unapproved origins are rejected');

[$status, $login, $adminCookie] = request('/proge/login.php', 'POST', ['username'=>$adminName,'password'=>$adminPassword]);
check($status === 200 && ($login['user']['role'] ?? '') === 'admin' && is_string($login['csrf_token'] ?? null) && $adminCookie !== $cookie, 'valid admin login succeeds and rotates session id');
$adminId = (int)$login['user']['id'];
$adminCsrf = $login['csrf_token'];
check(request('/proge/logout.php', 'POST', [], $adminCookie, 'wrong-token')[0] === 403, 'invalid CSRF token blocks a write');
$unique = 'security-test-' . bin2hex(random_bytes(4));
$poi = ['name'=>$unique,'type'=>'Kiosk','company'=>'lumiere','geometry_type'=>'radius','lat'=>36.8,'lng'=>10.1,'radius'=>100,'created_by'=>999999];
[$status, $created] = request('/proge/add_poi.php', 'POST', $poi, $adminCookie, $adminCsrf);
check($status === 201, 'authenticated admin can create a validated POI');
[$status, $pois] = request('/proge/get_pois.php', 'GET', null, $adminCookie);
$createdPoi = null;
foreach (($pois['data'] ?? []) as $row) if (($row['name'] ?? '') === $unique) $createdPoi = $row;
check($status === 200 && $createdPoi !== null && (int)$createdPoi['created_by'] === $adminId, 'POI ownership comes from the authenticated session, not submitted user data');
check(request('/proge/get_users.php', 'GET', null, $adminCookie)[0] === 200, 'administrator can read user list');
check(request('/proge/get_activity.php', 'GET', null, $adminCookie)[0] === 200, 'administrator can read activity list');

$workerName = 'test-' . bin2hex(random_bytes(4));
$workerPassword = 'T-only-' . bin2hex(random_bytes(12));
[$status] = request('/proge/manage_user.php?action=add', 'POST', ['username'=>$workerName,'password'=>$workerPassword,'role'=>'worker'], $adminCookie, $adminCsrf);
check($status === 201, 'administrator can create a user with a password hash');
check(request('/proge/manage_user.php?action=add', 'POST', ['username'=>'short','password'=>'tiny','role'=>'worker'], $adminCookie, $adminCsrf)[0] === 400, 'weak user passwords are rejected');
[$status, $workerLogin, $workerCookie] = request('/proge/login.php', 'POST', ['username'=>$workerName,'password'=>$workerPassword]);
check($status === 200 && ($workerLogin['user']['role'] ?? '') === 'worker', 'new worker can sign in');
$workerCsrf = $workerLogin['csrf_token'];
check(request('/proge/get_users.php', 'GET', null, $workerCookie)[0] === 403, 'worker cannot read administrator-only users');
check(request('/proge/get_activity.php', 'GET', null, $workerCookie)[0] === 403, 'worker cannot read administrator-only activity');
check(request('/proge/delete_poi.php', 'POST', ['poi_id'=>$createdPoi['id']], $workerCookie, $workerCsrf)[0] === 403, 'worker cannot delete another user’s POI');
[$status] = request('/proge/add_poi.php', 'POST', ['name'=>$unique.'-worker','type'=>'Kiosk','company'=>'gias','geometry_type'=>'radius','lat'=>36.8,'lng'=>10.1,'radius'=>100], $workerCookie, $workerCsrf);
check($status === 201, 'worker can create a POI');
$workerPois = request('/proge/get_pois.php', 'GET', null, $workerCookie)[1]['data'] ?? [];
$workerPoi = null;
foreach ($workerPois as $row) if (($row['name'] ?? '') === $unique.'-worker') $workerPoi = $row;
check($workerPoi !== null && request('/proge/delete_poi.php', 'POST', ['poi_id'=>$workerPoi['id']], $workerCookie, $workerCsrf)[0] === 200, 'worker can delete their own POI');
check(request('/proge/delete_poi.php', 'POST', ['poi_id'=>$createdPoi['id']], $adminCookie, $adminCsrf)[0] === 200, 'admin can delete a POI');
request('/proge/manage_user.php?action=delete', 'POST', ['id'=>$workerLogin['user']['id']], $adminCookie, $adminCsrf);
[$status] = request('/proge/logout.php', 'POST', [], $adminCookie, $adminCsrf);
check($status === 200, 'logout clears the authenticated session');
check(request('/proge/get_pois.php', 'GET', null, $adminCookie)[0] === 401, 'logged-out session cannot access protected routes');
echo "Security integration checks passed.\n";
