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
    // Calculate Customer Ledger balance
    $stmt = $pdo->prepare("
        SELECT 
            SUM(opening_debit_amount - opening_credit_amount) as opening_balance
        FROM customers 
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $customer_opening = $stmt->fetch()['opening_balance'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $total_invoices = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $total_receipts = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL");
    $stmt->execute([$tenant_id]);
    $total_payments_to_customers = $stmt->fetch()['total'];
    
    $customer_ledger_balance = $customer_opening + $total_invoices - $total_receipts - $total_payments_to_customers;
    
    // Get Trade Debtors balance
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 2
    ");
    $stmt->execute([$tenant_id]);
    $trade_debtors_balance = $stmt->fetch()['balance'];
    
    echo json_encode([
        'success' => true,
        'customer_ledger' => [
            'opening' => $customer_opening,
            'invoices' => $total_invoices,
            'receipts' => $total_receipts,
            'payments_to_customers' => $total_payments_to_customers,
            'balance' => $customer_ledger_balance
        ],
        'trade_debtors_balance' => $trade_debtors_balance,
        'difference' => $customer_ledger_balance - $trade_debtors_balance,
        'match' => abs($customer_ledger_balance - $trade_debtors_balance) < 0.01
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
