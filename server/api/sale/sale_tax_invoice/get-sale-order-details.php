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
    // Get order data
    $stmt = $pdo->prepare("
        SELECT 
            so.*,
            c.customer_code,
            c.customer_name,
            c.address as customer_address,
            c.current_balance
        FROM sale_order so
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE so.id = ? AND so.tenant_id = ?
    ");
    $stmt->execute([$order_id, $tenant_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Get order items
    $itemStmt = $pdo->prepare("
        SELECT 
            soi.*,
            p.name as product_name,
            p.code as product_code,
            p.stock_affects,
            p.invoice_affects
        FROM sale_order_items soi
        LEFT JOIN products p ON soi.product_id = p.id
        WHERE soi.sale_invoice_id = ? AND soi.tenant_id = ?
        ORDER BY COALESCE(soi.parent_row_id, soi.id), soi.id
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
