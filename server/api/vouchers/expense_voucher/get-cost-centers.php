<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || $tenant_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$account_id = $_GET['account_id'] ?? null;

if (!$account_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Account ID is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM cost_centers WHERE tenant_id = ? AND account_id = ? AND is_active = 1");
    $stmt->execute([$tenant_id, $account_id]);
    $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $costCenters]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch cost centers']);
}
