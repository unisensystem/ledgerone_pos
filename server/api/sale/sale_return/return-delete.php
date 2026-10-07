<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

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

    // A mobile bearer token may only delete its own returns — otherwise any
    // field booker's token could delete any return in the tenant by
    // guessing an id. A web session keeps the existing tenant-wide behavior.
    if ($auth['via'] === 'token' && (int) $returnRow['created_by'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own returns']);
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