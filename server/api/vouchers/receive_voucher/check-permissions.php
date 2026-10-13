<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

try {
    $stmt = $pdo->prepare("
        SELECT rp.sub_permission
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id AND ur.tenant_id = rp.tenant_id
        WHERE ur.user_id = ? AND ur.tenant_id = ? AND ur.is_active = 1 
        AND rp.allowed = 1 AND rp.form_name = 'Receive Voucher'
    ");
    
    $stmt->execute([$user_id, $tenant_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'can_create' => in_array('Add', $permissions),
        'can_edit' => in_array('Edit', $permissions),
        'can_delete' => in_array('Delete', $permissions)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch permissions']);
}
?>