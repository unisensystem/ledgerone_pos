<?php
// Given an employee + payroll period, reads their APPROVED leave requests and
// APPROVED overtime from the Leave module (leave_records table) for that
// period and returns a suggested Salary amount with a traceable breakdown
// (paid leave days, unpaid leave days, daily rate, deduction, overtime).
// Read-only — never writes anything. The payroll UI shows this as a starting
// point; HR can still override the final amount before saving (see
// payroll-add.php).
//
// Pending and rejected leave/overtime are never counted here — only
// 'approved' rows affect the suggested amount. Pending rows in the period
// are still reported (pending_leave_requests) so HR can see why something
// might be missing.
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Same formula as hrm/leave/leave-request.php's calculateDailyRate() so the
// rate used to deduct unpaid leave here always matches the rate the leave
// request itself was filed and priced against.
function calculateDailyRate(array $emp): float {
    $salary = (float) ($emp['base_salary'] ?? 0);
    $freq   = $emp['salary_frequency'] ?? 'monthly';
    $workDaysPerWeek = (float) ($emp['work_days_per_week'] ?? 6);
    if ($workDaysPerWeek <= 0) $workDaysPerWeek = 6;

    switch ($freq) {
        case 'daily':
            return round($salary, 2);
        case 'weekly':
            return round($salary / $workDaysPerWeek, 2);
        case 'yearly':
        case 'annual':
            return round($salary / ($workDaysPerWeek * 52), 2);
        case 'monthly':
        default:
            $workDaysPerMonth = $workDaysPerWeek * 4.345;
            return $workDaysPerMonth > 0 ? round($salary / $workDaysPerMonth, 2) : 0.0;
    }
}

try {
    $employeeCode = $_GET['employee_id'] ?? '';
    $periodStart = $_GET['period_start'] ?? '';
    $periodEnd = $_GET['period_end'] ?? '';

    if (!$employeeCode || !$periodStart || !$periodEnd) {
        throw new Exception('employee_id, period_start and period_end are required');
    }
    if (strtotime($periodEnd) < strtotime($periodStart)) {
        throw new Exception('period_end cannot be before period_start');
    }

    $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ? AND tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$employeeCode, $tenant_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) throw new Exception('Employee not found');

    $dailyRate = calculateDailyRate($employee);

    // Approved leave requests (range kind — Annual/Casual/Sick/custom types)
    // that overlap this period at all. paid_days/unpaid_days are taken as
    // already stored on the leave request (set when it was approved, or by
    // the excess-days review flow) rather than re-derived here.
    $stmt = $pdo->prepare("
        SELECT lr.id, lr.start_date, lr.end_date, lr.total_days, lr.paid_days, lr.unpaid_days,
               lt.name AS leave_type_name
        FROM leave_records lr
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.tenant_id = ? AND lr.employee_id = ? AND lr.status = 'approved'
          AND lr.leave_type_id IS NOT NULL AND lr.ot_hours IS NULL
          AND NOT (lr.end_date < ? OR lr.start_date > ?)
        ORDER BY lr.start_date ASC
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $leaveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paidLeaveDays = 0.0; $unpaidLeaveDays = 0.0;
    $leaveRecords = [];
    foreach ($leaveRows as $r) {
        $paidLeaveDays += (float) $r['paid_days'];
        $unpaidLeaveDays += (float) $r['unpaid_days'];
        $leaveRecords[] = [
            'start_date'  => $r['start_date'],
            'end_date'    => $r['end_date'],
            'leave_type'  => $r['leave_type_name'] ?? 'Leave',
            'total_days'  => (float) $r['total_days'],
            'paid_days'   => (float) $r['paid_days'],
            'unpaid_days' => (float) $r['unpaid_days'],
        ];
    }

    // Pending leave overlapping this period — not counted, just surfaced so
    // HR knows a request is still awaiting approval.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM leave_records
        WHERE tenant_id = ? AND employee_id = ? AND status = 'pending'
          AND leave_type_id IS NOT NULL AND ot_hours IS NULL
          AND NOT (end_date < ? OR start_date > ?)
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $pendingLeaveCount = (int) $stmt->fetchColumn();

    // Approved overtime logged in this period.
    $stmt = $pdo->prepare("
        SELECT start_date AS ot_date, ot_hours, ot_rate_multiplier, ot_amount
        FROM leave_records
        WHERE tenant_id = ? AND employee_id = ? AND ot_hours IS NOT NULL
          AND status = 'approved' AND start_date BETWEEN ? AND ?
        ORDER BY start_date ASC
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $otRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $otHoursTotal = 0.0; $otAmountTotal = 0.0; $otRecords = [];
    foreach ($otRows as $r) {
        $otHoursTotal += (float) $r['ot_hours'];
        $otAmountTotal += (float) $r['ot_amount'];
        $otRecords[] = [
            'date'            => $r['ot_date'],
            'hours'           => (float) $r['ot_hours'],
            'rate_multiplier' => (float) $r['ot_rate_multiplier'],
            'amount'          => (float) $r['ot_amount'],
        ];
    }
    $otAmountTotal = round($otAmountTotal, 2);

    $baseSalary = (float) $employee['base_salary'];
    $grossAmount = $baseSalary
        + (float) $employee['housing_allowance'] + (float) $employee['transport_allowance']
        + (float) $employee['medical_allowance'] + (float) $employee['meal_allowance']
        + (float) $employee['communication_allowance'] + (float) $employee['education_allowance']
        + (float) $employee['travel_allowance'] + (float) $employee['other_allowance'];

    $unpaidDeduction = round($unpaidLeaveDays * $dailyRate, 2);
    // Clamp at 0 — unpaid leave should never push the suggested figure below
    // zero. Overtime is added back in as a straight plus.
    $netAmount = max(0, round($grossAmount - $unpaidDeduction + $otAmountTotal, 2));

    echo json_encode([
        'success' => true,
        'employee' => ['employee_id' => $employeeCode, 'full_name' => $employee['full_name'], 'base_salary' => $baseSalary],
        'period' => ['start' => $periodStart, 'end' => $periodEnd],
        'daily_rate' => $dailyRate,
        'attendance' => [
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'pending_leave_requests' => $pendingLeaveCount,
        ],
        // Approved leave requests overlapping the period — for the "Details" popup.
        'leave_records' => $leaveRecords,
        'overtime' => [
            'total_hours' => $otHoursTotal,
            'total_amount' => $otAmountTotal,
            'records' => $otRecords,
        ],
        'gross_amount' => $grossAmount,
        'unpaid_leave_deduction' => $unpaidDeduction,
        'suggested_net_amount' => $netAmount,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}