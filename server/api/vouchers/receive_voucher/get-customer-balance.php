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

$customer_code = $_GET['customer_code'] ?? '';

if (!$customer_code) {
    echo json_encode(['success' => true, 'balance' => 0]);
    exit();
}

try {
    // Get customer ID
    $stmt = $pdo->prepare("SELECT id, opening_debit_amount, opening_credit_amount FROM customers WHERE tenant_id = ? AND customer_code = ?");
    $stmt->execute([$tenant_id, $customer_code]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => true, 'balance' => 0]);
        exit();
    }
    
    $customer_id = $customer['id'];
    $opening_balance = $customer['opening_debit_amount'] - $customer['opening_credit_amount'];

    // Get total invoices
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id = ?");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_invoices = $stmt->fetch()['total'];

    // Get opening balance invoices
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(debit), 0) as total FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ?");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_invoices += $stmt->fetch()['total'];
    
    // Get total payments
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND customer_id = ?");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_payments = $stmt->fetch()['total'];
    
    // Get total returns (credit)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount - amount_refunded), 0) as total FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'");
    $stmt->execute([$tenant_id, $customer_id]);
    $total_returns = $stmt->fetch()['total'];
    
    $balance = $opening_balance + $total_invoices - $total_payments - $total_returns;
    
    echo json_encode(['success' => true, 'balance' => $balance]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
