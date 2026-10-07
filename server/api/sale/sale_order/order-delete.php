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
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Grab values before deletion, for the Activity Log (record is gone afterward)
    $stmt = $pdo->prepare("SELECT bill_no, sale_date, customer_id, branch_id, net_amount, status, created_by FROM sale_order WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orderRow) {
        throw new Exception('Order not found or already deleted');
    }

    // A mobile bearer token may only delete its own orders — otherwise any
    // field booker's token could delete any order in the tenant by guessing
    // an id. A web session keeps the existing tenant-wide behavior.
    if ($auth['via'] === 'token' && (int) $orderRow['created_by'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own orders']);
        exit;
    }

    $pdo->beginTransaction();

    // Delete from sale_order_items
    $pdo->prepare("DELETE FROM sale_order_items WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);

    // Delete from sale_invoice
    $stmt = $pdo->prepare("DELETE FROM sale_order WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Order not found or already deleted');
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Sale Order', $invoice_id, $orderRow['bill_no'],
        "Deleted sale order \"{$orderRow['bill_no']}\"",
        $orderRow, null
    );

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Sale order deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}