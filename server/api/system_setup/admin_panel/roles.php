<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT id, name, description, is_system_role FROM roles WHERE (tenant_id = ? OR tenant_id = 0) AND is_active = 1");
            $stmt->execute([$tenant_id]);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'roles' => $roles]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO roles (tenant_id, name, description, is_system_role, is_active) VALUES (?, ?, ?, 0, 1)");
            $stmt->execute([$tenant_id, $input['name'], $input['description'] ?? null]);
            $newRoleId = $pdo->lastInsertId();
            logActivity(
                $pdo, $tenant_id, $user_id, 'create', 'Role', $newRoleId, $input['name'],
                "Created role \"{$input['name']}\"",
                null,
                ['name' => $input['name'], 'description' => $input['description'] ?? null]
            );
            echo json_encode(['success' => true, 'message' => 'Role created successfully']);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $oldStmt = $pdo->prepare("SELECT name, description FROM roles WHERE id = ? AND tenant_id = ?");
            $oldStmt->execute([$input['id'], $tenant_id]);
            $oldRole = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$input['name'], $input['description'], $input['id'], $tenant_id]);

            if ($oldRole) {
                $newRole = ['name' => $input['name'], 'description' => $input['description']];
                $changeDescriptions = buildChangeDescription($oldRole, $newRole, ['name' => 'Name', 'description' => 'Description']);
                $editDescription = !empty($changeDescriptions)
                    ? "Updated role \"{$oldRole['name']}\": " . implode('; ', $changeDescriptions)
                    : "Updated role \"{$oldRole['name']}\" - no tracked field changes";
                logActivity($pdo, $tenant_id, $user_id, 'update', 'Role', $input['id'], $oldRole['name'], $editDescription, $oldRole, $newRole);
            }

            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Role ID required']);
                exit;
            }
            $oldStmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND tenant_id = ?");
            $oldStmt->execute([$id, $tenant_id]);
            $roleRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ? AND tenant_id = ? AND is_system_role = 0");
            $stmt->execute([$id, $tenant_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Cannot delete system role or role not found']);
                exit;
            }

            if ($roleRow) {
                logActivity($pdo, $tenant_id, $user_id, 'delete', 'Role', $id, $roleRow['name'], "Deleted role \"{$roleRow['name']}\"", $roleRow, null);
            }

            echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Roles API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'debug' => $e->getMessage()]);
}