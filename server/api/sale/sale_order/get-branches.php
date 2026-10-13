<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$tenant_id = $auth['tenant_id'];
$user_id = $auth['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.branch_code,
            b.branch_name,
            b.branch_type,
            pb.branch_name AS parent_branch_name
        FROM user_branches ub
        JOIN branches b  ON ub.branch_id = b.id
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        WHERE ub.tenant_id = ?
          AND ub.user_id   = ?
          AND b.is_active  = 1
        ORDER BY pb.branch_name, b.branch_name
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}