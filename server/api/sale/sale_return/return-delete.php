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
    // Grab full row before deletion, for the Activity Log
    $stOld = $pdo->prepare("SELECT * FROM sale_return WHERE id = ? AND tenant_id = ?");
    $stOld->execute([$invoice_id, $tenant_id]);
    $returnRow = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$returnRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Return not found']);
        exit;
    }

    $pdo->beginTransaction();

    // Delete from accounting_ledger
    $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'sale_return' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from stock_ledger
    $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'sale_return' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from sale_return_items
    $pdo->prepare("DELETE FROM sale_return_items WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    // Delete from sale_return
    $stmt = $pdo->prepare("DELETE FROM sale_return WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Return not found or already deleted');
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Sale Return', $invoice_id, $returnRow['bill_no'],
        "Deleted sale return \"{$returnRow['bill_no']}\"",
        $returnRow, null
    );

    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Sale return deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}