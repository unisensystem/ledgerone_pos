<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $po_id = $_GET['po_id'] ?? null;
    
    if (!$po_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Production order ID required']);
        exit;
    }
    
    // Get PO details
    $stmt_po = $pdo->prepare("
        SELECT po.order_no, po.order_qty, p.name as product_name, u.uom_name
        FROM production_orders po
        INNER JOIN products p ON po.product_id = p.id
        LEFT JOIN uom u ON p.default_unit_id = u.id
        WHERE po.id = ? AND po.tenant_id = ?
    ");
    $stmt_po->execute([$po_id, $tenant_id]);
    $po = $stmt_po->fetch();
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Production order not found']);
        exit;
    }
    
    // Get total expenses
    $stmt_exp = $pdo->prepare("
        SELECT SUM(total_amount) as total_expenses
        FROM production_expenses
        WHERE production_order_id = ? AND tenant_id = ? AND status = 'Posted'
    ");
    $stmt_exp->execute([$po_id, $tenant_id]);
    $expenses = $stmt_exp->fetch();
    
    $total_expenses = $expenses['total_expenses'] ?? 0;
    $per_unit_cost = $po['order_qty'] > 0 ? $total_expenses / $po['order_qty'] : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'order_no' => $po['order_no'],
            'product_name' => $po['product_name'],
            'order_qty' => $po['order_qty'],
            'uom_name' => $po['uom_name'] ?? 'Units',
            'total_expenses' => $total_expenses,
            'per_unit_cost' => $per_unit_cost
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
