<?php
// Given an employee + payroll period, reads their ACTUAL attendance/leave
// records for that period and returns a suggested Salary amount with a
// fully traceable breakdown (present/absent/paid-leave/unpaid-leave days,
// daily rate, deduction amounts). Read-only — never writes anything.
// The payroll UI shows this as a starting point; HR can still override the
// final amount before saving (see payroll-add.php).
require_once '../../../../includes/connection.php';
require_once '../leave/leave-helpers.php';

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

    $settings = getHrmSettings($pdo, $tenant_id);
    $workingDaysInPeriod = getWorkingDaysInRange($pdo, $tenant_id, $periodStart, $periodEnd);
    $workingDayCount = count($workingDaysInPeriod);

    // Divisor: the number of days a "full month" of base_salary is spread
    // across, per the tenant's configured policy.
    $divisor = match ($settings['payroll_divisor_basis']) {
        'calendar_days' => (int) date('t', strtotime($periodStart)), // days in that month
        'working_days' => max($workingDayCount, 1),
        default => (int) $settings['payroll_divisor_days'], // 'fixed'
    };

    $baseSalary = (float) $employee['base_salary'];
    $dailyRate = $divisor > 0 ? round($baseSalary / $divisor, 4) : 0;

    // Attendance rows actually recorded in this period.
    $stmt = $pdo->prepare("
        SELECT a.attendance_date, a.status, a.leave_fraction, lt.is_paid, lt.name AS leave_type_name
        FROM attendance a
        LEFT JOIN leave_types lt ON lt.id = a.leave_type_id
        WHERE a.tenant_id = ? AND a.employee_id = ? AND a.attendance_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recordedDates = [];

    $presentDays = 0; $paidLeaveDays = 0; $unpaidLeaveDays = 0; $absentDays = 0;
    $leaveBreakdown = [];

    foreach ($records as $r) {
        $recordedDates[$r['attendance_date']] = true;
        if ($r['status'] === 'on_leave') {
            // leave_fraction is 0.5 for a half-day leave, 1.0 (or NULL, for rows
            // synced before this column existed) for a full day — sum the real
            // fraction rather than counting every on_leave row as a whole day.
            $fraction = $r['leave_fraction'] !== null ? (float) $r['leave_fraction'] : 1.0;
            if ((int) $r['is_paid'] === 1) {
                $paidLeaveDays += $fraction;
            } else {
                $unpaidLeaveDays += $fraction;
            }
            $name = $r['leave_type_name'] ?? 'Leave';
            $leaveBreakdown[$name] = ($leaveBreakdown[$name] ?? 0) + $fraction;
        } elseif ($r['status'] === 'absent') {
            $absentDays++;
        } else {
            // present / late / anything else with an actual check-in counts as present.
            $presentDays++;
        }
    }

    // Working days in the period with NO attendance record at all are
    // unaccounted-for — treated as absent (the employee neither checked in
    // nor has an approved leave covering that day), matching normal payroll practice.
    $unrecordedAbsent = 0;
    foreach ($workingDaysInPeriod as $d) {
        if (!isset($recordedDates[$d])) $unrecordedAbsent++;
    }
    $absentDays += $unrecordedAbsent;

    $grossAmount = $baseSalary
        + (float) $employee['housing_allowance'] + (float) $employee['transport_allowance']
        + (float) $employee['medical_allowance'] + (float) $employee['meal_allowance']
        + (float) $employee['communication_allowance'] + (float) $employee['education_allowance']
        + (float) $employee['travel_allowance'] + (float) $employee['other_allowance'];

    $unpaidDeduction = round($unpaidLeaveDays * $dailyRate, 2);
    $absentDeduction = round($absentDays * $dailyRate, 2);
    $totalDeduction = $unpaidDeduction + $absentDeduction;
    // Clamp at 0 — e.g. a fully-absent period can have more working days than the
    // divisor (fixed-divisor policy), which would otherwise make deductions exceed
    // gross pay and suggest a negative salary. The full (uncapped) breakdown is
    // still returned so HR can see exactly why, but the suggested figure itself
    // never goes below zero.
    $netAmount = max(0, round($grossAmount - $totalDeduction, 2));

    echo json_encode([
        'success' => true,
        'employee' => ['employee_id' => $employeeCode, 'full_name' => $employee['full_name'], 'base_salary' => $baseSalary],
        'period' => ['start' => $periodStart, 'end' => $periodEnd, 'working_days' => $workingDayCount],
        'divisor_basis' => $settings['payroll_divisor_basis'],
        'divisor_days' => $divisor,
        'daily_rate' => $dailyRate,
        'attendance' => [
            'present_days' => $presentDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'absent_days' => $absentDays,
            'unrecorded_days_counted_as_absent' => $unrecordedAbsent,
            'leave_breakdown' => $leaveBreakdown,
        ],
        'gross_amount' => $grossAmount,
        'unpaid_leave_deduction' => $unpaidDeduction,
        'absent_deduction' => $absentDeduction,
        'total_deduction' => $totalDeduction,
        'suggested_net_amount' => $netAmount,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
