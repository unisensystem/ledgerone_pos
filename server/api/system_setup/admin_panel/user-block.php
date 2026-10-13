<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

$input = json_decode(file_get_contents('php://input'), true);

try {
    $oldStmt = $pdo->prepare("SELECT full_name, email, is_active as old_status FROM users WHERE id = ? AND tenant_id = ?");
    $oldStmt->execute([$input['id'], $tenant_id]);
    $userRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['is_active'], $input['id'], $tenant_id]);

    if ($userRow) {
        $action = $input['is_active'] ? 'Unblocked' : 'Blocked';
        $editDescription = (int)$userRow['old_status'] !== (int)$input['is_active']
            ? "$action user \"{$userRow['full_name']}\" ({$userRow['email']})"
            : "Updated user \"{$userRow['full_name']}\" ({$userRow['email']}) - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'User', $input['id'], $userRow['email'], $editDescription, ['is_active' => $userRow['old_status']], ['is_active' => $input['is_active']]);
    }

    echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}