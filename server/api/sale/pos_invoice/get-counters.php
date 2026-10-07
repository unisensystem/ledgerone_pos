<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $branch_id = !empty($_GET['branch_id']) ? intval($_GET['branch_id']) : null;

    $params = [$tenant_id];
    $branchClause = '';
    if ($branch_id) {
        $branchClause = 'AND branch_id = ?';
        $params[] = $branch_id;
    }

    $stmt = $pdo->prepare("
        SELECT id, counter_name
        FROM counters
        WHERE tenant_id = ? $branchClause AND is_active = 1
        ORDER BY counter_name
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'counters' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
