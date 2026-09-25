<?php
// Monthly Employee Report — single-page dashboard (backend + frontend + JS in one file).
// Shows, per employee, day-wise attendance/leave (who came on which date) plus a
// salary breakdown (present/absent/paid-leave/unpaid-leave/daily-rate/gross/
// deduction/overtime/net) for a selected date range. Tenant-scoped only (no
// company/department filter — every employee under the logged-in tenant).
//
// The salary math (daily_rate, gross proration, deduction rule, unmarked-days-as-absent
// rule, overtime pricing) intentionally mirrors hrm/payroll/calculate-suggested.php
// so this report always agrees with what the Payroll "Calculate" button would
// suggest for the same employee/period.
//
// OVERTIME (two sources, merged — same logic as calculate-suggested.php):
//   1. Approved leave_records OT entries (manually logged OT).
//   2. Attendance-derived OT — check_out_time past shift end time, priced at
//      overtime_rate_per_hour (fallback: daily_rate / 8). >= 15 min threshold.
//      Dates already in source 1 are skipped to avoid double-counting.
//
// This one file serves two roles:
//   - No ?action= param  -> renders the full HTML/CSS/JS page (requires login)
//   - ?action=employees  -> JSON list of employees for the filter dropdown
//   - ?action=report     -> JSON report data for the selected filters

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAjax = isset($_GET['action']);

if (!$isAjax) {
    require_once '../../../../includes/dashboard.php';
}
require_once '../../../../includes/connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if ($isAjax) {
    header('Content-Type: application/json');
    if (!$user_id || !$tenant_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
} else {
    if (!$user_id) {
        header('Location: ../../auth/login.html');
        exit();
    }
}

