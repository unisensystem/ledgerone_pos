<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['dept_name'])) {
        throw new Exception('Department name is required');
    }

    $sql = "INSERT INTO departments (dept_name, dept_code, parent_dept_id, description) 
            VALUES (:dept_name, :dept_code, :parent_dept_id, :description)";
            
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'dept_name' => $input['dept_name'],
        'dept_code' => $input['dept_code'] ?? null,
        'parent_dept_id' => !empty($input['parent_dept_id']) ? $input['parent_dept_id'] : null,
        'description' => $input['description'] ?? null
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully',
            'id' => $pdo->lastInsertId()
        ]);
    } else {
        throw new Exception('Failed to add department');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
