<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$supplier_id = $_GET['supplier_id'] ?? null;
if (!$supplier_id) { echo json_encode(['success' => true, 'data' => []]); exit; }

try {
    // Verify this supplier belongs to the tenant
    $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE tenant_id = ? AND id = ?");
    $checkStmt->execute([$tenant_id, $supplier_id]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Pending PDCs the supplier has given the business (transaction_type 'Received', supplier_id set)
    $stmt = $pdo->prepare("
        SELECT
            pdc.id,
            pdc.cheque_no,
            pdc.amount,
            pdc.cheque_date,
            ba.bank_name,
            COALESCE(SUM(rv.amount), 0) as paid_amount
        FROM post_dated_cheques pdc
        LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id
        LEFT JOIN receive_voucher rv ON rv.against_pdc_id = pdc.id AND rv.tenant_id = pdc.tenant_id
        WHERE pdc.tenant_id = ?
          AND pdc.supplier_id = ?
          AND pdc.transaction_type = 'Received'
          AND pdc.status = 'Pending'
        GROUP BY pdc.id, pdc.cheque_no, pdc.amount, pdc.cheque_date, ba.bank_name
        HAVING pdc.amount > COALESCE(SUM(rv.amount), 0)
        ORDER BY pdc.cheque_date ASC
    ");
    $stmt->execute([$tenant_id, $supplier_id]);
    $pdcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pdcs as &$pdc) {
        $pdc['remaining'] = floatval($pdc['amount']) - floatval($pdc['paid_amount']);
    }

    echo json_encode(['success' => true, 'data' => $pdcs]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
