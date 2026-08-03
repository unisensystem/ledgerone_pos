<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['user_id']   ?? null;

if (!$tenant_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $company_id = isset($_GET['company_id']) && $_GET['company_id'] !== '' ? (int)$_GET['company_id'] : null;

    $sql = "
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
    ";
    $params = [$tenant_id, $user_id];

    if ($company_id) {
        $sql .= " AND b.company_id = ?";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY pb.branch_name, b.branch_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}