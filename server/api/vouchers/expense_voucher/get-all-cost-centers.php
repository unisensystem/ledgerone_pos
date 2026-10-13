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

try {
    $stmt = $pdo->prepare("
        SELECT cc.id, cc.name, cc.account_id, a.name as account_name 
        FROM cost_centers cc
        INNER JOIN accounts a ON cc.account_id = a.id
        WHERE cc.tenant_id = ? AND cc.is_active = 1
        ORDER BY cc.name
    ");
    $stmt->execute([$tenant_id]);
    $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $costCenters]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch cost centers']);
}
