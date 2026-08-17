<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.branch_name,
            b.branch_type,
            b.parent_branch_id,
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

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}