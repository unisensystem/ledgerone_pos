<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Gatepass ID is required']);
    exit;
}

try {
    // Fetch gatepass
    $stmt = $pdo->prepare("
        SELECT gatepass_code, date, company_id, supplier_id, branch_id, container_id 
        FROM inward_gatepass 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);
    $gatepass = $stmt->fetch();
    
    if (!$gatepass) {
        throw new Exception('Gatepass not found');
    }
    
    // Fetch items with engine_no and chassis_no
    $stmt = $pdo->prepare("
        SELECT igi.product_id, igi.unit_id, igi.quantity, igi.engine_no, igi.chassis_no, p.name as product_name
        FROM inward_gatepass_items igi
        LEFT JOIN products p ON igi.product_id = p.id
        WHERE igi.gatepass_id = ? AND igi.tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);
    $items = $stmt->fetchAll();
    
    $gatepass['items'] = $items;
    
    echo json_encode(['success' => true, 'data' => $gatepass]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
