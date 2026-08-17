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
    $customer_id = $input['id'] ?? null;
    
    if (!$customer_id) {
        throw new Exception('Customer ID is required');
    }
    
    // Check if customer exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customer_id, $tenant_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        throw new Exception('Customer not found');
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
    
    echo json_encode([
        'success' => true,
        'message' => 'Customer deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>