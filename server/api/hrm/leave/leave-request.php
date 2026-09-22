<?php
/**
 * Leave Management — single API endpoint.
 * ALL actions: bootstrap, pending_excess, submit, resolve_excess, 
 * update_status, delete, add_type, delete_type
 */

require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/* Helpers */
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

function calculateHourlyRate(array $emp, float $dailyRate): float {
    $hoursPerWeek = (float) ($emp['work_hours_per_week'] ?? 0);
    $daysPerWeek  = (float) ($emp['work_days_per_week'] ?? 0);
    $hoursPerDay  = ($hoursPerWeek > 0 && $daysPerWeek > 0) ? ($hoursPerWeek / $daysPerWeek) : 8.0;
    return $hoursPerDay > 0 ? round($dailyRate / $hoursPerDay, 2) : 0.0;
}

function slugify(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'leave-type';
}

function countDays(string $start, string $end): int {
    $s = new DateTime($start);
    $e = new DateTime($end);
    return (int) $s->diff($e)->days + 1;
}

function ensureBuiltinTypes(PDO $pdo, int $tenant_id, int $user_id): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_types WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    if ((int) $stmt->fetchColumn() > 0) return;

    // [name, slug, icon, kind, quota_days_per_year, is_paid]
    $builtins = [
        ['Annual Leave', 'annual', 'fa-sun', 'range', 14, 1],
        ['Casual Leave', 'casual', 'fa-umbrella-beach', 'range', 10, 1],
        ['Sick Leave',   'sick',   'fa-briefcase-medical', 'range', 8, 1],
        ['Over Time',    'ot',     'fa-clock', 'overtime', null, 1],
    ];
    $ins = $pdo->prepare("INSERT INTO leave_types (tenant_id, name, slug, icon, kind, quota_days_per_year, is_paid, is_builtin, created_by) VALUES (?,?,?,?,?,?,?,1,?)");
    foreach ($builtins as $b) {
        $ins->execute([$tenant_id, $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $user_id]);
    }
}

/* Routing */
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET'
    ? ($_GET['action'] ?? 'bootstrap')
    : (json_decode(file_get_contents('php://input'), true)['action'] ?? 'submit');

