<?php
require_once '../../../../includes/connection.php';

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

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? null;
$description = $input['description'] ?? null;
$project_id = $input['project_id'] ?? null;
$container_id = $input['container_id'] ?? null;
$vendor_id = $input['vendor_id'] ?? null;
$type = $input['type'] ?? 'CR';
$entries = $input['entries'] ?? [];

if (!$date || empty($entries)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Delete existing records
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'expense_voucher' AND reference_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    $stmt = $pdo->prepare("DELETE FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'expense_voucher' AND reference_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    $stmt = $pdo->prepare("DELETE FROM expense_voucher_line WHERE tenant_id = ? AND voucher_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);
    
    // Calculate totals
    $total_amount = array_sum(array_column($entries, 'amount'));
    $total_items = count($entries);
    
    // Update expense_voucher
    $stmt = $pdo->prepare("UPDATE expense_voucher SET date = ?, company_id = ?, description = ?, total_amount = ?, total_items = ?, project_id = ?, container_id = ?, vendor_id = ?, type = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$date, $input['company_id'], $description, $total_amount, $total_items, $project_id, $container_id, $vendor_id, $type, $voucher_id, $tenant_id]);
    
    // Insert new lines and ledger entries
    foreach ($entries as $entry) {
        $expense_account_id = $entry['expense_account_id'];
        $cost_center_id = $entry['cost_center_id'];
        $amount = $entry['amount'];
        $payment_method_id = $entry['payment_method_id'];
        $bank_account_id = $entry['bank_account_id'];
        $cheque_no = $entry['cheque_no'];
        $cheque_date = $entry['cheque_date'];
        
        $stmt = $pdo->prepare("INSERT INTO expense_voucher_line (tenant_id, voucher_id, account_id, cost_center_id, amount, payment_method_id, bank_account_id, cheque_no, cheque_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $voucher_id, $expense_account_id, $cost_center_id, $amount, $payment_method_id, $bank_account_id, $cheque_no, $cheque_date]);
        
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'expense_voucher', 'expense_voucher', ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$tenant_id, $voucher_id, $expense_account_id, $date, $description, $amount]);
        
        $payment_account_id = null;
        if ($payment_method_id == 7) {
            $payment_account_id = 1;
        } elseif ($payment_method_id == 6) {
            $payment_account_id = 79;
        } elseif ($payment_method_id == 5 && $bank_account_id) {
            $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$bank_account_id, $tenant_id]);
            $payment_account_id = $stmt->fetchColumn();
        }
        
        if ($payment_account_id) {
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'expense_voucher', 'expense_voucher', ?, ?, ?, ?, 0, ?)");
            $stmt->execute([$tenant_id, $voucher_id, $payment_account_id, $date, $description, $amount]);
        }
        
        if ($payment_method_id == 6 && $cheque_date && $bank_account_id) {
            $stmt = $pdo->prepare("INSERT INTO post_dated_cheques (tenant_id, cheque_no, account_id, bank_account_id, transaction_type, amount, cheque_date, reference_id, reference_table) VALUES (?, ?, ?, ?, 'Expense', ?, ?, ?, 'expense_voucher')");
            $stmt->execute([$tenant_id, $cheque_no, $expense_account_id, $bank_account_id, $amount, $cheque_date, $voucher_id]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Expense voucher updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update expense voucher']);
}
