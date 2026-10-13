<?php
/**
 * Leave Management — single API endpoint.
 * SIMPLIFIED: only the 4 fixed leave types (Annual, Casual, Sick, Over Time),
 * seeded once by ensureBuiltinTypes(). No custom leave types.
 * Payment for a range request is chosen directly by the caller via `pay_mode`
 * ('paid' | 'unpaid' | 'split' + unpaid_days) — no quota tracking, no
 * over-quota approval round trip.
 *
 * Actions: bootstrap, submit, edit, update_status, delete, pending_excess, resolve_excess
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
    return round($salary / 30, 2);
}

function calculateHourlyRate(array $emp, float $dailyRate): float {
    $hoursPerWeek = (float) ($emp['work_hours_per_week'] ?? 0);
    $daysPerWeek  = (float) ($emp['work_days_per_week'] ?? 0);
    $hoursPerDay  = ($hoursPerWeek > 0 && $daysPerWeek > 0) ? ($hoursPerWeek / $daysPerWeek) : 8.0;
    return $hoursPerDay > 0 ? round($dailyRate / $hoursPerDay, 2) : 0.0;
}

function countDays(string $start, string $end): int {
    $s = new DateTime($start);
    $e = new DateTime($end);
    return (int) $s->diff($e)->days + 1;
}

function resolvePayMode(array $data, float $totalDays): array {
    $payMode = $data['pay_mode'] ?? 'paid';

    if ($payMode === 'unpaid') {
        return [0.0, $totalDays];
    }

    if ($payMode === 'split') {
        $unpaidDays = isset($data['unpaid_days']) ? (float) $data['unpaid_days'] : 0;
        if (!is_numeric($data['unpaid_days'] ?? null) || $unpaidDays < 0 || $unpaidDays > $totalDays) {
            throw new Exception("Unpaid days must be between 0 and {$totalDays}");
        }
        return [round($totalDays - $unpaidDays, 2), $unpaidDays];
    }

    return [$totalDays, 0.0];
}

function ensureBuiltinTypes(PDO $pdo, int $tenant_id, int $user_id): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_types WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    if ((int) $stmt->fetchColumn() > 0) return;

    $builtins = [
        ['Annual Leave', 'annual', 'fa-sun',              'range'],
        ['Casual Leave', 'casual', 'fa-umbrella-beach',   'range'],
        ['Sick Leave',   'sick',   'fa-briefcase-medical', 'range'],
        ['Over Time',    'ot',     'fa-clock',             'overtime'],
    ];
    $ins = $pdo->prepare("INSERT INTO leave_types (tenant_id, name, slug, icon, kind, is_paid, is_builtin, created_by) VALUES (?,?,?,?,?,1,1,?)");
    foreach ($builtins as $b) {
        $ins->execute([$tenant_id, $b[0], $b[1], $b[2], $b[3], $user_id]);
    }
}

/* Routing */
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET'
    ? ($_GET['action'] ?? 'bootstrap')
    : (json_decode(file_get_contents('php://input'), true)['action'] ?? 'submit');

