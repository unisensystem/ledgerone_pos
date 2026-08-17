<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$pdc_id = $_GET['pdc_id'] ?? null;
if (!$pdc_id) { echo json_encode(['success' => true, 'data' => [], 'summary' => []]); exit; }

try {
    // PDC info
    $stmt = $pdo->prepare("
        SELECT pdc.cheque_no, pdc.amount, pdc.cheque_date, pdc.status, ba.bank_name
        FROM post_dated_cheques pdc
        LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id
        WHERE pdc.id = ? AND pdc.tenant_id = ?
    ");
    $stmt->execute([$pdc_id, $tenant_id]);
    $pdc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pdc) { echo json_encode(['success' => false, 'message' => 'PDC not found']); exit; }

    // Payments against this PDC
    $stmt = $pdo->prepare("
        SELECT rv.voucher_number, rv.voucher_date, rv.amount, a.name as payment_method
        FROM receive_voucher rv
        LEFT JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.against_pdc_id = ? AND rv.tenant_id = ?
        ORDER BY rv.voucher_date ASC
    ");
    $stmt->execute([$pdc_id, $tenant_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_paid = array_sum(array_column($payments, 'amount'));

    echo json_encode([
        'success'     => true,
        'pdc'         => $pdc,
        'data'        => $payments,
        'total_paid'  => $total_paid,
        'remaining'   => floatval($pdc['amount']) - $total_paid
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
