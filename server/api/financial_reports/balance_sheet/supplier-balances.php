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
    // Get each supplier with their transactions
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.supplier_name,
            s.opening_debit_amount,
            s.opening_credit_amount,
            COALESCE(SUM(CASE WHEN pi.id IS NOT NULL THEN pi.net_amount ELSE 0 END), 0) as total_purchases,
            COALESCE(SUM(CASE WHEN pv.id IS NOT NULL THEN pv.amount ELSE 0 END), 0) as total_payments,
            s.opening_credit_amount - s.opening_debit_amount + 
            COALESCE(SUM(CASE WHEN pi.id IS NOT NULL THEN pi.net_amount ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN pv.id IS NOT NULL THEN pv.amount ELSE 0 END), 0) as calculated_balance
        FROM suppliers s
        LEFT JOIN purchase_invoice pi ON s.id = pi.supplier_id AND pi.tenant_id = ?
        LEFT JOIN payment_voucher pv ON s.id = pv.supplier_id AND pv.tenant_id = ?
        WHERE s.tenant_id = ?
        GROUP BY s.id, s.supplier_name, s.opening_debit_amount, s.opening_credit_amount
        ORDER BY calculated_balance DESC
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers,
        'note' => 'Calculated balance = Opening Credit - Opening Debit + Purchases - Payments. Positive = you owe them, Negative = they owe you (advance)'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
