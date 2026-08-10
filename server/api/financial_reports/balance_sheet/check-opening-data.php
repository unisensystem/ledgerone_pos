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
    // Get customer opening totals
    $stmt = $pdo->prepare("
        SELECT 
            SUM(opening_debit_amount) as total_debit,
            SUM(opening_credit_amount) as total_credit,
            SUM(opening_debit_amount - opening_credit_amount) as net_balance
        FROM customers
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetch();
    
    // Get supplier opening totals
    $stmt = $pdo->prepare("
        SELECT 
            SUM(opening_debit_amount) as total_debit,
            SUM(opening_credit_amount) as total_credit,
            SUM(opening_credit_amount - opening_debit_amount) as net_balance
        FROM suppliers
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $suppliers = $stmt->fetch();
    
    // Sample customers
    $stmt = $pdo->prepare("SELECT id, customer_name, opening_debit_amount, opening_credit_amount FROM customers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0) LIMIT 5");
    $stmt->execute([$tenant_id]);
    $sample_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sample suppliers
    $stmt = $pdo->prepare("SELECT id, supplier_name, opening_debit_amount, opening_credit_amount FROM suppliers WHERE tenant_id = ? AND (opening_debit_amount != 0 OR opening_credit_amount != 0) LIMIT 5");
    $stmt->execute([$tenant_id]);
    $sample_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'customers' => [
            'totals' => $customers,
            'samples' => $sample_customers,
            'note' => 'opening_debit_amount = customer owes you, opening_credit_amount = you owe customer'
        ],
        'suppliers' => [
            'totals' => $suppliers,
            'samples' => $sample_suppliers,
            'note' => 'opening_credit_amount = you owe supplier, opening_debit_amount = supplier owes you'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
