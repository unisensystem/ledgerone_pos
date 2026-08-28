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
    $customer_id = $input['customer_id'] ?? null;

    if (!$customer_id) {
        throw new Exception('Customer ID is required');
    }

    $stmt = $pdo->prepare("SELECT id, linked_supplier_id FROM customers WHERE id = ? AND tenant_id = ? AND is_both = 1");
    $stmt->execute([$customer_id, $tenant_id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        throw new Exception('Customer + Supplier (Both) record not found');
    }

    $supplier_id = $customer['linked_supplier_id'];

    $pdo->beginTransaction();

    // Customer side cleanup (mirrors customers/customer-delete.php)
    $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'customers' AND reference_id = ?")
        ->execute([$tenant_id, $customer_id]);
    $pdo->prepare("DELETE FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ?")
        ->execute([$tenant_id, $customer_id]);
    $pdo->prepare("DELETE FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?")
        ->execute([$tenant_id, $customer_id]);
    $pdo->prepare("DELETE FROM customers WHERE id = ? AND tenant_id = ?")
        ->execute([$customer_id, $tenant_id]);

    // Supplier side cleanup (mirrors suppliers/supplier-delete.php)
    if ($supplier_id) {
        $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'suppliers' AND reference_id = ?")
            ->execute([$tenant_id, $supplier_id]);
        $pdo->prepare("DELETE FROM supplier_associated_companies WHERE tenant_id = ? AND supplier_id = ?")
            ->execute([$tenant_id, $supplier_id]);
        $pdo->prepare("DELETE FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ?")
            ->execute([$tenant_id, $supplier_id]);
        $pdo->prepare("DELETE FROM suppliers WHERE id = ? AND tenant_id = ?")
            ->execute([$supplier_id, $tenant_id]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Customer + Supplier deleted successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
