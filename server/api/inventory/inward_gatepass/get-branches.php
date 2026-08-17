<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.branch_name, p.branch_name as parent_name 
        FROM user_branches ub
        JOIN branches b  ON ub.branch_id = b.id
        LEFT JOIN branches p ON b.parent_branch_id = p.id 
        WHERE ub.tenant_id = ? AND ub.user_id = ? AND b.is_active = 1
        ORDER BY p.branch_name, b.branch_name ASC
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $branches = [];
    
    while ($row = $stmt->fetch()) {
        $branches[] = [
            'id' => $row['id'],
            'name' => $row['parent_name'] . ' → ' . $row['branch_name']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $branches]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