try {
    switch ($action) {

        /* ── BOOTSTRAP ── */
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
                    'id'          => $e['employee_id'],
                    'name'        => $e['full_name'],
                    'dept'        => $e['department_name'] ?: 'N/A',
                    'currency'    => 'PKR',
                    'daily_rate'  => $dailyRate,
                    'hourly_rate' => calculateHourlyRate($e, $dailyRate),
                ];
            }, $employeeRows);

            $stmt = $pdo->prepare("
                SELECT id, name, icon, kind
                FROM leave_types
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY is_builtin DESC, id ASC
            ");
            $stmt->execute([$tenant_id]);
            $leaveTypes = array_map(function ($t) {
                return [
                    'id'   => (int) $t['id'],
                    'name' => $t['name'],
                    'icon' => $t['icon'],
                    'kind' => $t['kind'],
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("
                SELECT lr.id, lr.employee_id, e.full_name AS employee_name, lt.name AS type_name,
                       lr.leave_type_id, lr.start_date, lr.end_date, lr.total_days,
                       lr.paid_days, lr.unpaid_days, lr.daily_rate, lr.reason,
                       lr.contact_during_leave, lr.ot_hours, lr.ot_rate_multiplier,
                       lr.ot_amount, lr.status, lr.created_at
                FROM leave_records lr
                LEFT JOIN employees e
                       ON e.employee_id = lr.employee_id AND e.tenant_id = lr.tenant_id
                LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.tenant_id = ?
                ORDER BY lr.created_at DESC
                LIMIT 200
            ");
            $stmt->execute([$tenant_id]);
            $recent = array_map(function ($r) {
                return [
                    'id'                   => (int) $r['id'],
                    'employee_id'          => $r['employee_id'],
                    'employee_name'        => $r['employee_name'] ?: $r['employee_id'],
                    'type_name'            => $r['type_name'] ?: 'Leave',
                    'leave_type_id'        => $r['leave_type_id'] !== null ? (int) $r['leave_type_id'] : null,
                    'start_date'           => $r['start_date'],
                    'end_date'             => $r['end_date'],
                    'total_days'           => $r['total_days']           !== null ? (float) $r['total_days']           : null,
                    'paid_days'            => $r['paid_days']            !== null ? (float) $r['paid_days']            : null,
                    'unpaid_days'          => $r['unpaid_days']          !== null ? (float) $r['unpaid_days']          : null,
                    'daily_rate'           => $r['daily_rate']           !== null ? (float) $r['daily_rate']           : 0,
                    'reason'               => $r['reason'],
                    'contact_during_leave' => $r['contact_during_leave'],
                    'ot_hours'             => $r['ot_hours']             !== null ? (float) $r['ot_hours']             : null,
                    'ot_rate_multiplier'   => $r['ot_rate_multiplier']   !== null ? (float) $r['ot_rate_multiplier']   : null,
                    'ot_amount'            => $r['ot_amount']            !== null ? (float) $r['ot_amount']            : null,
                    'status'               => $r['status'],
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode([
                'success'         => true,
                'employees'       => $employees,
                'leave_types'     => $leaveTypes,
                'recent_requests' => $recent,
            ]);
            break;
        }

        /* ── PENDING EXCESS (dashboard approval card) ── */
        case 'pending_excess': {
            $stmt = $pdo->prepare("
                SELECT lr.id, lr.employee_id, e.full_name AS employee_name, lt.name AS type_name,
                       lr.start_date, lr.end_date, lr.total_days, lr.paid_days, lr.unpaid_days,
                       lr.daily_rate, lr.reason, lr.ot_hours, lr.ot_rate_multiplier, lr.ot_amount,
                       lr.status, 0 AS excess_days
                FROM leave_records lr
                LEFT JOIN employees e
                       ON e.employee_id = lr.employee_id AND e.tenant_id = lr.tenant_id
                LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.tenant_id = ? AND lr.status = 'pending'
                ORDER BY lr.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$tenant_id]);

            echo json_encode([
                'success' => true,
                'data'    => array_map(function ($r) {
                    return [
                        'id'                 => (int) $r['id'],
                        'employee_id'        => $r['employee_id'],
                        'employee_name'      => $r['employee_name'] ?: $r['employee_id'],
                        'type_name'          => $r['type_name'] ?: 'Leave',
                        'start_date'         => $r['start_date'],
                        'end_date'           => $r['end_date'],
                        'total_days'         => $r['total_days']         !== null ? (float) $r['total_days']         : null,
                        'paid_days'          => $r['paid_days']          !== null ? (float) $r['paid_days']          : null,
                        'unpaid_days'        => $r['unpaid_days']        !== null ? (float) $r['unpaid_days']        : null,
                        'daily_rate'         => (float) ($r['daily_rate'] ?? 0),
                        'reason'             => $r['reason'],
                        'ot_hours'           => $r['ot_hours']           !== null ? (float) $r['ot_hours']           : null,
                        'ot_rate_multiplier' => $r['ot_rate_multiplier'] !== null ? (float) $r['ot_rate_multiplier'] : null,
                        'ot_amount'          => $r['ot_amount']          !== null ? (float) $r['ot_amount']          : null,
                        'excess_days'        => 0,
                    ];
                }, $stmt->fetchAll(PDO::FETCH_ASSOC)),
            ]);
            break;
        }

        /* ── RESOLVE EXCESS ── */
        case 'resolve_excess': {
            $data     = json_decode(file_get_contents('php://input'), true);
            $id       = (int) ($data['id'] ?? 0);
            $paidDays = isset($data['paid_days']) ? (float) $data['paid_days'] : null;

            if (!$id || $paidDays === null) throw new Exception('ID and paid_days are required');

            $stmt = $pdo->prepare("SELECT total_days FROM leave_records WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $rec = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rec) throw new Exception('Record not found');

            $totalDays  = (float) $rec['total_days'];
            $unpaidDays = round($totalDays - $paidDays, 2);

            if ($paidDays < 0 || $paidDays > $totalDays) {
                throw new Exception("Paid days must be between 0 and {$totalDays}");
            }

            $stmt = $pdo->prepare("
                UPDATE leave_records
                SET paid_days = ?, unpaid_days = ?, status = 'approved'
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$paidDays, $unpaidDays, $id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Leave resolved and approved']);
            break;
        }

        /* ── UPDATE STATUS ── */
        case 'update_status': {
            $data   = json_decode(file_get_contents('php://input'), true);
            $id     = (int) ($data['id'] ?? 0);
            $status = $data['status'] ?? '';

            if (!$id || !in_array($status, ['approved', 'rejected', 'pending'], true)) {
                throw new Exception('Valid record ID and status are required');
            }

            $stmt = $pdo->prepare("UPDATE leave_records SET status = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$status, $id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Status updated']);
            break;
        }

        /* ── DELETE ── */
        case 'delete': {
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int) ($data['id'] ?? 0);

            if (!$id) throw new Exception('Record ID is required');

            $stmt = $pdo->prepare("DELETE FROM leave_records WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Leave request deleted']);
            break;
        }

        /* ── EDIT ── */
        case 'edit': {
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int) ($data['id'] ?? 0);
            if (!$id) throw new Exception('Record ID is required');

            $stmt = $pdo->prepare("SELECT * FROM leave_records WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new Exception('Request not found');

            // Any edit resets status to pending for re-approval
            $reason          = trim($data['reason'] ?? '');
            $contact         = trim($data['contact'] ?? '');
            $newEmployeeCode = trim($data['employee_id'] ?? $existing['employee_id']);
            if (!$reason) throw new Exception('Reason is required');

            // Verify new employee exists
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ? AND tenant_id = ?");
            $stmt->execute([$newEmployeeCode, $tenant_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) throw new Exception('Employee not found');

            if ($existing['ot_hours'] !== null) {
                /* ── Overtime edit ── */
                $dailyRate  = calculateDailyRate($emp);
                $hourlyRate = calculateHourlyRate($emp, $dailyRate);

                $otDate     = $data['ot_date'] ?? $existing['start_date'];
                $otHours    = isset($data['ot_hours']) ? (float) $data['ot_hours'] : (float) $existing['ot_hours'];
                if ($otHours <= 0) throw new Exception('Overtime hours must be greater than 0');

                $otRateKey  = $data['ot_rate'] ?? null;
                $multiplier = $otRateKey !== null
                    ? (['1x' => 1.0, '1.5x' => 1.5, '2x' => 2.0][$otRateKey] ?? 1.0)
                    : (float) $existing['ot_rate_multiplier'];
                $otAmount   = round($hourlyRate * $multiplier * $otHours, 2);

                $stmt = $pdo->prepare("
                    UPDATE leave_records
                    SET employee_id = ?, start_date = ?, end_date = ?,
                        reason = ?, contact_during_leave = ?, status = 'pending',
                        daily_rate = ?, ot_hours = ?, ot_rate_multiplier = ?, ot_amount = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $newEmployeeCode,
                    $otDate, $otDate,
                    $reason, $contact,
                    $dailyRate, $otHours, $multiplier, $otAmount,
                    $id, $tenant_id,
                ]);

            } else {
                /* ── Range leave edit ── */
                $startDate = $data['start_date'] ?? $existing['start_date'];
                $endDate   = $data['end_date']   ?? $existing['end_date'];
                $isHalfDay = !empty($data['is_half_day']);

                if ($endDate < $startDate) throw new Exception('End date cannot be before start date');

                // Overlap check against the new employee
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM leave_records
                    WHERE tenant_id = ? AND employee_id = ? AND status != 'rejected' AND id != ?
                      AND NOT (end_date < ? OR start_date > ?)
                ");
                $stmt->execute([$tenant_id, $newEmployeeCode, $id, $startDate, $endDate]);
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new Exception('This employee already has a leave request overlapping these dates');
                }

                $totalDays = $isHalfDay ? 0.5 : countDays($startDate, $endDate);
                [$paidDays, $unpaidDays] = resolvePayMode($data, $totalDays);

                $dailyRate   = calculateDailyRate($emp);
                $legacyType  = $unpaidDays >= $totalDays ? 'unpaid' : 'paid';

                $stmt = $pdo->prepare("
                    UPDATE leave_records
                    SET employee_id = ?, leave_type = ?,
                        start_date = ?, end_date = ?,
                        total_days = ?, paid_days = ?, unpaid_days = ?,
                        is_half_day = ?, daily_rate = ?,
                        reason = ?, contact_during_leave = ?, status = 'pending'
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $newEmployeeCode, $legacyType,
                    $startDate, $endDate,
                    $totalDays, $paidDays, $unpaidDays,
                    $isHalfDay ? 1 : 0, $dailyRate,
                    $reason, $contact,
                    $id, $tenant_id,
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Leave request updated — pending re-approval']);
            break;
        }

        /* ── SUBMIT (create new) ── */
        case 'submit':
        default: {
            $data = json_decode(file_get_contents('php://input'), true);

            $employeeCode   = trim($data['employee_id'] ?? '');
            $typeId         = (int) ($data['leave_type_id'] ?? 0);
            $reason         = trim($data['reason'] ?? '');
            $contact        = trim($data['contact'] ?? '');
            $approvalStatus = ($data['approval_status'] ?? 'pending') === 'approved' ? 'approved' : 'pending';

            if (!$employeeCode || !$typeId || !$reason) {
                throw new Exception('Employee, leave type and reason are required');
            }

            $stmt = $pdo->prepare("
                SELECT * FROM employees
                WHERE employee_id = ? AND tenant_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$employeeCode, $tenant_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) throw new Exception('Employee not found');

            $stmt = $pdo->prepare("
                SELECT * FROM leave_types WHERE id = ? AND tenant_id = ? AND is_active = 1
            ");
            $stmt->execute([$typeId, $tenant_id]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$type) throw new Exception('Leave type not found');

            $dailyRate = calculateDailyRate($emp);

            /* Overtime */
            if ($type['kind'] === 'overtime') {
                $otDate     = $data['ot_date'] ?? null;
                $otHours    = isset($data['ot_hours']) ? (float) $data['ot_hours'] : 0;
                $otRate     = $data['ot_rate'] ?? '1x';
                $multiplier = ['1x' => 1.0, '1.5x' => 1.5, '2x' => 2.0][$otRate] ?? 1.0;

                if (!$otDate || $otHours <= 0) throw new Exception('Overtime date and hours are required');

                $hourlyRate = calculateHourlyRate($emp, $dailyRate);
                $otAmount   = round($hourlyRate * $multiplier * $otHours, 2);

                $stmt = $pdo->prepare("
                    INSERT INTO leave_records
                        (tenant_id, employee_id, leave_type, leave_type_id,
                         start_date, end_date, total_days,
                         reason, contact_during_leave, status,
                         daily_rate, ot_hours, ot_rate_multiplier, ot_amount, created_by)
                    VALUES (?,?,?,?,?,?,0,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $tenant_id, $employeeCode, 'overtime', $typeId,
                    $otDate, $otDate,
                    $reason, $contact, $approvalStatus,
                    $dailyRate, $otHours, $multiplier, $otAmount, $user_id,
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Overtime logged',
                    'record'  => [
                        'id'        => (int) $pdo->lastInsertId(),
                        'ot_hours'  => $otHours,
                        'ot_rate'   => $otRate,
                        'ot_amount' => $otAmount,
                        'status'    => $approvalStatus,
                    ],
                ]);
                break;
            }

            /* Range leave */
            $startDate = $data['start_date'] ?? null;
            $endDate   = $data['end_date']   ?? null;
            $isHalfDay = !empty($data['is_half_day']);

            if (!$startDate || !$endDate) throw new Exception('Start and end date are required');
            if ($endDate < $startDate)    throw new Exception('End date cannot be before start date');

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM leave_records
                WHERE tenant_id = ? AND employee_id = ? AND status != 'rejected'
                  AND NOT (end_date < ? OR start_date > ?)
            ");
            $stmt->execute([$tenant_id, $employeeCode, $startDate, $endDate]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new Exception('This employee already has a leave request overlapping these dates');
            }

            $totalDays = $isHalfDay ? 0.5 : countDays($startDate, $endDate);
            [$paidDays, $unpaidDays] = resolvePayMode($data, $totalDays);
            $legacyType = $unpaidDays >= $totalDays ? 'unpaid' : 'paid';

            $stmt = $pdo->prepare("
                INSERT INTO leave_records
                    (tenant_id, employee_id, leave_type, leave_type_id,
                     start_date, end_date, total_days,
                     paid_days, unpaid_days, is_half_day,
                     reason, contact_during_leave, status,
                     daily_rate, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $tenant_id, $employeeCode, $legacyType, $typeId,
                $startDate, $endDate, $totalDays,
                $paidDays, $unpaidDays, $isHalfDay ? 1 : 0,
                $reason, $contact, $approvalStatus,
                $dailyRate, $user_id,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Leave request submitted',
                'record'  => [
                    'id'          => (int) $pdo->lastInsertId(),
                    'total_days'  => $totalDays,
                    'paid_days'   => $paidDays,
                    'unpaid_days' => $unpaidDays,
                    'daily_rate'  => $dailyRate,
                    'paid_amount' => round($dailyRate * $paidDays, 2),
                    'status'      => $approvalStatus,
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