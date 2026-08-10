<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

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
