<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    $group_id = $_GET['group_id'] ?? '';
    
    if (empty($group_id)) {
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.uom_name, u.unit_scope, u.is_base_unit
        FROM uom_group_units ugu
        INNER JOIN uom u ON ugu.uom_id = u.id
        WHERE ugu.uom_group_id = ?
        ORDER BY u.uom_name
    ");
    $stmt->execute([$group_id]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'units' => $units]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
