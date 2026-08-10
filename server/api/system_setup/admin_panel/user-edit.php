<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $employee_id = !empty($input['employee_id']) ? $input['employee_id'] : null;
        
        $sql = "UPDATE users SET full_name = ?, email = ?, is_active = ?, employee_id = ? WHERE id = ? AND tenant_id = ?";
        $params = [$input['full_name'], $input['email'], $input['is_active'], $employee_id, $input['id'], $tenant_id];
        
        if (!empty($input['password'])) {
            $sql = "UPDATE users SET full_name = ?, email = ?, is_active = ?, employee_id = ?, password_hash = ? WHERE id = ? AND tenant_id = ?";
            $params = [$input['full_name'], $input['email'], $input['is_active'], $employee_id, password_hash($input['password'], PASSWORD_DEFAULT), $input['id'], $tenant_id];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}