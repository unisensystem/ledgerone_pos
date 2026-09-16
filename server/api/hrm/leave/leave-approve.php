<?php
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
    $decision = $data['decision'] ?? ''; // 'approve' | 'reject'
    $remarks = trim($data['remarks'] ?? '');
    $override = !empty($data['override']);

    if (!$leaveId || !in_array($decision, ['approve', 'reject'], true)) {
        throw new Exception('Leave ID and a valid decision (approve/reject) are required');
    }

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
    if ($leave['status'] !== 'pending') throw new Exception("Only pending requests can be actioned (this one is '{$leave['status']}')");

    $workingDaysForApproval = [];
    if ($decision === 'approve') {
        // Attendance may have been entered for these dates AFTER the request was
        // submitted but BEFORE it was approved — re-check for a real-attendance
        // conflict right before overwriting it, same as the auto-approve path.
        $workingDaysForApproval = getWorkingDaysInRange($pdo, $tenant_id, $leave['start_date'], $leave['end_date']);
        $conflicts = getAttendanceConflicts($pdo, $tenant_id, $leave['employee_id'], $workingDaysForApproval);
        if ($conflicts && !$override) {
            $list = implode(', ', array_map(fn($c) => "{$c['attendance_date']} ({$c['status']})", $conflicts));
            throw new Exception("Employee already has recorded attendance on: $list. Resubmit with override=true to approve and replace it with leave (the original attendance is preserved and restored if this leave is later cancelled).");
        }
    }

    $pdo->beginTransaction();

    if ($leave['track_balance']) {
        // Release the pending hold either way — approval converts it into a
        // committed 'leave_used' transaction instead; rejection just frees it.
        adjustPendingBalance($pdo, $tenant_id, $leave['employee_id'], $leave['leave_type_id'], $leave['start_date'], -(float) $leave['total_days']);
    }

    if ($decision === 'approve') {
        $stmt = $pdo->prepare("UPDATE leave_records SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id, $leaveId]);

        if ($leave['track_balance']) {
            applyLeaveBalanceTransaction($pdo, $tenant_id, $leave['employee_id'], $leave['leave_type_id'], $leave['start_date'], 'leave_used', -(float) $leave['total_days'], 'leave_records', $leaveId, "Leave #$leaveId approved", $user_id);
        }

        syncAttendanceForLeave($pdo, $tenant_id, $leave['employee_id'], $leaveId, (int) $leave['leave_type_id'], $workingDaysForApproval, $user_id, ((int) $leave['is_half_day']) ? 0.5 : 1.0);

        $stmt = $pdo->prepare("UPDATE employees SET current_status = 'on-leave', status_effective_date = ?, status_change_reason = ?, current_leave_id = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
        $stmt->execute([$leave['start_date'], $leave['reason'], $leaveId, $user_id, $leave['employee_id'], $tenant_id]);

        $message = 'Leave approved';
    } else {
        $stmt = $pdo->prepare("UPDATE leave_records SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejected_reason = ? WHERE id = ?");
        $stmt->execute([$user_id, $remarks ?: null, $leaveId]);
        // Rejected: no balance deduction (pending hold already released above), no attendance change.
        $message = 'Leave rejected';
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Leave Request', $leaveId, $leave['employee_id'],
        "{$message} for \"{$leave['full_name']}\" ({$leave['employee_id']}): {$leave['leave_type_name']}, {$leave['start_date']} to {$leave['end_date']}" . ($remarks ? " — {$remarks}" : ''),
        ['status' => 'pending'],
        ['status' => $decision === 'approve' ? 'approved' : 'rejected', 'remarks' => $remarks]
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
