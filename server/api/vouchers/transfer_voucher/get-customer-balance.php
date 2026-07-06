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

    // Transfer voucher: FROM this customer = balance reduces (credit), TO this customer = balance increases (debit)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND from_customer_id = ?");
    $stmt->execute([$tenant_id, $cid]);
    $tv_out = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND to_customer_id = ?");
    $stmt->execute([$tenant_id, $cid]);
    $tv_in = $stmt->fetch()['total'];

    echo json_encode(['success' => true, 'balance' => $opening + $invoices - $payments - $returns - $tv_out + $tv_in]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
