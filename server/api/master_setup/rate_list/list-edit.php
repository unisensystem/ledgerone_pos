<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    
    // Snapshot old header + items for the Activity Log (before mutation)
    $stOld = $pdo->prepare("SELECT list_code, list_name FROM rate_list WHERE id = ? AND tenant_id = ?");
    $stOld->execute([$rate_list_id, $tenant_id]);
    $oldList = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldList) throw new Exception('Rate list not found');

    $stOldItems = $pdo->prepare("SELECT customer_id, supplier_id, product_id, rate, quantity, foc_quantity, trade_offer FROM rate_list_items WHERE rate_list_id = ? AND tenant_id = ? ORDER BY id");
    $stOldItems->execute([$rate_list_id, $tenant_id]);
    $oldItems = $stOldItems->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // Update rate list
    $stmt = $pdo->prepare("UPDATE rate_list SET list_name = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$list_name, $rate_list_id, $tenant_id]);

    // Delete existing items
    $stmt = $pdo->prepare("DELETE FROM rate_list_items WHERE rate_list_id = ? AND tenant_id = ?");
    $stmt->execute([$rate_list_id, $tenant_id]);

    // Insert new items
    $stmt = $pdo->prepare("INSERT INTO rate_list_items (tenant_id, rate_list_id, customer_id, supplier_id, product_id, rate, quantity, foc_quantity, trade_offer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $newItemsForCompare = [];
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
            $newItemsForCompare[] = [
                'customer_id' => $customer_id, 'supplier_id' => $supplier_id, 'product_id' => $item['product']['id'],
                'rate' => (float)$item['salePrice'], 'quantity' => (float)$item['quantity'],
                'foc_quantity' => (float)$item['focQuantity'], 'trade_offer' => (float)$item['tradeOffer']
            ];
        }
    }

    $newList = ['list_code' => $oldList['list_code'], 'list_name' => $list_name];
    $changeDescriptions = buildChangeDescription($oldList, $newList, ['list_name' => 'List Name']);
    if (itemsChanged($oldItems, $newItemsForCompare, ['customer_id', 'supplier_id', 'product_id', 'rate', 'quantity', 'foc_quantity', 'trade_offer'])) {
        $changeDescriptions[] = 'Items updated';
    }
    $editDescription = !empty($changeDescriptions)
        ? "Updated rate list \"{$oldList['list_code']}\": " . implode('; ', $changeDescriptions)
        : "Updated rate list \"{$oldList['list_code']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Rate List', $rate_list_id, $oldList['list_code'], $editDescription, $oldList, $newList);

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
