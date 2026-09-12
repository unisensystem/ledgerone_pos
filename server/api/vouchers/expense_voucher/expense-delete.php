<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || $tenant_id === null) {
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
    // Grab values before deletion, for the Activity Log (record is gone afterward)
    $stmt = $pdo->prepare("SELECT voucher_no, date, description, total_amount FROM expense_voucher WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    $voucherRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voucherRow) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    $pdo->beginTransaction();

    // Delete from accounting_ledger
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'expense_voucher' AND reference_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    // Delete from post_dated_cheques
    $stmt = $pdo->prepare("DELETE FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'expense_voucher' AND reference_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    // Delete from expense_voucher_line
    $stmt = $pdo->prepare("DELETE FROM expense_voucher_line WHERE tenant_id = ? AND voucher_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    // Delete from expense_voucher
    $stmt = $pdo->prepare("DELETE FROM expense_voucher WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Expense Voucher', $voucher_id, $voucherRow['voucher_no'],
        "Deleted expense voucher \"{$voucherRow['voucher_no']}\"",
        $voucherRow, null
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Expense voucher deleted successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete expense voucher']);
}
