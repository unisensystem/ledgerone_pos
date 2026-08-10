<?php
require_once '../../config/config.php';
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get JSON input and decode
    $json = file_get_contents('php://input');
    error_log("Received JSON: " . $json); // Debug log
    $input = json_decode($json, true);
    
    $id = $input['id'] ?? null;
    error_log("Processing delete request for employee ID: " . $id); // Debug log

    if (!$id) {
        throw new Exception('Employee ID is required');
    }

    // Start transaction
    $pdo->beginTransaction();
    error_log("Transaction started"); // Debug log

    // First check if employee exists
    $check = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        throw new Exception("Employee with ID $id not found");
    }
    error_log("Employee found, proceeding with deletion"); // Debug log

    // Delete from related tables first
    $tables = ['salary_components', 'employee_education', 'employee_work_history'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE employee_id = ?");
            $result = $stmt->execute([$id]);
            error_log("Delete from $table result: " . ($result ? 'success' : 'fail'));
        } catch (PDOException $e) {
            error_log("Error deleting from $table: " . $e->getMessage());
        }
    }

    // Delete from employees table
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $result = $stmt->execute([$id]);
    error_log("Delete from employees result: " . ($result ? 'success' : 'fail')); // Debug log

    if (!$result) {
        throw new Exception('Failed to delete employee record: ' . implode(', ', $stmt->errorInfo()));
    }

    // Commit transaction
    $pdo->commit();
    error_log("Transaction committed successfully"); // Debug log

    http_response_code(200); // Explicitly set success status
    echo json_encode([
        'success' => true,
        'message' => 'Employee deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in delete_employee.php: " . $e->getMessage()); // Debug log
    
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transaction rolled back"); // Debug log
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString() // Include stack trace for debugging
    ]);
}
