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
    
    // Step 1: Delete ALL customer and supplier ledger entries (we'll recreate from scratch)
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND account_id IN (2, 14)");
    $stmt->execute([$tenant_id]);
    $deleted = $stmt->rowCount();
    
    // Step 2: Get customers and recreate entries with CORRECT logic
    // Customer owes us = DEBIT Trade Debtors (asset increases)
    $stmt = $pdo->prepare("SELECT id, customer_name, opening_debit_amount, opening_credit_amount FROM customers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($customers as $c) {
        if ($c['opening_debit_amount'] > 0) {
            // DEBIT Trade Debtors, CREDIT Opening Balance Equity
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 2, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $c['id'], 'Opening - ' . $c['customer_name'], $c['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 90, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $c['id'], 'Opening - ' . $c['customer_name'], $c['opening_debit_amount']]);
        }
        if ($c['opening_credit_amount'] > 0) {
            // CREDIT Trade Debtors, DEBIT Opening Balance Equity
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 2, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $c['id'], 'Opening - ' . $c['customer_name'], $c['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'customer_opening', 'customers', ?, 90, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $c['id'], 'Opening - ' . $c['customer_name'], $c['opening_credit_amount']]);
        }
    }
    
    // Step 3: Recreate sale invoices - DEBIT Trade Debtors, CREDIT Sales
    $stmt = $pdo->prepare("SELECT id, customer_id, net_amount, sale_date FROM sale_invoice WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($invoices as $inv) {
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'sale_invoice', 'sale_invoice', ?, 2, ?, 'Sale Invoice', ?, 0)");
        $stmt->execute([$tenant_id, $inv['id'], $inv['sale_date'], $inv['net_amount']]);
    }
    
    // Step 4: Recreate receive vouchers - CREDIT Trade Debtors, DEBIT Cash/Bank
    $stmt = $pdo->prepare("SELECT id, customer_id, amount, payment_date, payment_method FROM receive_voucher WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($receipts as $rv) {
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'receive_voucher', 'receive_voucher', ?, 2, ?, 'Payment received', 0, ?)");
        $stmt->execute([$tenant_id, $rv['id'], $rv['payment_date'], $rv['amount']]);
    }
    
    // Step 5: Get suppliers and recreate with CORRECT logic
    // We owe supplier = CREDIT Trade Creditors (liability increases)
    $stmt = $pdo->prepare("SELECT id, supplier_name, opening_debit_amount, opening_credit_amount FROM suppliers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($suppliers as $s) {
        if ($s['opening_credit_amount'] > 0) {
            // CREDIT Trade Creditors, DEBIT Opening Balance Equity
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $s['id'], 'Opening - ' . $s['supplier_name'], $s['opening_credit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $s['id'], 'Opening - ' . $s['supplier_name'], $s['opening_credit_amount']]);
        }
        if ($s['opening_debit_amount'] > 0) {
            // DEBIT Trade Creditors, CREDIT Opening Balance Equity
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 14, CURDATE(), ?, ?, 0)");
            $stmt->execute([$tenant_id, $s['id'], 'Opening - ' . $s['supplier_name'], $s['opening_debit_amount']]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'supplier_opening', 'suppliers', ?, 90, CURDATE(), ?, 0, ?)");
            $stmt->execute([$tenant_id, $s['id'], 'Opening - ' . $s['supplier_name'], $s['opening_debit_amount']]);
        }
    }
    
    // Step 6: Recreate purchase invoices - CREDIT Trade Creditors
    $stmt = $pdo->prepare("SELECT id, supplier_id, net_amount, purchase_date FROM purchase_invoice WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($purchases as $pi) {
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'purchase_invoice', 'purchase_invoice', ?, 14, ?, 'Purchase Invoice', 0, ?)");
        $stmt->execute([$tenant_id, $pi['id'], $pi['purchase_date'], $pi['net_amount']]);
    }
    
    // Step 7: Recreate payment vouchers - DEBIT Trade Creditors
    $stmt = $pdo->prepare("SELECT id, supplier_id, amount, payment_date FROM payment_voucher WHERE tenant_id = ? AND supplier_id IS NOT NULL");
    $stmt->execute([$tenant_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $pv) {
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'payment_voucher', 'payment_voucher', ?, 14, ?, 'Payment made', ?, 0)");
        $stmt->execute([$tenant_id, $pv['id'], $pv['payment_date'], $pv['amount']]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'All customer and supplier accounts fixed with correct debit/credit logic',
        'deleted_entries' => $deleted
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
