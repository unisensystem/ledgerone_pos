<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.branch_code,
            b.branch_name,
            b.branch_type,
            pb.branch_name AS parent_branch_name
        FROM branches b
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        WHERE b.tenant_id = ? AND b.is_active = 1 AND b.parent_branch_id IS NOT NULL
        ORDER BY pb.branch_name, b.branch_name
    ");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
