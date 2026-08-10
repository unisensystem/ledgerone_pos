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
    // Check Trade Debtors entries by transaction type
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            reference_table,
            COUNT(*) as count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 2
        GROUP BY transaction_type, reference_table
        ORDER BY transaction_type, reference_table
    ");
    $stmt->execute([$tenant_id]);
    $ledger_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count actual transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(opening_debit_amount - opening_credit_amount) as total FROM customers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(net_amount) as total FROM sale_invoice WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $invoices = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM receive_voucher WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $receipts = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL");
    $stmt->execute([$tenant_id]);
    $payments = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'trade_debtors_breakdown' => $ledger_breakdown,
        'source_transactions' => [
            'customers' => $customers,
            'sale_invoices' => $invoices,
            'receive_vouchers' => $receipts,
            'payment_vouchers' => $payments
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
