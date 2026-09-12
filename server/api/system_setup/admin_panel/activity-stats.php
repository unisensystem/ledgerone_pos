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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE tenant_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $todayActivities = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE tenant_id = ? AND attempt_status = 'success' AND DATE(attempted_at) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $todayLogins = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE tenant_id = ? AND attempt_status IN ('failed', 'locked') AND DATE(attempted_at) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $todayFailedLogins = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE tenant_id = ? AND action = 'delete' AND created_at >= (NOW() - INTERVAL 30 DAY)");
    $stmt->execute([$tenant_id]);
    $deletions30d = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as day, COUNT(*) as total
        FROM activity_logs
        WHERE tenant_id = ? AND created_at >= (NOW() - INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    $stmt->execute([$tenant_id]);
    $dailyRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $daily = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $daily[] = ['day' => $day, 'total' => (int)($dailyRows[$day] ?? 0)];
    }

    $stmt = $pdo->prepare("
        SELECT module, COUNT(*) as total
        FROM activity_logs
        WHERE tenant_id = ? AND created_at >= (NOW() - INTERVAL 30 DAY)
        GROUP BY module
        ORDER BY total DESC
        LIMIT 8
    ");
    $stmt->execute([$tenant_id]);
    $byModule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'today_activities'    => $todayActivities,
            'today_logins'        => $todayLogins,
            'today_failed_logins' => $todayFailedLogins,
            'deletions_30d'       => $deletions30d,
            'daily'               => $daily,
            'by_module'           => $byModule,
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
