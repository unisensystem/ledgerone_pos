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
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            reference_table,
            COUNT(*) as count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 19
        GROUP BY transaction_type, reference_table
        ORDER BY transaction_type
    ");
    $stmt->execute([$tenant_id]);
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'purchases_breakdown' => $breakdown
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
