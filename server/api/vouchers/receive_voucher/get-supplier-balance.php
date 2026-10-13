<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$supplier_code = $_GET['supplier_code'] ?? '';

if (!$supplier_code) {
    echo json_encode(['success' => true, 'balance' => 0]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, opening_debit_amount, opening_credit_amount
        FROM suppliers
        WHERE tenant_id = ? AND supplier_code = ?
    ");
    $stmt->execute([$tenant_id, $supplier_code]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode(['success' => true, 'balance' => 0]);
        exit();
    }

    $supplier_id = $supplier['id'];
    // Debit-normal ("Previous Balance") representation, mirroring get-customer-balance.php's
    // convention: opening_debit - opening_credit. For a supplier this is normally negative
    // (payable). Purchase invoices and receive-from-supplier both increase the payable
    // (subtracted here); payments and purchase returns both reduce it (added here).
    $opening_balance = $supplier['opening_debit_amount'] - $supplier['opening_credit_amount'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);
    $total_invoices = $stmt->fetch()['total'];

    // Payments to supplier (exclude cheques still Pending in post_dated_cheques)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pv.amount), 0) as total
        FROM payment_voucher pv
        WHERE pv.tenant_id = ? AND pv.supplier_id = ?
        AND pv.id NOT IN (
            SELECT reference_id FROM post_dated_cheques
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND status = 'Pending'
        )
    ");
    $stmt->execute([$tenant_id, $supplier_id, $tenant_id]);
    $total_payments = $stmt->fetch()['total'];

    // Money received FROM this supplier (exclude cheques still Pending)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount + rv.adjust_amount), 0) as total
        FROM receive_voucher rv
        WHERE rv.tenant_id = ? AND rv.supplier_id = ?
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
    $stmt->execute([$tenant_id, $supplier_id]);
    $total_received = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND supplier_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);
    $total_returns = $stmt->fetch()['total'];

    // Transfer voucher: FROM supplier (tv_out) = payable increases (-), TO supplier (tv_in) = payable decreases (+)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'supplier' AND from_supplier_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);
    $tv_out = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'supplier' AND to_supplier_id = ?");
    $stmt->execute([$tenant_id, $supplier_id]);
    $tv_in = $stmt->fetch()['total'];

    $balance = $opening_balance - $total_invoices + $total_payments + $total_returns - $total_received - $tv_out + $tv_in;

    echo json_encode(['success' => true, 'balance' => $balance]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
