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

try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.id,
            sa.adjustment_code,
            sa.date,
            b.branch_name,
            sa.adjustment_type,
            sa.main_reason,
            sa.primary_reason,
            sa.secondary_reason,
            sa.remarks,
            sa.total_amount,
            sa.total_items,
            sa.company_id,
            c.company_name
        FROM stock_adjustment sa
        JOIN branches b ON sa.branch_id = b.id
        LEFT JOIN companies c ON sa.company_id = c.id
        WHERE sa.tenant_id = ?
        ORDER BY sa.date DESC, sa.id DESC
    ");
    $stmt->execute([$tenant_id]);
    $adjustments = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'adjustments' => $adjustments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching adjustments: ' . $e->getMessage()
    ]);
}