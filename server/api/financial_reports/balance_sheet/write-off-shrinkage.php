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
    
    // Write off 99,304 as inventory shrinkage/loss
    // Credit Purchases, Debit Inventory Shrinkage Expense (or use existing expense account)
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Inventory Adjustment', 'inventory_adjustment', 0, 95, CURDATE(), 'Inventory shrinkage/adjustment', 99304, 0)
    ");
    $stmt->execute([$tenant_id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Inventory Adjustment', 'inventory_adjustment', 0, 19, CURDATE(), 'Inventory shrinkage/adjustment', 0, 99304)
    ");
    $stmt->execute([$tenant_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Inventory shrinkage/adjustment recorded'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}