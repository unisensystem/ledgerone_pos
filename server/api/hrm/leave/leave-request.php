<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once 'leave-helpers.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List leave requests, optionally filtered — powers both the "Pending
    // Approvals" view and a general leave register.
    $employeeId = $_GET['employee_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    $sql = "
        SELECT lr.*, lt.name AS leave_type_name, lt.code AS leave_type_code, lt.is_paid,
               e.full_name, e.employee_id AS employee_code, d.department_name,
               approver.full_name AS approved_by_name
        FROM leave_records lr
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        LEFT JOIN employees e ON e.employee_id = lr.employee_id AND e.tenant_id = lr.tenant_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN users approver ON approver.id = lr.approved_by
        WHERE lr.tenant_id = ?
    ";
    $params = [$tenant_id];

    if ($employeeId) { $sql .= " AND lr.employee_id = ?"; $params[] = $employeeId; }
    if ($status) { $sql .= " AND lr.status = ?"; $params[] = $status; }
    if ($startDate) { $sql .= " AND lr.end_date >= ?"; $params[] = $startDate; }
    if ($endDate) { $sql .= " AND lr.start_date <= ?"; $params[] = $endDate; }

    $sql .= " ORDER BY lr.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $employeeCode = trim($data['employee_id'] ?? '');
    $leaveTypeId = (int) ($data['leave_type_id'] ?? 0);
    $startDate = $data['start_date'] ?? '';
    $endDate = $data['end_date'] ?? '';
    $isHalfDay = !empty($data['is_half_day']);
    $halfDayPeriod = $data['half_day_period'] ?? null;
    $reason = trim($data['reason'] ?? '');
    $contact = $data['contact'] ?? null;
    $override = !empty($data['override']);
    $overrideReason = $data['override_reason'] ?? null;

    if (!$employeeCode || !$leaveTypeId || !$startDate || !$endDate || !$reason) {
        throw new Exception('Employee, leave type, dates and reason are required');
    }
    if ($isHalfDay && $startDate !== $endDate) {
        throw new Exception('Half-day leave must be a single date (start date = end date)');
    }
    if (strtotime($endDate) < strtotime($startDate)) {
        throw new Exception('End date cannot be before start date');
    }

    // Employee must exist, belong to this tenant, and be active.
    $stmt = $pdo->prepare("SELECT id, full_name, current_status FROM employees WHERE employee_id = ? AND tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$employeeCode, $tenant_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    if ($employee['current_status'] === 'terminated') {
        throw new Exception('Cannot request leave for a terminated employee');
    }

    // Leave type must exist for this tenant and be active.
    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ? AND tenant_id = ? AND is_active = 1");
    $stmt->execute([$leaveTypeId, $tenant_id]);
    $leaveType = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leaveType) {
        throw new Exception('Invalid or inactive leave type');
    }

    // Overlap check: any existing pending/approved leave for this employee
    // that intersects the requested range blocks the new request (unless overridden).
    $stmt = $pdo->prepare("
        SELECT id, start_date, end_date, status FROM leave_records
        WHERE tenant_id = ? AND employee_id = ? AND status IN ('pending', 'approved')
          AND start_date <= ? AND end_date >= ?
    ");
    $stmt->execute([$tenant_id, $employeeCode, $endDate, $startDate]);
    $overlap = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($overlap && !$override) {
        throw new Exception("Overlaps an existing {$overlap['status']} leave (#{$overlap['id']}, {$overlap['start_date']} to {$overlap['end_date']})");
    }

    // Working-day-aware day count (respects weekly-off + holidays).
    $workingDays = getWorkingDaysInRange($pdo, $tenant_id, $startDate, $endDate);
    $totalDays = calculateLeaveDays($pdo, $tenant_id, $startDate, $endDate, $isHalfDay);
    if ($totalDays <= 0) {
        throw new Exception('Selected date range has no working days (all weekly-off/holiday) — nothing to charge');
    }

    // If this leave type auto-approves, attendance gets overwritten immediately
    // below — check for real (non-leave) attendance already recorded on these
    // days first, and require an explicit override before clobbering it.
    if (!$leaveType['requires_approval']) {
        $conflicts = getAttendanceConflicts($pdo, $tenant_id, $employeeCode, $workingDays);
        if ($conflicts && !$override) {
            $list = implode(', ', array_map(fn($c) => "{$c['attendance_date']} ({$c['status']})", $conflicts));
            throw new Exception("Employee already has recorded attendance on: $list. Resubmit with override=true to replace it with leave (the original attendance is preserved and restored if this leave is later cancelled).");
        }
    }

    // Balance check for track_balance leave types.
    $remaining = null;
    if ($leaveType['track_balance']) {
        $remaining = getRemainingBalance($pdo, $tenant_id, $employeeCode, $leaveTypeId);
        if ($totalDays > $remaining && !$override) {
            throw new Exception("Insufficient {$leaveType['name']} balance: requested {$totalDays}, available {$remaining}");
        }
    }

    $pdo->beginTransaction();

    $initialStatus = $leaveType['requires_approval'] ? 'pending' : 'approved';

    $stmt = $pdo->prepare("
        INSERT INTO leave_records
            (tenant_id, employee_id, leave_type, leave_type_id, start_date, end_date, total_days, is_half_day, half_day_period,
             reason, contact_during_leave, status, override_by, override_reason, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenant_id, $employeeCode, strtolower($leaveType['code']), $leaveTypeId, $startDate, $endDate, $totalDays,
        $isHalfDay ? 1 : 0, $halfDayPeriod, $reason, $contact, $initialStatus,
        $override ? $user_id : null, $override ? $overrideReason : null, $user_id
    ]);
    $leaveId = (int) $pdo->lastInsertId();

    if ($initialStatus === 'approved') {
        if ($leaveType['track_balance']) {
            applyLeaveBalanceTransaction($pdo, $tenant_id, $employeeCode, $leaveTypeId, $startDate, 'leave_used', -$totalDays, 'leave_records', $leaveId, "Leave #$leaveId ($startDate to $endDate)", $user_id);
        }
        syncAttendanceForLeave($pdo, $tenant_id, $employeeCode, $leaveId, $leaveTypeId, $workingDays, $user_id, $isHalfDay ? 0.5 : 1.0);

        $stmt = $pdo->prepare("UPDATE employees SET current_status = 'on-leave', status_effective_date = ?, status_change_reason = ?, current_leave_id = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
        $stmt->execute([$startDate, $reason, $leaveId, $user_id, $employeeCode, $tenant_id]);
    } else {
        // Pending — reserve the days so a second request can't also claim them,
        // but do NOT touch the immutable ledger or attendance until approved.
        if ($leaveType['track_balance']) {
            adjustPendingBalance($pdo, $tenant_id, $employeeCode, $leaveTypeId, $startDate, $totalDays);
        }
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Leave Request', $leaveId, $employeeCode,
        "Leave request for \"{$employee['full_name']}\" ({$employeeCode}): {$leaveType['name']}, {$startDate} to {$endDate} ({$totalDays} day(s)), status: {$initialStatus}" . ($override ? " [OVERRIDE: {$overrideReason}]" : ''),
        null,
        ['leave_type' => $leaveType['name'], 'start_date' => $startDate, 'end_date' => $endDate, 'total_days' => $totalDays, 'status' => $initialStatus]
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $initialStatus === 'approved' ? 'Leave approved and recorded' : 'Leave request submitted, pending approval',
        'leave_id' => $leaveId,
        'status' => $initialStatus,
        'total_days' => $totalDays,
        'remaining_balance' => $remaining !== null ? ($remaining - ($initialStatus === 'approved' ? $totalDays : 0)) : null,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
