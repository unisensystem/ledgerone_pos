<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $employee_id = $_GET['employee_id'] ?? 'all';
    $department_id = $_GET['department_id'] ?? 'all';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    // Calculate soft opening balance (balance brought forward)
    $softOpeningBalances = [];

    // Fetch employees
    $employeeSql = "SELECT e.id, e.employee_id, e.full_name, d.department_name 
                    FROM employees e 
                    LEFT JOIN departments d ON e.department_id = d.id 
                    WHERE e.tenant_id = ? AND e.is_active = 1";
    $params = [$tenant_id];
    
    if ($employee_id !== 'all') {
        $employeeSql .= " AND e.employee_id = ?";
        $params[] = $employee_id;
    }
    
    if ($department_id !== 'all') {
        $employeeSql .= " AND e.department_id = ?";
        $params[] = $department_id;
    }
    
    $stmt = $pdo->prepare($employeeSql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch transactions from payroll_entries_items
    $transactions = [];
    foreach ($employees as $emp) {
        // Only finalized payroll is actually committed money — a draft hasn't
        // posted to accounting_ledger yet (see payroll-add.php/payroll-finalize.php)
        // and must not show up here as if it had been paid.
       // Use the payroll's own payroll_date (what the user actually set/backdated
        // this payroll to), not pei.created_at (when the row happened to be
        // inserted into the DB) — otherwise a payroll dated e.g. Sep 24 but
        // entered/finalized on Sep 26 shows up under Sep 26 in the ledger,
        // mismatching payroll-list.php and the date range filter below.
       $transSql = "SELECT pe.payroll_date as date, pe.payroll_code as voucher_no,
                    pei.type, pei.description, pei.amount, pei.employee_id,
                    pei.gross_amount, pei.deduction_amount, pei.ot_amount,
                    pei.absent_days, pei.unpaid_leave_days
             FROM payroll_entries_items pei
             JOIN payroll_entries pe ON pei.payroll_id = pe.id
             WHERE pei.tenant_id = ? AND pei.employee_id = ? AND pe.status = 'finalized'";
        $transParams = [$tenant_id, $emp['employee_id']];

        if ($start_date) {
            $transSql .= " AND pe.payroll_date >= ?";
            $transParams[] = $start_date;
        }
        if ($end_date) {
            $transSql .= " AND pe.payroll_date <= ?";
            $transParams[] = $end_date;
        }

        $transSql .= " ORDER BY pe.payroll_date ASC, pei.id ASC";
        
        $transStmt = $pdo->prepare($transSql);
        $transStmt->execute($transParams);
        $empTransactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($empTransactions as $trans) {
            $date = date('Y-m-d', strtotime($trans['date']));

            // Debit: Advance (employee owes company) — single line, real balance movement.
            // Credit: Advance Return, Salary Adjustment (employee pays back) — single line.
            // Salary / Commission / Bonus / Allowance / Daily Wages are accrued AND paid
            // in the same payroll transaction (payment method is chosen right there), so
            // per the reference ledger example we post BOTH legs instead of a single
            // balance-blind "display only" row:
            //   "<Type> Payable" (Cr — liability incurred)
            //   "<Type> Paid"    (Dr — liability settled)
            // Net effect on the running balance is zero (matches old display-only
            // behavior) but the two legs are now visible/traceable, like Advance/
            // Advance Return already are.
            if (in_array($trans['type'], ['Advance'])) {
                $transactions[] = [
                    'employeeId' => $trans['employee_id'],
                    'date' => $date,
                    'voucherNo' => $trans['voucher_no'],
                    'particulars' => $trans['description'] ?: $trans['type'],
                    'debit' => (float)$trans['amount'],
                    'credit' => 0,
                    'displayAmount' => 0
                ];
            } elseif (in_array($trans['type'], ['Advance Return', 'Salary Adjustment'])) {
                $transactions[] = [
                    'employeeId' => $trans['employee_id'],
                    'date' => $date,
                    'voucherNo' => $trans['voucher_no'],
                    'particulars' => $trans['description'] ?: $trans['type'],
                    'debit' => 0,
                    'credit' => (float)$trans['amount'],
                    'displayAmount' => 0
                ];
         } else {
    // Agar row auto-calculate hui thi (gross_amount stored hai), to Payable
    // aur Paid dono legs pe GROSS (original) salary post karo — net paid
    // nahi. Dono legs ab bhi ek-dusre ko cancel karte hain (net effect 0),
    // isliye balance/closing-balance par koi farq nahi padta — sirf jo
    // number dikhta hai wo gross ho jata hai, aur Description us gross se
    // net tak ka justification deti hai.
    $hasBreakdown = $trans['gross_amount'] !== null;
    $displayAmt = $hasBreakdown ? (float)$trans['gross_amount'] : (float)$trans['amount'];

    $description = '';
    if ($hasBreakdown) {
        $deduction = (float)$trans['deduction_amount'];
        $ot = (float)$trans['ot_amount'];
        $netPaid = (float)$trans['amount'];
        $parts = [];
        if ($trans['absent_days'] > 0) $parts[] = $trans['absent_days'] . ' absent day(s)';
        if ($trans['unpaid_leave_days'] > 0) $parts[] = $trans['unpaid_leave_days'] . ' unpaid leave day(s)';
        $reason = $parts ? implode(' + ', $parts) : 'deductions';

        $description = "Gross " . number_format($displayAmt, 2)
            . ($deduction > 0 ? " − " . number_format($deduction, 2) . " ({$reason})" : "")
            . ($ot > 0 ? " + OT " . number_format($ot, 2) : "")
            . " = Net Paid " . number_format($netPaid, 2);
    }

    $transactions[] = [
        'employeeId' => $trans['employee_id'],
        'date' => $date,
        'voucherNo' => $trans['voucher_no'],
        'particulars' => $trans['type'] . ' Payable',
        'debit' => 0,
        'credit' => $displayAmt,
        'displayAmount' => 0,
        'description' => $description
    ];
    $transactions[] = [
        'employeeId' => $trans['employee_id'],
        'date' => $date,
        'voucherNo' => $trans['voucher_no'],
        'particulars' => $trans['description'] ? ($trans['description'] . ' - Paid') : ($trans['type'] . ' Paid'),
        'debit' => $displayAmt,
        'credit' => 0,
        'displayAmount' => 0,
        'description' => $description
    ];
}
        }
    }

    // Format employees for response with opening balances
    $employeeData = [];
    foreach ($employees as $emp) {
        // Get hard opening balance from accounting_ledger
        $balanceStmt = $pdo->prepare("SELECT SUM(debit - credit) FROM accounting_ledger WHERE account_id != 90 AND tenant_id = ? AND transaction_type = 'opening_balance' AND reference_table = 'employees' AND reference_id = ?");
        $balanceStmt->execute([$tenant_id, $emp['id']]);
        $hardOpeningBalance = $balanceStmt->fetchColumn() ?: 0;
        
        // Calculate soft opening balance (transactions before start_date)
        // NOTE: Salary/Commission/Bonus/Allowance/Daily Wages are intentionally
        // excluded here, same as before — they net to zero (Payable + Paid
        // cancel out), so they never carried into the opening balance and
        // still don't.
        $softOpeningBalance = $hardOpeningBalance;
        if ($start_date) {
            $beforeDateSql = "SELECT pei.type, pei.amount
                             FROM payroll_entries_items pei
                             JOIN payroll_entries pe ON pei.payroll_id = pe.id
                             WHERE pei.tenant_id = ? AND pei.employee_id = ? AND pe.status = 'finalized' AND pe.payroll_date < ?";
            $beforeStmt = $pdo->prepare($beforeDateSql);
            $beforeStmt->execute([$tenant_id, $emp['employee_id'], $start_date]);
            $beforeTransactions = $beforeStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($beforeTransactions as $trans) {
                if (in_array($trans['type'], ['Advance'])) {
                    $softOpeningBalance += $trans['amount'];
                } elseif (in_array($trans['type'], ['Advance Return', 'Salary Adjustment'])) {
                    $softOpeningBalance -= $trans['amount'];
                }
            }
        }
        
        $employeeData[] = [
            'id' => $emp['employee_id'],
            'name' => $emp['full_name'],
            'department' => $emp['department_name'] ?: 'N/A',
            'openingBalance' => (float)abs($softOpeningBalance),
            'openingType' => $softOpeningBalance >= 0 ? 'Dr' : 'Cr'
        ];
    }

    echo json_encode([
        'success' => true,
        'employees' => $employeeData,
        'transactions' => $transactions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}