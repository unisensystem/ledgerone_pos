<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    
    $employeeId = $data['employee_id'] ?? '';
    $leaveType = $data['leave_type'] ?? '';
    $amount = $data['amount'] ?? 0;
    $operation = $data['operation'] ?? 'add'; // 'add' or 'set'
    
    if (!$employeeId || !$leaveType) {
        throw new Exception('Employee ID and leave type are required');
    }
    
    $leaveField = $leaveType === 'sick' ? 'leave_balance_sick' :
                 ($leaveType === 'paid' ? 'leave_balance_paid' : 'leave_balance_unpaid');

    $stmt = $pdo->prepare("SELECT id, full_name, {$leaveField} as old_balance FROM employees WHERE employee_id = ? AND tenant_id = ?");
    $stmt->execute([$employeeId, $tenant_id]);
    $empRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empRow) {
        throw new Exception('Employee not found');
    }

    if ($operation === 'add') {
        $sql = "UPDATE employees SET {$leaveField} = {$leaveField} + ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?";
    } else {
        $sql = "UPDATE employees SET {$leaveField} = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$amount, $user_id, $employeeId, $tenant_id]);
    
    // Get updated balance
    $stmt = $pdo->prepare("SELECT {$leaveField} as balance FROM employees WHERE employee_id = ? AND tenant_id = ?");
    $stmt->execute([$employeeId, $tenant_id]);
    $result = $stmt->fetch();
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Employee', $empRow['id'], $employeeId,
        "Updated {$leaveType} leave balance for \"{$empRow['full_name']}\" ({$employeeId}): {$empRow['old_balance']} \u{2192} {$result['balance']}",
        [$leaveField => $empRow['old_balance']],
        [$leaveField => $result['balance']]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Leave balance updated successfully',
        'new_balance' => $result['balance']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}