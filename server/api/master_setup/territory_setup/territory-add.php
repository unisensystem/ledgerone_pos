<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';

try {
    switch ($type) {
        case 'country':
            $stmt = $pdo->prepare("INSERT INTO countries (tenant_id, country_name) VALUES (?, ?)");
            $stmt->execute([$tenant_id, $data['name']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $tenant_id, $user_id, 'create', 'Territory', $newId, $data['name'], "Created country \"{$data['name']}\"", null, ['type' => 'country', 'name' => $data['name']]);
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        case 'region':
            $stmt = $pdo->prepare("INSERT INTO regions (tenant_id, country_id, region_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['country_id'], $data['name']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $tenant_id, $user_id, 'create', 'Territory', $newId, $data['name'], "Created region \"{$data['name']}\"", null, ['type' => 'region', 'name' => $data['name'], 'country_id' => $data['country_id']]);
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        case 'city':
            $stmt = $pdo->prepare("INSERT INTO cities (tenant_id, region_id, city_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['region_id'], $data['name']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $tenant_id, $user_id, 'create', 'Territory', $newId, $data['name'], "Created city \"{$data['name']}\"", null, ['type' => 'city', 'name' => $data['name'], 'region_id' => $data['region_id']]);
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        case 'zone':
            $stmt = $pdo->prepare("INSERT INTO city_zones (tenant_id, city_id, city_zone_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['city_id'], $data['name']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $tenant_id, $user_id, 'create', 'Territory', $newId, $data['name'], "Created zone \"{$data['name']}\"", null, ['type' => 'zone', 'name' => $data['name'], 'city_id' => $data['city_id']]);
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        case 'area':
            $stmt = $pdo->prepare("INSERT INTO areas (tenant_id, city_zone_id, area_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['city_zone_id'], $data['name']]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $tenant_id, $user_id, 'create', 'Territory', $newId, $data['name'], "Created area \"{$data['name']}\"", null, ['type' => 'area', 'name' => $data['name'], 'city_zone_id' => $data['city_zone_id']]);
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}