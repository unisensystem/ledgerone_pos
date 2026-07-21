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
    echo json_encode(['error' => 'Voucher ID is required']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $voucher_date = $input['voucher_date'] ?? '';
    $company_id = $input['company_id'] ?? null;
    $sub_account_id = !empty($input['sub_account_id']) ? $input['sub_account_id'] : null;
    $bill_no = !empty($input['bill_no']) ? $input['bill_no'] : null;
    $amount = $input['amount'] ?? 0;
    $currency_id = $input['currency_id'] ?? null;
    $payment_method_id = $input['payment_method_id'] ?? null;
    $bank_account_id = !empty($input['bank_account_id']) ? $input['bank_account_id'] : null;
    $cheque_no = !empty($input['cheque_no']) ? $input['cheque_no'] : null;
    $cheque_date = !empty($input['cheque_date']) ? $input['cheque_date'] : null;
    $description = !empty($input['description']) ? $input['description'] : null;
    $project_id = !empty($input['project_id']) ? $input['project_id'] : null;
    $expense_voucher_ref_id = !empty($input['expense_voucher_ref_id']) ? $input['expense_voucher_ref_id'] : null;

    // Get current voucher details for payment method check
    $stmt = $pdo->prepare("SELECT payment_method_id FROM payment_voucher WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit();
    }
    
    // Update payment voucher
    $stmt = $pdo->prepare("
        UPDATE payment_voucher 
        SET voucher_date = ?, company_id = ?, sub_account_id = ?, bill_no = ?, amount = ?, currency_id = ?, 
            payment_method_id = ?, bank_account_id = ?, cheque_no = ?, cheque_date = ?, description = ?, project_id = ?, expense_voucher_ref_id = ?, updated_by = ?
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->execute([
        $voucher_date, $company_id, $sub_account_id, $bill_no, $amount, $currency_id,
        $payment_method_id, $bank_account_id, $cheque_no, $cheque_date, $description, $project_id, $expense_voucher_ref_id, $user_id,
        $voucher_id, $tenant_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        // Update post-dated cheque if payment method is 6
        if ($voucher['payment_method_id'] == 6) {
            $stmt = $pdo->prepare("
                UPDATE post_dated_cheques 
                SET amount = ?
                WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ?
            ");
            $stmt->execute([$amount, $tenant_id, $voucher_id]);
        }
        
        // Update accounting ledger entries
        $stmt = $pdo->prepare("
            UPDATE accounting_ledger 
            SET date = ?, description = ?, debit = ?, credit = ?
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ? AND debit > 0
        ");
        $stmt->execute([$voucher_date, $description, $amount, 0.00, $tenant_id, $voucher_id]);
        
        $stmt = $pdo->prepare("
            UPDATE accounting_ledger 
            SET date = ?, description = ?, debit = ?, credit = ?
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ? AND credit > 0
        ");
        $stmt->execute([$voucher_date, $description, 0.00, $amount, $tenant_id, $voucher_id]);
        
        echo json_encode(['success' => true, 'message' => 'Payment voucher updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Voucher not found or no changes made']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}
?>