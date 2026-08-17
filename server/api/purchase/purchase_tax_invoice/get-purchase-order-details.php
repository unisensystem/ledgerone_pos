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

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Get purchase order data
    $stmt = $pdo->prepare("
        SELECT 
            po.*,
            s.supplier_code,
            s.supplier_name,
            po.company_id
        FROM purchase_order po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ? AND po.tenant_id = ?
    ");
    $stmt->execute([$order_id, $tenant_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    // Get purchase order items
    $itemStmt = $pdo->prepare("
        SELECT 
            poi.*,
            p.name as product_name,
            p.code as product_code
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.id
        WHERE poi.purchase_invoice_id = ? AND poi.tenant_id = ?
    ");
    $itemStmt->execute([$order_id, $tenant_id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
