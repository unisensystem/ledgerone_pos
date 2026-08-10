<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM products 
        WHERE tenant_id = ? 
        AND is_active = 1 
        AND product_type IN ('physical', 'service')
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
