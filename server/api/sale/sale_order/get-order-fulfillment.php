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

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Get ordered quantities
    $orderStmt = $pdo->prepare("
        SELECT 
            soi.product_id,
            p.name as product_name,
            soi.quantity as ordered_qty,
            soi.uom_id,
            u.uom_name,
            soi.parent_row_id
        FROM sale_order_items soi
        LEFT JOIN products p ON soi.product_id = p.id
        LEFT JOIN uom u ON soi.uom_id = u.id
        WHERE soi.sale_invoice_id = ? AND soi.tenant_id = ?
    ");
    $orderStmt->execute([$order_id, $tenant_id]);
    $orderedItems = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get served quantities grouped by product_id + uom_id
    $servedStmt = $pdo->prepare("
        SELECT 
            sii.product_id,
            sii.uom_id,
            SUM(sii.quantity) as served_qty
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        WHERE si.sale_order_id = ? AND si.tenant_id = ? AND si.status = 'Posted'
        GROUP BY sii.product_id, sii.uom_id
    ");
    $servedStmt->execute([$order_id, $tenant_id]);

    // Map: product_id_uom_id => served_qty
    $servedMap = [];
    foreach ($servedStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $servedMap[$s['product_id'] . '_' . $s['uom_id']] = floatval($s['served_qty']);
    }

    // Combine — one row per sale_order_items row, matched by product_id + uom_id
    $fulfillment = [];
    foreach ($orderedItems as $item) {
        if ($item['parent_row_id']) continue;
        $orderedQty = floatval($item['ordered_qty']);
        $servedQty  = $servedMap[$item['product_id'] . '_' . $item['uom_id']] ?? 0;
        $fulfillment[] = [
            'product_name'        => $item['product_name'],
            'uom_name'            => $item['uom_name'],
            'ordered_qty'         => $orderedQty,
            'served_qty'          => $servedQty,
            'remaining_qty'       => $orderedQty - $servedQty,
            'fulfillment_percent' => $orderedQty > 0 ? round(($servedQty / $orderedQty) * 100, 2) : 0,
        ];
    }
    
    echo json_encode(['success' => true, 'fulfillment' => $fulfillment]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
