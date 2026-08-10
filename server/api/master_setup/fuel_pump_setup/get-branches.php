<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.branch_name, b.branch_type, pb.branch_name as parent_branch_name
        FROM branches b
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        WHERE b.tenant_id = ? AND b.parent_branch_id IS NOT NULL
    ");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}