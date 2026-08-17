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
    // Get payment vouchers to customers
    $stmt = $pdo->prepare("
        SELECT 
            pv.id,
            pv.voucher_number,
            pv.amount,
            pv.voucher_date,
            c.customer_name,
            COUNT(al.id) as ledger_entries
        FROM payment_voucher pv
        JOIN customers c ON pv.customer_id = c.id
        LEFT JOIN accounting_ledger al ON al.reference_table = 'payment_voucher' 
            AND al.reference_id = pv.id 
            AND al.account_id = 2
            AND al.tenant_id = ?
        WHERE pv.tenant_id = ? AND pv.customer_id IS NOT NULL
        GROUP BY pv.id
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $payment_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total from payment vouchers
    $total_pv_amount = array_sum(array_column($payment_vouchers, 'amount'));
    
    // Get Trade Debtors entries from payment_voucher
    $stmt = $pdo->prepare("
        SELECT 
            SUM(debit) as total_debit,
            SUM(credit) as total_credit
        FROM accounting_ledger
        WHERE tenant_id = ? 
        AND account_id = 2 
        AND reference_table = 'payment_voucher'
    ");
    $stmt->execute([$tenant_id]);
    $pv_ledger = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'payment_vouchers_to_customers' => $payment_vouchers,
        'total_count' => count($payment_vouchers),
        'total_amount' => $total_pv_amount,
        'trade_debtors_entries' => $pv_ledger,
        'note' => 'Payment vouchers to customers should DEBIT Trade Debtors (reduce the credit balance)'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
