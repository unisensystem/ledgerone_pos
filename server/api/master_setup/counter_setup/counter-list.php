<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $branch_id  = !empty($_GET['branch_id'])  ? intval($_GET['branch_id'])  : null;
    $company_id = !empty($_GET['company_id']) ? intval($_GET['company_id']) : null;
    $search     = $_GET['search'] ?? '';

    $where  = ['c.tenant_id = ?'];
    $params = [$tenant_id];

    if ($branch_id) {
        $where[]  = 'c.branch_id = ?';
        $params[] = $branch_id;
    }
    if ($company_id) {
        $where[]  = 'c.company_id = ?';
        $params[] = $company_id;
    }
    if ($search !== '') {
        $where[]  = 'c.counter_name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $whereStr = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT c.id, c.counter_name, c.branch_id, c.company_id, c.is_active,
               b.branch_name, co.company_name
        FROM counters c
        LEFT JOIN branches  b  ON b.id = c.branch_id
        LEFT JOIN companies co ON co.id = c.company_id
        WHERE $whereStr
        ORDER BY b.branch_name, c.counter_name
    ");
    $stmt->execute($params);
    $counters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'counters' => $counters]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
