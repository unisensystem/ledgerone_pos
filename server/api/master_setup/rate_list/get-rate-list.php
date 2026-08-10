<?php
session_start();
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

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rate_list_id = $_GET['id'] ?? null;

if (!$rate_list_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rate list ID is required']);
    exit;
}

try {
    // Get rate list details
    $stmt = $pdo->prepare("SELECT id, list_code, list_name FROM rate_list WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$rate_list_id, $tenant_id]);
    $rateList = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rateList) {
        throw new Exception('Rate list not found');
    }
    
    // Get rate list items with customer and product details
    $stmt = $pdo->prepare("
        SELECT 
            rli.customer_id,
            rli.product_id,
            rli.rate,
            rli.quantity,
            rli.foc_quantity,
            rli.trade_offer,
            c.customer_name,
            c.customer_code,
            p.name as product_name,
            p.code as product_code
        FROM rate_list_items rli
        JOIN customers c ON rli.customer_id = c.id
        JOIN products p ON rli.product_id = p.id
        WHERE rli.rate_list_id = ? AND rli.tenant_id = ?
    ");
    $stmt->execute([$rate_list_id, $tenant_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group customers and get distinct products
    $customers = [];
    $products = [];
    $seenProducts = [];
    
    foreach ($items as $item) {
        $customerId = $item['customer_id'];
        if (!isset($customers[$customerId])) {
            $customers[$customerId] = [
                'id' => $customerId,
                'name' => $item['customer_name'] . ' (' . $item['customer_code'] . ')'
            ];
        }
        
        // Only track by product_id to get distinct products
        $productId = $item['product_id'];
        
        if (!isset($seenProducts[$productId])) {
            $seenProducts[$productId] = true;
            $products[] = [
                'product' => [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'code' => $item['product_code']
                ],
                'salePrice' => $item['rate'],
                'quantity' => $item['quantity'],
                'focQuantity' => $item['foc_quantity'],
                'tradeOffer' => $item['trade_offer']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'rateList' => [
            'id' => $rateList['id'],
            'listCode' => $rateList['list_code'],
            'listName' => $rateList['list_name'],
            'customers' => array_values($customers),
            'items' => $products
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
