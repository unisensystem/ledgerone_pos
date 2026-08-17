<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, branch_name FROM branches WHERE tenant_id = ? AND parent_branch_id IS NULL AND is_active = 1 ORDER BY branch_name");
    $stmt->execute([$tenant_id]);
    $branches = [];
    
    while ($row = $stmt->fetch()) {
        $branches[] = [
            'branch_id' => $row['id'],
            'branch_name' => $row['branch_name']
        ];
    }
    
    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch branches', 'message' => $e->getMessage()]);
}
