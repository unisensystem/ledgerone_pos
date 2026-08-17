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

$customer_id = $_GET['customer_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

if (!$customer_id || !$product_id) {
    echo json_encode(['success' => true, 'history' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            si.sale_date,
            sii.sale_price,
            sii.quantity,
            cur.symbol as currency_symbol
        FROM sale_invoice_items sii
        JOIN sale_invoice si ON sii.sale_invoice_id = si.id
        LEFT JOIN fuelingsys_public.currencies cur ON si.currency_id = cur.id
        WHERE si.tenant_id = ? 
        AND si.customer_id = ? 
        AND sii.product_id = ?
        AND si.status = 'Posted'
        ORDER BY si.sale_date DESC, si.id DESC
        LIMIT 3
    ");
    $stmt->execute([$tenant_id, $customer_id, $product_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
