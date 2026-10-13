<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    // Grab values before deletion, for the Activity Log (record is gone afterward). This
    // endpoint is shared by both the Purchase Invoice and Purchase Tax Invoice pages (same
    // underlying purchase_invoice table), so the module label is generic to both.
    $stmt = $pdo->prepare("SELECT bill_no, purchase_date, supplier_id, branch_id, net_amount FROM purchase_invoice WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    $invoiceRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoiceRow) {
        throw new Exception('Invoice not found or already deleted');
    }

    $pdo->beginTransaction();

    // Delete from accounting_ledger
    $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from stock_ledger
    $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from purchase_invoice_items
    $pdo->prepare("DELETE FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from purchase_invoice
    $stmt = $pdo->prepare("DELETE FROM purchase_invoice WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Invoice not found or already deleted');
    }
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Purchase Invoice', $invoice_id, $invoiceRow['bill_no'],
        "Deleted purchase invoice \"{$invoiceRow['bill_no']}\"",
        $invoiceRow, null
    );

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Purchase invoice deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}