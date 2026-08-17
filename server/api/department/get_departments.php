<?php
require_once '../../config/config.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, dept_name FROM departments ORDER BY dept_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($departments);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
