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
        FROM outward_gatepass 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);
    $gatepass = $stmt->fetch();
    
    if (!$gatepass) {
        throw new Exception('Gatepass not found');
    }
    
    // Fetch items with engine_no and chassis_no
    $stmt = $pdo->prepare("
        SELECT ogi.product_id, ogi.unit_id, ogi.quantity, ogi.engine_no, ogi.chassis_no, p.name as product_name
        FROM outward_gatepass_items ogi
        LEFT JOIN products p ON ogi.product_id = p.id
        WHERE ogi.gatepass_id = ? AND ogi.tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);
    $items = $stmt->fetchAll();
    
    $gatepass['items'] = $items;
    
    echo json_encode(['success' => true, 'data' => $gatepass]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
