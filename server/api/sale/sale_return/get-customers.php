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

try {
    $sql = "SELECT c.id, c.customer_code, c.customer_name, c.current_balance, c.address, c.primary_phone, c.supplier_man_id, e.full_name as supplier_man_name FROM customers c LEFT JOIN employees e ON c.supplier_man_id = e.id WHERE (c.tenant_id = ? OR c.tenant_id = 0) AND c.status = 'ACTIVE'";
    $params = [$tenant_id];
    $sql .= " ORDER BY c.customer_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'customers' => $customers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}