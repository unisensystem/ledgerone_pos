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
    $stmt = $pdo->prepare("
        SELECT 
            id,
            supplier_code,
            supplier_name,
            supplier_type,
            primary_phone
        FROM suppliers
        WHERE tenant_id = ? 
        AND status = 'ACTIVE'
        AND is_blacklisted = 0
        ORDER BY supplier_name
    ");
    
    $stmt->execute([$tenant_id]);
    $suppliers = [];
    
    while ($row = $stmt->fetch()) {
        $suppliers[] = [
            'id' => $row['id'],
            'supplier_code' => $row['supplier_code'],
            'supplier_name' => $row['supplier_name'],
            'supplier_type' => $row['supplier_type'],
            'primary_phone' => $row['primary_phone'],
            'label' => $row['supplier_name'] . ' (' . $row['supplier_code'] . ')'
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $suppliers]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
