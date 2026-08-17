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
    // Preview what will be deleted and created
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'customer_opening'");
    $stmt->execute([$tenant_id]);
    $customer_to_delete = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'supplier_opening'");
    $stmt->execute([$tenant_id]);
    $supplier_to_delete = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as customers, SUM(opening_debit_amount) as total_debit, SUM(opening_credit_amount) as total_credit FROM customers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0)");
    $stmt->execute([$tenant_id]);
    $customer_preview = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as suppliers, SUM(opening_debit_amount) as total_debit, SUM(opening_credit_amount) as total_credit FROM suppliers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0)");
    $stmt->execute([$tenant_id]);
    $supplier_preview = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'preview' => [
            'will_delete' => [
                'customer_entries' => $customer_to_delete,
                'supplier_entries' => $supplier_to_delete,
                'total' => $customer_to_delete + $supplier_to_delete
            ],
            'will_create' => [
                'customers' => $customer_preview['customers'],
                'customer_entries' => $customer_preview['customers'] * 2,
                'suppliers' => $supplier_preview['suppliers'],
                'supplier_entries' => $supplier_preview['suppliers'] * 2,
                'total' => ($customer_preview['customers'] + $supplier_preview['suppliers']) * 2
            ],
            'expected_balance' => [
                'customer_debit' => $customer_preview['total_debit'],
                'customer_credit' => $customer_preview['total_credit'],
                'supplier_debit' => $supplier_preview['total_debit'],
                'supplier_credit' => $supplier_preview['total_credit'],
                'net_difference' => ($customer_preview['total_debit'] - $customer_preview['total_credit']) - ($supplier_preview['total_credit'] - $supplier_preview['total_debit'])
            ]
        ],
        'message' => 'This is a preview. Run fix-opening-duplicates.php to apply the fix.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
