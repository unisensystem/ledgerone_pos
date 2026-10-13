<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        throw new Exception('Department ID is required');
    }

    // First check if department has any employees
    $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
    $check->execute([$input['id']]);
    if ($check->fetchColumn() > 0) {
        throw new Exception('Cannot delete department: It has employees assigned to it');
    }

    // Check if department has child departments
    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE parent_dept_id = ?");
    $check->execute([$input['id']]);
    if ($check->fetchColumn() > 0) {
        throw new Exception('Cannot delete department: It has sub-departments');
    }

    // Delete the department
    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    $result = $stmt->execute([$input['id']]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Department deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete department');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
