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
    
    $payroll_id = $data['payroll_id'] ?? null;
    $payroll_date = $data['payroll_date'] ?? null;
    $payroll_code = $data['payroll_code'] ?? null;
    $company_id = $data['company_id'] ?? null;
    $items = $data['items'] ?? [];
    
    if (!$payroll_id || !$payroll_date || !$payroll_code || empty($items)) {
        throw new Exception('Missing required fields');
    }
    
    // Snapshot old header + items for the Activity Log (before mutation)
    $stOld = $pdo->prepare("SELECT payroll_code, payroll_date, company_id, total_amount, status FROM payroll_entries WHERE id = ? AND tenant_id = ?");
    $stOld->execute([$payroll_id, $tenant_id]);
    $oldPayroll = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldPayroll) throw new Exception('Payroll entry not found');
    if ($oldPayroll['status'] === 'finalized' && empty($data['acknowledge_finalized'])) {
        throw new Exception("This payroll (\"{$oldPayroll['payroll_code']}\") is finalized and locked. Resubmit with acknowledge_finalized=true to make a controlled correction.");
    }

    $stOldItems = $pdo->prepare("SELECT employee_id, type, payment_method_id, bank_account_id, amount FROM payroll_entries_items WHERE tenant_id = ? AND payroll_id = ? ORDER BY id");
    $stOldItems->execute([$tenant_id, $payroll_id]);
    $oldItems = $stOldItems->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // Calculate total amount
    $total_amount = array_sum(array_column($items, 'amount'));

    // Update payroll entry
    $stmt = $pdo->prepare("
        UPDATE payroll_entries
        SET payroll_date = ?, total_amount = ?, company_id = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$payroll_date, $total_amount, $company_id, $payroll_id, $tenant_id]);

    // Delete old accounting entries — only relevant if this payroll was ever posted
    // (i.e. it's finalized). A draft payroll being edited never had ledger entries
    // in the first place (see payroll-add.php), so there's nothing to remove.
    if ($oldPayroll['status'] === 'finalized') {
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'payroll' AND reference_table = 'payroll_entries' AND reference_id = ?");
        $stmt->execute([$tenant_id, $payroll_id]);
    }

    // Delete old payroll items
    $stmt = $pdo->prepare("DELETE FROM payroll_entries_items WHERE tenant_id = ? AND payroll_id = ?");
    $stmt->execute([$tenant_id, $payroll_id]);
    
    // Insert new payroll items
    $stmt = $pdo->prepare("
        INSERT INTO payroll_entries_items
        (tenant_id, payroll_id, employee_id, type, payment_method_id, bank_account_id, cheque_date, description, amount,
         present_days, paid_leave_days, unpaid_leave_days, absent_days, daily_rate, gross_amount, deduction_amount, period_start, period_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $data['period_start'] ?? null,
            $data['period_end'] ?? null,
        ]);
    }
    
    // Create new accounting entries — same gating as the delete above: only a
    // finalized payroll is on the books at all, so only re-post for one.
    if ($oldPayroll['status'] === 'finalized') {
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
                // account_id 60 = Salaries Expense. (This used to post to 28,
                // Salaries Payable — a Liability account — which payroll-add.php
                // already had a fix for; this edit path had been missed.)
                $stmt->execute([$tenant_id, $payroll_id, 60, $payroll_date, $description, $item['amount'], 0]);
                $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, 0, $item['amount']]);
            }
        }
    }

    $newPayroll = ['payroll_code' => $oldPayroll['payroll_code'], 'payroll_date' => $payroll_date, 'company_id' => $company_id, 'total_amount' => $total_amount];
    $changeDescriptions = buildChangeDescription($oldPayroll, $newPayroll, [
        'payroll_date' => 'Payroll Date', 'company_id' => 'Company', 'total_amount' => 'Total Amount'
    ]);
    $newItemsForCompare = array_map(function($it) {
        return [
            'employee_id' => $it['employee_code'], 'type' => $it['type'], 'payment_method_id' => $it['payment_method_id'],
            'bank_account_id' => $it['bank_account_id'] ?? null, 'amount' => (float)$it['amount']
        ];
    }, $items);
    if (itemsChanged($oldItems, $newItemsForCompare, ['employee_id', 'type', 'payment_method_id', 'bank_account_id', 'amount'])) {
        $changeDescriptions[] = 'Payroll items updated';
    }
    $editDescription = !empty($changeDescriptions)
        ? "Updated payroll entry \"{$oldPayroll['payroll_code']}\": " . implode('; ', $changeDescriptions)
        : "Updated payroll entry \"{$oldPayroll['payroll_code']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Payroll', $payroll_id, $oldPayroll['payroll_code'], $editDescription, $oldPayroll, $newPayroll);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payroll entry updated successfully',
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