function getCurrencySymbol(PDO $pdo, $tenant_id): string {
    $stmt = $pdo->prepare("
        SELECT c.symbol
        FROM tenant_currencies tc
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch();
    return $row['symbol'] ?? '$';
}

// Same formula as hrm/leave/leave-request.php and hrm/payroll/calculate-suggested.php
// so unpaid-leave/absent deduction here always matches those.
function calculateDailyRate(array $emp): float {
    $salary = (float) ($emp['base_salary'] ?? 0);
    return round($salary / 30, 2);
}

// Per-employee summary — same aggregate rules as calculate-suggested.php.
// OT is merged from two sources: approved leave_records entries + attendance-derived
// surplus hours past shift end time.
function computeEmployeeSummary(PDO $pdo, $tenant_id, array $employee, string $periodStart, string $periodEnd): array {
    $employeeCode = $employee['employee_id'];
    $dailyRate = calculateDailyRate($employee);

    // Approved leave (range kind), overlapping the period.
    $stmt = $pdo->prepare("
        SELECT lr.paid_days, lr.unpaid_days
        FROM leave_records lr
        WHERE lr.tenant_id = ? AND lr.employee_id = ? AND lr.status = 'approved'
          AND lr.leave_type_id IS NOT NULL AND lr.ot_hours IS NULL
          AND NOT (lr.end_date < ? OR lr.start_date > ?)
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $paidLeaveDays = 0.0; $unpaidLeaveDays = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $paidLeaveDays += (float) $r['paid_days'];
        $unpaidLeaveDays += (float) $r['unpaid_days'];
    }

    // ── SOURCE 1: Approved overtime from leave_records ──────────────────────
    $stmt = $pdo->prepare("
        SELECT start_date AS ot_date, ot_hours, ot_amount
        FROM leave_records
        WHERE tenant_id = ? AND employee_id = ? AND ot_hours IS NOT NULL
          AND LOWER(status) = 'approved' AND start_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $otHoursTotal = 0.0; $otAmountTotal = 0.0;
    $approvedOtDates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $otHoursTotal += (float) $r['ot_hours'];
        $otAmountTotal += (float) $r['ot_amount'];
        $approvedOtDates[] = $r['ot_date'];
    }

    // ── SOURCE 2: Attendance-derived overtime ────────────────────────────────
    $shiftTiming   = $employee['shift_timing'] ?? '';
    $otRatePerHour = (float) ($employee['overtime_rate_per_hour'] ?? 0);
    if ($otRatePerHour <= 0) {
        $otRatePerHour = round($dailyRate / 8, 4);
    }

    if (!empty($shiftTiming)) {
        $shiftParts  = explode('-', $shiftTiming);
        $shiftEndStr = isset($shiftParts[1]) ? trim($shiftParts[1]) : null;

        if ($shiftEndStr) {
            $stmt = $pdo->prepare("
                SELECT attendance_date, check_out_time
                FROM attendance
                WHERE tenant_id = ? AND employee_id = ?
                  AND attendance_date BETWEEN ? AND ?
                  AND status = 'present'
                  AND check_out_time IS NOT NULL
            ");
            $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                $dateStr = $pr['attendance_date'];
                if (in_array($dateStr, $approvedOtDates, true)) continue;

                $shiftEndDt = new DateTime($dateStr . ' ' . $shiftEndStr . ':00');
                $checkOutDt = new DateTime($pr['check_out_time']);
                if ($checkOutDt <= $shiftEndDt) continue;

                $diffMinutes = ($checkOutDt->getTimestamp() - $shiftEndDt->getTimestamp()) / 60;
                if ($diffMinutes < 15) continue;

                $extraHours   = round($diffMinutes / 60, 2);
                $otHoursTotal  += $extraHours;
                $otAmountTotal += round($extraHours * $otRatePerHour, 2);
            }
        }
    }

    $otAmountTotal = round($otAmountTotal, 2);

    // Attendance present/absent counts for the period.
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM attendance
        WHERE tenant_id = ? AND employee_id = ? AND attendance_date BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $presentDays = 0.0; $absentDays = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['status'] === 'present') $presentDays += (float) $r['cnt'];
        if ($r['status'] === 'absent')  $absentDays  += (float) $r['cnt'];
    }

    $startDate = new DateTime($periodStart);
    $endDate = new DateTime($periodEnd);
    $calendarDays = (int) $endDate->diff($startDate)->format('%a') + 1;

    $totalMarkedDays = $presentDays + $absentDays + $paidLeaveDays + $unpaidLeaveDays;
    $unmarkedDays = $calendarDays - $totalMarkedDays;

    if ($unmarkedDays > 0) {
        $absentDays += $unmarkedDays;
    }

    $baseSalary = (float) $employee['base_salary'];
    $fullMonthGrossAmount = $baseSalary
        + (float) $employee['housing_allowance'] + (float) $employee['transport_allowance']
        + (float) $employee['medical_allowance'] + (float) $employee['meal_allowance']
        + (float) $employee['communication_allowance'] + (float) $employee['education_allowance']
        + (float) $employee['travel_allowance'] + (float) $employee['other_allowance'];

    $grossAmount = round(($fullMonthGrossAmount / 30) * $calendarDays, 2);

    $unpaidLeaveDeduction = round($unpaidLeaveDays * $dailyRate, 2);
    $absentDeduction = round($absentDays * $dailyRate, 2);
    $totalDeduction = round($unpaidLeaveDeduction + $absentDeduction, 2);

    $netAmount = max(0, round($grossAmount - $totalDeduction + $otAmountTotal, 2));

    return [
        'employee_id'   => $employeeCode,
        'full_name'     => $employee['full_name'],
        'department'    => $employee['department_name'] ?? '',
        'daily_rate'    => $dailyRate,
        'present_days'  => $presentDays,
        'absent_days'   => $absentDays,
        'paid_leave_days'   => $paidLeaveDays,
        'unpaid_leave_days' => $unpaidLeaveDays,
        'gross_amount'  => $grossAmount,
        'deduction_amount' => $totalDeduction,
        'ot_hours'      => $otHoursTotal,
        'ot_amount'     => $otAmountTotal,
        'net_amount'    => $netAmount,
    ];
}

