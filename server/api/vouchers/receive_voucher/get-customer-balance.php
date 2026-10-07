<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

$customer_code = $_GET['customer_code'] ?? '';

if (!$customer_code) {
    echo json_encode(['success' => true, 'balance' => 0]);
    exit();
}

try {
    // Get customer ID
    $stmt = $pdo->prepare("
        SELECT id, opening_debit_amount, opening_credit_amount
        FROM customers
        WHERE tenant_id = ? AND customer_code = ?
    ");
    $stmt->execute([$tenant_id, $customer_code]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => true, 'balance' => 0]);
        exit();
    }
    
    $customer_id = $customer['id'];
    $opening_balance = $customer['opening_debit_amount'] - $customer['opening_credit_amount'];

    // Get total invoices (sale invoices only, NOT opening_balance_invoices — already in opening_debit_amount)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id = ?");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_invoices = $stmt->fetch()['total'];
    
    // Get total payments (exclude cheques that are still Pending in post_dated_cheques)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount + rv.adjust_amount), 0) as total 
        FROM receive_voucher rv
        WHERE rv.tenant_id = ? AND rv.customer_id = ?
        AND NOT (
            rv.payment_method_id = 6
            AND EXISTS (
                SELECT 1 FROM post_dated_cheques pdc 
                WHERE pdc.reference_id = rv.id 
                AND pdc.reference_table = 'receive_voucher'
                AND pdc.status = 'Pending'
            )
        )
    ");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_payments = $stmt->fetch()['total'];
    
    // Get total returns (credit)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount - amount_refunded), 0) as total FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_returns = $stmt->fetch()['total'];

    // Transfer voucher: FROM customer (tv_out) = balance reduces (-), TO customer (tv_in) = balance increases (+)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND from_customer_id = ?");
    $stmt->execute([$tenant_id, $customer_id]);
    $tv_out = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND to_customer_id = ?");
    $stmt->execute([$tenant_id, $customer_id]);
    $tv_in = $stmt->fetch()['total'];

    $balance = $opening_balance + $total_invoices - $total_payments - $total_returns - $tv_out + $tv_in;
    
    echo json_encode(['success' => true, 'balance' => $balance]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
