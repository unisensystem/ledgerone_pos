<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE (tenant_id = ? OR tenant_id = 0) AND sub_account_id = 1");
    $stmt->execute([$tenant_id]);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'payment_methods' => $payment_methods]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
