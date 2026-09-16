<?php
// Manual HR balance adjustment — records a proper 'adjustment' transaction
// in employee_leave_balance_transactions (see server/api/hrm/leave/) instead
// of blindly overwriting a single number. Mirrors into the legacy
// employees.leave_balance_paid/sick/unpaid columns too, for the 3 leave
// types that map onto them, so anything older still reading those columns
// keeps working.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once '../leave/leave-helpers.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $employeeCode = trim($data['employee_id'] ?? '');
    $leaveTypeId = (int) ($data['leave_type_id'] ?? 0);
    $amount = (float) ($data['amount'] ?? 0);
    $operation = $data['operation'] ?? 'add'; // 'add' (delta) or 'set' (absolute)
    $reason = trim($data['reason'] ?? 'Manual HR adjustment');

    if (!$employeeCode || !$leaveTypeId) {
        throw new Exception('Employee and leave type are required');
    }

    $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE employee_id = ? AND tenant_id = ?");
    $stmt->execute([$employeeCode, $tenant_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) throw new Exception('Employee not found');

    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$leaveTypeId, $tenant_id]);
    $leaveType = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leaveType) throw new Exception('Leave type not found');
    if (!$leaveType['track_balance']) throw new Exception("{$leaveType['name']} does not track a balance (unlimited) — nothing to adjust");

    $pdo->beginTransaction();

    $before = getRemainingBalance($pdo, $tenant_id, $employeeCode, $leaveTypeId);
    $delta = $operation === 'set' ? ($amount - $before) : $amount;

    applyLeaveBalanceTransaction($pdo, $tenant_id, $employeeCode, $leaveTypeId, date('Y-m-d'), 'adjustment', $delta, 'manual', null, $reason, $user_id);

    $after = getRemainingBalance($pdo, $tenant_id, $employeeCode, $leaveTypeId);

    // Mirror into the legacy fixed columns for the 3 types that map to them,
    // so any older code still reading employees.leave_balance_* stays correct.
    $legacyColumn = match ($leaveType['code']) {
        'ANNUAL' => 'leave_balance_paid',
        'SICK' => 'leave_balance_sick',
        default => null,
    };
    if ($legacyColumn) {
        $stmt = $pdo->prepare("UPDATE employees SET {$legacyColumn} = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
        $stmt->execute([$after, $user_id, $employeeCode, $tenant_id]);
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Employee', $employee['id'], $employeeCode,
        "Adjusted {$leaveType['name']} balance for \"{$employee['full_name']}\" ({$employeeCode}): {$before} \u{2192} {$after} ({$reason})",
        ['balance' => $before],
        ['balance' => $after]
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Leave balance updated successfully', 'new_balance' => $after]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
