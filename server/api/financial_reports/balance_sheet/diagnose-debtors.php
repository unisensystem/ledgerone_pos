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
    // 1. Customer Ledger Method (from sale_invoice, receive_voucher, sale_return)
    $stmt = $pdo->prepare("
        SELECT 
            SUM(opening_debit_amount - opening_credit_amount) as opening_balance
        FROM customers 
        WHERE tenant_id = ? AND status = 'ACTIVE'
    ");
    $stmt->execute([$tenant_id]);
    $customer_opening = $stmt->fetch()['opening_balance'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $total_invoices = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $total_payments = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_return WHERE tenant_id = ? AND status = 'Posted'");
    $stmt->execute([$tenant_id]);
    $total_returns = $stmt->fetch()['total'];
    
    $customer_ledger_balance = $customer_opening + $total_invoices - $total_payments - $total_returns;
    
    // 2. Accounting Ledger Method (Trade Debtors account)
    $stmt = $pdo->prepare("
        SELECT a.id, a.name, a.debit as opening_debit, a.credit as opening_credit,
               COALESCE(SUM(al.debit), 0) as total_debit,
               COALESCE(SUM(al.credit), 0) as total_credit
        FROM accounts a
        LEFT JOIN accounting_ledger al ON a.id = al.account_id AND al.tenant_id = ?
        WHERE (a.tenant_id = ? OR a.tenant_id = 0) 
        AND a.name = 'Trade Debtors'
        GROUP BY a.id, a.name, a.debit, a.credit
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $debtors_account = $stmt->fetch();
    
    $accounting_ledger_balance = ($debtors_account['opening_debit'] + $debtors_account['total_debit']) 
                                - ($debtors_account['opening_credit'] + $debtors_account['total_credit']);
    
    // 3. Check accounting_ledger entries for Trade Debtors
    $stmt = $pdo->prepare("
        SELECT transaction_type, reference_table, COUNT(*) as count, 
               SUM(debit) as total_debit, SUM(credit) as total_credit
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = ?
        GROUP BY transaction_type, reference_table
        ORDER BY transaction_type, reference_table
    ");
    $stmt->execute([$tenant_id, $debtors_account['id']]);
    $ledger_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'customer_ledger_balance' => $customer_ledger_balance,
            'accounting_ledger_balance' => $accounting_ledger_balance,
            'difference' => $customer_ledger_balance - $accounting_ledger_balance
        ],
        'customer_ledger_calculation' => [
            'opening_balance' => $customer_opening,
            'total_invoices' => $total_invoices,
            'total_payments' => $total_payments,
            'total_returns' => $total_returns,
            'closing_balance' => $customer_ledger_balance
        ],
        'accounting_ledger_calculation' => [
            'account_id' => $debtors_account['id'],
            'account_name' => $debtors_account['name'],
            'opening_debit' => $debtors_account['opening_debit'],
            'opening_credit' => $debtors_account['opening_credit'],
            'total_debit' => $debtors_account['total_debit'],
            'total_credit' => $debtors_account['total_credit'],
            'closing_balance' => $accounting_ledger_balance
        ],
        'ledger_breakdown' => $ledger_breakdown
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
