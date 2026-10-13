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

$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT
            user_name,
            SUM(action = 'create') AS created,
            SUM(action = 'update') AS updated,
            SUM(action = 'delete') AS deleted,
            COUNT(*) AS total
        FROM activity_logs
        WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY user_name
        ORDER BY total DESC
    ");
    $stmt->execute([$tenant_id, $from_date, $to_date]);
    $activityRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT u.full_name, COUNT(*) AS login_count
        FROM login_attempts la
        LEFT JOIN users u ON u.id = la.user_id AND u.tenant_id = la.tenant_id
        WHERE la.tenant_id = ? AND la.attempt_status = 'success' AND DATE(la.attempted_at) BETWEEN ? AND ?
        GROUP BY u.full_name
    ");
    $stmt->execute([$tenant_id, $from_date, $to_date]);
    $loginCounts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['full_name']) $loginCounts[$row['full_name']] = (int)$row['login_count'];
    }

    $data = array_map(function ($row) use ($loginCounts) {
        return [
            'user_name' => $row['user_name'],
            'created'   => (int)$row['created'],
            'updated'   => (int)$row['updated'],
            'deleted'   => (int)$row['deleted'],
            'total'     => (int)$row['total'],
            'logins'    => $loginCounts[$row['user_name']] ?? 0,
        ];
    }, $activityRows);

    echo json_encode(['success' => true, 'data' => $data, 'from_date' => $from_date, 'to_date' => $to_date]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
