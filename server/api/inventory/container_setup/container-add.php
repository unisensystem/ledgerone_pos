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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['container_name'])) {
        throw new Exception('Container name is required');
    }

    $tenant_id = isset($input['tenant_id']) ? (int)$input['tenant_id'] : $session_tenant_id;
    
    if ($tenant_id !== $session_tenant_id) {
        throw new Exception('Invalid tenant');
    }

    // Generate container code
    $container_code = generateContainerCode($pdo, $tenant_id);
    $container_name = trim($input['container_name']);
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (empty($container_name)) {
        throw new Exception('Container name cannot be empty');
    }

    $query = "INSERT INTO containers (tenant_id, container_code, container_name, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($query);
    
    if (!$stmt->execute([$tenant_id, $container_code, $container_name, $is_active])) {
        throw new Exception('Failed to add container');
    }

    $container_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Container added successfully',
        'container_id' => $container_id,
        'container_code' => $container_code
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateContainerCode($pdo, $tenant_id) {
    $prefix = 'CNT';
    
    // Get the count of containers for this tenant
    $countQuery = "SELECT COUNT(*) as count FROM containers WHERE tenant_id = ?";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute([$tenant_id]);
    $result = $countStmt->fetch();
    $count = $result['count'] + 1;
    
    // Format: CNT-TENANT-SEQUENCE (e.g., CNT-1-00001)
    $code = sprintf("%s-%d-%05d", $prefix, $tenant_id, $count);
    
    return $code;
}
?>
