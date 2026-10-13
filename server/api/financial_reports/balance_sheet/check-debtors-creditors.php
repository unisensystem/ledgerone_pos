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
    // Check Trade Debtors (account 2)
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 2
        GROUP BY transaction_type
        ORDER BY transaction_type
    ");
    $stmt->execute([$tenant_id]);
    $debtors_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check Trade Creditors (account 14)
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 14
        GROUP BY transaction_type
        ORDER BY transaction_type
    ");
    $stmt->execute([$tenant_id]);
    $creditors_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total for Trade Debtors
    $stmt = $pdo->prepare("
        SELECT 
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 2
    ");
    $stmt->execute([$tenant_id]);
    $debtors_total = $stmt->fetch();
    
    // Total for Trade Creditors
    $stmt = $pdo->prepare("
        SELECT 
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 14
    ");
    $stmt->execute([$tenant_id]);
    $creditors_total = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'trade_debtors' => [
            'breakdown' => $debtors_breakdown,
            'total' => $debtors_total,
            'note' => 'Should have DEBIT balance (customers owe you)'
        ],
        'trade_creditors' => [
            'breakdown' => $creditors_breakdown,
            'total' => $creditors_total,
            'note' => 'Should have CREDIT balance (you owe suppliers)'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
