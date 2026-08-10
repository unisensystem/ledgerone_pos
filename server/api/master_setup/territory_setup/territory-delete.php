<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

try {
    switch ($type) {
        case 'country':
            $stmt = $pdo->prepare("DELETE FROM countries WHERE id = ? AND tenant_id = ?");
            break;

        case 'region':
            $stmt = $pdo->prepare("DELETE FROM regions WHERE id = ? AND tenant_id = ?");
            break;

        case 'city':
            $stmt = $pdo->prepare("DELETE FROM cities WHERE id = ? AND tenant_id = ?");
            break;

        case 'zone':
            $stmt = $pdo->prepare("DELETE FROM city_zones WHERE id = ? AND tenant_id = ?");
            break;

        case 'area':
            $stmt = $pdo->prepare("DELETE FROM areas WHERE id = ? AND tenant_id = ?");
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }

    $stmt->execute([$id, $tenant_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}