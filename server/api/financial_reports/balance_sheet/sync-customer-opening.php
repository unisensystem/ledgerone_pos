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
    
    // Get Trade Debtors account ID
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE (tenant_id = ? OR tenant_id = 0) AND name = 'Trade Debtors' LIMIT 1");
    $stmt->execute([$tenant_id]);
    $debtors_account = $stmt->fetch();
    $debtors_account_id = $debtors_account['id'];
    
    // Delete only opening balance entries
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger 
        WHERE tenant_id = ? 
        AND account_id = ?
        AND reference_table = 'customers'
        AND transaction_type = 'customer_opening'
    ");
    $stmt->execute([$tenant_id, $debtors_account_id]);
    $deleted_count = $stmt->rowCount();
    
    // Get all customers with opening balances
    $stmt = $pdo->prepare("
        SELECT id, customer_name, opening_debit_amount, opening_credit_amount 
        FROM customers 
        WHERE tenant_id = ? AND status = 'ACTIVE'
        AND (opening_debit_amount != 0 OR opening_credit_amount != 0)
    ");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $inserted_count = 0;
    $total_debit = 0;
    $total_credit = 0;
    
    // Insert correct opening balance entries
    foreach ($customers as $customer) {
        // opening_debit_amount = customer owes us (asset - debit Trade Debtors)
        // opening_credit_amount = we owe customer (liability - credit Trade Debtors)
        
        if ($customer['opening_debit_amount'] > 0) {
            // Customer owes us - Debit Trade Debtors, Credit Opening Balance Equity
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'customer_opening', 'customers', ?, ?, CURDATE(), ?, ?, 0)
            ");
            $stmt->execute([$tenant_id, $customer['id'], $debtors_account_id, 'Opening balance - ' . $customer['customer_name'], $customer['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'customer_opening', 'customers', ?, 90, CURDATE(), ?, 0, ?)
            ");
            $stmt->execute([$tenant_id, $customer['id'], 'Opening balance - ' . $customer['customer_name'], $customer['opening_debit_amount']]);
            
            $total_debit += $customer['opening_debit_amount'];
        }
        
        if ($customer['opening_credit_amount'] > 0) {
            // We owe customer - Credit Trade Debtors, Debit Opening Balance Equity
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'customer_opening', 'customers', ?, ?, CURDATE(), ?, 0, ?)
            ");
            $stmt->execute([$tenant_id, $customer['id'], $debtors_account_id, 'Opening balance - ' . $customer['customer_name'], $customer['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'customer_opening', 'customers', ?, 90, CURDATE(), ?, ?, 0)
            ");
            $stmt->execute([$tenant_id, $customer['id'], 'Opening balance - ' . $customer['customer_name'], $customer['opening_credit_amount']]);
            
            $total_credit += $customer['opening_credit_amount'];
        }
        
        if ($customer['opening_debit_amount'] > 0 || $customer['opening_credit_amount'] > 0) {
            $inserted_count++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Customer opening balances synced successfully',
        'data' => [
            'deleted_entries' => $deleted_count,
            'inserted_entries' => $inserted_count,
            'total_debit' => $total_debit,
            'total_credit' => $total_credit,
            'net_balance' => $total_debit - $total_credit
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
