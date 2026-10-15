<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$tenantId = $auth['tenant_id'];
$userId = $auth['user_id'];

try {
    $pdo->prepare(
        "INSERT INTO officer_tracking_settings (tenant_id, user_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE id = id"
    )->execute([$tenantId, $userId]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($input['enabled'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'enabled is required']);
            exit;
        }
        // Officers can only ever change their OWN switch — admin_enabled is
        // web-admin-only (see server/api/system_setup/admin_panel/set-tracking-enabled.php).
        $pdo->prepare(
            "UPDATE officer_tracking_settings SET officer_enabled = ? WHERE tenant_id = ? AND user_id = ?"
        )->execute([$input['enabled'] ? 1 : 0, $tenantId, $userId]);
    }

    $stmt = $pdo->prepare(
        "SELECT admin_enabled, officer_enabled FROM officer_tracking_settings WHERE tenant_id = ? AND user_id = ?"
    );
    $stmt->execute([$tenantId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'admin_enabled' => (bool) $row['admin_enabled'],
        'officer_enabled' => (bool) $row['officer_enabled'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
