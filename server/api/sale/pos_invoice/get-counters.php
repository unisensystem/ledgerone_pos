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

    // Does this user have any counters assigned?
    $chk = $pdo->prepare("SELECT COUNT(*) FROM user_counters WHERE user_id = ?");
    $chk->execute([$user_id]);
    $hasAssigned = (int)$chk->fetchColumn() > 0;

    $params = [$tenant_id];
    $branchClause = '';
    if ($branch_id) {
        $branchClause = 'AND c.branch_id = ?';
        $params[] = $branch_id;
    }

    $assignedJoin = '';
    if ($hasAssigned) {
        // Only the counters assigned to this user
        $assignedJoin = 'JOIN user_counters uc ON uc.counter_id = c.id AND uc.user_id = ?';
        array_unshift($params, $user_id);
    }

    $stmt = $pdo->prepare("
        SELECT c.id, c.counter_name
        FROM counters c
        $assignedJoin
        WHERE c.tenant_id = ? $branchClause AND c.is_active = 1
        ORDER BY c.counter_name
    ");
    $stmt->execute($params);

    echo json_encode([
        'success'  => true,
        'counters' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'assigned' => $hasAssigned
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}