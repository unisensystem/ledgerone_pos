<?php
// Given an employee + payroll period, reads their APPROVED leave requests,
// APPROVED overtime, and attendance (present/absent) from the period and
// returns a suggested Salary amount with a traceable breakdown (present
// days, absent days, paid leave days, unpaid leave days, daily rate,
// deduction, overtime). Read-only — never writes anything. The payroll UI
// shows this as a starting point; HR can still override the final amount
// before saving (see payroll-add.php).
//
// PRORATION: gross salary (base + allowances) is prorated to the number of
// calendar days actually in [period_start, period_end], not always paid as
// a full month. The period itself is decided by the caller — payroll-add.js
// sends 1st-of-month..Date (or the full month if Date is on/after
// month-end) — so running payroll mid-month (e.g. Date = the 23rd) pays
// 23/30 of the month's gross rather than the whole month. This keeps
// gross_amount consistent with whatever period the caller asks about.
//
// Deduction rule: Unpaid leave, absent days, and UNMARKED days (no
// attendance record) are deducted at the same daily_rate (base_salary / 30).
// Unmarked days that have no attendance mark and no leave request are
// treated as ABSENT to prevent silent under-payment when HR forgets to mark
// attendance. This is the same rule used by the Monthly Employee Report, so
// both always agree.
//
// OVERTIME (two sources, merged):
//   1. Approved leave_records OT entries (manually logged OT) — ot_amount
//      already stored at approval time, used as-is.
//   2. Attendance-derived OT — if check_out_time exceeds the employee's
//      shift end time (from shift_timing column, e.g. "09:00-18:00"), the
//      extra hours are priced at overtime_rate_per_hour. If that rate is 0
//      or not set, falls back to (base_salary / 30 / 8). Only surplus of
//      >= 15 minutes is counted (avoids rounding noise).
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
// rate used to deduct unpaid leave / absent days here always matches the
// rate a leave request itself is priced against. This does NOT change with
// proration — a day of unpaid leave/absence always costs one daily_rate,
// regardless of how many days are in the period.
function calculateDailyRate(array $emp): float {
    $salary = (float) ($emp['base_salary'] ?? 0);
    return round($salary / 30, 2);
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

    // ── SOURCE 1: Approved overtime from leave_records ──────────────────────
    // ot_amount here is already priced at the employee's own
    // overtime_rate_per_hour at the time the OT was approved — this file
    // never re-derives or re-prices it.
    $stmt = $pdo->prepare("
        SELECT start_date AS ot_date, ot_hours, ot_rate_multiplier, ot_amount
        FROM leave_records
        WHERE tenant_id = ? AND employee_id = ? AND ot_hours IS NOT NULL
          AND LOWER(status) = 'approved' AND start_date BETWEEN ? AND ?
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
            'source'          => 'approved_entry',
        ];
    }

    // ── SOURCE 2: Attendance-derived overtime ────────────────────────────────
    // For every 'present' attendance row in the period that has a
    // check_out_time past the employee's shift end time, price the surplus
    // at overtime_rate_per_hour. Fall back to daily_rate/8 when that column
    // is zero or null. Only surplus >= 15 minutes is counted (avoids noise
    // from clock-rounding). Dates already covered by a leave_records OT
    // entry (source 1 above) are skipped to avoid double-counting.
    $attendanceOtHours  = 0.0;
    $attendanceOtAmount = 0.0;
    $attendanceOtRecords = [];

    $shiftTiming = $employee['shift_timing'] ?? ''; // e.g. "09:00-18:00"
    $otRatePerHour = (float) ($employee['overtime_rate_per_hour'] ?? 0);

    // Fallback: if no per-hour rate set, derive from daily rate / 8 hours
    if ($otRatePerHour <= 0) {
        $otRatePerHour = round($dailyRate / 8, 4);
    }

    if (!empty($shiftTiming)) {
        $shiftParts = explode('-', $shiftTiming);
        // shift_timing stores "HH:MM-HH:MM"; take the second part as end time
        $shiftEndStr = isset($shiftParts[1]) ? trim($shiftParts[1]) : null;

        if ($shiftEndStr) {
            // Dates already covered by an approved leave_records OT entry
            $approvedOtDates = array_column($otRecords, 'date');

            // Fetch all present attendance rows with check_out in this period
            $stmt = $pdo->prepare("
                SELECT attendance_date, check_out_time
                FROM attendance
                WHERE tenant_id = ? AND employee_id = ?
                  AND attendance_date BETWEEN ? AND ?
                  AND status = 'present'
                  AND check_out_time IS NOT NULL
                ORDER BY attendance_date ASC
            ");
            $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
            $presentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($presentRows as $pr) {
                $dateStr = $pr['attendance_date'];

                // Skip if already covered by a manually approved OT entry
                if (in_array($dateStr, $approvedOtDates, true)) {
                    continue;
                }

                // Build shift-end datetime for this attendance date
                $shiftEndDt  = new DateTime($dateStr . ' ' . $shiftEndStr . ':00');
                $checkOutDt  = new DateTime($pr['check_out_time']);

                if ($checkOutDt <= $shiftEndDt) {
                    continue; // Left on time or early — no OT
                }

                $diffSeconds = $checkOutDt->getTimestamp() - $shiftEndDt->getTimestamp();
                $diffMinutes = $diffSeconds / 60;

                // Ignore anything under 15 minutes (clock noise / rounding)
                if ($diffMinutes < 15) {
                    continue;
                }

                $extraHours  = round($diffMinutes / 60, 2);
                $extraAmount = round($extraHours * $otRatePerHour, 2);

                $attendanceOtHours  += $extraHours;
                $attendanceOtAmount += $extraAmount;
                $attendanceOtRecords[] = [
                    'date'            => $dateStr,
                    'hours'           => $extraHours,
                    'rate_multiplier' => 1,
                    'amount'          => $extraAmount,
                    'source'          => 'attendance',
                ];
            }
        }
    }

    $attendanceOtAmount = round($attendanceOtAmount, 2);

    // Merge both OT sources
    $otHoursTotal  += $attendanceOtHours;
    $otAmountTotal += $attendanceOtAmount;
    $otAmountTotal  = round($otAmountTotal, 2);
    $otRecords      = array_merge($otRecords, $attendanceOtRecords);

    // Attendance for the period. Count explicit 'present' / 'absent' marks.
    // Unmarked days (no attendance record) that aren't covered by leave are
    // treated as ABSENT — prevents silent under-payment when HR forgets to mark
    // attendance.
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM attendance
        WHERE tenant_id = ? AND employee_id = ? AND attendance_date BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $attendanceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $presentDays = 0.0; $absentDays = 0.0;
    foreach ($attendanceRows as $r) {
        if ($r['status'] === 'present') $presentDays += (float) $r['cnt'];
        if ($r['status'] === 'absent')  $absentDays  += (float) $r['cnt'];
    }

    // Calculate calendar days in the period. This drives BOTH the
    // unmarked-days-as-absent check below AND the gross salary proration.
    $startDate = new DateTime($periodStart);
    $endDate = new DateTime($periodEnd);
    $calendarDays = (int) $endDate->diff($startDate)->format('%a') + 1;

    $totalMarkedDays = $presentDays + $absentDays + $paidLeaveDays + $unpaidLeaveDays;
    $unmarkedDays = $calendarDays - $totalMarkedDays;

    // Every unmarked day counts as absent — same rule as the Monthly Employee Report.
    if ($unmarkedDays > 0) {
        $absentDays += $unmarkedDays;
    }

    $baseSalary = (float) $employee['base_salary'];
    $fullMonthGrossAmount = $baseSalary
        + (float) $employee['housing_allowance'] + (float) $employee['transport_allowance']
        + (float) $employee['medical_allowance'] + (float) $employee['meal_allowance']
        + (float) $employee['communication_allowance'] + (float) $employee['education_allowance']
        + (float) $employee['travel_allowance'] + (float) $employee['other_allowance'];

    // Prorate gross to the days actually in this period.
    $grossAmount = round(($fullMonthGrossAmount / 30) * $calendarDays, 2);

    // Two separate deduction buckets so the breakdown stays traceable.
    $unpaidLeaveDeduction = round($unpaidLeaveDays * $dailyRate, 2);
    $absentDeduction = round($absentDays * $dailyRate, 2);
    $totalDeduction = round($unpaidLeaveDeduction + $absentDeduction, 2);

    // Clamp at 0 — deductions should never push the suggested figure below zero.
    $netAmount = max(0, round($grossAmount - $totalDeduction + $otAmountTotal, 2));

    echo json_encode([
        'success' => true,
        'employee' => ['employee_id' => $employeeCode, 'full_name' => $employee['full_name'], 'base_salary' => $baseSalary],
        'period' => ['start' => $periodStart, 'end' => $periodEnd],
        'calendar_days' => $calendarDays,
        'daily_rate' => $dailyRate,
        'attendance' => [
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'pending_leave_requests' => $pendingLeaveCount,
        ],
        'leave_records' => $leaveRecords,
        'overtime' => [
            'total_hours' => $otHoursTotal,
            'total_amount' => $otAmountTotal,
            'records' => $otRecords,
        ],
        'full_month_gross' => $fullMonthGrossAmount,
        'gross_amount' => $grossAmount,
        'unpaid_leave_deduction' => $unpaidLeaveDeduction,
        'absent_deduction' => $absentDeduction,
        'total_deduction' => $totalDeduction,
        'suggested_net_amount' => $netAmount,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}