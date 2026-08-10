<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT id, department_name, tenant_id FROM departments WHERE (tenant_id = 0 OR tenant_id = ?) AND is_active = 1 ORDER BY department_name");
        $stmt->execute([$tenant_id]);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'departments' => $departments]);
    } 
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        if ($action === 'add') {
            $name = trim($data['name'] ?? '');
            
            if (!$name) {
                throw new Exception('Department name is required');
            }
            
            // Check if exists
            $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_name = ? AND tenant_id = ?");
            $stmt->execute([$name, $tenant_id]);
            if ($stmt->fetch()) {
                throw new Exception('Department already exists');
            }
            
            // Generate department code
            $code = strtoupper(substr($name, 0, 4));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_code LIKE ?");
            $stmt->execute([$code . '%']);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $code = $code . ($count + 1);
            }
            
            $stmt = $pdo->prepare("INSERT INTO departments (tenant_id, department_name, department_code, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$tenant_id, $name, $code]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        else if ($action === 'delete') {
            $id = $data['id'] ?? null;
            
            if (!$id) {
                throw new Exception('Department ID is required');
            }
            
            // Check if department belongs to tenant
            $stmt = $pdo->prepare("SELECT tenant_id FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $dept = $stmt->fetch();
            
            if (!$dept || $dept['tenant_id'] != $tenant_id) {
                throw new Exception('Cannot delete this department');
            }
            
            // Check if department has employees
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete department with employees');
            }
            
            $stmt = $pdo->prepare("UPDATE departments SET is_active = 0 WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
