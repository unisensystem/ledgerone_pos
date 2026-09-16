<?php
// Locks a draft payroll entry so it can no longer be silently edited or
// deleted (payroll-edit.php / payroll-delete.php both require an explicit
// acknowledge_finalized flag once status = 'finalized').
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $payrollId = (int) ($data['payroll_id'] ?? 0);
    if (!$payrollId) throw new Exception('payroll_id is required');

    $stmt = $pdo->prepare("SELECT payroll_code, payroll_date, status FROM payroll_entries WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$payrollId, $tenant_id]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payroll) throw new Exception('Payroll entry not found');
    if ($payroll['status'] === 'finalized') throw new Exception('Already finalized');

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE payroll_entries SET status = 'finalized', finalized_at = NOW(), finalized_by = ? WHERE id = ?");
    $stmt->execute([$user_id, $payrollId]);

    // A draft payroll was never posted to the books (see payroll-add.php) —
    // finalizing it is what actually commits it, so post the ledger entries now.
    $itemsStmt = $pdo->prepare("SELECT employee_id AS employee_code, type, payment_method_id, bank_account_id, amount FROM payroll_entries_items WHERE tenant_id = ? AND payroll_id = ?");
    $itemsStmt->execute([$tenant_id, $payrollId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $ledgerStmt = $pdo->prepare("
        INSERT INTO accounting_ledger
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, 'payroll', 'payroll_entries', ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $description = $item['type'] . ' - ' . $item['employee_code'];

        if ($item['payment_method_id'] == 7) {
            $payment_account_id = 1; // Cash
        } else {
            $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
            $bankStmt->execute([$item['bank_account_id'], $tenant_id]);
            $payment_account_id = $bankStmt->fetchColumn() ?: 1;
        }

        if (in_array($item['type'], ['Advance', 'Advance Return'])) {
            if ($item['type'] === 'Advance') {
                $ledgerStmt->execute([$tenant_id, $payrollId, 31, $payroll['payroll_date'], $description, $item['amount'], 0]);
                $ledgerStmt->execute([$tenant_id, $payrollId, $payment_account_id, $payroll['payroll_date'], $description, 0, $item['amount']]);
            } else {
                $ledgerStmt->execute([$tenant_id, $payrollId, $payment_account_id, $payroll['payroll_date'], $description, $item['amount'], 0]);
                $ledgerStmt->execute([$tenant_id, $payrollId, 31, $payroll['payroll_date'], $description, 0, $item['amount']]);
            }
        } else {
            // account_id 60 = Salaries Expense (see payroll-add.php's account_id fix note).
            $ledgerStmt->execute([$tenant_id, $payrollId, 60, $payroll['payroll_date'], $description, $item['amount'], 0]);
            $ledgerStmt->execute([$tenant_id, $payrollId, $payment_account_id, $payroll['payroll_date'], $description, 0, $item['amount']]);
        }
    }

    logActivity($pdo, $tenant_id, $user_id, 'update', 'Payroll', $payrollId, $payroll['payroll_code'], "Finalized payroll \"{$payroll['payroll_code']}\" — posted to accounting ledger and locked from further edits", ['status' => 'draft'], ['status' => 'finalized']);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Payroll finalized, posted to the ledger, and locked']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
