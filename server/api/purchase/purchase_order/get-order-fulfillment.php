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
    $stmt = $pdo->prepare("
        SELECT 
            poi.product_id,
            p.name as product_name,
            u.uom_name,
            poi.quantity as ordered_qty,
            COALESCE((
                SELECT SUM(pii.quantity)
                FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pi.purchase_order_id = :order_id1 AND pii.product_id = poi.product_id
            ), 0) as served_qty,
            (poi.quantity - COALESCE((
                SELECT SUM(pii.quantity)
                FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pi.purchase_order_id = :order_id2 AND pii.product_id = poi.product_id
            ), 0)) as remaining_qty,
            CASE 
                WHEN poi.quantity = 0 THEN 0
                ELSE (COALESCE((
                    SELECT SUM(pii.quantity)
                    FROM purchase_invoice pi
                    JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                    WHERE pi.purchase_order_id = :order_id3 AND pii.product_id = poi.product_id
                ), 0) / poi.quantity * 100)
            END as fulfillment_percent
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.id
        JOIN uom u ON poi.uom_id = u.id
        WHERE poi.purchase_invoice_id = :order_id4 AND poi.tenant_id = :tenant_id
    ");
    
    $stmt->execute([
        'order_id1' => $order_id,
        'order_id2' => $order_id,
        'order_id3' => $order_id,
        'order_id4' => $order_id,
        'tenant_id' => $tenant_id
    ]);
    $fulfillment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'fulfillment' => $fulfillment]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
