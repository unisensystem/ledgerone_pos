<?php
require_once('config.php');

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID is required']);
    exit;
}

try {
    // Get employee details with department name
    $sql = "SELECT e.*, d.dept_name, d.dept_code,
            CONCAT(h.first_name, ' ', h.last_name) as hired_by_name
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees h ON e.hired_by = h.id
            WHERE e.id = :id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $_GET['id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
        exit;
    }

    // Get all departments for dropdown
    $dept_sql = "SELECT id, dept_name, dept_code FROM departments ORDER BY dept_name";
    $dept_stmt = $pdo->query($dept_sql);
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all active employees for hired_by dropdown
    $emp_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
                FROM employees 
                WHERE status = 'Active' AND id != :id 
                ORDER BY first_name, last_name";
    $emp_stmt = $pdo->prepare($emp_sql);
    $emp_stmt->execute(['id' => $_GET['id']]);
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for form inputs
    $employee['date_of_birth'] = date('Y-m-d', strtotime($employee['date_of_birth']));
    $employee['hire_date'] = date('Y-m-d', strtotime($employee['hire_date']));
    
    // Return all data needed for the edit form
    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'departments' => $departments,
        'employees' => $employees
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching employee data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
