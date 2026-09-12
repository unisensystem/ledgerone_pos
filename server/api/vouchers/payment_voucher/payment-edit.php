<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/posting_lock.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Voucher ID is required']);
    exit();
}

try {
    // Get current voucher (need old values to know what changed)
    $stmt = $pdo->prepare("
        SELECT voucher_number, voucher_date, payment_method_id, company_id, bank_account_id, cheque_no, cheque_date,
               supplier_id, customer_id, sub_account_id, bill_no, amount as old_amount, currency_id, description
        FROM payment_voucher
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit();
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit();
    }

    $voucher_date = $input['voucher_date'] ?? '';
    if (empty($voucher_date)) {
        echo json_encode(['success' => false, 'error' => 'Voucher date is required']);
        exit();
    }

    // Check posting lock for the new date
    $lockCheck = checkPostingDateAllowed($pdo, $tenant_id, 'payment_voucher', $voucher_date);
    if (!$lockCheck['allowed']) {
        echo json_encode(['success' => false, 'error' => $lockCheck['message']]);
        exit();
    }

    $company_id = $input['company_id'] ?? null;
    if (empty($company_id)) {
        echo json_encode(['success' => false, 'error' => 'Company is required']);
        exit();
    }

    $sub_account_id         = !empty($input['sub_account_id']) ? $input['sub_account_id'] : null;
    $bill_no                = !empty($input['bill_no']) ? $input['bill_no'] : null;
    $amount                 = $input['amount'] ?? 0;
    $currency_id            = $input['currency_id'] ?? null;
    $payment_method_id      = $input['payment_method_id'] ?? null;
    $bank_account_id        = !empty($input['bank_account_id']) ? $input['bank_account_id'] : null;
    $cheque_no              = !empty($input['cheque_no']) ? $input['cheque_no'] : null;
    $cheque_date            = !empty($input['cheque_date']) ? $input['cheque_date'] : null;
    $description            = !empty($input['description']) ? $input['description'] : null;
    $project_id             = !empty($input['project_id']) ? $input['project_id'] : null;
    $container_id           = !empty($input['container_id']) ? $input['container_id'] : null;
    $expense_voucher_ref_id = !empty($input['expense_voucher_ref_id']) ? $input['expense_voucher_ref_id'] : null;

    if (empty($currency_id) || empty($payment_method_id)) {
        echo json_encode(['success' => false, 'error' => 'Currency and payment method are required']);
        exit();
    }

    // If new method is cheque (6), cheque_no and cheque_date are required
    if ($payment_method_id == 6 && (empty($cheque_no) || empty($cheque_date))) {
        echo json_encode(['success' => false, 'error' => 'Cheque number and cheque date are required for cheque payments']);
        exit();
    }

    $old_method = $voucher['payment_method_id'];
    $new_method = $payment_method_id;
    $entity_id  = $voucher['supplier_id'] ?? $voucher['customer_id'];
    $entity_type = $voucher['supplier_id'] ? 'supplier' : 'customer';

    $pdo->beginTransaction();

    // Update payment voucher
    $stmt = $pdo->prepare("
        UPDATE payment_voucher 
        SET voucher_date = ?, company_id = ?, sub_account_id = ?, bill_no = ?, amount = ?, currency_id = ?, 
            payment_method_id = ?, bank_account_id = ?, cheque_no = ?, cheque_date = ?, description = ?, 
            project_id = ?, container_id = ?, expense_voucher_ref_id = ?, updated_by = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $voucher_date, $company_id, $sub_account_id, $bill_no, $amount, $currency_id,
        $payment_method_id, $bank_account_id, $cheque_no, $cheque_date, $description,
        $project_id, $container_id, $expense_voucher_ref_id, $user_id,
        $voucher_id, $tenant_id
    ]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Voucher not found or no changes made']);
        exit();
    }

    // --- Handle post-dated cheque record based on method transition ---
    if ($old_method == 6 && $new_method != 6) {
        // Was cheque, now something else -> remove the PDC record
        $stmt = $pdo->prepare("
            DELETE FROM post_dated_cheques 
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ?
        ");
        $stmt->execute([$tenant_id, $voucher_id]);

    } elseif ($old_method != 6 && $new_method == 6) {
        // Was something else, now cheque -> insert new PDC record
        if ($entity_type === 'customer') {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type, customer_id,
                    amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, 'Payment', ?, ?, ?, ?, 'payment_voucher')
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type, supplier_id,
                    amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, 'Payment', ?, ?, ?, ?, 'payment_voucher')
            ");
        }
        $stmt->execute([
            $tenant_id, $company_id, $cheque_no, $bank_account_id, $entity_id,
            $amount, $cheque_date, $voucher_id
        ]);

    } elseif ($old_method == 6 && $new_method == 6) {
        // Still cheque -> update the existing PDC record's details
        $stmt = $pdo->prepare("
            UPDATE post_dated_cheques 
            SET amount = ?, cheque_no = ?, cheque_date = ?, bank_account_id = ?, company_id = ?
            WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ?
        ");
        $stmt->execute([$amount, $cheque_no, $cheque_date, $bank_account_id, $company_id, $tenant_id, $voucher_id]);
    }
    // else: old != 6 and new != 6 -> no PDC involved either way, nothing to do

    // --- Recompute debit/credit accounts based on the NEW payment method ---
    if ($new_method == 6) {
        $credit_account_id = 79; // Post Dated Cheques Receivable
        $debit_account_id = $entity_type === 'customer' ? 2 : 14; // Trade Debtors or Trade Creditors
    } else {
        $debit_account_id = $entity_type === 'customer' ? 2 : 14;
        if ($new_method == 7) {
            $credit_account_id = 1; // Cash
        } else {
            if ($bank_account_id) {
                $baStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
                $baStmt->execute([$bank_account_id, $tenant_id]);
                $bankAccount = $baStmt->fetch(PDO::FETCH_ASSOC);
                $credit_account_id = $bankAccount['account_id'] ?? 1;
            } else {
                $credit_account_id = 1; // Default to Cash if no bank account selected
            }
        }
    }

    // Update accounting ledger entries (debit row and credit row), including account_id
    // in case the payment method / bank account changed and the credit account differs now.
    $stmt = $pdo->prepare("
        UPDATE accounting_ledger 
        SET date = ?, description = ?, debit = ?, credit = ?, account_id = ?
        WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ? AND debit > 0
    ");
    $stmt->execute([$voucher_date, $description, $amount, 0.00, $debit_account_id, $tenant_id, $voucher_id]);

    $stmt = $pdo->prepare("
        UPDATE accounting_ledger 
        SET date = ?, description = ?, debit = ?, credit = ?, account_id = ?
        WHERE tenant_id = ? AND reference_table = 'payment_voucher' AND reference_id = ? AND credit > 0
    ");
    $stmt->execute([$voucher_date, $description, 0.00, $amount, $credit_account_id, $tenant_id, $voucher_id]);

    $pdo->commit();

    $oldVoucherForLog = [
        'voucher_date' => $voucher['voucher_date'], 'bill_no' => $voucher['bill_no'], 'amount' => $voucher['old_amount'],
        'currency_id' => $voucher['currency_id'], 'payment_method_id' => $voucher['payment_method_id'],
        'bank_account_id' => $voucher['bank_account_id'], 'cheque_no' => $voucher['cheque_no'], 'description' => $voucher['description']
    ];
    $newVoucherForLog = [
        'voucher_date' => $voucher_date, 'bill_no' => $bill_no, 'amount' => $amount, 'currency_id' => $currency_id,
        'payment_method_id' => $payment_method_id, 'bank_account_id' => $bank_account_id, 'cheque_no' => $cheque_no, 'description' => $description
    ];
    $changeDescriptions = buildChangeDescription($oldVoucherForLog, $newVoucherForLog, [
        'voucher_date' => 'Voucher Date', 'bill_no' => 'Bill No', 'amount' => 'Amount',
        'payment_method_id' => 'Payment Method', 'bank_account_id' => 'Bank Account',
        'cheque_no' => 'Cheque No', 'description' => 'Description'
    ]);
    $editDescription = !empty($changeDescriptions)
        ? "Updated payment voucher \"{$voucher['voucher_number']}\": " . implode('; ', $changeDescriptions)
        : "Updated payment voucher \"{$voucher['voucher_number']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Payment Voucher', $voucher_id, $voucher['voucher_number'], $editDescription, $oldVoucherForLog, $newVoucherForLog);

    echo json_encode(['success' => true, 'message' => 'Payment voucher updated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}