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
    $product_id = $_GET['product_id'] ?? '';
    
    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT so.*, b.branch_name, b.branch_type,
               CONCAT(b.branch_name, ' (', b.branch_type, ')') as branch_display
        FROM stock_opening so
        LEFT JOIN branches b ON so.branch_id = b.id
        WHERE so.product_id = ? AND so.tenant_id = ?
        ORDER BY b.branch_name
    ");
    $stmt->execute([$product_id, $tenant_id]);
    $stock_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stock_entries' => $stock_entries]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}