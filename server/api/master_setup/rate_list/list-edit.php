<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    
    $rate_list_id = $data['id'] ?? null;
    $list_name = trim($data['listName'] ?? '');
    $type = $data['type'] ?? 'customer';
    $entities = $data['entities'] ?? [];
    $items = $data['items'] ?? [];
    
    if (!$rate_list_id) {
        throw new Exception('Rate list ID is required');
    }
    
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
    
    // Update rate list
    $stmt = $pdo->prepare("UPDATE rate_list SET list_name = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$list_name, $rate_list_id, $tenant_id]);
    
    // Delete existing items
    $stmt = $pdo->prepare("DELETE FROM rate_list_items WHERE rate_list_id = ? AND tenant_id = ?");
    $stmt->execute([$rate_list_id, $tenant_id]);
    
    // Insert new items
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
        'message' => 'Rate list updated successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
