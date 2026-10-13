<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

$userId = $_GET['id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    $oldStmt = $pdo->prepare("SELECT full_name, email, phone, employee_id, is_active FROM users WHERE id = ? AND tenant_id = ?");
    $oldStmt->execute([$userId, $tenant_id]);
    $userRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$userId, $tenant_id]);

    if ($userRow) {
        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'User', $userId, $userRow['email'],
            "Deleted user \"{$userRow['full_name']}\" ({$userRow['email']})",
            $userRow, null
        );
    }

    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}