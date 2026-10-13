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

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            rv.id,
            rv.voucher_number,
            rv.voucher_date,
            rv.customer_id,
            rv.supplier_id,
            rv.company_id,
            rv.sub_account_id,
            rv.bill_no,
            rv.dsr_no,
                       rv.project_id,
            rv.container_id,
            rv.amount,
            rv.adjust_amount,
            rv.previous_balance,
            rv.currency_id,
            rv.payment_method_id,
            rv.bank_account_id,
            rv.cheque_date,
            rv.cheque_no,
            rv.description,
            rv.recovery_officer_id,
            rv.against_pdc_id,
            rv.created_by,
            COALESCE(cu.customer_name, su.supplier_name) as customer_name,
            COALESCE(cu.customer_code, su.supplier_code) as customer_code,
            CASE WHEN rv.supplier_id IS NOT NULL THEN 'supplier' ELSE 'customer' END as receive_type,
            c.symbol as currency_symbol,
            pdc.cheque_no as against_pdc_cheque_no,
            pdc.amount as against_pdc_amount
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        LEFT JOIN suppliers su ON rv.supplier_id = su.id
        LEFT JOIN ledgerone_public.currencies c ON rv.currency_id = c.id
        LEFT JOIN post_dated_cheques pdc ON rv.against_pdc_id = pdc.id
        WHERE rv.id = ? AND rv.tenant_id = ?
    ");
    
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    // Same ownership rule as receive-edit.php — a mobile token may only
    // view its own vouchers.
    if ($auth['via'] === 'token' && (int) $voucher['created_by'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only view your own vouchers']);
        exit();
    }

    echo json_encode(['success' => true, 'data' => $voucher]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
