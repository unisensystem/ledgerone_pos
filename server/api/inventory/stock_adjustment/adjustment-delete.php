<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $data = json_decode(file_get_contents('php://input'), true);
    $adjustment_id = $data['id'];
    
    $pdo->beginTransaction();
    
    // Delete from accounting_ledger
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger 
        WHERE tenant_id = ? AND reference_table = 'stock_adjustment' AND reference_id = ?
    ");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    // Delete from stock_ledger
    $stmt = $pdo->prepare("
        DELETE FROM stock_ledger 
        WHERE tenant_id = ? AND reference_table = 'stock_adjustment' AND reference_id = ?
    ");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    // Delete from stock_adjustment_items
    $stmt = $pdo->prepare("
        DELETE FROM stock_adjustment_items 
        WHERE tenant_id = ? AND adjustment_id = ?
    ");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    // Delete from stock_adjustment
    $stmt = $pdo->prepare("
        DELETE FROM stock_adjustment 
        WHERE tenant_id = ? AND id = ?
    ");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Adjustment deleted successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting adjustment: ' . $e->getMessage()
    ]);
}