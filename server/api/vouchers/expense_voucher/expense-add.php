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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? null;
$voucher_number = $input['voucher_number'] ?? null;
$description = $input['description'] ?? null;
$project_id = $input['project_id'] ?? null;
$entries = $input['entries'] ?? [];

if (!$date || !$voucher_number || empty($entries)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Calculate total amount and items
    $total_amount = array_sum(array_column($entries, 'amount'));
    $total_items = count($entries);
    
    // Insert into expense_voucher
    $stmt = $pdo->prepare("
        INSERT INTO expense_voucher (tenant_id, company_id, voucher_no, date, description, total_amount, total_items, project_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tenant_id, $input['company_id'], $voucher_number, $date, $description, $total_amount, $total_items, $project_id]);
    $voucher_id = $pdo->lastInsertId();
    
    foreach ($entries as $entry) {
        $expense_account_id = $entry['expense_account_id'];
        $cost_center_id = $entry['cost_center_id'];
        $amount = $entry['amount'];
        $payment_method_id = $entry['payment_method_id'];
        $bank_account_id = $entry['bank_account_id'];
        $cheque_no = $entry['cheque_no'];
        $cheque_date = $entry['cheque_date'];
        
        // Insert into expense_voucher_line
        $stmt = $pdo->prepare("
            INSERT INTO expense_voucher_line (tenant_id, voucher_id, account_id, cost_center_id, amount, payment_method_id, bank_account_id, cheque_no, cheque_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $voucher_id, $expense_account_id, $cost_center_id, $amount, $payment_method_id, $bank_account_id, $cheque_no, $cheque_date]);
        
        // Debit expense account
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
            VALUES (?, 'expense_voucher', 'expense_voucher', ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$tenant_id, $voucher_id, $expense_account_id, $date, $description, $amount]);
        
        // Credit payment account
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
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
                VALUES (?, 'expense_voucher', 'expense_voucher', ?, ?, ?, ?, 0, ?)
            ");
            $stmt->execute([$tenant_id, $voucher_id, $payment_account_id, $date, $description, $amount]);
        }
        
        // Insert into post_dated_cheques if payment method is Cheque
        if ($payment_method_id == 6 && $cheque_date && $bank_account_id) {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (tenant_id, company_id, cheque_no, account_id, bank_account_id, transaction_type, amount, cheque_date, reference_id, reference_table)
                VALUES (?, ?, ?, ?, ?, 'Expense', ?, ?, ?, 'expense_voucher')
            ");
            $stmt->execute([$tenant_id, $input['company_id'], $cheque_no, $expense_account_id, $bank_account_id, $amount, $cheque_date, $voucher_id]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Expense voucher posted successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to post expense voucher']);
}