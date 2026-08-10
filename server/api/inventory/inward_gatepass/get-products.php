<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, u.uom_name 
        FROM products p 
        LEFT JOIN uom u ON p.default_unit_id = u.id 
        WHERE p.tenant_id = ? AND p.is_active = 1 
        ORDER BY p.name ASC
    ");
    $stmt->execute([$tenant_id]);
    $products = [];
    
    while ($row = $stmt->fetch()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'unit' => $row['uom_name'] ?? ''
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $products]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
