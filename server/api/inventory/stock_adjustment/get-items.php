<?php
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

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $adjustment_id = $_GET['id'] ?? null;
    
    if (!$adjustment_id) {
        throw new Exception('Adjustment ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            p.code as product_code,
            p.name as product_name,
            sai.qty,
            sai.rate,
            sai.stock_value,
            u.uom_name
        FROM stock_adjustment_items sai
        JOIN products p ON sai.product_id = p.id
        LEFT JOIN uom u ON sai.unit_id = u.id
        WHERE sai.tenant_id = ? AND sai.adjustment_id = ?
    ");
    $stmt->execute([$tenant_id, $adjustment_id]);
    $items = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching items: ' . $e->getMessage()
    ]);
}
