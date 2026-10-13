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
    // Get Trade Creditors ledger breakdown by transaction type
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            reference_table,
            COUNT(*) as count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as net_balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 14
        GROUP BY transaction_type, reference_table
        ORDER BY transaction_type, reference_table
    ");
    $stmt->execute([$tenant_id]);
    $ledger_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total purchase invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(net_amount) as total FROM purchase_invoice WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $purchase_stats = $stmt->fetch();
    
    // Get total payment vouchers to suppliers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM payment_voucher WHERE tenant_id = ? AND supplier_id IS NOT NULL");
    $stmt->execute([$tenant_id]);
    $payment_stats = $stmt->fetch();
    
    // Get supplier opening balances
    $stmt = $pdo->prepare("
        SELECT 
            SUM(opening_debit_amount) as total_opening_debit,
            SUM(opening_credit_amount) as total_opening_credit,
            SUM(opening_credit_amount - opening_debit_amount) as net_opening
        FROM suppliers
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $opening_stats = $stmt->fetch();
    
    // Calculate expected balance
    $expected_balance = $opening_stats['net_opening'] + $purchase_stats['total'] - $payment_stats['total'];
    
    // Get actual balance
    $stmt = $pdo->prepare("
        SELECT 
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 14
    ");
    $stmt->execute([$tenant_id]);
    $actual = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'ledger_breakdown' => $ledger_breakdown,
        'source_data' => [
            'purchase_invoices' => $purchase_stats,
            'payment_vouchers' => $payment_stats,
            'supplier_openings' => $opening_stats
        ],
        'comparison' => [
            'expected_balance' => $expected_balance,
            'actual_balance' => $actual['balance'],
            'difference' => $expected_balance - $actual['balance'],
            'note' => 'Expected = Opening + Purchases - Payments. Should be CREDIT balance (negative) if you owe suppliers.'
        ],
        'actual_ledger' => $actual
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