try {
    switch ($action) {

        /* BOOTSTRAP */
        case 'bootstrap': {
            ensureBuiltinTypes($pdo, $tenant_id, $user_id);

            $stmt = $pdo->prepare("
                SELECT e.id, e.employee_id, e.full_name, e.base_salary, e.salary_currency,
                       e.salary_frequency, e.work_days_per_week, e.work_hours_per_week,
                       d.department_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.tenant_id = ? AND e.deleted_at IS NULL AND e.current_status != 'terminated'
                ORDER BY e.full_name
            ");
            $stmt->execute([$tenant_id]);
            $employeeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $employees = array_map(function ($e) {
                $dailyRate = calculateDailyRate($e);
                return [
                    'id'         => $e['employee_id'],
                    'name'       => $e['full_name'],
                    'dept'       => $e['department_name'] ?: 'N/A',
'currency'   => 'PKR',
                    'daily_rate' => $dailyRate,
                    'hourly_rate'=> calculateHourlyRate($e, $dailyRate),
                ];
            }, $employeeRows);

            $stmt = $pdo->prepare("SELECT id, name, icon, kind, quota_days_per_year, is_paid FROM leave_types WHERE tenant_id = ? AND is_active = 1 ORDER BY is_builtin DESC, id ASC");
            $stmt->execute([$tenant_id]);
            $leaveTypes = array_map(function ($t) {
                return [
                    'id'      => (int) $t['id'],
                    'name'    => $t['name'],
                    'icon'    => $t['icon'],
                    'kind'    => $t['kind'],
                    'quota'   => $t['quota_days_per_year'] !== null ? (float) $t['quota_days_per_year'] : null,
                    'is_paid' => (bool) $t['is_paid'],
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("
                SELECT employee_id, leave_type_id, SUM(paid_days) AS used_paid
                FROM leave_records
                WHERE tenant_id = ? AND status != 'rejected' AND YEAR(start_date) = YEAR(CURDATE())
                  AND leave_type_id IS NOT NULL
                GROUP BY employee_id, leave_type_id
            ");
            $stmt->execute([$tenant_id]);
            $balances = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $balances[$row['employee_id']][(int) $row['leave_type_id']] = (float) $row['used_paid'];
            }

                  $stmt = $pdo->prepare("
                SELECT lr.id, lr.employee_id, e.full_name AS employee_name, lt.name AS type_name,
                       lr.leave_type_id, lr.start_date, lr.end_date, lr.total_days, lr.paid_days, lr.unpaid_days,
                       lr.excess_days, lr.excess_status, lr.daily_rate, lr.reason, lr.contact_during_leave,
                       lr.ot_hours, lr.ot_rate_multiplier, lr.ot_amount, lr.status, lr.created_at
                FROM leave_records lr
                LEFT JOIN employees e ON e.employee_id = lr.employee_id AND e.tenant_id = lr.tenant_id
                LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.tenant_id = ?
                ORDER BY lr.created_at DESC
                LIMIT 200
            ");
            $stmt->execute([$tenant_id]);
            $recent = array_map(function ($r) {
                return [
                    'id'                => (int) $r['id'],
                    'employee_id'       => $r['employee_id'],
                    'employee_name'     => $r['employee_name'] ?: $r['employee_id'],
                    'type_name'         => $r['type_name'] ?: 'Leave',
                    'leave_type_id'     => $r['leave_type_id'] !== null ? (int) $r['leave_type_id'] : null,
                    'start_date'        => $r['start_date'],
                    'end_date'          => $r['end_date'],
                    'total_days'        => $r['total_days'] !== null ? (float) $r['total_days'] : null,
                    'paid_days'         => $r['paid_days'] !== null ? (float) $r['paid_days'] : null,
                    'unpaid_days'       => $r['unpaid_days'] !== null ? (float) $r['unpaid_days'] : null,
                    'excess_days'       => $r['excess_days'] !== null ? (float) $r['excess_days'] : 0,
                    'excess_status'     => $r['excess_status'] ?: 'none',
                    'daily_rate'        => $r['daily_rate'] !== null ? (float) $r['daily_rate'] : 0,
                    'reason'            => $r['reason'],
                    'contact_during_leave' => $r['contact_during_leave'],
                    'ot_hours'          => $r['ot_hours'] !== null ? (float) $r['ot_hours'] : null,
                    'ot_rate_multiplier' => $r['ot_rate_multiplier'] !== null ? (float) $r['ot_rate_multiplier'] : null,
                    'ot_amount'         => $r['ot_amount'] !== null ? (float) $r['ot_amount'] : null,
                    'status'            => $r['status'],
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode([
                'success'         => true,
                'employees'       => $employees,
                'leave_types'     => $leaveTypes,
                'used_paid'       => $balances,
                'recent_requests' => $recent,
            ]);
            break;
        }

        /* PENDING EXCESS - Dashboard */
        case 'pending_excess': {
            $stmt = $pdo->prepare("
                SELECT lr.id, lr.employee_id, e.full_name AS employee_name, lt.name AS type_name,
                       lr.start_date, lr.end_date, lr.total_days, lr.paid_days, lr.excess_days,
                       lr.daily_rate, lr.reason, lr.created_at
                FROM leave_records lr
                LEFT JOIN employees e ON e.employee_id = lr.employee_id AND e.tenant_id = lr.tenant_id
                LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.tenant_id = ? AND lr.excess_status = 'pending'
                ORDER BY lr.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

               /* RESOLVE EXCESS */
        case 'resolve_excess': {
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int) ($data['id'] ?? 0);
            $note = trim($data['note'] ?? '');

            $stmt = $pdo->prepare("SELECT id, total_days, excess_days FROM leave_records WHERE id = ? AND tenant_id = ? AND excess_status = 'pending'");
            $stmt->execute([$id, $tenant_id]);
            $rec = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rec) throw new Exception('This request is no longer pending');

            $totalDays = (float) $rec['total_days'];
            $paidDays  = $data['paid_days'] ?? null;

            if ($paidDays === null || !is_numeric($paidDays) || $paidDays < 0 || $paidDays > $totalDays) {
                throw new Exception("Paid days must be between 0 and {$totalDays}");
            }
            $paidDays   = (float) $paidDays;
            $unpaidDays = round($totalDays - $paidDays, 2);

            $stmt = $pdo->prepare("
                UPDATE leave_records
                SET paid_days = ?, unpaid_days = ?, excess_status = 'none',
                    is_override = 1, override_reason = ?, excess_resolved_by = ?, excess_resolved_at = NOW()
                WHERE id = ? AND tenant_id = ? AND excess_status = 'pending'
            ");
            $stmt->execute([$paidDays, $unpaidDays, $note ?: null, $user_id, $id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Decision saved']);
            break;
        }
                /* UPDATE STATUS - Approve/Reject */
        case 'update_status': {
            $data   = json_decode(file_get_contents('php://input'), true);
            $id     = (int) ($data['id'] ?? 0);
            $status = $data['status'] ?? '';

            if (!$id || !in_array($status, ['approved', 'rejected', 'pending'], true)) {
                throw new Exception('Valid record and status are required');
            }

            // Reject needs no paid/unpaid decision, so always clear it off the dashboard.
            // Approve only clears it when there was no excess (excess_days = 0) - a real
            // excess still needs its own Paid/Unpaid decision via resolve_excess.
            $excessClearSql = $status === 'rejected'
                ? "'none'"
                : "IF(excess_days = 0, 'none', excess_status)";

            $stmt = $pdo->prepare("
                UPDATE leave_records
                SET status = ?, excess_status = $excessClearSql
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$status, $id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Status updated']);
            break;
        }

        /* DELETE - Remove a leave request */
        case 'delete': {
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int) ($data['id'] ?? 0);

            if (!$id) throw new Exception('Record ID is required');

            // Soft delete or hard delete based on your preference
$stmt = $pdo->prepare("DELETE FROM leave_records WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Leave request deleted']);
            break;
        }

        /* ADD TYPE */
        case 'add_type': {
            $data = json_decode(file_get_contents('php://input'), true);
            $name  = trim($data['name'] ?? '');
            $quota = $data['quota_days_per_year'] ?? null;
            // Defaults to paid (1) if the caller doesn't specify — matches the
            // built-in Annual/Casual/Sick types.
            $isPaid = array_key_exists('is_paid', $data) ? (int) !!$data['is_paid'] : 1;

            if ($name === '') throw new Exception('Leave type name is required');
            if ($quota === null || !is_numeric($quota) || $quota < 0) {
                throw new Exception('Paid days per year must be 0 or more');
            }

            $slug = slugify($name) . '-' . substr(uniqid(), -5);
            $stmt = $pdo->prepare("INSERT INTO leave_types (tenant_id, name, slug, icon, kind, quota_days_per_year, is_paid, is_builtin, created_by) VALUES (?,?,?,?,?,?,?,0,?)");
            $stmt->execute([$tenant_id, $name, $slug, 'fa-calendar-alt', 'range', $quota, $isPaid, $user_id]);

            echo json_encode(['success' => true, 'leave_type' => [
                'id' => (int) $pdo->lastInsertId(),
                'name' => $name,
                'icon' => 'fa-calendar-alt',
                'kind' => 'range',
                'quota' => (float) $quota,
                'is_paid' => (bool) $isPaid,
            ]]);
            break;
        }

        /* DELETE TYPE */
        case 'delete_type': {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? 0);

            if (!$id) throw new Exception('Leave type id is required');

            $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ? AND tenant_id = ? AND is_builtin = 0");
            $stmt->execute([$id, $tenant_id]);

            echo json_encode(['success' => true]);
            break;
        }

        /* SUBMIT - Create new leave */
        case 'submit':
        default: {
            $data = json_decode(file_get_contents('php://input'), true);

            $employeeCode = trim($data['employee_id'] ?? '');
            $typeId       = (int) ($data['leave_type_id'] ?? 0);
            $reason       = trim($data['reason'] ?? '');
            $contact      = trim($data['contact'] ?? '');
            $approvalStatus = ($data['approval_status'] ?? 'pending') === 'approved' ? 'approved' : 'pending';

            if (!$employeeCode || !$typeId || !$reason) {
                throw new Exception('Employee, leave type and reason are required');
            }

            $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ? AND tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$employeeCode, $tenant_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) throw new Exception('Employee not found');

            $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ? AND tenant_id = ? AND is_active = 1");
            $stmt->execute([$typeId, $tenant_id]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$type) throw new Exception('Leave type not found');

            $dailyRate = calculateDailyRate($emp);

            /* Overtime */
            if ($type['kind'] === 'overtime') {
                $otDate  = $data['ot_date'] ?? null;
                $otHours = isset($data['ot_hours']) ? (float) $data['ot_hours'] : 0;
                $otRate  = $data['ot_rate'] ?? '1x';
                $multiplier = ['1x' => 1.0, '1.5x' => 1.5, '2x' => 2.0][$otRate] ?? 1.0;

                if (!$otDate || $otHours <= 0) throw new Exception('Overtime date and hours are required');

                $hourlyRate = calculateHourlyRate($emp, $dailyRate);
                $otAmount   = round($hourlyRate * $multiplier * $otHours, 2);

                $stmt = $pdo->prepare("
                    INSERT INTO leave_records
                        (tenant_id, employee_id, leave_type, leave_type_id, start_date, end_date, total_days,
                         reason, contact_during_leave, status, daily_rate, ot_hours, ot_rate_multiplier, ot_amount, created_by)
                    VALUES (?,?,?,?,?,?,0,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $tenant_id, $employeeCode, 'overtime', $typeId, $otDate, $otDate,
                    $reason, $contact, $approvalStatus, $dailyRate, $otHours, $multiplier, $otAmount, $user_id,
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Overtime logged',
                    'record'  => [
                        'id' => (int) $pdo->lastInsertId(),
                        'ot_hours' => $otHours,
                        'ot_rate' => $otRate,
                        'ot_amount' => $otAmount,
                        'status' => $approvalStatus
                    ],
                ]);
                break;
            }

            /* Range leave */
            $startDate = $data['start_date'] ?? null;
            $endDate   = $data['end_date'] ?? null;
            $isHalfDay = !empty($data['is_half_day']);

            if (!$startDate || !$endDate) throw new Exception('Start and end date are required');
            if ($endDate < $startDate) throw new Exception('End date cannot be before start date');

            $totalDays = $isHalfDay ? 0.5 : countDays($startDate, $endDate);

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM leave_records
                WHERE tenant_id = ? AND employee_id = ? AND status != 'rejected'
                  AND NOT (end_date < ? OR start_date > ?)
            ");
            $stmt->execute([$tenant_id, $employeeCode, $startDate, $endDate]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new Exception('This employee already has a leave request overlapping these dates');
            }

            $quota = $type['quota_days_per_year'] !== null ? (float) $type['quota_days_per_year'] : 0;
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(paid_days), 0) FROM leave_records
                WHERE tenant_id = ? AND employee_id = ? AND leave_type_id = ?
                  AND status != 'rejected' AND YEAR(start_date) = YEAR(CURDATE())
            ");
            $stmt->execute([$tenant_id, $employeeCode, $typeId]);
            $used = (float) $stmt->fetchColumn();
            $remaining = max(0, $quota - $used);

            $excessDecision = $data['excess_decision'] ?? null;
            $overrideReason = trim($data['override_reason'] ?? '');
            $sendToDashboard = !empty($data['send_to_dashboard']);
            $excessDays   = 0;
            $excessStatus = 'none';

            if ($quota <= 0) {
                $paidDays = 0; $unpaidDays = $totalDays; $isOverride = 0;
            } elseif ($totalDays <= $remaining) {
                $paidDays = $totalDays; $unpaidDays = 0; $isOverride = 0;
                if ($sendToDashboard) {
                    $excessStatus = 'pending';
                }
            } else {
                $excess = $totalDays - $remaining;

                if (!in_array($excessDecision, ['paid', 'unpaid', 'pending'], true)) {
                    echo json_encode([
                        'success'  => false,
                        'status'   => 'needs_decision',
                        'message'  => $remaining > 0
                            ? "Only {$remaining} paid day(s) remain for {$type['name']}. {$excess} day(s) go over the quota."
                            : "The paid quota for {$type['name']} is already used up. All {$excess} day(s) are over quota.",
                        'quota'     => $quota,
                        'used'      => $used,
                        'remaining' => $remaining,
                        'excess_days' => $excess,
                        'total_days'  => $totalDays,
                    ]);
                    exit;
                }

                if ($excessDecision === 'pending') {
                    $paidDays = $remaining; $unpaidDays = 0; $isOverride = 0;
                    $excessDays = $excess; $excessStatus = 'pending';
                } elseif ($excessDecision === 'paid') {
                    $paidDays = $totalDays; $unpaidDays = 0; $isOverride = 1;
                } else {
                    $paidDays = $remaining; $unpaidDays = $excess; $isOverride = 1;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO leave_records
                    (tenant_id, employee_id, leave_type, leave_type_id, start_date, end_date, total_days,
                     paid_days, unpaid_days, is_half_day, reason, contact_during_leave, status,
                     is_override, override_reason, daily_rate, excess_days, excess_status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $legacyType = $unpaidDays >= $totalDays ? 'unpaid' : ($type['kind'] === 'range' ? 'paid' : $type['name']);
            $stmt->execute([
                $tenant_id, $employeeCode, $legacyType, $typeId, $startDate, $endDate, $totalDays,
                $paidDays, $unpaidDays, $isHalfDay ? 1 : 0, $reason, $contact, $approvalStatus,
                $isOverride, $overrideReason ?: null, $dailyRate, $excessDays, $excessStatus, $user_id,
            ]);

            echo json_encode([
                'success' => true,
                'message' => $excessStatus === 'pending'
                    ? 'Leave request submitted and sent to dashboard for approval'
                    : 'Leave request submitted',
                'record'  => [
                    'id' => (int) $pdo->lastInsertId(),
                    'total_days' => $totalDays,
                    'paid_days' => $paidDays,
                    'unpaid_days' => $unpaidDays,
                    'daily_rate' => $dailyRate,
                    'paid_amount' => round($dailyRate * $paidDays, 2),
                    'is_override' => (bool) $isOverride,
                    'status' => $approvalStatus,
                    'excess_pending' => $excessStatus === 'pending',
                    'excess_days' => $excessDays,
                ],
            ]);
            break;
        }
    }
} catch (Exception $e) {
    error_log('Leave Request API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}