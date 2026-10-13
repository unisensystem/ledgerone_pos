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
    $group_id = $_GET['group_id'] ?? null;
    
    if (!$group_id) {
        echo json_encode(['success' => false, 'message' => 'group_id is required']);
        exit;
    }

    // Get all units in this UOM group, ordered by is_base_unit DESC and conversion_factor DESC
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.uom_name,
            u.uom_type,
            u.is_base_unit,
            u.base_unit_id,
            u.conversion_factor
        FROM uom_group_units ugu
        JOIN uom u ON ugu.uom_id = u.id
        WHERE ugu.uom_group_id = ? 
            AND u.tenant_id = ?
        ORDER BY u.is_base_unit DESC, u.conversion_factor DESC, u.uom_name ASC
    ");
    
    $stmt->execute([$group_id, $tenant_id]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($units)) {
        echo json_encode(['success' => false, 'message' => 'No units found for this group']);
        exit;
    }
    
    echo json_encode(['success' => true, 'units' => $units]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
