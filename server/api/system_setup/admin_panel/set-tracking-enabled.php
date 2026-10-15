<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/role-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
// Without this, any logged-in tenant user could turn tracking on/off for
// any other employee (privilege escalation) — admin-only action.
if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$targetUserId = isset($input['user_id']) ? (int) $input['user_id'] : null;
$enabled = isset($input['enabled']) ? (bool) $input['enabled'] : null;

if (!$targetUserId || $enabled === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'user_id and enabled are required']);
    exit;
}

try {
    // Confirm the target user actually belongs to this tenant before
    // touching anything — tenant_id in the INSERT alone isn't enough to
    // stop a cross-tenant user_id from being targeted.
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $check->execute([$targetUserId, $tenant_id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $pdo->prepare(
        "INSERT INTO officer_tracking_settings (tenant_id, user_id, admin_enabled) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE admin_enabled = VALUES(admin_enabled)"
    )->execute([$tenant_id, $targetUserId, $enabled ? 1 : 0]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
