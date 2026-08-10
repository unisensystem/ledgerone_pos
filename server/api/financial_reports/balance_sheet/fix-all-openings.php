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
    
    // 1. Swap customer opening amounts
    $stmt = $pdo->prepare("
        UPDATE customers 
        SET 
            opening_debit_amount = opening_credit_amount,
            opening_credit_amount = opening_debit_amount
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $customers_updated = $stmt->rowCount();
    
    // 2. Swap supplier opening amounts
    $stmt = $pdo->prepare("
        UPDATE suppliers 
        SET 
            opening_debit_amount = opening_credit_amount,
            opening_credit_amount = opening_debit_amount
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $suppliers_updated = $stmt->rowCount();
    
    // 3. Delete ALL opening ledger entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'customer_opening'");
    $stmt->execute([$tenant_id]);
    
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'supplier_opening'");
    $stmt->execute([$tenant_id]);
    
    // 4. Recreate customer opening entries
    $stmt = $pdo->prepare("SELECT id, customer_name, opening_debit_amount, opening_credit_amount FROM customers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0)");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $customer_entries = 0;
    foreach ($customers as $customer) {
        if ($customer['opening_debit_amount'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 2, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $customer['id'], 'Opening balance - ' . $customer['customer_name'], $customer['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 90, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $customer['id'], 'Opening balance - ' . $customer['customer_name'], $customer['opening_debit_amount']]);
            $customer_entries += 2;
        }
        
        if ($customer['opening_credit_amount'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 2, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $customer['id'], 'Opening balance - ' . $customer['customer_name'], $customer['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 90, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $customer['id'], 'Opening balance - ' . $customer['customer_name'], $customer['opening_credit_amount']]);
            $customer_entries += 2;
        }
    }
    
    // 5. Recreate supplier opening entries
    $stmt = $pdo->prepare("SELECT id, supplier_name, opening_debit_amount, opening_credit_amount FROM suppliers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0)");
    $stmt->execute([$tenant_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $supplier_entries = 0;
    foreach ($suppliers as $supplier) {
        if ($supplier['opening_debit_amount'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_debit_amount']]);
            $supplier_entries += 2;
        }
        
        if ($supplier['opening_credit_amount'] > 0) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $supplier['id'], 'Opening balance - ' . $supplier['supplier_name'], $supplier['opening_credit_amount']]);
            $supplier_entries += 2;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'All opening balances swapped and fixed successfully',
        'data' => [
            'customers_updated' => $customers_updated,
            'suppliers_updated' => $suppliers_updated,
            'customer_ledger_entries' => $customer_entries,
            'supplier_ledger_entries' => $supplier_entries
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
