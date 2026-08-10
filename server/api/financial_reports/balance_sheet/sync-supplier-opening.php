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
    
    // Delete only opening balance entries
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger 
        WHERE tenant_id = ? 
        AND account_id = 14
        AND reference_table = 'suppliers'
        AND transaction_type = 'supplier_opening'
    ");
    $stmt->execute([$tenant_id]);
    $deleted_count = $stmt->rowCount();
    
    // Get all suppliers with opening balances
    $stmt = $pdo->prepare("
        SELECT id, supplier_name, opening_debit_amount, opening_credit_amount 
        FROM suppliers 
        WHERE tenant_id = ?
        AND (opening_debit_amount != 0 OR opening_credit_amount != 0)
    ");
    $stmt->execute([$tenant_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $inserted_count = 0;
    $total_debit = 0;
    $total_credit = 0;
    
    // Insert correct opening balance entries
    foreach ($suppliers as $supplier) {
        // opening_debit_amount = supplier owes us (asset - debit Trade Creditors)
        // opening_credit_amount = we owe supplier (liability - credit Trade Creditors)
        
        if ($supplier['opening_debit_amount'] > 0) {
            // Supplier owes us - Debit Trade Creditors, Credit Opening Balance Equity
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, ?, 0)
            ");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, 0, ?)
            ");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_debit_amount']]);
            
            $total_debit += $supplier['opening_debit_amount'];
        }
        
        if ($supplier['opening_credit_amount'] > 0) {
            // We owe supplier - Credit Trade Creditors, Debit Opening Balance Equity
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, ?, 0)
            ");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, 0, ?)
            ");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_credit_amount']]);
            
            $total_credit += $supplier['opening_credit_amount'];
        }
        
        if ($supplier['opening_debit_amount'] > 0 || $supplier['opening_credit_amount'] > 0) {
            $inserted_count++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Supplier opening balances synced successfully',
        'data' => [
            'deleted_entries' => $deleted_count,
            'inserted_entries' => $inserted_count,
            'total_debit' => $total_debit,
            'total_credit' => $total_credit,
            'net_balance' => $total_credit - $total_debit
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
