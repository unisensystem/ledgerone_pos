<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit(); }
$supplier_code = $_GET['supplier_code'] ?? '';
if (!$supplier_code) { echo json_encode(['success' => true, 'balance' => 0]); exit(); }
try {
    $stmt = $pdo->prepare("SELECT id, opening_debit_amount, opening_credit_amount FROM suppliers WHERE tenant_id = ? AND supplier_code = ?");
    $stmt->execute([$tenant_id, $supplier_code]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) { echo json_encode(['success' => true, 'balance' => 0]); exit(); }
    $sid = $supplier['id'];
    $opening = $supplier['opening_credit_amount'] - $supplier['opening_debit_amount'];

    // Total purchase invoices (what we owe supplier)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ?");
    $stmt->execute([$tenant_id, $sid]);
    $invoices = $stmt->fetch()['total'];

    // Total payments made to supplier
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ?");
    $stmt->execute([$tenant_id, $sid]);
    $payments = $stmt->fetch()['total'];

    // Transfer voucher: Supplier = Credit-normal
    // FROM supplier (tv_out) = payable increases (+) | TO supplier (tv_in) = payable reduces (-)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND from_supplier_id = ?");
    $stmt->execute([$tenant_id, $sid]);
    $tv_out = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher WHERE tenant_id = ? AND to_supplier_id = ?");
    $stmt->execute([$tenant_id, $sid]);
    $tv_in = $stmt->fetch()['total'];

    echo json_encode(['success' => true, 'balance' => $opening + $invoices - $payments + $tv_out - $tv_in]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
