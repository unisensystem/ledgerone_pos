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
        SELECT 
            cc.name as cost_center_name,
            SUM(evl.amount) as total_amount
        FROM expense_voucher_line evl
        INNER JOIN cost_centers cc ON evl.cost_center_id = cc.id
        WHERE evl.tenant_id = ? AND evl.cost_center_id IS NOT NULL
        GROUP BY cc.id, cc.name
        ORDER BY total_amount DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant_id]);
    $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $costCenters]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch cost center expenses']);
}
