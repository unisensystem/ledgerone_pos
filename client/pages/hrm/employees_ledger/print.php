<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../../../includes/connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

try {

// Get parameters
$employee_id = $_GET['employee_id'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$view = $_GET['view'] ?? 'summary';

// Get currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

// Get company info
$stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$tenant_id]);
$company = $stmt->fetch();
$company_name = $company['company_name'] ?? 'Company Name';
$company_address = $company['address'] ?? '';
$company_phone = $company['phone'] ?? '';
$company_email = $company['email'] ?? '';
$company_logo = $company['logo_url'] ?? '';

// Get user info
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$generated_by = $user['full_name'] ?? 'Unknown User';

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

$stmt = $pdo->prepare($employeeSql);
$stmt->execute($params);
$employeesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch transactions
$transactions = [];
foreach ($employeesData as $emp) {
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

            if (in_array($trans['type'], ['Advance'])) {
                // Simple debit — employee owes company
                $transactions[] = [
                    'employeeId'    => $trans['employee_id'],
                    'date'          => $date,
                    'voucherNo'     => $trans['voucher_no'],
                    'particulars'   => $trans['type'],
                    'description'   => $trans['description'] ?? '',
                    'debit'         => (float)$trans['amount'],
                    'credit'        => 0,
                    'displayAmount' => 0,
                ];
            } elseif (in_array($trans['type'], ['Advance Return', 'Salary Adjustment'])) {
                // Simple credit — employee pays back
                $transactions[] = [
                    'employeeId'    => $trans['employee_id'],
                    'date'          => $date,
                    'voucherNo'     => $trans['voucher_no'],
                    'particulars'   => $trans['type'],
                    'description'   => $trans['description'] ?? '',
                    'debit'         => 0,
                    'credit'        => (float)$trans['amount'],
                    'displayAmount' => 0,
                ];
            } else {
                // Salary / Commission / Bonus etc — dual leg (Payable + Paid)
                // net effect on balance = 0, same as API
                $hasBreakdown  = isset($trans['gross_amount']) && $trans['gross_amount'] !== null;
                $displayAmt    = $hasBreakdown ? (float)$trans['gross_amount'] : (float)$trans['amount'];

                $description = '';
                if ($hasBreakdown) {
                    $deduction = (float)$trans['deduction_amount'];
                    $ot        = (float)$trans['ot_amount'];
                    $netPaid   = (float)$trans['amount'];
                    $parts = [];
                    if ($trans['absent_days'] > 0)      $parts[] = $trans['absent_days']      . ' absent day(s)';
                    if ($trans['unpaid_leave_days'] > 0) $parts[] = $trans['unpaid_leave_days'] . ' unpaid leave day(s)';
                    $reason = $parts ? implode(' + ', $parts) : 'deductions';

                    $description = 'Gross ' . number_format($displayAmt, 2)
                        . ($deduction > 0 ? ' − ' . number_format($deduction, 2) . " ({$reason})" : '')
                        . ($ot        > 0 ? ' + OT ' . number_format($ot, 2)       : '')
                        . ' = Net Paid ' . number_format($netPaid, 2);
                }

                // Leg 1 — Payable (Credit)
                $transactions[] = [
                    'employeeId'    => $trans['employee_id'],
                    'date'          => $date,
                    'voucherNo'     => $trans['voucher_no'],
                    'particulars'   => $trans['type'] . ' Payable',
                    'description'   => '',           // description only on Paid leg
                    'debit'         => 0,
                    'credit'        => $displayAmt,
                    'displayAmount' => 0,
                ];

                // Leg 2 — Paid (Debit)
                $transactions[] = [
                    'employeeId'    => $trans['employee_id'],
                    'date'          => $date,
                    'voucherNo'     => $trans['voucher_no'],
                    'particulars'   => ($trans['description'] ? $trans['description'] . ' - Paid' : $trans['type'] . ' Paid'),
                    'description'   => $description,  // breakdown shown here
                    'debit'         => $displayAmt,
                    'credit'        => 0,
                    'displayAmount' => 0,
                ];
            }
        }
}

