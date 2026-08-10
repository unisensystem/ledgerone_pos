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
    $stmt = $pdo->prepare("
        SELECT ug.id, ug.group_name, ug.tenant_id, GROUP_CONCAT(u.uom_name ORDER BY u.uom_name SEPARATOR ', ') as unit_names
        FROM uom_groups ug
        LEFT JOIN uom_group_units ugu ON ug.id = ugu.uom_group_id
        LEFT JOIN uom u ON ugu.uom_id = u.id
        WHERE ug.tenant_id = ? OR ug.tenant_id = 0
        GROUP BY ug.id, ug.group_name, ug.tenant_id
        ORDER BY ug.group_name
    ");
    $stmt->execute([$tenant_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'groups' => $groups]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
