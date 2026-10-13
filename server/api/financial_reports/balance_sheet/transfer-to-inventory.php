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
    
    // Transfer unsold purchases (99,304) to Inventory
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Inventory Adjustment', 'inventory_adjustment', 0, 33, CURDATE(), 'Transfer unsold purchases to inventory', 99304, 0)
    ");
    $stmt->execute([$tenant_id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Inventory Adjustment', 'inventory_adjustment', 0, 19, CURDATE(), 'Transfer unsold purchases to inventory', 0, 99304)
    ");
    $stmt->execute([$tenant_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Unsold purchases transferred to inventory'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}