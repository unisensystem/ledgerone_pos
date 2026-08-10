<?php
require_once '../../../../includes/connection.php';

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
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['user_id'], $tenant_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User not found or access denied']);
        exit;
    }
    
    // Delete existing roles first
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$input['user_id'], $tenant_id]);
    
    // Insert new role assignment
    $stmt = $pdo->prepare("INSERT INTO user_roles (tenant_id, user_id, role_id, assigned_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $input['user_id'], $input['role_id'], $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('User Role API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'debug' => $e->getMessage()]);
}