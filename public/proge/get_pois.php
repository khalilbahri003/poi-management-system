<?php
require_once __DIR__ . '/config.php';
require_method('GET');
require_auth();
try {
    $rows = $pdo->query('SELECT p.id, p.name, p.address, p.type, p.lat, p.lng, p.radius, p.geometry_type, p.coordinates, p.company, p.created_by, p.created_at, u.username AS creator_name FROM pois p LEFT JOIN users u ON u.id = p.created_by ORDER BY p.created_at DESC')->fetchAll();
    foreach ($rows as &$row) { $row['id'] = (int)$row['id']; $row['lat'] = $row['lat'] === null ? null : (float)$row['lat']; $row['lng'] = $row['lng'] === null ? null : (float)$row['lng']; $row['radius'] = $row['radius'] === null ? null : (int)$row['radius']; $row['created_by'] = $row['created_by'] === null ? null : (int)$row['created_by']; }
    json_response(200, ['status' => 'success', 'data' => $rows]);
} catch (PDOException $e) { error_log('POI read failed: ' . $e->getMessage()); fail(500, 'Could not load locations.'); }
