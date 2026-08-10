<?php
header('Content-Type: application/json');
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$session_tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$session_tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id']) || !isset($input['container_code']) || !isset($input['container_name'])) {
        throw new Exception('Missing required fields');
    }

    $id = (int)$input['id'];
    $tenant_id = isset($input['tenant_id']) ? (int)$input['tenant_id'] : $session_tenant_id;
    
    // Verify tenant_id matches session
    if ($tenant_id !== $session_tenant_id) {
        throw new Exception('Invalid tenant');
    }

    $container_code = trim($input['container_code']);
    $container_name = trim($input['container_name']);
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (empty($container_code) || empty($container_name)) {
        throw new Exception('Container code and name cannot be empty');
    }

    // Verify container exists for this tenant
    $verifyQuery = "SELECT id FROM containers WHERE id = ? AND tenant_id = ?";
    $verifyStmt = $pdo->prepare($verifyQuery);
    $verifyStmt->execute([$id, $tenant_id]);

    if ($verifyStmt->rowCount() === 0) {
        throw new Exception('Container not found');
    }

    // Check if container code already exists (for other containers)
    $checkQuery = "SELECT id FROM containers WHERE container_code = ? AND id != ? AND tenant_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$container_code, $id, $tenant_id]);

    if ($checkStmt->rowCount() > 0) {
        throw new Exception('Container code already exists for this tenant');
    }

    $query = "UPDATE containers SET container_code = ?, container_name = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($query);
    
    if (!$stmt->execute([$container_code, $container_name, $is_active, $id, $tenant_id])) {
        throw new Exception('Failed to update container');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Container updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
