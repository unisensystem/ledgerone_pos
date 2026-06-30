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
        SELECT b.id, b.branch_code, b.branch_name, b.branch_type
        FROM user_branches ub
        JOIN branches b ON ub.branch_id = b.id
        WHERE ub.tenant_id = ? AND ub.user_id = ? AND b.is_active = 1
        ORDER BY b.branch_name ASC
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $branches = $stmt->fetchAll();

    $result = [];
    foreach ($branches as $b) {
        $result[] = [
            'id'         => $b['id'],
            'code'       => $b['branch_code'],
            'name'       => $b['branch_name'],
            'display'    => $b['branch_name'],
            'level'      => 1,
            'selectable' => true
        ];
    }

    echo json_encode(['success' => true, 'branches' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
