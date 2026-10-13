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
    $reason = trim($data['reason'] ?? '');
    if (!$leaveId || !$reason) throw new Exception('Leave ID and a cancellation reason are required');

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
    if (!in_array($leave['status'], ['pending', 'approved'], true)) {
        throw new Exception("Only pending or approved leave can be cancelled (this one is '{$leave['status']}')");
    }

    // Was this leave period already paid out in a FINALIZED payroll? If so,
    // silent cancellation would corrupt a locked, already-paid payroll —
    // refuse and point to the controlled adjustment path instead.
    $stmt = $pdo->prepare("
        SELECT pe.payroll_code FROM payroll_entries pe
        JOIN payroll_entries_items pei ON pei.payroll_id = pe.id
        WHERE pe.tenant_id = ? AND pei.employee_id = ? AND pe.status = 'finalized'
          AND pei.period_start IS NOT NULL AND pei.period_end IS NOT NULL
          AND pei.period_start <= ? AND pei.period_end >= ?
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $leave['employee_id'], $leave['end_date'], $leave['start_date']]);
    $finalizedPayroll = $stmt->fetchColumn();
    if ($finalizedPayroll && empty($data['acknowledge_finalized_payroll'])) {
        throw new Exception("This leave period was already paid in finalized payroll {$finalizedPayroll}. Cancelling now will NOT change that payroll automatically — a manual payroll adjustment will be needed. Resubmit with acknowledge_finalized_payroll=true to proceed anyway.");
    }

    $pdo->beginTransaction();

    if ($leave['status'] === 'pending') {
        if ($leave['track_balance']) {
            adjustPendingBalance($pdo, $tenant_id, $leave['employee_id'], $leave['leave_type_id'], $leave['start_date'], -(float) $leave['total_days']);
        }
    } else { // approved
        if ($leave['track_balance']) {
            applyLeaveBalanceTransaction($pdo, $tenant_id, $leave['employee_id'], $leave['leave_type_id'], date('Y-m-d'), 'leave_reversed', (float) $leave['total_days'], 'leave_records', $leaveId, "Leave #$leaveId cancelled: $reason", $user_id);
        }
        clearAttendanceForLeave($pdo, $tenant_id, $leaveId);

        // If this was the employee's CURRENT active leave, put them back to active.
        $stmt = $pdo->prepare("SELECT current_leave_id FROM employees WHERE employee_id = ? AND tenant_id = ?");
        $stmt->execute([$leave['employee_id'], $tenant_id]);
        if ((int) $stmt->fetchColumn() === $leaveId) {
            $stmt = $pdo->prepare("UPDATE employees SET current_status = 'active', current_leave_id = NULL, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
            $stmt->execute([$user_id, $leave['employee_id'], $tenant_id]);
        }
    }

    $stmt = $pdo->prepare("UPDATE leave_records SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?");
    $stmt->execute([$user_id, $reason, $leaveId]);

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Leave Request', $leaveId, $leave['employee_id'],
        "Cancelled {$leave['leave_type_name']} leave for \"{$leave['full_name']}\" ({$leave['employee_id']}), {$leave['start_date']} to {$leave['end_date']}: {$reason}" . ($finalizedPayroll ? " [payroll {$finalizedPayroll} already finalized for this period — needs manual adjustment]" : ''),
        ['status' => $leave['status']],
        ['status' => 'cancelled', 'reason' => $reason]
    );

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Leave cancelled',
        'warning' => $finalizedPayroll ? "Payroll {$finalizedPayroll} for this period is already finalized and was NOT changed — adjust it manually." : null,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
