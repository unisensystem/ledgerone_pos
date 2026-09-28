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
            rv.amount,
            rv.currency_id,
            rv.payment_method_id,
            rv.bank_account_id,
            rv.cheque_date,
            rv.cheque_no,
            rv.description,
            rv.recovery_officer_id,
            COALESCE(cu.customer_name, su.supplier_name) as customer_name,
            COALESCE(cu.customer_code, su.supplier_code) as customer_code,
            CASE WHEN rv.supplier_id IS NOT NULL THEN 'supplier' ELSE 'customer' END as receive_type,
            c.symbol as currency_symbol
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        LEFT JOIN suppliers su ON rv.supplier_id = su.id
        LEFT JOIN ledgerone_public.currencies c ON rv.currency_id = c.id
        WHERE rv.id = ? AND rv.tenant_id = ?
    ");
    
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }
    
    echo json_encode(['success' => true, 'data' => $voucher]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
