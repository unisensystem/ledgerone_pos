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

$module = trim($_GET['module'] ?? '');
$record_id = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;

if ($module === '' || !$record_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'module and record_id are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, user_name, action, module, record_reference, description, old_values, new_values, created_at
        FROM activity_logs
        WHERE tenant_id = ? AND module = ? AND record_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$tenant_id, $module, $record_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $timeline = array_map(function ($row) {
        return [
            'id'               => (int)$row['id'],
            'user_name'        => $row['user_name'],
            'action'           => $row['action'],
            'record_reference' => $row['record_reference'],
            'description'      => $row['description'],
            'created_at'       => $row['created_at'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $timeline]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
