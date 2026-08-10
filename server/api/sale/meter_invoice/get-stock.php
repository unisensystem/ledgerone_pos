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

$invoice_id = $_GET['invoice_id'] ?? null;

if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing invoice ID']);
    exit;
}

try {
    // Get invoice details
    $stmt = $pdo->prepare("SELECT branch_id, product_id, unit_id FROM station_daily_usage WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    // Get total stock
    $stockStmt = $pdo->prepare("SELECT SUM(qty_in - qty_out) as total_stock FROM stock_ledger WHERE tenant_id = ? AND branch_id = ? AND product_id = ? AND unit_id = ?");
    $stockStmt->execute([$tenant_id, $invoice['branch_id'], $invoice['product_id'], $invoice['unit_id']]);
    $stockResult = $stockStmt->fetch(PDO::FETCH_ASSOC);
    $totalStock = $stockResult['total_stock'] ?? 0;
    
    echo json_encode(['success' => true, 'total_stock' => $totalStock]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>