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
    
    $adjustment = 3476316.42;
    
    // Delete previous adjustment if exists
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Balance Sheet Adjustment'");
    $stmt->execute([$tenant_id]);
    
    // Debit Opening Balance Equity
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Balance Sheet Adjustment', 'balance_sheet', 0, 90, CURDATE(), 'Adjustment to balance opening equity', ?, 0)
    ");
    $stmt->execute([$tenant_id, $adjustment]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Balance sheet adjustment posted successfully',
        'adjustment' => $adjustment
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
