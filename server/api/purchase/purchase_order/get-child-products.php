<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$parent_id = $_GET['parent_id'] ?? null;

if (!$parent_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parent ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, code, name, default_unit_id, stock_affects, invoice_affects
        FROM products 
        WHERE tenant_id = ? AND parent_product_id = ? AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute([$tenant_id, $parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'children' => $children]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
