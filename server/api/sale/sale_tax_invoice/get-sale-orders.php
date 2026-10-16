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

try {
    // Only orders not yet turned into an invoice — once a sale_invoice
    // references a sale_order (sale_invoice.sale_order_id), that order is
    // done and shouldn't be pickable again here. Also carries the fields
    // the dropdown needs to show "date - number - salesman - customer" so
    // it's actually searchable instead of a bare bill_no list.
    $stmt = $pdo->prepare("
        SELECT so.id, so.bill_no, so.sale_date, c.customer_name,
               e.full_name AS sale_officer_name
        FROM sale_order so
        JOIN customers c ON c.id = so.customer_id
        LEFT JOIN employees e ON e.id = so.sale_officer_id
        WHERE so.tenant_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM sale_invoice si
              WHERE si.sale_order_id = so.id AND si.tenant_id = so.tenant_id
          )
        ORDER BY so.sale_date DESC, so.bill_no DESC
    ");
    $stmt->execute([$tenant_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
