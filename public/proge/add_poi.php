<?php
require_once __DIR__ . '/config.php';
require_method('POST');
require_csrf();
$user = require_auth();
$data = json_input();
$name = trim(is_string($data['name'] ?? null) ? $data['name'] : '');
$type = is_string($data['type'] ?? null) ? trim($data['type']) : '';
$company = $data['company'] ?? 'lumiere';
$geometry = $data['geometry_type'] ?? '';
$address = is_string($data['address'] ?? null) ? trim($data['address']) : '';
if ($name === '' || mb_strlen($name) > 100 || $type === '' || mb_strlen($type) > 50 || mb_strlen($address) > 500) fail(400, 'Name, type or address is invalid.');
if (!in_array($company, ['lumiere', 'gias'], true)) fail(400, 'Company is invalid.');
if (!in_array($geometry, ['radius', 'polygon'], true)) fail(400, 'Geometry type is invalid.');
$lat = $lng = $radius = $coordinatesJson = null;
if ($geometry === 'radius') {
    $lat = filter_var($data['lat'] ?? null, FILTER_VALIDATE_FLOAT); $lng = filter_var($data['lng'] ?? null, FILTER_VALIDATE_FLOAT); $radius = filter_var($data['radius'] ?? null, FILTER_VALIDATE_INT);
    if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || $radius === false || $radius < 10 || $radius > 1000) fail(400, 'Coordinates or radius are invalid.');
} else {
    $points = $data['coordinates'] ?? null;
    if (!is_array($points) || count($points) < 3 || count($points) > 200) fail(400, 'A polygon needs 3 to 200 points.');
    $clean = [];
    foreach ($points as $point) {
        if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0]) || !is_numeric($point[1])) fail(400, 'Polygon coordinates are invalid.');
        $a = (float)$point[0]; $b = (float)$point[1];
        if ($a < -90 || $a > 90 || $b < -180 || $b > 180) fail(400, 'Polygon coordinates are out of range.');
        $clean[] = [$a, $b];
    }
    $coordinatesJson = json_encode($clean, JSON_THROW_ON_ERROR);
}
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO pois (name, address, type, lat, lng, radius, geometry_type, coordinates, company, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $address, $type, $lat, $lng, $radius, $geometry, $coordinatesJson, $company, (int)$user['id']]);
    record_activity((int)$user['id'], 'add_poi', 'Added a point of interest');
    $pdo->commit();
    json_response(201, ['status' => 'success', 'message' => 'Location added.', 'id' => (int)$pdo->lastInsertId()]);
} catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('POI create failed: ' . $e->getMessage()); fail(500, 'Could not save this location.'); }
