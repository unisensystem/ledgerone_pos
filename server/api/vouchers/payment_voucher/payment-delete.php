<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    $stmt = $pdo->prepare("SELECT voucher_number, payment_method_id, company_id, voucher_date, amount FROM payment_voucher WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit();
    }

    // Delete from post_dated_cheques or accounting_ledger based on payment method
    if ($voucher['payment_method_id'] == 6) {
        $stmt = $pdo->prepare("
            DELETE FROM post_dated_cheques 
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ?
        ");
        $stmt->execute([$tenant_id, $voucher_id]);
    }

    $stmt = $pdo->prepare("
            DELETE FROM accounting_ledger 
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ?
        ");
    $stmt->execute([$tenant_id, $voucher_id]);

    // Delete the payment voucher
    $stmt = $pdo->prepare("
        DELETE FROM payment_voucher 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);

    if ($stmt->rowCount() > 0) {
        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Payment Voucher', $voucher_id, $voucher['voucher_number'],
            "Deleted payment voucher \"{$voucher['voucher_number']}\"",
            $voucher, null
        );
        echo json_encode(['success' => true, 'message' => 'Payment voucher deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}
?>