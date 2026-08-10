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
    // Check if customers with opening_debit have sales (they should if they owe you)
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.customer_name,
            c.opening_debit_amount,
            c.opening_credit_amount,
            COUNT(si.id) as invoice_count,
            COALESCE(SUM(si.net_amount), 0) as total_sales,
            COUNT(rv.id) as payment_count,
            COALESCE(SUM(rv.amount), 0) as total_payments
        FROM customers c
        LEFT JOIN sale_invoice si ON c.id = si.customer_id AND si.tenant_id = ?
        LEFT JOIN receive_voucher rv ON c.id = rv.customer_id AND rv.tenant_id = ?
        WHERE c.tenant_id = ? AND c.opening_debit_amount > 0
        GROUP BY c.id, c.customer_name, c.opening_debit_amount, c.opening_credit_amount
        LIMIT 5
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id]);
    $customer_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check customer with highest opening_credit
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.customer_name,
            c.opening_debit_amount,
            c.opening_credit_amount,
            COUNT(si.id) as invoice_count,
            COALESCE(SUM(si.net_amount), 0) as total_sales
        FROM customers c
        LEFT JOIN sale_invoice si ON c.id = si.customer_id AND si.tenant_id = ?
        WHERE c.tenant_id = ? AND c.opening_credit_amount > 0
        GROUP BY c.id
        ORDER BY c.opening_credit_amount DESC
        LIMIT 3
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $high_credit_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'customer_debit_analysis' => $customer_analysis,
        'high_credit_customers' => $high_credit_customers,
        'interpretation' => 'If customers with opening_debit have sales/payments, the data is correct. If customers with opening_credit have more activity, data is backwards.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
