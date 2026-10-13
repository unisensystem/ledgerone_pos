<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    $typeConfig = [
        'country' => ['table' => 'countries', 'column' => 'country_name'],
        'region'  => ['table' => 'regions', 'column' => 'region_name'],
        'city'    => ['table' => 'cities', 'column' => 'city_name'],
        'zone'    => ['table' => 'city_zones', 'column' => 'city_zone_name'],
        'area'    => ['table' => 'areas', 'column' => 'area_name'],
    ];

    if (!isset($typeConfig[$type])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
    }

    $table = $typeConfig[$type]['table'];
    $column = $typeConfig[$type]['column'];

    $oldStmt = $pdo->prepare("SELECT $column as old_name FROM $table WHERE id = ? AND tenant_id = ?");
    $oldStmt->execute([$id, $tenant_id]);
    $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $oldName = $oldRow['old_name'] ?? null;

    $stmt = $pdo->prepare("UPDATE $table SET $column = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$name, $id, $tenant_id]);

    if ($oldRow) {
        $editDescription = $oldName !== $name
            ? "Updated $type: \"$oldName\" \u{2192} \"$name\""
            : "Updated $type \"$name\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Territory', $id, $name, $editDescription, ['type' => $type, 'name' => $oldName], ['type' => $type, 'name' => $name]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}