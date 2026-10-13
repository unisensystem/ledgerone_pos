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
$quantity = $_GET['quantity'] ?? 0;

if (!$customer_id || !$product_id || !$quantity) {
    echo json_encode(['success' => true, 'foc_qty' => 0]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT foc_quantity, quantity, rate, trade_offer 
        FROM rate_list_items 
        WHERE tenant_id = ? AND customer_id = ? AND product_id = ? AND quantity <= ?
        ORDER BY quantity DESC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $customer_id, $product_id, $quantity]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $foc_qty = floor($quantity / $result['quantity']) * $result['foc_quantity'];
        $trade_offer_amt = $quantity * $result['trade_offer'];
        echo json_encode(['success' => true, 'foc_qty' => $foc_qty, 'rate' => $result['rate'], 'trade_offer' => $trade_offer_amt]);
    } else {
        echo json_encode(['success' => true, 'foc_qty' => 0, 'rate' => null, 'trade_offer' => null]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
