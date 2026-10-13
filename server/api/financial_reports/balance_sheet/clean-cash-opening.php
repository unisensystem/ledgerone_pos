<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'CASH_OPENING'");
    $stmt->execute([$tenant_id]);
    $deleted = $stmt->rowCount();
    
    // Now add back ONE correct CASH_OPENING entry
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'CASH_OPENING', 'cash_opening', 0, 1, CURDATE(), 'Cash opening balance', 8573964, 0)
    ");
    $stmt->execute([$tenant_id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'CASH_OPENING', 'cash_opening', 0, 90, CURDATE(), 'Cash opening balance', 0, 8573964)
    ");
    $stmt->execute([$tenant_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'CASH_OPENING entries cleaned and restored',
        'deleted' => $deleted
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
