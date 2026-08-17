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
        SELECT 
            po.id,
            po.order_no,
            po.order_qty,
            po.status,
            po.start_date,
            p.name as product_name,
            p.default_unit_id,
            u.uom_name,
            bom.bom_code
        FROM production_orders po
        INNER JOIN products p ON po.product_id = p.id
        LEFT JOIN uom u ON p.default_unit_id = u.id
        LEFT JOIN bill_of_materials bom ON po.bom_id = bom.id
        WHERE po.tenant_id = ? 
        AND po.status IN ('Planned', 'In Progress', 'Open', 'Completed')
        ORDER BY po.start_date DESC, po.created_at DESC
    ");
    
    $stmt->execute([$tenant_id]);
    $orders = [];
    
    while ($row = $stmt->fetch()) {
        $orders[] = [
            'id' => $row['id'],
            'order_no' => $row['order_no'],
            'product_name' => $row['product_name'],
            'order_qty' => $row['order_qty'],
            'uom_name' => $row['uom_name'] ?? 'Units',
            'status' => $row['status'],
            'start_date' => $row['start_date'],
            'bom_name' => $row['bom_code'] ?? 'N/A'
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $orders]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
