<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // Verify user belongs to this tenant first
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['user_id'], $tenant_id]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$targetUser) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User not found or access denied']);
        exit;
    }

    // Snapshot old role assignment for the Activity Log
    $oldRoleStmt = $pdo->prepare("SELECT r.id, r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? AND ur.tenant_id = ?");
    $oldRoleStmt->execute([$input['user_id'], $tenant_id]);
    $oldRole = $oldRoleStmt->fetch(PDO::FETCH_ASSOC);

    $newRoleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $newRoleStmt->execute([$input['role_id']]);
    $newRoleName = $newRoleStmt->fetchColumn();

    // Delete existing roles first
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$input['user_id'], $tenant_id]);

    // Insert new role assignment
    $stmt = $pdo->prepare("INSERT INTO user_roles (tenant_id, user_id, role_id, assigned_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $input['user_id'], $input['role_id'], $user_id]);

    $oldRoleName = $oldRole['name'] ?? null;
    $editDescription = $oldRoleName !== $newRoleName
        ? "Assigned role to user \"{$targetUser['full_name']}\" ({$targetUser['email']}): " . ($oldRoleName ?: '(none)') . " \u{2192} $newRoleName"
        : "Reassigned role for user \"{$targetUser['full_name']}\" ({$targetUser['email']}) - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'User', $input['user_id'], $targetUser['email'], $editDescription, ['role' => $oldRoleName], ['role' => $newRoleName]);

    echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('User Role API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'debug' => $e->getMessage()]);
}