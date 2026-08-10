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
        $department_id = $_GET['department_id'] ?? null;
        
        if (!$department_id) {
            throw new Exception('Department ID is required');
        }
        
        $stmt = $pdo->prepare("SELECT id, position_title, tenant_id FROM positions WHERE (tenant_id = 0 OR tenant_id = ?) AND (department_id = ? OR department_id IS NULL) AND is_active = 1 ORDER BY position_title");
        $stmt->execute([$tenant_id, $department_id]);
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'positions' => $positions]);
    } 
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        if ($action === 'add') {
            $name = trim($data['name'] ?? '');
            $department_id = $data['department_id'] ?? null;
            
            if (!$name) {
                throw new Exception('Position title is required');
            }
            
            if (!$department_id) {
                throw new Exception('Department is required');
            }
            
            // Check if exists
            $stmt = $pdo->prepare("SELECT id FROM positions WHERE position_title = ? AND tenant_id = ? AND department_id = ?");
            $stmt->execute([$name, $tenant_id, $department_id]);
            if ($stmt->fetch()) {
                throw new Exception('Position already exists in this department');
            }
            
            // Generate position code
            $code = strtoupper(substr($name, 0, 4));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE position_code LIKE ?");
            $stmt->execute([$code . '%']);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $code = $code . ($count + 1);
            }
            
            $stmt = $pdo->prepare("INSERT INTO positions (tenant_id, department_id, position_title, position_code, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$tenant_id, $department_id, $name, $code]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        else if ($action === 'delete') {
            $id = $data['id'] ?? null;
            
            if (!$id) {
                throw new Exception('Position ID is required');
            }
            
            // Check if position belongs to tenant
            $stmt = $pdo->prepare("SELECT tenant_id FROM positions WHERE id = ?");
            $stmt->execute([$id]);
            $pos = $stmt->fetch();
            
            if (!$pos || $pos['tenant_id'] != $tenant_id) {
                throw new Exception('Cannot delete this position');
            }
            
            // Check if position has employees
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE position_id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete position with employees');
            }
            
            $stmt = $pdo->prepare("UPDATE positions SET is_active = 0 WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
