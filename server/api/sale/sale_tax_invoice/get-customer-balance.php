<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $customer_id = $_GET['customer_id'] ?? null;
    $project_id  = $_GET['project_id']  ?? null;

    if (!$customer_id) {
        echo json_encode(['success' => false, 'message' => 'customer_id required']);
        exit;
    }

    // Get base currency
    $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
    $stmt->execute([$tenant_id]);
    $base_currency_id = $stmt->fetchColumn();

    if ($project_id) {
        // ── Project-wise balance ──────────────────────────────────────────────

        // Invoices for this project
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sale_invoice
            WHERE tenant_id = ? AND customer_id = ? AND project_id = ?");
        $stmt->execute([$tenant_id, $customer_id, $project_id]);
        $invoices = (float) $stmt->fetchColumn();

        // Sale returns for this project
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sale_return
            WHERE tenant_id = ? AND customer_id = ? AND project_id = ? AND status = 'Posted'");
        $stmt->execute([$tenant_id, $customer_id, $project_id]);
        $returns = (float) $stmt->fetchColumn();

        // Receive vouchers (payments received) for this project
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM receive_voucher
            WHERE tenant_id = ? AND customer_id = ? AND project_id = ?
            AND id NOT IN (
                SELECT reference_id FROM post_dated_cheques
                WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND status = 'Pending'
            )");
        $stmt->execute([$tenant_id, $customer_id, $project_id, $tenant_id]);
        $receipts = (float) $stmt->fetchColumn();

        // Payment vouchers for this project
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_voucher
            WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL
            AND customer_id = ? AND project_id = ?");
        $stmt->execute([$tenant_id, $customer_id, $project_id]);
        $payment_vouchers = (float) $stmt->fetchColumn();

        // balance = invoices + payment_vouchers - returns - receipts
        $balance = $invoices + $payment_vouchers - $returns - $receipts;

    } else {
        // ── Total balance (existing logic via customer opening + all transactions) ──

        $stmt = $pdo->prepare("SELECT opening_debit_amount, opening_credit_amount FROM customers
            WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $opening = ($customer['opening_debit_amount'] ?? 0) - ($customer['opening_credit_amount'] ?? 0);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sale_invoice
            WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $invoices = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sale_return
            WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'");
        $stmt->execute([$tenant_id, $customer_id]);
        $returns = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM receive_voucher
            WHERE tenant_id = ? AND customer_id = ?
            AND id NOT IN (
                SELECT reference_id FROM post_dated_cheques
                WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND status = 'Pending'
            )");
        $stmt->execute([$tenant_id, $customer_id, $tenant_id]);
        $receipts = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_voucher
            WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $payment_vouchers = (float) $stmt->fetchColumn();

        $balance = $opening + $invoices + $payment_vouchers - $returns - $receipts;
    }

    echo json_encode([
        'success'     => true,
        'balance'     => $balance,
        'project_id'  => $project_id,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
