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

try {
    $group_id = $_POST['id'] ?? '';
    
    if (empty($group_id)) {
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        exit;
    }
    
    // Verify group belongs to tenant and is not system-locked
    $stmt = $pdo->prepare("SELECT id, tenant_id FROM uom_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        exit;
    }
    
    if ($group['tenant_id'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete system-locked UOM Group']);
        exit;
    }
    
    if ($group['tenant_id'] != $tenant_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to delete this group']);
        exit;
    }
    
    // Delete the group (cascade will delete group units)
    $stmt = $pdo->prepare("DELETE FROM uom_groups WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$group_id, $tenant_id]);
    
    echo json_encode(['success' => true, 'message' => 'UOM Group deleted successfully']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
