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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID required']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $payment_method_id = $input['payment_method_id'] ?? null;
    // Map Cash account (id=7 in dropdown) to id=1 for storage
    if ($payment_method_id == 7) $payment_method_id = 1;
    
    $stmt = $pdo->prepare("
        UPDATE receive_voucher 
        SET 
            voucher_date = ?,
            company_id = ?,
            bill_no = ?,
            amount = ?,
            currency_id = ?,
            payment_method_id = ?,
            bank_account_id = ?,
            cheque_date = ?,
            cheque_no = ?,
            description = ?,
            project_id = ?,
            updated_by = ?
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->execute([
        $input['voucher_date'] ?? '',
        $input['company_id'] ?? null,
        $input['bill_no'] ?? null,
        $input['amount'] ?? 0,
        $input['currency_id'] ?? null,
        $payment_method_id,
        $input['bank_account_id'] ?? null,
        $input['cheque_date'] ?? null,
        $input['cheque_no'] ?? null,
        $input['description'] ?? null,
        $input['project_id'] ?? null,
        $user_id,
        $voucher_id,
        $tenant_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Voucher updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>