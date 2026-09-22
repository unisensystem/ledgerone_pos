<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit(); }
$customer_code = $_GET['customer_code'] ?? '';
if (!$customer_code) { echo json_encode(['success' => true, 'balance' => 0]); exit(); }
try {
    $stmt = $pdo->prepare("SELECT id, opening_debit_amount, opening_credit_amount FROM customers WHERE tenant_id = ? AND customer_code = ?");
    $stmt->execute([$tenant_id, $customer_code]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) { echo json_encode(['success' => true, 'balance' => 0]); exit(); }
    $cid = $customer['id'];
    $opening = $customer['opening_debit_amount'] - $customer['opening_credit_amount'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id = ?");
    $stmt->execute([$tenant_id, $cid]);
    $invoices = $stmt->fetch()['total'];

    // opening_balance_invoices already included in opening_debit_amount — do NOT add again

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(rv.amount), 0) as total FROM receive_voucher rv WHERE rv.tenant_id = ? AND rv.customer_id = ? AND NOT (rv.payment_method_id = 6 AND EXISTS (SELECT 1 FROM post_dated_cheques pdc WHERE pdc.reference_id = rv.id AND pdc.reference_table = 'receive_voucher' AND pdc.status = 'Pending'))");
    $stmt->execute([$tenant_id, $cid]);
    $payments = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount - amount_refunded), 0) as total FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'");
    $stmt->execute([$tenant_id, $cid]);
    $returns = $stmt->fetch()['total'];

    // Transfer voucher: FROM this customer = balance reduces (credit)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND from_customer_id = ?");
    $stmt->execute([$tenant_id, $cid]);
    $tv_out = $stmt->fetch()['total'];

    // Transfer voucher: TO this customer
    // From customer => debit (+) | From supplier => credit (-)  (matches accounting_ledger)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN from_type = 'customer' THEN amount ELSE -amount END), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND to_customer_id = ?");
    $stmt->execute([$tenant_id, $cid]);
    $tv_in = $stmt->fetch()['total'];

    // ===== Journal adjustment: single party (customer line = account_id 2) =====
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(l.debit - l.credit), 0) as total
        FROM journal_voucher jv
        JOIN journal_voucher_line l
          ON l.voucher_id = jv.id AND l.tenant_id = jv.tenant_id AND l.account_id = 2
        WHERE jv.tenant_id = ? AND jv.status = 'posted'
          AND jv.adj_mode = 'single'
          AND jv.party_type = 'customer' AND jv.party_id = ?
    ");
    $stmt->execute([$tenant_id, $cid]);
    $adj_single = $stmt->fetch()['total'];

    // ===== Journal adjustment: cross mode, customer is Party 1 (first line) =====
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(l.debit - l.credit), 0) as total
        FROM journal_voucher jv
        JOIN journal_voucher_line l
          ON l.voucher_id = jv.id AND l.tenant_id = jv.tenant_id
         AND l.id = (SELECT MIN(l2.id) FROM journal_voucher_line l2 WHERE l2.voucher_id = jv.id AND l2.tenant_id = jv.tenant_id)
        WHERE jv.tenant_id = ? AND jv.status = 'posted'
          AND jv.adj_mode = 'cross'
          AND jv.party_type = 'customer' AND jv.party_id = ?
    ");
    $stmt->execute([$tenant_id, $cid]);
    $adj_cross1 = $stmt->fetch()['total'];

    // ===== Journal adjustment: cross mode, customer is Party 2 (last line) =====
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(l.debit - l.credit), 0) as total
        FROM journal_voucher jv
        JOIN journal_voucher_line l
          ON l.voucher_id = jv.id AND l.tenant_id = jv.tenant_id
         AND l.id = (SELECT MAX(l2.id) FROM journal_voucher_line l2 WHERE l2.voucher_id = jv.id AND l2.tenant_id = jv.tenant_id)
        WHERE jv.tenant_id = ? AND jv.status = 'posted'
          AND jv.adj_mode = 'cross'
          AND jv.party_type2 = 'customer' AND jv.party_id2 = ?
    ");
    $stmt->execute([$tenant_id, $cid]);
    $adj_cross2 = $stmt->fetch()['total'];

    $balance = $opening + $invoices - $payments - $returns - $tv_out + $tv_in
             + $adj_single + $adj_cross1 + $adj_cross2;

    echo json_encode(['success' => true, 'balance' => $balance]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>