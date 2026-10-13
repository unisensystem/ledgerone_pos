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

$supplier_id = $_GET['supplier_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

if (!$supplier_id || !$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Supplier ID and Product ID are required']);
    exit;
}

try {
    // Get rate from rate_list_items for supplier (customer_id IS NULL or 0)
    $stmt = $pdo->prepare("
        SELECT rate, quantity, foc_quantity, trade_offer
        FROM rate_list_items
        WHERE tenant_id = ? 
        AND supplier_id = ? 
        AND product_id = ?
        AND (customer_id IS NULL OR customer_id = 0)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $supplier_id, $product_id]);
    $rateItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateItem) {
        echo json_encode([
            'success' => true,
            'price' => floatval($rateItem['rate']),
            'quantity' => intval($rateItem['quantity']),
            'foc_quantity' => intval($rateItem['foc_quantity']),
            'trade_offer' => floatval($rateItem['trade_offer'])
        ]);
    } else {
        // Fallback to product's purchase price
        $productStmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ? AND tenant_id = ?");
        $productStmt->execute([$product_id, $tenant_id]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'price' => floatval($product['purchase_price'] ?? 0),
            'quantity' => 0,
            'foc_quantity' => 0,
            'trade_offer' => 0
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
