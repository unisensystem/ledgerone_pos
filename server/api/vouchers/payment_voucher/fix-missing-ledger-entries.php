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

try {
    // Check customer vouchers and their ledger entries
    $stmt = $pdo->prepare("
        SELECT pv.id, pv.voucher_number, pv.customer_id, pv.amount,
               COUNT(al.id) as ledger_count,
               GROUP_CONCAT(CONCAT(a.name, ':', al.debit, '/', al.credit) SEPARATOR ' | ') as entries
        FROM payment_voucher pv
        LEFT JOIN accounting_ledger al ON al.reference_table = 'payment_voucher' AND al.reference_id = pv.id
        LEFT JOIN accounts a ON al.account_id = a.id
        WHERE pv.tenant_id = ? AND pv.customer_id IS NOT NULL
        GROUP BY pv.id
    ");
    $stmt->execute([$tenant_id]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $vouchers]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
