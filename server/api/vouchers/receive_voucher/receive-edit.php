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

// Chart-of-accounts IDs used by Receive Voucher on this tenant's live server
if (!defined('ADJUSTMENT_ACCOUNT_ID'))     define('ADJUSTMENT_ACCOUNT_ID', 406);
if (!defined('TRADE_DEBTORS_ACCOUNT_ID'))  define('TRADE_DEBTORS_ACCOUNT_ID', 2);
if (!defined('CASH_ACCOUNT_ID'))           define('CASH_ACCOUNT_ID', 1);
if (!defined('PDC_RECEIVABLE_ACCOUNT_ID')) define('PDC_RECEIVABLE_ACCOUNT_ID', 78);

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $payment_method_id = $input['payment_method_id'] ?? null;
    // Map Cash account (id=7 in dropdown) to id=1 for storage
    if ($payment_method_id == 7) $payment_method_id = 1;

    $amount = (float) ($input['amount'] ?? 0);
    $adjust_amount = (!empty($input['adjust_amount']) && $input['adjust_amount'] !== 'null')
        ? (float) $input['adjust_amount']
        : 0;
    $bank_account_id = !empty($input['bank_account_id']) ? intval($input['bank_account_id']) : null;
    $cheque_no = $input['cheque_no'] ?? null;
    $cheque_date = $input['cheque_date'] ?? null;
    $voucher_date = $input['voucher_date'] ?? '';
    $description = $input['description'] ?? null;
    $dsr_no = $input['dsr_no'] ?? null;
$container_id = !empty($input['container_id']) ? intval($input['container_id']) : null;
$sub_account_id = !empty($input['sub_account_id']) ? intval($input['sub_account_id']) : null;
$recovery_officer_id = !empty($input['recovery_officer_id']) ? $input['recovery_officer_id'] : null;
$against_pdc_id = !empty($input['against_pdc_id']) ? intval($input['against_pdc_id']) : null;

    // Need customer_id for the PDC rebuild below, plus full old row for the Activity Log diff
    $stmt = $pdo->prepare("SELECT voucher_number, customer_id, company_id, voucher_date, bill_no, dsr_no, amount, adjust_amount, currency_id, payment_method_id, bank_account_id, cheque_no, description FROM receive_voucher WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$voucher_id, $tenant_id]);
    $existingVoucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingVoucher) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit();
    }

    $customer_id = $existingVoucher['customer_id'];
    $company_id  = $input['company_id'] ?? $existingVoucher['company_id'];

$stmt = $pdo->prepare("
    UPDATE receive_voucher
    SET
        voucher_date = ?,
        company_id = ?,
        bill_no = ?,
        dsr_no = ?,
        amount = ?,
        adjust_amount = ?,
        currency_id = ?,
        payment_method_id = ?,
        bank_account_id = ?,
        cheque_date = ?,
        cheque_no = ?,
        description = ?,
        project_id = ?,
        container_id = ?,
        sub_account_id = ?,
        recovery_officer_id = ?,
        against_pdc_id = ?,
        updated_by = ?
    WHERE id = ? AND tenant_id = ?
");

$stmt->execute([
    $voucher_date,
    $company_id,
    $input['bill_no'] ?? null,
    $dsr_no,
    $amount,
    $adjust_amount,
    $input['currency_id'] ?? null,
    $payment_method_id,
    $bank_account_id,
    $cheque_date,
    $cheque_no,
    $description,
    $input['project_id'] ?? null,
    $container_id,
    $sub_account_id,
    $recovery_officer_id,
    $against_pdc_id,
    $user_id,
    $voucher_id,
    $tenant_id
]);

    // ---- Re-sync accounting_ledger to match the new amount/adjust_amount/payment method ----
    // Wipe the old entries for this voucher, then rebuild exactly like receive-add.php does.
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);

    // Determine debit account
    if ($payment_method_id == 6) {
        $debit_account_id = PDC_RECEIVABLE_ACCOUNT_ID;
    } elseif ($payment_method_id == 1) {
        $debit_account_id = CASH_ACCOUNT_ID;
    } else {
        $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$bank_account_id, $tenant_id]);
        $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
        $debit_account_id = $bank_account['account_id'] ?? null;
    }

    $ledgerStmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id, account_id,
            date, description, debit, credit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($debit_account_id) {
        $ledgerStmt->execute([
            $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, $debit_account_id,
            $voucher_date, $description, $amount, 0.00
        ]);
    }

    if ($adjust_amount > 0) {
        $ledgerStmt->execute([
            $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, ADJUSTMENT_ACCOUNT_ID,
            $voucher_date, $description, $adjust_amount, 0.00
        ]);
    }

    $ledgerStmt->execute([
        $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, TRADE_DEBTORS_ACCOUNT_ID,
        $voucher_date, $description, 0.00, $amount + $adjust_amount
    ]);

    // ---- Re-sync post_dated_cheques ----
    // Remove any existing PDC row tied to this voucher, then recreate only if still a cheque payment
    $stmt = $pdo->prepare("
        DELETE FROM post_dated_cheques
        WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id = ?
    ");
    $stmt->execute([$tenant_id, $voucher_id]);

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

    $newVoucher = [
        'voucher_number' => $existingVoucher['voucher_number'],
        'customer_id' => $customer_id,
        'company_id' => $company_id,
        'voucher_date' => $voucher_date,
        'bill_no' => $input['bill_no'] ?? null,
        'dsr_no' => $dsr_no,
        'amount' => $amount,
        'adjust_amount' => $adjust_amount,
        'currency_id' => $input['currency_id'] ?? null,
        'payment_method_id' => $payment_method_id,
        'bank_account_id' => $bank_account_id,
        'cheque_no' => $cheque_no,
        'description' => $description
    ];
    $changeDescriptions = buildChangeDescription($existingVoucher, $newVoucher, [
        'voucher_date' => 'Voucher Date', 'bill_no' => 'Bill No', 'amount' => 'Amount',
        'adjust_amount' => 'Adjust Amount', 'payment_method_id' => 'Payment Method',
        'bank_account_id' => 'Bank Account', 'cheque_no' => 'Cheque No', 'description' => 'Description'
    ]);
    $editDescription = !empty($changeDescriptions)
        ? "Updated receive voucher \"{$existingVoucher['voucher_number']}\": " . implode('; ', $changeDescriptions)
        : "Updated receive voucher \"{$existingVoucher['voucher_number']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Receive Voucher', $voucher_id, $existingVoucher['voucher_number'], $editDescription, $existingVoucher, $newVoucher);

    echo json_encode(['success' => true, 'message' => 'Voucher updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}