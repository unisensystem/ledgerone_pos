<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$auth = resolveMobileAuth($pdo);
if (!$auth) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
$user_id   = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

$customer_id = $_GET['customer_id'] ?? null;
if (!$customer_id) { echo json_encode(['success' => true, 'data' => []]); exit; }

try {
    // Verify this customer belongs to the tenant
    $checkStmt = $pdo->prepare("
        SELECT id FROM customers
        WHERE tenant_id = ? AND id = ?
    ");
    $checkStmt->execute([$tenant_id, $customer_id]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

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
          AND pdc.customer_id = ?
          AND pdc.transaction_type = 'Received'
          AND pdc.status = 'Pending'
        GROUP BY pdc.id, pdc.cheque_no, pdc.amount, pdc.cheque_date, ba.bank_name
        HAVING pdc.amount > COALESCE(SUM(rv.amount), 0)
        ORDER BY pdc.cheque_date ASC
    ");
    $stmt->execute([$tenant_id, $customer_id]);
    $pdcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pdcs as &$pdc) {
        $pdc['remaining'] = floatval($pdc['amount']) - floatval($pdc['paid_amount']);
    }

    echo json_encode(['success' => true, 'data' => $pdcs]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
