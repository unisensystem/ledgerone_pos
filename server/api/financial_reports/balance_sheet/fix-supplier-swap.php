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
    
    // Swap supplier opening amounts
    $stmt = $pdo->prepare("
        UPDATE suppliers 
        SET 
            opening_debit_amount = opening_credit_amount,
            opening_credit_amount = opening_debit_amount
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $updated = $stmt->rowCount();
    
    // Delete all supplier opening ledger entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'supplier_opening'");
    $stmt->execute([$tenant_id]);
    
    // Recreate supplier opening entries with correct amounts
    $stmt = $pdo->prepare("SELECT id, supplier_name, opening_debit_amount, opening_credit_amount FROM suppliers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0)");
    $stmt->execute([$tenant_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $entries = 0;
    foreach ($suppliers as $supplier) {
        if ($supplier['opening_debit_amount'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_debit_amount']]);
            $entries += 2;
        }
        
        if ($supplier['opening_credit_amount'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_credit_amount']]);
            $entries += 2;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Supplier opening balances swapped and fixed',
        'data' => [
            'suppliers_updated' => $updated,
            'ledger_entries_created' => $entries
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
