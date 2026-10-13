<?php
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
    
    $payroll_date = $data['payroll_date'] ?? null;
    $payroll_code = $data['payroll_code'] ?? null;
    $company_id = $data['company_id'] ?? null;
    $items = $data['items'] ?? [];
    $period_start = $data['period_start'] ?? null;
    $period_end = $data['period_end'] ?? null;
    // Existing callers that don't know about the draft/finalized concept
    // keep getting the old always-final behavior; only a caller that
    // explicitly opts in (the new attendance-aware salary flow) starts as
    // 'draft' so it can still be reviewed/corrected before locking.
    $status = $data['status'] ?? 'finalized';
    if (!in_array($status, ['draft', 'finalized'], true)) {
        $status = 'finalized';
    }

    if (!$payroll_date || !$payroll_code || empty($items)) {
        throw new Exception('Missing required fields');
    }

    $pdo->beginTransaction();

    // Calculate total amount
    $total_amount = array_sum(array_column($items, 'amount'));

    // Insert payroll entry
    $stmt = $pdo->prepare("
        INSERT INTO payroll_entries (tenant_id, company_id, payroll_code, payroll_date, total_amount, status, period_start, period_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tenant_id, $company_id, $payroll_code, $payroll_date, $total_amount, $status, $period_start, $period_end]);
    $payroll_id = $pdo->lastInsertId();
    if ($status === 'finalized') {
        $pdo->prepare("UPDATE payroll_entries SET finalized_at = NOW(), finalized_by = ? WHERE id = ?")->execute([$user_id, $payroll_id]);
    }

    // Insert payroll items.
    // ot_hours / ot_amount carry the Overtime a "Calculate" row was suggested with
    // (see calculate-suggested.php) so it stays traceable alongside the existing
    // present/paid-leave/unpaid-leave/absent breakdown — run
    // sql/payroll-ot-migration.sql once before using this (adds those 2 columns,
    // nothing existing is dropped or renamed).
    $stmt = $pdo->prepare("
        INSERT INTO payroll_entries_items
        (tenant_id, payroll_id, employee_id, type, payment_method_id, bank_account_id, cheque_date, description, amount,
         present_days, paid_leave_days, unpaid_leave_days, absent_days, daily_rate, gross_amount, deduction_amount,
         ot_hours, ot_amount, period_start, period_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $stmt->execute([
            $tenant_id,
            $payroll_id,
            $item['employee_code'],
            $item['type'],
            $item['payment_method_id'],
            $item['bank_account_id'] ?? null,
            $item['cheque_date'] ?? null,
            $item['description'] ?? null,
            $item['amount'],
            $item['present_days'] ?? null,
            $item['paid_leave_days'] ?? null,
            $item['unpaid_leave_days'] ?? null,
            $item['absent_days'] ?? null,
            $item['daily_rate'] ?? null,
            $item['gross_amount'] ?? null,
            $item['deduction_amount'] ?? null,
            $item['ot_hours'] ?? null,
            $item['ot_amount'] ?? null,
            $period_start,
            $period_end,
        ]);
    }
    
    // Create accounting entries — only once this payroll is finalized. A 'draft'
    // payroll (built from the attendance/leave "Calculate" flow) is meant to be
    // reviewable/correctable before it hits the books; posting here regardless of
    // status would make the draft/finalized distinction cosmetic. payroll-finalize.php
    // posts these same entries when a draft is later locked.
    if ($status === 'finalized') {
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
            VALUES (?, 'payroll', 'payroll_entries', ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $description = $item['type'] . ' - ' . $item['employee_code'];

            // Get payment account_id
            if ($item['payment_method_id'] == 7) {
                $payment_account_id = 1; // Cash
            } else {
                $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
                $bankStmt->execute([$item['bank_account_id'], $tenant_id]);
                $payment_account_id = $bankStmt->fetchColumn() ?: 1;
            }

            // Determine accounts based on type
            if (in_array($item['type'], ['Advance', 'Advance Return'])) {
                if ($item['type'] === 'Advance') {
                    $stmt->execute([$tenant_id, $payroll_id, 31, $payroll_date, $description, $item['amount'], 0]);
                    $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, 0, $item['amount']]);
                } else {
                    $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, $item['amount'], 0]);
                    $stmt->execute([$tenant_id, $payroll_id, 31, $payroll_date, $description, 0, $item['amount']]);
                }
            } else {
                // FIX: was account_id 28 ('Salaries Payable' - a Liability account),
                // now correctly debiting account_id 60 ('Salaries Expense')
                // Note: item['amount'] already includes any Overtime the row was
                // calculated with (see calculate-suggested.php), so it's posted as
                // one combined Salaries Expense figure — no separate OT ledger line.
                $stmt->execute([$tenant_id, $payroll_id, 60, $payroll_date, $description, $item['amount'], 0]);
                $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, 0, $item['amount']]);
            }
        }
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Payroll', $payroll_id, $payroll_code,
        "Created payroll entry \"$payroll_code\" for $payroll_date, total " . number_format($total_amount, 2) . ", " . count($items) . " item(s)",
        null,
        ['payroll_code' => $payroll_code, 'payroll_date' => $payroll_date, 'company_id' => $company_id, 'total_amount' => $total_amount]
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payroll entry created successfully',
        'payroll_id' => $payroll_id,
        'payroll_code' => $payroll_code
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}