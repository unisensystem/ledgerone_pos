<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $role_id = $_GET['role_id'] ?? null;
        if (!$role_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Role ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, category, form_name, sub_permission, allowed FROM role_permissions WHERE tenant_id = ? AND role_id = ? ORDER BY category, form_name, sub_permission");
        $stmt->execute([$tenant_id, $role_id]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'permissions' => $permissions]);
    } 
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $role_id = $input['role_id'] ?? null;
        $permissions = $input['permissions'] ?? [];
        
        if (!$role_id || empty($permissions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO role_permissions (tenant_id, role_id, category, form_name, sub_permission, allowed) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($permissions as $perm) {
            $stmt->execute([$tenant_id, $role_id, $perm['category'], $perm['form_name'], $perm['sub_permission'], $perm['allowed']]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Permissions created successfully']);
    }
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $role_id = $input['role_id'] ?? null;
        $permissions = $input['permissions'] ?? [];
        
        if (!$role_id || empty($permissions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE role_permissions SET allowed = ? WHERE id = ? AND tenant_id = ? AND role_id = ?");
        foreach ($permissions as $perm) {
            $stmt->execute([$perm['allowed'], $perm['id'], $tenant_id, $role_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('Permissions API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'debug' => $e->getMessage()]);
}
