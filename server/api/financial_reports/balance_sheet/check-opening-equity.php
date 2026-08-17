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
    // Get all entries in Opening Balance Equity (account 90)
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            reference_table,
            COUNT(*) as count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 90
        GROUP BY transaction_type, reference_table
        ORDER BY transaction_type, reference_table
    ");
    $stmt->execute([$tenant_id]);
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total
    $stmt = $pdo->prepare("
        SELECT 
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 90
    ");
    $stmt->execute([$tenant_id]);
    $total = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'breakdown' => $breakdown,
        'total' => $total,
        'note' => 'Opening Balance Equity should only have customer/supplier opening entries and stock adjustment'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