// Format employees
$employees = [];
foreach ($employeesData as $emp) {
    $balanceStmt = $pdo->prepare("SELECT SUM(debit - credit) FROM accounting_ledger WHERE account_id != 90 AND tenant_id = ? AND transaction_type = 'opening_balance' AND reference_table = 'employees' AND reference_id = ?");
    $balanceStmt->execute([$tenant_id, $emp['id']]);
    $hardOpeningBalance = $balanceStmt->fetchColumn() ?: 0;
    
    $softOpeningBalance = $hardOpeningBalance;
    if ($start_date) {
        $beforeDateSql = "SELECT pei.type, pei.amount, pei.gross_amount, pei.deduction_amount, pei.ot_amount,
                                 pei.absent_days, pei.unpaid_leave_days
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
    
    $employees[] = [
        'id'             => $emp['employee_id'],
        'name'           => $emp['full_name'],
        'department'     => $emp['department_name'] ?: 'N/A',
        'openingBalance' => (float)abs($softOpeningBalance),
        'openingType'    => $softOpeningBalance >= 0 ? 'Dr' : 'Cr',
    ];
}

} catch (Exception $e) {
    die('Error: ' . $e->getMessage() . '<br>Line: ' . $e->getLine() . '<br>File: ' . $e->getFile());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Ledger Report - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 12px;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .print-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .print-header h2 {
            font-size: 18px;
            font-weight: normal;
            margin-bottom: 10px;
        }
        
        .print-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #f0f0f0;
            padding: 8px;
            text-align: left;
            border: 1px solid #000;
            font-weight: bold;
        }
        
        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        /* Description column: smaller, muted */
        .col-description {
            font-size: 10px;
            color: #555;
            max-width: 200px;
        }
        
        .amount-debit {
            color: #c00;
            text-align: right;
        }
        
        .amount-credit {
            color: #080;
            text-align: right;
        }
        
        .balance-dr {
            color: #c00;
            font-weight: bold;
            text-align: right;
        }
        
        .balance-cr {
            color: #080;
            font-weight: bold;
            text-align: right;
        }
        
        .balance-row {
            background: #f9f9f9;
            font-weight: bold;
        }
        
        .employee-details-row {
            background: #e8e8e8;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #000;
            font-size: 10px;
            text-align: center;
        }
        
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <!-- ===== HEADER ===== -->
    <div class="print-header">
        <?php if ($company_logo): ?>
            <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($company_name); ?></h1>
        <?php if ($company_address): ?>
            <p style="margin: 5px 0; font-size: 11px;"><?php echo htmlspecialchars($company_address); ?></p>
        <?php endif; ?>
        <?php if ($company_phone || $company_email): ?>
            <p style="margin: 5px 0; font-size: 11px;">
                <?php if ($company_phone): ?>Tel: <?php echo htmlspecialchars($company_phone); ?><?php endif; ?>
                <?php if ($company_phone && $company_email): ?> | <?php endif; ?>
                <?php if ($company_email): ?>Email: <?php echo htmlspecialchars($company_email); ?><?php endif; ?>
            </p>
        <?php endif; ?>
        <h2 style="margin-top: 10px;">Employee Ledger Report</h2>
    </div>

    <!-- ===== PRINT INFO ===== -->
    <div class="print-info">
        <div>
            <strong>Period:</strong>
            <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?>
        </div>
        <div>
            <strong>Generated By:</strong> <?php echo htmlspecialchars($generated_by); ?><br>
            <strong>Print Date:</strong> <?php echo date('d M Y h:i A'); ?>
        </div>
    </div>

    <?php if ($view === 'summary'): ?>
    <!-- ===================================================
         SUMMARY VIEW  (no Description column here — totals only)
         =================================================== -->
    <table>
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Department</th>
                <th class="text-right">Opening Balance</th>
                <th class="text-right">Total Debit</th>
                <th class="text-right">Total Credit</th>
                <th class="text-right">Closing Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $employeeIds = array_unique(array_column($transactions, 'employeeId'));
            if (empty($employeeIds)) {
                $employeeIds = array_column($employees, 'id');
            }

            foreach ($employeeIds as $empId):
                $employee = array_values(array_filter($employees, fn($e) => $e['id'] === $empId))[0] ?? null;
                if (!$employee) continue;

                $empTrans = array_filter($transactions, fn($t) => $t['employeeId'] === $empId);
                $totalDebit  = array_sum(array_column($empTrans, 'debit'));
                $totalCredit = array_sum(array_column($empTrans, 'credit'));

                $openingBalance = $employee['openingBalance'];
                $openingType    = $employee['openingType'];
                $signedOpening  = $openingType === 'Cr' ? -$openingBalance : $openingBalance;

                $closingBalance = $signedOpening + $totalDebit - $totalCredit;
                $closingType    = $closingBalance >= 0 ? 'Dr' : 'Cr';
                $absClosing     = abs($closingBalance);

                if ($employee_id === 'all' && $absClosing === 0 && $openingBalance === 0) continue;
            ?>
            <tr>
                <td><?php echo strtoupper($empId); ?></td>
                <td><?php echo htmlspecialchars($employee['name']); ?></td>
                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                <td class="text-right">
                    <?php echo $currency_symbol . number_format($employee['openingBalance'], 0) . ' ' . $openingType; ?>
                </td>
                <td class="amount-debit"><?php echo $currency_symbol . number_format($totalDebit, 0); ?></td>
                <td class="amount-credit"><?php echo $currency_symbol . number_format($totalCredit, 0); ?></td>
                <td class="<?php echo $closingType === 'Dr' ? 'balance-dr' : 'balance-cr'; ?>">
                    <?php echo $currency_symbol . number_format($absClosing, 0) . ' ' . $closingType; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php else: ?>
    <!-- ===================================================
         INDIVIDUAL VIEW  (with Description column)
         7 columns: Date | Voucher | Particulars | Description | Debit | Credit | Balance
         =================================================== -->
    <?php
    $employeeIds = array_unique(array_column($transactions, 'employeeId'));

    foreach ($employeeIds as $empId):
        $employee = array_values(array_filter($employees, fn($e) => $e['id'] === $empId))[0] ?? null;
        if (!$employee) continue;

        $empTrans = array_values(array_filter($transactions, fn($t) => $t['employeeId'] === $empId));
        usort($empTrans, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));
    ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Voucher No.</th>
                <th>Particulars</th>
                <th>Description</th>
                <th class="text-right">Debit (Dr)</th>
                <th class="text-right">Credit (Cr)</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <!-- Employee header row -->
            <tr class="employee-details-row">
                <td colspan="7">
                    <?php echo htmlspecialchars($employee['name'])
                        . ' (' . strtoupper($empId) . ')'
                        . ' - ' . htmlspecialchars($employee['department']); ?>
                </td>
            </tr>

            <!-- Opening balance row -->
            <tr class="balance-row">
                <td colspan="4">Opening Balance</td>
                <td colspan="2"></td>
                <td class="<?php echo $employee['openingType'] === 'Dr' ? 'balance-dr' : 'balance-cr'; ?>">
                    <?php
                    echo $employee['openingBalance']
                        ? $currency_symbol . number_format($employee['openingBalance'], 0) . ' ' . $employee['openingType']
                        : $currency_symbol . '0';
                    ?>
                </td>
            </tr>

            <?php
            // FIX: keep runningBalance as a magnitude (absolute value),
            // track the sign separately in runningType — same logic as JS.
            $runningBalance = $employee['openingBalance'] ?? 0;
            $runningType    = $employee['openingType']    ?? null;

            foreach ($empTrans as $trans):
                // Convert magnitude → signed for this step
                $signed = ($runningType === 'Cr') ? -$runningBalance : $runningBalance;
                $signed = $signed + $trans['debit'] - $trans['credit'];

                $runningType    = $signed >= 0 ? 'Dr' : 'Cr';
                $runningBalance = abs($signed);            // store back as magnitude

                $debitDisplay = $trans['debit'] > 0
                    ? $currency_symbol . number_format($trans['debit'], 0)
                    : ($trans['displayAmount'] > 0
                        ? $currency_symbol . number_format($trans['displayAmount'], 0)
                        : '');

                // Show description ONLY on debit rows (e.g. Salary Paid, Advance)
                $showDescription = $trans['debit'] > 0 ? htmlspecialchars($trans['description']) : '';
            ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($trans['date'])); ?></td>
                <td><?php echo htmlspecialchars($trans['voucherNo']); ?></td>
                <td><?php echo htmlspecialchars($trans['particulars']); ?></td>
                <td class="col-description"><?php echo $showDescription; ?></td>
                <td class="amount-debit"><?php echo $debitDisplay; ?></td>
                <td class="amount-credit">
                    <?php echo $trans['credit'] > 0 ? $currency_symbol . number_format($trans['credit'], 0) : ''; ?>
                </td>
                <td class="<?php echo $runningType === 'Dr' ? 'balance-dr' : 'balance-cr'; ?>">
                    <?php echo $currency_symbol . number_format($runningBalance, 0) . ' ' . $runningType; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <!-- Closing balance row -->
            <tr class="balance-row">
                <td colspan="4">Closing Balance</td>
                <td colspan="2"></td>
                <td class="<?php echo $runningType === 'Dr' ? 'balance-dr' : 'balance-cr'; ?>">
                    <?php echo $currency_symbol . number_format($runningBalance, 0) . ' ' . $runningType; ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ===== FOOTER ===== -->
    <div class="print-footer">
        <p>This is a computer-generated report and does not require a signature.</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">Print Report</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px;">Close</button>
    </div>

    <script>
        window.onload = function () {
            setTimeout(function () { window.print(); }, 500);
        };
    </script>
</body>
</html>