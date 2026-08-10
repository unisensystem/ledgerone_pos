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
    echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get bill_no before deletion
    $billStmt = $pdo->prepare("SELECT bill_no FROM sale_invoice WHERE id = ? AND tenant_id = ?");
    $billStmt->execute([$invoice_id, $tenant_id]);
    $billNo = $billStmt->fetchColumn();
    
    // Get receive_voucher id if exists
    if ($billNo) {
        $rvStmt = $pdo->prepare("SELECT id FROM receive_voucher WHERE bill_no = ? AND tenant_id = ?");
        $rvStmt->execute([$billNo, $tenant_id]);
        $rvId = $rvStmt->fetchColumn();
        
        // Delete receive_voucher accounting entries
        if ($rvId) {
            $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'receive_voucher' AND reference_id = ? AND tenant_id = ?")->execute([$rvId, $tenant_id]);
            $pdo->prepare("DELETE FROM receive_voucher WHERE id = ? AND tenant_id = ?")->execute([$rvId, $tenant_id]);
        }
    }
    
    // Delete from accounting_ledger
    $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'sale_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from stock_ledger
    $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'sale_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from sale_invoice_items
    $pdo->prepare("DELETE FROM sale_invoice_items WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from sale_invoice
    $stmt = $pdo->prepare("DELETE FROM sale_invoice WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Invoice not found or already deleted');
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Sale invoice deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}