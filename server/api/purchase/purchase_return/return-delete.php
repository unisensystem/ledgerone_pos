<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Return ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Delete from accounting_ledger
    $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'purchase_return' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from stock_ledger
    $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'purchase_return' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from purchase_return_items
    $pdo->prepare("DELETE FROM purchase_return_items WHERE purchase_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from purchase_return
    $stmt = $pdo->prepare("DELETE FROM purchase_return WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Return not found or already deleted');
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Purchase return deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}