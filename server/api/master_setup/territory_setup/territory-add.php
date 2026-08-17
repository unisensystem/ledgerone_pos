<?php
require_once '../../../../includes/connection.php';

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
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'region':
            $stmt = $pdo->prepare("INSERT INTO regions (tenant_id, country_id, region_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['country_id'], $data['name']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'city':
            $stmt = $pdo->prepare("INSERT INTO cities (tenant_id, region_id, city_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['region_id'], $data['name']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'zone':
            $stmt = $pdo->prepare("INSERT INTO city_zones (tenant_id, city_id, city_zone_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['city_id'], $data['name']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'area':
            $stmt = $pdo->prepare("INSERT INTO areas (tenant_id, city_zone_id, area_name) VALUES (?, ?, ?)");
            $stmt->execute([$tenant_id, $data['city_zone_id'], $data['name']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}