// Day-by-day detail for one employee.
function computeEmployeeDayDetails(PDO $pdo, $tenant_id, string $employeeCode, string $periodStart, string $periodEnd): array {
    $stmt = $pdo->prepare("
        SELECT a.attendance_date, a.status, lt.name AS leave_type_name,
               DATE_FORMAT(a.check_in_time, '%l:%i %p') AS check_in,
               DATE_FORMAT(a.check_out_time, '%l:%i %p') AS check_out
        FROM attendance a
        LEFT JOIN leave_types lt ON lt.id = a.leave_type_id
        WHERE a.tenant_id = ? AND a.employee_id = ? AND a.attendance_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $attendanceByDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $attendanceByDate[$r['attendance_date']] = [
            'status' => $r['status'],
            'leave_type_name' => $r['leave_type_name'],
            'check_in' => $r['check_in'],
            'check_out' => $r['check_out'],
        ];
    }

    $stmt = $pdo->prepare("
        SELECT lr.start_date, lr.end_date, lt.name AS leave_type_name, lr.unpaid_days
        FROM leave_records lr
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.tenant_id = ? AND lr.employee_id = ? AND lr.status = 'approved'
          AND lr.leave_type_id IS NOT NULL AND lr.ot_hours IS NULL
          AND NOT (lr.end_date < ? OR lr.start_date > ?)
    ");
    $stmt->execute([$tenant_id, $employeeCode, $periodStart, $periodEnd]);
    $leaveRanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $details = [];
    $cursor = new DateTime($periodStart);
    $end = new DateTime($periodEnd);
    while ($cursor <= $end) {
        $dateStr = $cursor->format('Y-m-d');

        if (isset($attendanceByDate[$dateStr])) {
            $row = $attendanceByDate[$dateStr];
            $details[] = [
                'date' => $dateStr,
                'status' => $row['status'],
                'label' => $row['leave_type_name'] ? ucfirst($row['status']) . ' (' . $row['leave_type_name'] . ')' : ucfirst($row['status']),
                'check_in' => $row['check_in'] ?: '',
                'check_out' => $row['check_out'] ?: '',
            ];
        } else {
            $onLeave = null;
            foreach ($leaveRanges as $lr) {
                if ($dateStr >= $lr['start_date'] && $dateStr <= $lr['end_date']) {
                    $onLeave = $lr;
                    break;
                }
            }
            if ($onLeave) {
                $paidUnpaid = ((float) $onLeave['unpaid_days'] > 0) ? 'Unpaid' : 'Paid';
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'leave',
                    'label' => ($onLeave['leave_type_name'] ?? 'Leave') . ' (' . $paidUnpaid . ')',
                    'check_in' => '',
                    'check_out' => '',
                ];
            } else {
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'unmarked',
                    'label' => 'Unmarked',
                    'check_in' => '',
                    'check_out' => '',
                ];
            }
        }
        $cursor->modify('+1 day');
    }
    return $details;
}

