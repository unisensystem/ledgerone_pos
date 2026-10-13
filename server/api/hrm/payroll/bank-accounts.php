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
    $stmt = $pdo->prepare("SELECT id, CONCAT(bank_name, ' - ', account_number) as name FROM bank_accounts WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'bank_accounts' => $bank_accounts]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
