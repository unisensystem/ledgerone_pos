<?php
// Closes out an approved leave when the employee actually comes back.
//
// - Returned EARLY (before the approved end date): the unused working days
//   are un-charged — balance is refunded and attendance for those days is
//   cleared back to normal, so they can be marked present as usual.
// - Returned LATE / overstayed (after the approved end date): the extra
//   working days automatically become a new, separate Unpaid Leave record
//   (approved, no HR step needed) and get marked on the attendance
//   register — nothing is silently left in limbo.
// - Returned exactly on time: just closes the leave and reactivates the employee.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once 'leave-helpers.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $leaveId = (int) ($data['leave_id'] ?? 0);
    $actualReturnDate = $data['actual_return_date'] ?? date('Y-m-d');

    if (!$leaveId) throw new Exception('Leave ID is required');

    $stmt = $pdo->prepare("
        SELECT lr.*, lt.name AS leave_type_name, lt.track_balance, e.full_name
        FROM leave_records lr
        JOIN leave_types lt ON lt.id = lr.leave_type_id
        LEFT JOIN employees e ON e.employee_id = lr.employee_id AND e.tenant_id = lr.tenant_id
        WHERE lr.id = ? AND lr.tenant_id = ?
    ");
    $stmt->execute([$leaveId, $tenant_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leave) throw new Exception('Leave request not found');
    if ($leave['status'] !== 'approved') throw new Exception("Only an approved leave can be closed out with a return (this one is '{$leave['status']}')");
    if ($leave['actual_return_date'] !== null) throw new Exception('This leave has already been closed out');

    $plannedEnd = $leave['end_date'];
    $unpaidLeaveTypeId = null;
    $overstayDays = 0.0;
    $newUnpaidLeaveId = null;
    $refundedDays = 0.0;

    $pdo->beginTransaction();

    if (strtotime($actualReturnDate) > strtotime($plannedEnd)) {
        // OVERSTAY — the day after the approved end date through the actual
        // return date (exclusive of the return date itself, since the
        // employee IS present that day) becomes auto-unpaid leave.
        $extraStart = date('Y-m-d', strtotime($plannedEnd . ' +1 day'));
        $extraEnd = date('Y-m-d', strtotime($actualReturnDate . ' -1 day'));

        if (strtotime($extraStart) <= strtotime($extraEnd)) {
            $stmt = $pdo->prepare("SELECT id FROM leave_types WHERE tenant_id = ? AND code = 'UNPAID' AND is_active = 1");
            $stmt->execute([$tenant_id]);
            $unpaidLeaveTypeId = $stmt->fetchColumn();
            if (!$unpaidLeaveTypeId) throw new Exception('No active Unpaid Leave type configured for this tenant — cannot auto-record the overstay');

            $extraWorkingDays = getWorkingDaysInRange($pdo, $tenant_id, $extraStart, $extraEnd);
            $overstayDays = count($extraWorkingDays);

            if ($overstayDays > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO leave_records
                        (tenant_id, employee_id, leave_type, leave_type_id, start_date, end_date, total_days, reason, status, approved_by, approved_at, created_by)
                    VALUES (?, ?, 'unpaid', ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?)
                ");
                $autoReason = "Auto-recorded: returned {$actualReturnDate}, after approved leave #{$leaveId} ended {$plannedEnd}";
                $stmt->execute([$tenant_id, $leave['employee_id'], $unpaidLeaveTypeId, $extraStart, $extraEnd, $overstayDays, $autoReason, $user_id, $user_id]);
                $newUnpaidLeaveId = (int) $pdo->lastInsertId();

                syncAttendanceForLeave($pdo, $tenant_id, $leave['employee_id'], $newUnpaidLeaveId, (int) $unpaidLeaveTypeId, $extraWorkingDays, $user_id);
                // Unpaid leave doesn't track balance, so no ledger transaction here —
                // its financial effect happens later, at payroll time (see payroll calc).
            }
        }
    } elseif (strtotime($actualReturnDate) < strtotime($plannedEnd)) {
        // EARLY RETURN — refund the working days between the actual return
        // date (inclusive, employee is present again) and the original end date.
        $refundStart = $actualReturnDate;
        $refundEnd = $plannedEnd;
        $refundWorkingDays = getWorkingDaysInRange($pdo, $tenant_id, $refundStart, $refundEnd);
        $refundedDays = count($refundWorkingDays);

        if ($refundedDays > 0) {
            if ($leave['track_balance']) {
                applyLeaveBalanceTransaction($pdo, $tenant_id, $leave['employee_id'], $leave['leave_type_id'], $actualReturnDate, 'leave_reversed', $refundedDays, 'leave_records', $leaveId, "Early return from leave #$leaveId — {$refundedDays} unused day(s) refunded", $user_id);
            }
            // Clear the now-unused attendance rows this leave had auto-marked.
            // DELETE first (while leave_request_id still matches) for rows this
            // leave created outright — a fake midnight check-in with no real
            // attendance behind it and no pre-leave snapshot. Rows that DO carry
            // a snapshot (real attendance existed before an override) are restored
            // exactly instead of being guessed as 'present'.
            foreach ($refundWorkingDays as $date) {
                $stmt = $pdo->prepare("DELETE FROM attendance WHERE tenant_id = ? AND employee_id = ? AND attendance_date = ? AND leave_request_id = ? AND check_in_time = CONCAT(?, ' 00:00:00') AND pre_leave_snapshot IS NULL");
                $stmt->execute([$tenant_id, $leave['employee_id'], $date, $leaveId, $date]);

                $stmt = $pdo->prepare("SELECT id, pre_leave_snapshot FROM attendance WHERE tenant_id = ? AND employee_id = ? AND attendance_date = ? AND leave_request_id = ?");
                $stmt->execute([$tenant_id, $leave['employee_id'], $date, $leaveId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if ($row['pre_leave_snapshot']) {
                        $snap = json_decode($row['pre_leave_snapshot'], true);
                        $upd = $pdo->prepare("UPDATE attendance SET status = ?, check_in_time = ?, check_out_time = ?, notes = ?, leave_request_id = NULL, leave_type_id = NULL, leave_fraction = NULL, pre_leave_snapshot = NULL WHERE id = ?");
                        $upd->execute([$snap['status'], $snap['check_in_time'], $snap['check_out_time'], $snap['notes'], $row['id']]);
                    } else {
                        $upd = $pdo->prepare("UPDATE attendance SET status = 'present', leave_request_id = NULL, leave_type_id = NULL, leave_fraction = NULL WHERE id = ?");
                        $upd->execute([$row['id']]);
                    }
                }
            }
            // Shrink the record to reflect what was actually taken (history stays honest).
            $newTotalDays = (float) $leave['total_days'] - $refundedDays;
            $stmt = $pdo->prepare("UPDATE leave_records SET end_date = ?, total_days = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d', strtotime($actualReturnDate . ' -1 day')), $newTotalDays, $leaveId]);
        }
    }

    $stmt = $pdo->prepare("UPDATE leave_records SET actual_return_date = ?, overstay_days = ? WHERE id = ?");
    $stmt->execute([$actualReturnDate, $overstayDays, $leaveId]);

    $stmt = $pdo->prepare("UPDATE employees SET current_status = 'active', status_effective_date = ?, current_leave_id = NULL, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
    $stmt->execute([$actualReturnDate, $user_id, $leave['employee_id'], $tenant_id]);

    $summary = $overstayDays > 0
        ? "returned {$actualReturnDate} — {$overstayDays} day(s) overstay auto-recorded as Unpaid Leave (#$newUnpaidLeaveId)"
        : ($refundedDays > 0
            ? "returned early {$actualReturnDate} — {$refundedDays} unused day(s) refunded"
            : "returned on time {$actualReturnDate}");

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Leave Request', $leaveId, $leave['employee_id'],
        "Closed out {$leave['leave_type_name']} leave for \"{$leave['full_name']}\" ({$leave['employee_id']}): {$summary}",
        ['actual_return_date' => null],
        ['actual_return_date' => $actualReturnDate, 'overstay_days' => $overstayDays, 'refunded_days' => $refundedDays]
    );

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => ucfirst($summary),
        'overstay_days' => $overstayDays,
        'overstay_leave_id' => $newUnpaidLeaveId,
        'refunded_days' => $refundedDays,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
