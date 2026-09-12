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
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Grab values before deletion, for the Activity Log (record is gone afterward)
    $stmt = $pdo->prepare("SELECT bill_no, sale_date, customer_id, branch_id, net_amount, status FROM sale_order WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);
    $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orderRow) {
        throw new Exception('Order not found or already deleted');
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