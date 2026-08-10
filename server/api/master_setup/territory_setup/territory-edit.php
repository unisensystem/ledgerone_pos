<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$id = $data['id'] ?? 0;
$name = $data['name'] ?? '';

try {
    switch ($type) {
        case 'country':
            $stmt = $pdo->prepare("UPDATE countries SET country_name = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $id, $tenant_id]);
            break;

        case 'region':
            $stmt = $pdo->prepare("UPDATE regions SET region_name = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $id, $tenant_id]);
            break;

        case 'city':
            $stmt = $pdo->prepare("UPDATE cities SET city_name = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $id, $tenant_id]);
            break;

        case 'zone':
            $stmt = $pdo->prepare("UPDATE city_zones SET city_zone_name = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $id, $tenant_id]);
            break;

        case 'area':
            $stmt = $pdo->prepare("UPDATE areas SET area_name = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $id, $tenant_id]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}