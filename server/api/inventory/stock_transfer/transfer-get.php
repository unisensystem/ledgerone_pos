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

$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Transfer ID is required']);
    exit;
}

try {
    // Get transfer header with branch and company names
    $query = "SELECT 
                st.*,
                c.company_name,
                fb.branch_name as from_branch,
                tb.branch_name as to_branch
              FROM stock_transfer st
              LEFT JOIN companies c ON st.company_id = c.id
              LEFT JOIN branches fb ON st.from_location_id = fb.id
              LEFT JOIN branches tb ON st.to_location_id = tb.id
              WHERE st.tenant_id = ? AND st.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id, $transfer_id]);
    $transfer = $stmt->fetch();
    
    if (!$transfer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        exit;
    }
    
    // Get transfer items with product details
    $query = "SELECT sti.*, p.default_unit_id, p.name as product_name
              FROM stock_transfer_items sti
              LEFT JOIN products p ON sti.product_id = p.id
              WHERE sti.tenant_id = ? AND sti.transfer_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id, $transfer_id]);
    $items = $stmt->fetchAll();
    
    $transfer['items'] = $items;
    
    echo json_encode(['success' => true, 'transfer' => $transfer]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
