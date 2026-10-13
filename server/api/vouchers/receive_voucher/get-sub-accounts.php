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
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

$customer_id = $_GET['customer_id'] ?? '';

if (!$customer_id) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, sub_account_name 
        FROM customer_sub_accounts 
        WHERE tenant_id = ? AND customer_id = ?
        ORDER BY sub_account_name
    ");
    $stmt->execute([$tenant_id, $customer_id]);
    $subAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $subAccounts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
