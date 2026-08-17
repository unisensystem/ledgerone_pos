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
    
    // Delete previous closing entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Period Close'");
    $stmt->execute([$tenant_id]);
    
    // Close Purchases (99,304) to Retained Earnings
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Period Close', 'period_close', 0, 19, CURDATE(), 'Close Purchases to Retained Earnings', 0, 99304)
    ");
    $stmt->execute([$tenant_id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Period Close', 'period_close', 0, 93, CURDATE(), 'Close Purchases to Retained Earnings', 99304, 0)
    ");
    $stmt->execute([$tenant_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchases closed to Retained Earnings'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
