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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Customer ID and Product ID are required']);
    exit;
}

try {
    // Check rate_list_items first
    $stmt = $pdo->prepare("
        SELECT rate, quantity, foc_quantity, trade_offer 
        FROM rate_list_items 
        WHERE tenant_id = ? AND customer_id = ? AND product_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $customer_id, $product_id]);
    $rateItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateItem) {
        echo json_encode([
            'success' => true,
            'price' => floatval($rateItem['rate']),
            'quantity' => intval($rateItem['quantity']),
            'foc_quantity' => intval($rateItem['foc_quantity']),
            'trade_offer' => floatval($rateItem['trade_offer']),
            'source' => 'rate_list'
        ]);
    } else {
        // Fallback to product's trade_price
        $stmt = $pdo->prepare("SELECT trade_price FROM products WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$product_id, $tenant_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'price' => floatval($product['trade_price'] ?? 0),
            'quantity' => 0,
            'foc_quantity' => 0,
            'trade_offer' => 0,
            'source' => 'product'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);}
