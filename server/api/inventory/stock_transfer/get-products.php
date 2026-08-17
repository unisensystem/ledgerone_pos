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
    $query = "SELECT id, code, name, purchase_price, default_unit_id 
              FROM products 
              WHERE tenant_id = :tenant_id AND is_active = 1 AND product_type = 'physical'
              ORDER BY name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['tenant_id' => $tenant_id]);
    $products = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'products' => $products]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
