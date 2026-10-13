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
            ig.id,
            ig.gatepass_code,
            ig.date,
            ig.company_id,
            s.customer_name,
            b.branch_name,
            COUNT(igi.id) as item_count,
            SUM(igi.quantity) as total_qty
        FROM outward_gatepass ig
        LEFT JOIN customers s ON ig.supplier_id = s.id
        LEFT JOIN branches b ON ig.branch_id = b.id
        LEFT JOIN outward_gatepass_items igi ON ig.id = igi.gatepass_id
        WHERE ig.tenant_id = ?
        GROUP BY ig.id
        ORDER BY ig.date DESC, ig.id DESC
    ");
    $stmt->execute([$tenant_id]);
    $gatepasses = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $gatepasses]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}