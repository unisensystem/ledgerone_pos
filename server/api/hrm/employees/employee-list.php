<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
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
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Fetch employees
        $sql = "SELECT e.id, e.employee_id, e.full_name, e.email, e.phone_number, 
                       e.current_status, e.hire_date, e.employment_type, e.work_location,
                       e.leave_balance_paid, e.leave_balance_sick, e.leave_balance_unpaid,
                       d.department_name, p.position_title,
                       m.full_name as manager_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN positions p ON e.position_id = p.id
                LEFT JOIN employees m ON e.reporting_manager_id = m.id
                WHERE e.tenant_id = ? AND e.deleted_at IS NULL
                ORDER BY e.full_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch opening balances
        foreach ($employees as &$employee) {
            $balanceStmt = $pdo->prepare("SELECT SUM(debit - credit) FROM accounting_ledger WHERE account_id != 90 AND tenant_id = ? AND transaction_type = 'opening_balance' AND reference_table = 'employees' AND reference_id = ?");
            $balanceStmt->execute([$tenant_id, $employee['id']]);
            $employee['opening_balance'] = $balanceStmt->fetchColumn() ?: 0;
        }
        unset($employee);
        
        // Get stats
        $stats = [
            'active' => 0,
            'on_leave' => 0,
            'suspended' => 0,
            'terminated' => 0
        ];
        
        foreach ($employees as $emp) {
            $status = str_replace('-', '_', $emp['current_status']);
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }
        
        // Get departments
        $departments = [];
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT department_name FROM departments WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY department_name");
            $stmt->execute([$tenant_id]);
            $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            // If departments table doesn't exist, get from employees
            $stmt = $pdo->prepare("SELECT DISTINCT d.department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.tenant_id = ? AND e.deleted_at IS NULL AND d.department_name IS NOT NULL ORDER BY d.department_name");
            $stmt->execute([$tenant_id]);
            $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Get positions
        $positions = [];
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT position_title FROM positions WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY position_title");
            $stmt->execute([$tenant_id]);
            $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            // If positions table doesn't exist, get from employees
            $stmt = $pdo->prepare("SELECT DISTINCT p.position_title FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.tenant_id = ? AND e.deleted_at IS NULL AND p.position_title IS NOT NULL ORDER BY p.position_title");
            $stmt->execute([$tenant_id]);
            $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        echo json_encode([
            'success' => true,
            'employees' => $employees,
            'stats' => $stats,
            'departments' => $departments,
            'positions' => $positions
        ]);
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle employee actions
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        $employeeId = $data['employee_id'] ?? '';
        
        if (!$employeeId) {
            throw new Exception('Employee ID is required');
        }
        
        switch ($action) {
            case 'leave':
                $pdo->beginTransaction();
                
                // Calculate leave days
                $start = new DateTime($data['start_date']);
                $end = new DateTime($data['end_date']);
                $days = $start->diff($end)->days + 1;
                
                // Insert leave record
                $stmt = $pdo->prepare("INSERT INTO leave_records (tenant_id, employee_id, leave_type, start_date, end_date, total_days, reason, contact_during_leave, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $employeeId, $data['leave_type'] ?? 'paid', $data['start_date'], $data['end_date'], $days, $data['reason'], $data['contact'] ?? null, $user_id]);
                
                // Update employee status and leave balance
                $leaveField = $data['leave_type'] === 'sick' ? 'leave_balance_sick' : ($data['leave_type'] === 'paid' ? 'leave_balance_paid' : 'leave_balance_unpaid');
                $stmt = $pdo->prepare("UPDATE employees SET current_status = 'on-leave', status_effective_date = ?, status_change_reason = ?, {$leaveField} = {$leaveField} - ?, current_leave_id = LAST_INSERT_ID(), updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
                $stmt->execute([$data['start_date'], $data['reason'], $days, $user_id, $employeeId, $tenant_id]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Leave request processed']);
                break;
                
            case 'suspend':
                $pdo->beginTransaction();
                
                // Insert suspension record
                $stmt = $pdo->prepare("INSERT INTO suspension_records (tenant_id, employee_id, start_date, end_date, suspension_type, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $employeeId, $data['start_date'], $data['end_date'], $data['type'], $data['reason'], $data['notes'] ?? null, $user_id]);
                
                // Update employee status
                $stmt = $pdo->prepare("UPDATE employees SET current_status = 'suspended', is_suspended = 1, suspension_start_date = ?, suspension_end_date = ?, suspension_type = ?, suspension_reason = ?, status_effective_date = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
                $stmt->execute([$data['start_date'], $data['end_date'], $data['type'], $data['reason'], $data['start_date'], $user_id, $employeeId, $tenant_id]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Employee suspended']);
                break;
                
            case 'terminate':
                $pdo->beginTransaction();
                
                // Insert termination record
                $stmt = $pdo->prepare("INSERT INTO termination_records (tenant_id, employee_id, termination_date, termination_type, notice_period, reason, exit_interview_notes, final_pay_processed, gratuity_processed, assets_returned, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $employeeId, $data['date'], $data['type'], $data['notice'], $data['reason'], $data['exit_notes'] ?? null, !empty($data['final_pay']) ? 1 : 0, !empty($data['gratuity']) ? 1 : 0, !empty($data['assets_returned']) ? 1 : 0, $user_id]);
                
                // Update employee status
                $stmt = $pdo->prepare("UPDATE employees SET current_status = 'terminated', is_terminated = 1, termination_date = ?, termination_type = ?, termination_reason = ?, notice_period = ?, exit_interview_notes = ?, final_settlement_processed = ?, gratuity_paid = ?, status_effective_date = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
                $stmt->execute([$data['date'], $data['type'], $data['reason'], $data['notice'], $data['exit_notes'] ?? null, !empty($data['final_pay']) ? 1 : 0, !empty($data['gratuity']) ? 1 : 0, $data['date'], $user_id, $employeeId, $tenant_id]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Employee terminated']);
                break;
                
            case 'status':
                // Reset flags when changing to active
                if ($data['status'] === 'active') {
                    $stmt = $pdo->prepare("UPDATE employees SET current_status = ?, status_effective_date = ?, status_change_reason = ?, is_suspended = 0, is_terminated = 0, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
                    $stmt->execute([$data['status'], $data['effective_date'], $data['reason'], $user_id, $employeeId, $tenant_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE employees SET current_status = ?, status_effective_date = ?, status_change_reason = ?, updated_by = ? WHERE employee_id = ? AND tenant_id = ?");
                    $stmt->execute([$data['status'], $data['effective_date'], $data['reason'], $user_id, $employeeId, $tenant_id]);
                }
                echo json_encode(['success' => true, 'message' => 'Status updated']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
} catch (Exception $e) {
    error_log('Employee List API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}