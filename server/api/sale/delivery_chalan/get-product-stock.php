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

$product_id = $_GET['product_id'] ?? null;
$branch_id = $_GET['branch_id'] ?? null;

if (!$product_id) {
    echo json_encode(['success' => true, 'stock' => 0, 'unit_name' => '']);
    exit;
}

try {
    // Get product's inventory account and base unit
    $accountStmt = $pdo->prepare("
        SELECT p.inventory_account_id, p.default_unit_id, base_u.uom_name, base_u.id as base_unit_id
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
        WHERE p.id = ?
    ");
    $accountStmt->execute([$product_id]);
    $productData = $accountStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$productData) {
        echo json_encode(['success' => true, 'stock' => 0, 'unit_name' => '']);
        exit;
    }
    
    $inventoryAccountId = $productData['inventory_account_id'];
    $unitName = $productData['uom_name'] ?? '';
    $baseUnitId = $productData['base_unit_id'];
    
    // Get stock from stock_ledger with conversion factors applied (same as stock position)
    $query = "
        SELECT 
            COALESCE(SUM(
                (sl.qty_in * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)) - 
                (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1))
            ), 0) as stock
        FROM stock_ledger sl
        LEFT JOIN uom u ON sl.unit_id = u.id
        LEFT JOIN product_uom_conversions puc ON sl.product_id = puc.product_id AND sl.unit_id = puc.uom_id
        WHERE sl.tenant_id = ? AND sl.product_id = ? AND sl.account_id = ?
    ";
    
    $params = [$tenant_id, $product_id, $inventoryAccountId];
    
    if ($branch_id) {
        $query .= " AND sl.branch_id = ?";
        $params[] = $branch_id;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stock = floatval($result['stock'] ?? 0);
    
    echo json_encode([
        'success' => true, 
        'stock' => $stock,
        'unit_name' => $unitName
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
