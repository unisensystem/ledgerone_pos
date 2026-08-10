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

$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Transfer ID is required']);
    exit;
}

try {
    // Get transfer header
    $query = "SELECT * FROM stock_transfer WHERE tenant_id = ? AND id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id, $transfer_id]);
    $transfer = $stmt->fetch();
    
    if (!$transfer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        exit;
    }
    
    // Get transfer items with product default unit
    $query = "SELECT sti.*, p.default_unit_id 
              FROM stock_transfer_items sti
              LEFT JOIN products p ON sti.product_id = p.id
              WHERE sti.tenant_id = ? AND sti.transfer_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id, $transfer_id]);
    $items = $stmt->fetchAll();
    
    $transfer['items'] = $items;
    
    echo json_encode(['success' => true, 'transfer' => $transfer]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
