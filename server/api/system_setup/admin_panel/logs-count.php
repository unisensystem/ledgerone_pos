<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/role-helper.php';

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

if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a Super Admin can manage log entries']);
    exit;
}

$logType = $_GET['log_type'] ?? '';
$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';
$toDate = $_GET['to_date'] ?? '';

if (!in_array($logType, ['audit', 'login'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'log_type must be audit or login']);
    exit;
}

try {
    if ($logType === 'audit') {
        $where = ['tenant_id = ?'];
        $params = [$tenant_id];
        if ($module !== '') { $where[] = 'module = ?'; $params[] = $module; }
        if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
        if ($toDate !== '') { $where[] = 'DATE(created_at) <= ?'; $params[] = $toDate; }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
    } else {
        $where = ['tenant_id = ?'];
        $params = [$tenant_id];
        if ($action !== '') { $where[] = 'attempt_status = ?'; $params[] = $action; }
        if ($toDate !== '') { $where[] = 'DATE(attempted_at) <= ?'; $params[] = $toDate; }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
    }

    $count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
