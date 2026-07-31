<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$customer_id = $_GET['customer_id'] ?? null;
if (!$customer_id) { echo json_encode(['success' => true, 'data' => []]); exit; }

try {
    // Get company IDs this user has access to (within current tenant)
    $companyStmt = $pdo->prepare("
        SELECT company_id
        FROM user_companies
        WHERE user_id = ? AND tenant_id = ?
    ");
    $companyStmt->execute([$user_id, $tenant_id]);
    $companyIds = $companyStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($companyIds)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    // Verify this customer belongs to one of the user's assigned companies
    $checkStmt = $pdo->prepare("
        SELECT id FROM customers 
        WHERE tenant_id = ? AND id = ? AND company_id IN ($placeholders)
    ");
    $checkStmt->execute(array_merge([$tenant_id, $customer_id], $companyIds));
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
