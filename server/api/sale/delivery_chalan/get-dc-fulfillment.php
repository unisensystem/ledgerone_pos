<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();

$tenant_id  = $_SESSION['tenant_id'] ?? null;
$invoice_id = $_GET['invoice_id']    ?? null;

if (!$tenant_id) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if (!$invoice_id){ http_response_code(400); echo json_encode(['success'=>false,'message'=>'invoice_id required']); exit; }

try {
    // Invoiced items
    $invStmt = $pdo->prepare("
        SELECT sii.product_id, p.name as product_name, sii.uom_id, u.uom_name,
               SUM(sii.quantity) as invoiced_qty
        FROM sale_invoice_items sii
        LEFT JOIN products p ON sii.product_id = p.id
        LEFT JOIN uom u      ON sii.uom_id = u.id
        WHERE sii.sale_invoice_id = ? AND sii.tenant_id = ?
        GROUP BY sii.product_id, sii.uom_id
    ");
    $invStmt->execute([$invoice_id, $tenant_id]);
    $invoicedItems = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    // Delivered items across all DCs for this invoice
    $dcStmt = $pdo->prepare("
        SELECT dci.product_id, dci.uom_id, SUM(dci.quantity) as delivered_qty
        FROM delivery_chalan_items dci
        JOIN delivery_chalan dc ON dci.delivery_chalan_id = dc.id
        WHERE dc.sale_invoice_id = ? AND dc.tenant_id = ?
        GROUP BY dci.product_id, dci.uom_id
    ");
    $dcStmt->execute([$invoice_id, $tenant_id]);
    $deliveredMap = [];
    foreach ($dcStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deliveredMap[$d['product_id'] . '_' . $d['uom_id']] = floatval($d['delivered_qty']);
    }

    $fulfillment = [];
    foreach ($invoicedItems as $item) {
        $invoicedQty  = floatval($item['invoiced_qty']);
        $deliveredQty = $deliveredMap[$item['product_id'] . '_' . $item['uom_id']] ?? 0;
        $remaining    = $invoicedQty - $deliveredQty;
        $fulfillment[] = [
            'product_name'        => $item['product_name'],
            'uom_name'            => $item['uom_name'],
            'invoiced_qty'        => $invoicedQty,
            'delivered_qty'       => $deliveredQty,
            'remaining_qty'       => $remaining,
            'fulfillment_percent' => $invoicedQty > 0 ? round(($deliveredQty / $invoicedQty) * 100, 2) : 0,
        ];
    }

    echo json_encode(['success' => true, 'fulfillment' => $fulfillment]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
