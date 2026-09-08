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

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Support both a single id (backward compatible) and a bulk ids[] array
    $customer_ids = [];
    if (!empty($input['ids']) && is_array($input['ids'])) {
        $customer_ids = array_values(array_unique(array_map('intval', $input['ids'])));
    } elseif (!empty($input['id'])) {
        $customer_ids = [(int)$input['id']];
    }

    if (empty($customer_ids)) {
        throw new Exception('Customer ID is required');
    }

    $deletedNames = [];
    $notFoundIds = [];

    $pdo->beginTransaction();

    foreach ($customer_ids as $customer_id) {
        // Check if customer exists and belongs to tenant
        $stmt = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            $notFoundIds[] = $customer_id;
            continue;
        }

        // Delete related accounting ledger entries
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'customers' AND reference_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);

        // Delete related opening balance invoices
        $stmt = $pdo->prepare("DELETE FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);

        // Delete related sub accounts
        $stmt = $pdo->prepare("DELETE FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);

        // Hard delete customer
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);

        $deletedNames[] = $customer['customer_name'];
    }

    if (empty($deletedNames)) {
        $pdo->rollBack();
        throw new Exception('Customer not found');
    }

    $pdo->commit();

    $deletedCount = count($deletedNames);
    $message = $deletedCount === 1
        ? 'Customer deleted successfully'
        : $deletedCount . ' customers deleted successfully';

    if (!empty($notFoundIds)) {
        $message .= ' (' . count($notFoundIds) . ' not found, skipped)';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_count' => $deletedCount,
        'not_found_ids' => $notFoundIds
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>