if ($isAjax) {
    $action = $_GET['action'];

    try {
        if ($action === 'employees') {
            $stmt = $pdo->prepare("
                SELECT e.employee_id, e.full_name
                FROM employees e
                WHERE e.tenant_id = ? AND e.deleted_at IS NULL
                ORDER BY e.full_name ASC
            ");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'report') {
            $periodStart = $_GET['period_start'] ?? '';
            $periodEnd = $_GET['period_end'] ?? '';
            $employeeIdsParam = trim($_GET['employee_ids'] ?? '');

            if (!$periodStart || !$periodEnd) {
                throw new Exception('period_start and period_end are required');
            }
            if (strtotime($periodEnd) < strtotime($periodStart)) {
                throw new Exception('period_end cannot be before period_start');
            }

            $sql = "
                SELECT e.*, d.department_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.tenant_id = ? AND e.deleted_at IS NULL
            ";
            $params = [$tenant_id];

            if ($employeeIdsParam !== '') {
                $employeeIds = array_filter(array_map('trim', explode(',', $employeeIdsParam)));
                if ($employeeIds) {
                    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
                    $sql .= " AND e.employee_id IN ($placeholders)";
                    $params = array_merge($params, $employeeIds);
                }
            }
            $sql .= " ORDER BY e.full_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rows = [];
            $summary = [
                'total_employees' => 0, 'total_present_days' => 0, 'total_absent_days' => 0,
                'total_paid_leave_days' => 0, 'total_unpaid_leave_days' => 0,
                'total_ot_hours' => 0, 'total_ot_amount' => 0, 'total_net_payroll' => 0,
            ];

            foreach ($employees as $emp) {
                $row = computeEmployeeSummary($pdo, $tenant_id, $emp, $periodStart, $periodEnd);
                $row['day_details'] = computeEmployeeDayDetails($pdo, $tenant_id, $emp['employee_id'], $periodStart, $periodEnd);
                $rows[] = $row;

                $summary['total_employees']++;
                $summary['total_present_days'] += $row['present_days'];
                $summary['total_absent_days'] += $row['absent_days'];
                $summary['total_paid_leave_days'] += $row['paid_leave_days'];
                $summary['total_unpaid_leave_days'] += $row['unpaid_leave_days'];
                $summary['total_ot_hours'] += $row['ot_hours'];
                $summary['total_ot_amount'] += $row['ot_amount'];
                $summary['total_net_payroll'] += $row['net_amount'];
            }
            $summary['total_ot_amount'] = round($summary['total_ot_amount'], 2);
            $summary['total_net_payroll'] = round($summary['total_net_payroll'], 2);

            echo json_encode([
                'success' => true,
                'currency_symbol' => getCurrencySymbol($pdo, $tenant_id),
                'period' => ['start' => $periodStart, 'end' => $periodEnd],
                'summary' => $summary,
                'employees' => $rows,
            ]);
            exit;
        }

        throw new Exception('Unknown action');

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$currency_symbol = getCurrencySymbol($pdo, $tenant_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monthly Employee Report - LedgerOne ERP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --primary: #4f46e5; --primary-light: #eef2ff; --border: #e2e8f0;
    --text: #1e293b; --text-muted: #64748b; --bg: #f8fafc;
    --success: #16a34a; --danger: #dc2626; --warning: #d97706;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 24px; }
  .container { max-width: 1400px; margin: 0 auto; }
  .header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
  .header h1 { font-size: 22px; font-weight: 700; }
  .header p { color: var(--text-muted); font-size: 13px; margin-top: 4px; }

  .card { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 18px; margin-bottom: 18px; }

  .filters { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
  .field { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
  .field label { font-size: 12px; font-weight: 600; color: var(--text-muted); }
  .field input[type="date"] { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; }

  .multiselect { position: relative; min-width: 240px; }
  .multiselect-toggle { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: #fff; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
  .multiselect-panel { display: none; position: absolute; top: 100%; left: 0; margin-top: 4px; width: 280px; max-height: 280px; overflow-y: auto; background: #fff; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); z-index: 20; }
  .multiselect-panel.open { display: block; }
  .multiselect-search { padding: 8px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: #fff; }
  .multiselect-search input { width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 5px; font-size: 12px; }
  .multiselect-actions { display: flex; justify-content: space-between; padding: 6px 10px; font-size: 11px; border-bottom: 1px solid var(--border); }
  .multiselect-actions button { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 11px; }
  .multiselect-item { display: flex; align-items: center; gap: 8px; padding: 7px 10px; font-size: 13px; cursor: pointer; }
  .multiselect-item:hover { background: var(--primary-light); }

  .btn { padding: 9px 16px; border-radius: 6px; border: 1px solid var(--border); background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
  .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
  .btn-primary:hover { background: #4338ca; }
  .btn:hover { background: #f1f5f9; }

  .switch-field { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer; padding-bottom: 9px; user-select: none; }
  .switch { position: relative; width: 38px; height: 22px; flex-shrink: 0; }
  .switch input { opacity: 0; width: 0; height: 0; position: absolute; }
  .switch .slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 22px; transition: .2s; }
  .switch .slider::before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
  .switch input:checked + .slider { background: var(--primary); }
  .switch input:checked + .slider::before { transform: translateX(16px); }

  .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
  .summary-box { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 14px; }
  .summary-box .label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
  .summary-box .value { font-size: 20px; font-weight: 700; margin-top: 4px; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
  th { background: #f1f5f9; font-weight: 700; font-size: 11px; text-transform: uppercase; color: var(--text-muted); position: sticky; top: 0; }
  tbody tr:hover { background: #f8fafc; }
  .table-wrap { overflow-x: auto; }
  .num { text-align: right; }
  .net-cell { font-weight: 700; color: var(--primary); }
  .link-btn { background: none; border: none; color: var(--primary); font-size: 12px; font-weight: 600; cursor: pointer; }

  .day-row td { background: #fafbff; padding: 14px; }
  .day-table-wrap { max-height: 340px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; }
  .day-table { font-size: 12px; }
  .day-table th { position: sticky; top: 0; }
  .status-pill { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .s-present { color: var(--success); background: #f0fdf4; }
  .s-absent { color: var(--danger); background: #fef2f2; }
  .s-leave { color: var(--warning); background: #fffbeb; }
  .s-unmarked { color: var(--text-muted); background: #f1f5f9; }

  .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
  .loading { text-align: center; padding: 40px; color: var(--text-muted); }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div>
      <h1>Monthly Employee Report</h1>
      <p>Attendance, leave and salary (incl. overtime) breakdown per employee for a selected date range.</p>
    </div>
    <div style="display:flex; gap:10px;">
      <button class="btn" id="printBtn"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>

  <div class="card">
    <div class="filters">
      <div class="field">
        <label>From</label>
        <input type="date" id="periodStart">
      </div>
      <div class="field">
        <label>To</label>
        <input type="date" id="periodEnd">
      </div>

      <div class="multiselect">
        <label style="font-size:12px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px;">Employees</label>
        <div class="multiselect-toggle" id="empToggle">
          <span id="empToggleLabel">All Employees</span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="multiselect-panel" id="empPanel">
          <div class="multiselect-search"><input type="text" id="empSearch" placeholder="Search employees..."></div>
          <div class="multiselect-actions">
            <button type="button" id="empSelectAll">Select All</button>
            <button type="button" id="empClearAll">Clear</button>
          </div>
          <div id="empItems"></div>
        </div>
      </div>

      <label class="switch-field">
        <span class="switch"><input type="checkbox" id="showAbsentToggle"><span class="slider"></span></span>
        <span id="showAbsentLabel">Show fully absent employees</span>
      </label>

      <button class="btn btn-primary" id="generateBtn"><i class="fas fa-chart-line"></i> Generate Report</button>
    </div>
  </div>

  <div id="summarySection" style="display:none;">
    <div class="summary-grid" id="summaryGrid"></div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table id="reportTable">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th class="num">Present</th>
            <th class="num">Absent</th>
            <th class="num">Paid Leave</th>
            <th class="num">Unpaid Leave</th>
            <th class="num">Daily Rate</th>
            <th class="num">Gross</th>
            <th class="num">Deduction</th>
            <th class="num">OT Hours</th>
            <th class="num">OT Amount</th>
            <th class="num">Net Salary</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody id="reportBody">
          <tr><td colspan="13" class="empty-state">Loading current month...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const currencySymbol = '<?php echo $currency_symbol; ?>';
let allEmployees = [];
let selectedEmployeeIds = new Set();
let allReportRows = [];
let currentReportRows = [];
let currentPeriod = { start: '', end: '' };

function fmtMoney(n) {
  return currencySymbol + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtNum(n) {
  return Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
}
function fmtMonthYear(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}

function defaultDates() {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1);
  const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  document.getElementById('periodStart').value = iso(first);
  document.getElementById('periodEnd').value = iso(now);
}

async function loadEmployees() {
  const res = await fetch('?action=employees');
  const data = await res.json();
  if (!data.success) return;
  allEmployees = data.data;
  renderEmployeeItems(allEmployees);
}

function renderEmployeeItems(list) {
  const container = document.getElementById('empItems');
  container.innerHTML = '';
  list.forEach(emp => {
    const item = document.createElement('div');
    item.className = 'multiselect-item';
    const checked = selectedEmployeeIds.has(emp.employee_id) ? 'checked' : '';
    item.innerHTML = `<input type="checkbox" data-id="${emp.employee_id}" ${checked}> <span>${emp.full_name} <small style="color:#94a3b8;">(${emp.employee_id})</small></span>`;
    item.querySelector('input').addEventListener('change', e => {
      if (e.target.checked) selectedEmployeeIds.add(emp.employee_id);
      else selectedEmployeeIds.delete(emp.employee_id);
      updateEmpToggleLabel();
    });
    container.appendChild(item);
  });
}

function updateEmpToggleLabel() {
  const label = document.getElementById('empToggleLabel');
  if (selectedEmployeeIds.size === 0) label.textContent = 'All Employees';
  else if (selectedEmployeeIds.size === 1) label.textContent = '1 Employee Selected';
  else label.textContent = selectedEmployeeIds.size + ' Employees Selected';
}

document.getElementById('empToggle').addEventListener('click', () => {
  document.getElementById('empPanel').classList.toggle('open');
});
document.addEventListener('click', e => {
  const panel = document.getElementById('empPanel');
  const toggle = document.getElementById('empToggle');
  if (!panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove('open');
});
document.getElementById('empSearch').addEventListener('input', e => {
  const q = e.target.value.toLowerCase();
  renderEmployeeItems(allEmployees.filter(emp =>
    emp.full_name.toLowerCase().includes(q) || emp.employee_id.toLowerCase().includes(q)
  ));
});
document.getElementById('empSelectAll').addEventListener('click', () => {
  allEmployees.forEach(emp => selectedEmployeeIds.add(emp.employee_id));
  renderEmployeeItems(allEmployees);
  updateEmpToggleLabel();
});
document.getElementById('empClearAll').addEventListener('click', () => {
  selectedEmployeeIds.clear();
  renderEmployeeItems(allEmployees);
  updateEmpToggleLabel();
});

document.getElementById('printBtn').addEventListener('click', openPrintView);
document.getElementById('generateBtn').addEventListener('click', generateReport);
document.getElementById('showAbsentToggle').addEventListener('change', applyAbsentFilter);

async function generateReport() {
  const periodStart = document.getElementById('periodStart').value;
  const periodEnd = document.getElementById('periodEnd').value;
  if (!periodStart || !periodEnd) { alert('Please select both From and To dates.'); return; }

  const body = document.getElementById('reportBody');
  body.innerHTML = '<tr><td colspan="13" class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
  document.getElementById('summarySection').style.display = 'none';

  const params = new URLSearchParams({ action: 'report', period_start: periodStart, period_end: periodEnd });
  if (selectedEmployeeIds.size > 0) params.set('employee_ids', Array.from(selectedEmployeeIds).join(','));

  try {
    const res = await fetch('?' + params.toString());
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load report');

    allReportRows = data.employees;
    currentPeriod = data.period;
    applyAbsentFilter();
  } catch (err) {
    body.innerHTML = `<tr><td colspan="13" class="empty-state">Error: ${err.message}</td></tr>`;
  }
}

function isFullyAbsent(r) {
  return Number(r.present_days) === 0 && Number(r.paid_leave_days) === 0 && Number(r.unpaid_leave_days) === 0;
}

function applyAbsentFilter() {
  const showAbsent = document.getElementById('showAbsentToggle').checked;
  const hiddenCount = showAbsent ? 0 : allReportRows.filter(isFullyAbsent).length;
  currentReportRows = showAbsent ? allReportRows.slice() : allReportRows.filter(r => !isFullyAbsent(r));

  document.getElementById('showAbsentLabel').textContent =
    'Show fully absent employees' + (hiddenCount > 0 ? ' (' + hiddenCount + ' hidden)' : '');

  const sum = k => currentReportRows.reduce((t, r) => t + Number(r[k] || 0), 0);
  renderSummary({
    total_employees: currentReportRows.length,
    total_present_days: sum('present_days'),
    total_absent_days: sum('absent_days'),
    total_paid_leave_days: sum('paid_leave_days'),
    total_unpaid_leave_days: sum('unpaid_leave_days'),
    total_ot_hours: sum('ot_hours'),
    total_ot_amount: sum('ot_amount'),
    total_net_payroll: sum('net_amount'),
  });
  renderTable(currentReportRows, hiddenCount);
}

function renderSummary(s) {
  const grid = document.getElementById('summaryGrid');
  const boxes = [
    ['Employees', fmtNum(s.total_employees)],
    ['Present Days', fmtNum(s.total_present_days)],
    ['Absent Days', fmtNum(s.total_absent_days)],
    ['Paid Leave', fmtNum(s.total_paid_leave_days)],
    ['Unpaid Leave', fmtNum(s.total_unpaid_leave_days)],
    ['OT Hours', fmtNum(s.total_ot_hours)],
    ['OT Amount', fmtMoney(s.total_ot_amount)],
    ['Total Net Payroll', fmtMoney(s.total_net_payroll)],
  ];
  grid.innerHTML = boxes.map(([label, value]) =>
    `<div class="summary-box"><div class="label">${label}</div><div class="value">${value}</div></div>`
  ).join('');
  document.getElementById('summarySection').style.display = 'block';
}

function statusClass(status) {
  if (status === 'present') return 's-present';
  if (status === 'absent') return 's-absent';
  if (status === 'leave') return 's-leave';
  return 's-unmarked';
}

function buildDayTable(dayDetails) {
  const rows = dayDetails.map(d => `
    <tr>
      <td>${d.date}</td>
      <td><span class="status-pill ${statusClass(d.status)}">${d.label}</span></td>
      <td>${d.check_in || '-'}</td>
      <td>${d.check_out || '-'}</td>
    </tr>
  `).join('');
  return `
    <table class="day-table">
      <thead>
        <tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th></tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;
}

function renderTable(rows, hiddenCount = 0) {
  const body = document.getElementById('reportBody');
  if (!rows.length) {
    body.innerHTML = hiddenCount > 0
      ? '<tr><td colspan="13" class="empty-state">All employees are fully absent in this period. Turn on "Show fully absent employees" to see them.</td></tr>'
      : '<tr><td colspan="13" class="empty-state">No employees found for this selection.</td></tr>';
    return;
  }

  body.innerHTML = '';
  rows.forEach((r, idx) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.full_name} <small style="color:#94a3b8;">(${r.employee_id})</small></td>
      <td>${r.department || '-'}</td>
      <td class="num">${fmtNum(r.present_days)}</td>
      <td class="num">${fmtNum(r.absent_days)}</td>
      <td class="num">${fmtNum(r.paid_leave_days)}</td>
      <td class="num">${fmtNum(r.unpaid_leave_days)}</td>
      <td class="num">${fmtMoney(r.daily_rate)}</td>
      <td class="num">${fmtMoney(r.gross_amount)}</td>
      <td class="num">${fmtMoney(r.deduction_amount)}</td>
      <td class="num">${fmtNum(r.ot_hours)}</td>
      <td class="num">${fmtMoney(r.ot_amount)}</td>
      <td class="num net-cell">${fmtMoney(r.net_amount)}</td>
      <td><button class="link-btn" data-idx="${idx}">Show Dates</button></td>
    `;
    body.appendChild(tr);

    const dayRow = document.createElement('tr');
    dayRow.className = 'day-row';
    dayRow.style.display = 'none';
    const dayCell = document.createElement('td');
    dayCell.colSpan = 13;
    dayCell.innerHTML = '<div class="day-table-wrap">' + buildDayTable(r.day_details) + '</div>';
    dayRow.appendChild(dayCell);
    body.appendChild(dayRow);

    tr.querySelector('.link-btn').addEventListener('click', e => {
      const isOpen = dayRow.style.display !== 'none';
      dayRow.style.display = isOpen ? 'none' : 'table-row';
      e.target.textContent = isOpen ? 'Show Dates' : 'Hide Dates';
    });
  });
}

function openPrintView() {
  if (!currentReportRows.length) { alert('Generate a report first.'); return; }

  const monthLabel = fmtMonthYear(currentPeriod.start);
  const rangeLabel = `${currentPeriod.start} to ${currentPeriod.end}`;
  const printedOn = new Date().toLocaleString();

  const pageHeader = `
    <div class="print-title">
      <h1>LedgerOne ERP</h1>
      <h2>Monthly Salary Report - ${monthLabel}</h2>
      <p class="muted">Period: ${rangeLabel} &nbsp;|&nbsp; Employees: ${currentReportRows.length} &nbsp;|&nbsp; Printed: ${printedOn}</p>
    </div>
  `;

  const sections = currentReportRows.map(r => `
    <section class="print-employee">
      <div class="print-head">
        <h1>${r.full_name} <span class="muted">(${r.employee_id})</span></h1>
        <h2>Salary - ${monthLabel}</h2>
        <p class="muted">Department: ${r.department || '-'} &nbsp;|&nbsp; Period: ${rangeLabel}</p>
      </div>

      <table class="print-summary">
        <tbody>
          <tr><th>Present</th><td>${fmtNum(r.present_days)}</td><th>Absent</th><td>${fmtNum(r.absent_days)}</td></tr>
          <tr><th>Paid Leave</th><td>${fmtNum(r.paid_leave_days)}</td><th>Unpaid Leave</th><td>${fmtNum(r.unpaid_leave_days)}</td></tr>
          <tr><th>Daily Rate</th><td>${fmtMoney(r.daily_rate)}</td><th>Gross</th><td>${fmtMoney(r.gross_amount)}</td></tr>
          <tr><th>OT Hours</th><td>${fmtNum(r.ot_hours)}</td><th>OT Amount</th><td>${fmtMoney(r.ot_amount)}</td></tr>
          <tr><th>Net Salary</th><td colspan="3" class="net">${fmtMoney(r.net_amount)}</td></tr>
        </tbody>
      </table>

      ${buildDayTable(r.day_details)}
    </section>
  `).join('');

  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Salary Report - ${rangeLabel}</title>
      <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; padding: 24px; }
        .print-title { text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 12px; margin-bottom: 20px; }
        .print-title h1 { font-size: 20px; letter-spacing: 0.5px; }
        .print-title h2 { font-size: 15px; font-weight: 600; color: #4f46e5; margin: 4px 0; }
        .print-employee { page-break-after: always; margin-bottom: 24px; }
        .print-employee:last-child { page-break-after: auto; }
        .print-head h1 { font-size: 20px; margin-bottom: 2px; }
        .print-head h2 { font-size: 15px; font-weight: 600; color: #4f46e5; margin-bottom: 4px; }
        .muted { color: #64748b; font-size: 12px; font-weight: 400; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin: 12px 0; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        .print-summary th { background: #f1f5f9; width: 16%; }
        .print-summary .net { font-weight: 700; color: #4f46e5; font-size: 14px; }
        .day-table th { background: #f1f5f9; }
        .status-pill { font-weight: 700; }
        .s-present { color: #16a34a; }
        .s-absent { color: #dc2626; }
        .s-leave { color: #d97706; }
        .s-unmarked { color: #64748b; }
        @media print {
          .print-employee { page-break-after: always; }
        }
      </style>
    </head>
    <body>
      ${pageHeader}
      ${sections}
      <script>window.onload = () => { window.print(); };<\/script>
    </body>
    </html>
  `;

  const printWindow = window.open('', '_blank');
  printWindow.document.open();
  printWindow.document.write(html);
  printWindow.document.close();
}

defaultDates();
loadEmployees();
generateReport();
</script>
</body>
</html>