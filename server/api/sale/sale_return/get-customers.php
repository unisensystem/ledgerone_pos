<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$tenant_id = $auth['tenant_id'];

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