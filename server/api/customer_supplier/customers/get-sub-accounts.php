<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;

if (!$tenant_id || !$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ? ORDER BY id");
    $stmt->execute([$tenant_id, $customer_id]);
    $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'sub_accounts' => $sub_accounts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
