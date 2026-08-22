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

    $amount = (float) ($input['amount'] ?? 0);
    $adjust_amount = (!empty($input['adjust_amount']) && $input['adjust_amount'] !== 'null')
        ? (float) $input['adjust_amount']
        : 0;
    $bank_account_id = $input['bank_account_id'] ?? null;
    $cheque_date = $input['cheque_date'] ?? null;
    $cheque_no = $input['cheque_no'] ?? null;
    $voucher_date = $input['voucher_date'] ?? '';

    // Need customer_id + company_id for rebuilding ledger entries (not sent by the edit form)
    $stmt = $pdo->prepare("SELECT customer_id, company_id, tenant_id FROM receive_voucher WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$voucher_id, $tenant_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit();
    }

    $customer_id = $existing['customer_id'];
    $company_id = $input['company_id'] ?? $existing['company_id'];

    $pdo->beginTransaction();

    // 1) Update the voucher row (now including adjust_amount)
    $stmt = $pdo->prepare("
        UPDATE receive_voucher
        SET
            voucher_date = ?,
            company_id = ?,
            bill_no = ?,
            amount = ?,
            adjust_amount = ?,
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
        $voucher_date,
        $company_id,
        $input['bill_no'] ?? null,
        $amount,
        $adjust_amount,
        $input['currency_id'] ?? null,
        $payment_method_id,
        $bank_account_id,
        $cheque_date,
        $cheque_no,
        $input['description'] ?? null,
        $input['project_id'] ?? null,
        $user_id,
        $voucher_id,
        $tenant_id
    ]);

    // 2) Wipe out the OLD ledger + PDC entries for this voucher — they were built from the
    //    pre-edit amount/adjust_amount/payment method and are now stale.
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger
        WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id = ?
    ");
    $stmt->execute([$tenant_id, $voucher_id]);

    $stmt = $pdo->prepare("
        DELETE FROM post_dated_cheques
        WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id = ?
    ");
    $stmt->execute([$tenant_id, $voucher_id]);

    // 3) Re-insert a fresh post-dated cheque row if the (possibly new) payment method is PDC (id 6)
    if ($payment_method_id == 6) {
        $stmt = $pdo->prepare("
            INSERT INTO post_dated_cheques (
                tenant_id, company_id, cheque_no, bank_account_id, transaction_type, customer_id,
                amount, cheque_date, reference_id, reference_table
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id, $company_id, $cheque_no, $bank_account_id, 'Received', $customer_id,
            $amount, $cheque_date, $voucher_id, 'receive_voucher'
        ]);
    }

    // 4) Recompute the debit account the same way receive-add.php does
    if ($payment_method_id == 6) {
        $debit_account_id = 78; // Post-Dated Cheques Receivable
    } elseif ($payment_method_id == 1) {
        $debit_account_id = 1; // Cash
    } else {
        $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$bank_account_id, $tenant_id]);
        $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
        $debit_account_id = $bank_account['account_id'] ?? null;
    }

    // 5) Re-insert fresh ledger entries matching the updated amount/adjust_amount
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id, account_id,
            date, description, debit, credit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Actual cash/bank/cheque received
    $stmt->execute([
        $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, $debit_account_id,
        $voucher_date, $input['description'] ?? null, $amount, 0.00
    ]);

    // Adjustment portion, if any
    if ($adjust_amount > 0) {
        $stmt->execute([
            $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, 324,
            $voucher_date, $input['description'] ?? null, $adjust_amount, 0.00
        ]);
    }

    // Trade Debtors credit for amount + adjust combined
    $stmt->execute([
        $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, 2,
        $voucher_date, $input['description'] ?? null, 0.00, $amount + $adjust_amount
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Voucher updated successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}