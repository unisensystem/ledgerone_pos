<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $list_name = trim($data['listName'] ?? '');
    $type = $data['type'] ?? 'customer';
    $entities = $data['entities'] ?? [];
    $items = $data['items'] ?? [];
    
    if (empty($list_name)) {
        throw new Exception('List name is required');
    }
    
    if (empty($entities)) {
        throw new Exception('At least one ' . ($type === 'customer' ? 'customer' : 'supplier') . ' is required');
    }
    
    if (empty($items)) {
        throw new Exception('At least one item is required');
    }
    
    $pdo->beginTransaction();
    
    // Generate list code
    $stmt = $pdo->prepare("SELECT list_code FROM rate_list WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        preg_match('/\d+$/', $row['list_code'], $matches);
        $last_num = isset($matches[0]) ? intval($matches[0]) : 0;
        $new_num = $last_num + 1;
        $list_code = 'RL-' . str_pad($new_num, 3, '0', STR_PAD_LEFT);
    } else {
        $list_code = 'RL-001';
    }
    
    // Insert rate list
    $stmt = $pdo->prepare("INSERT INTO rate_list (tenant_id, list_code, list_name) VALUES (?, ?, ?)");
    $stmt->execute([$tenant_id, $list_code, $list_name]);
    $rate_list_id = $pdo->lastInsertId();
    
    // Insert rate list items
    $stmt = $pdo->prepare("INSERT INTO rate_list_items (tenant_id, rate_list_id, customer_id, supplier_id, product_id, rate, quantity, foc_quantity, trade_offer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($items as $item) {
        foreach ($entities as $entity) {
            $customer_id = $type === 'customer' ? $entity['id'] : null;
            $supplier_id = $type === 'supplier' ? $entity['id'] : null;
            
            $stmt->execute([
                $tenant_id,
                $rate_list_id,
                $customer_id,
                $supplier_id,
                $item['product']['id'],
                $item['salePrice'],
                $item['quantity'],
                $item['focQuantity'],
                $item['tradeOffer']
            ]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Rate list created successfully',
        'rate_list_id' => $rate_list_id,
        'list_code' => $list_code
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
