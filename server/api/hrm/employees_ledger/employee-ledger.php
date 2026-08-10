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
        $transSql = "SELECT pei.created_at as date, pe.payroll_code as voucher_no, 
                            pei.type, pei.description, pei.amount, pei.employee_id
                     FROM payroll_entries_items pei
                     JOIN payroll_entries pe ON pei.payroll_id = pe.id
                     WHERE pei.tenant_id = ? AND pei.employee_id = ?";
        $transParams = [$tenant_id, $emp['employee_id']];
        
        if ($start_date) {
            $transSql .= " AND DATE(pei.created_at) >= ?";
            $transParams[] = $start_date;
        }
        if ($end_date) {
            $transSql .= " AND DATE(pei.created_at) <= ?";
            $transParams[] = $end_date;
        }
        
        $transSql .= " ORDER BY pei.created_at ASC";
        
        $transStmt = $pdo->prepare($transSql);
        $transStmt->execute($transParams);
        $empTransactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($empTransactions as $trans) {
            // Debit: Advance (employee owes company)
            // Credit: Advance Return, Salary Adjustment (employee pays back)
            // Salary and other payments shown in debit for record only (don't affect balance)
            $debit = 0;
            $credit = 0;
            $displayAmount = 0;
            
            if (in_array($trans['type'], ['Advance'])) {
                $debit = $trans['amount'];
            } elseif (in_array($trans['type'], ['Advance Return', 'Salary Adjustment'])) {
                $credit = $trans['amount'];
            } else {
                // Salary, Commission, Bonus, Allowance, Daily Wages - show in debit column for display
                $displayAmount = $trans['amount'];
            }
            
            $transactions[] = [
                'employeeId' => $trans['employee_id'],
                'date' => date('Y-m-d', strtotime($trans['date'])),
                'voucherNo' => $trans['voucher_no'],
                'particulars' => $trans['description'] ?: $trans['type'],
                'debit' => (float)$debit,
                'credit' => (float)$credit,
                'displayAmount' => (float)$displayAmount
            ];
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
        $softOpeningBalance = $hardOpeningBalance;
        if ($start_date) {
            $beforeDateSql = "SELECT pei.type, pei.amount
                             FROM payroll_entries_items pei
                             JOIN payroll_entries pe ON pei.payroll_id = pe.id
                             WHERE pei.tenant_id = ? AND pei.employee_id = ? AND DATE(pei.created_at) < ?";
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