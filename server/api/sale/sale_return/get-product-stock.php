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
    echo json_encode(['success' => true, 'stock' => 0]);
    exit;
}

try {
    $query = "
        SELECT 
            COALESCE(SUM(qty_in), 0) - COALESCE(SUM(qty_out), 0) as stock
        FROM stock_ledger
        WHERE tenant_id = ? AND product_id = ?
    ";
    
    $params = [$tenant_id, $product_id];
    
    if ($branch_id) {
        $query .= " AND branch_id = ?";
        $params[] = $branch_id;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stock' => floatval($result['stock'])]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
