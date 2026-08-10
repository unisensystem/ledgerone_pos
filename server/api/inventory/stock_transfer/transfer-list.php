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
    $query = "SELECT 
                st.id,
                st.transfer_code,
                st.date,
                st.company_id,
                st.total_amount,
                st.total_items,
                st.remarks,
                c.company_name,
                fb.branch_name as from_branch,
                tb.branch_name as to_branch
              FROM stock_transfer st
              LEFT JOIN companies c ON st.company_id = c.id
              LEFT JOIN branches fb ON st.from_location_id = fb.id
              LEFT JOIN branches tb ON st.to_location_id = tb.id
              WHERE st.tenant_id = :tenant_id
              ORDER BY st.date DESC, st.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['tenant_id' => $tenant_id]);
    $transfers = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'transfers' => $transfers]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
