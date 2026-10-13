<?php
session_start();
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.branch_name, pb.branch_name AS parent_name
        FROM user_branches ub
        JOIN branches b ON ub.branch_id = b.id
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        WHERE ub.tenant_id = ? AND ub.user_id = ? AND b.is_active = 1
        ORDER BY pb.branch_name, b.branch_name
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['display_name'] = $r['parent_name'] ? $r['parent_name'] . ' > ' . $r['branch_name'] : $r['branch_name'];
    }
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}