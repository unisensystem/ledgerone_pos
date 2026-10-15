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
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    // Every tenant user, not just ones who've opened the mobile app yet —
    // an admin should be able to pre-emptively exclude someone before they
    // ever send a single ping.
    $stmt = $pdo->prepare("
        SELECT u.id AS user_id, u.full_name, u.email,
               COALESCE(s.admin_enabled, 1) AS admin_enabled,
               COALESCE(s.officer_enabled, 1) AS officer_enabled
        FROM users u
        LEFT JOIN officer_tracking_settings s ON s.tenant_id = u.tenant_id AND s.user_id = u.id
        WHERE u.tenant_id = ? AND u.is_active = 1
        ORDER BY u.full_name, u.email
    ");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'user_id' => (int) $row['user_id'],
            'name' => $row['full_name'] ?: $row['email'],
            'admin_enabled' => (bool) $row['admin_enabled'],
            'officer_enabled' => (bool) $row['officer_enabled'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
