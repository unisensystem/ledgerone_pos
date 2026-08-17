<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Find all transaction types for Trade Debtors with customers reference
    $stmt = $pdo->prepare("
        SELECT DISTINCT transaction_type, COUNT(*) as count
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 2 AND reference_table = 'customers'
        GROUP BY transaction_type
    ");
    $stmt->execute([$tenant_id]);
    $customer_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find all transaction types for Trade Creditors with suppliers reference
    $stmt = $pdo->prepare("
        SELECT DISTINCT transaction_type, COUNT(*) as count
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 14 AND reference_table = 'suppliers'
        GROUP BY transaction_type
    ");
    $stmt->execute([$tenant_id]);
    $supplier_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'customer_transaction_types' => $customer_types,
        'supplier_transaction_types' => $supplier_types
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
