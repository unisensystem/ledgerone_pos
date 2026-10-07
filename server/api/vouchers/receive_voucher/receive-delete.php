<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID is required']);
    exit();
}

try {
    // Get payment method (to determine which table to delete from) and full row for the Activity Log
    $stmt = $pdo->prepare("SELECT voucher_number, payment_method_id, voucher_date, customer_id, supplier_id, amount, created_by FROM receive_voucher WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit();
    }

    // Same ownership rule as receive-edit.php — a mobile token may only
    // delete its own vouchers.
    if ($auth['via'] === 'token' && (int) $voucher['created_by'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only delete your own vouchers']);
        exit();
    }

    // Delete from post_dated_cheques or accounting_ledger based on payment method
    if ($voucher['payment_method_id'] == 6) {
        $stmt = $pdo->prepare("
            DELETE FROM post_dated_cheques 
            WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id = ?
        ");
        $stmt->execute([$tenant_id, $voucher_id]);
    }

    // Delete from accounting_ledger
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger 
        WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id = ?
    ");
    $stmt->execute([$tenant_id, $voucher_id]);

    // Delete the receive voucher
    $stmt = $pdo->prepare("
        DELETE FROM receive_voucher 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);

    if ($stmt->rowCount() > 0) {
        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Receive Voucher', $voucher_id, $voucher['voucher_number'],
            "Deleted receive voucher \"{$voucher['voucher_number']}\"",
            $voucher, null
        );
        echo json_encode(['success' => true, 'message' => 'Receive voucher deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}
?>