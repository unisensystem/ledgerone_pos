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
    // 1. Check if debits = credits in accounting_ledger
    $stmt = $pdo->prepare("
        SELECT 
            SUM(debit) as total_debits,
            SUM(credit) as total_credits,
            SUM(debit) - SUM(credit) as difference
        FROM accounting_ledger
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $ledger_totals = $stmt->fetch();
    
    // 2. Break down by transaction_type
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(debit) as total_debits,
            SUM(credit) as total_credits,
            SUM(debit) - SUM(credit) as difference
        FROM accounting_ledger
        WHERE tenant_id = ?
        GROUP BY transaction_type
        HAVING ABS(SUM(debit) - SUM(credit)) > 0.01
        ORDER BY ABS(SUM(debit) - SUM(credit)) DESC
    ");
    $stmt->execute([$tenant_id]);
    $imbalanced_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Find orphaned entries (single-sided entries)
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            reference_table,
            reference_id,
            COUNT(*) as entry_count,
            SUM(debit) as total_debits,
            SUM(credit) as total_credits
        FROM accounting_ledger
        WHERE tenant_id = ?
        GROUP BY transaction_type, reference_table, reference_id
        HAVING ABS(SUM(debit) - SUM(credit)) > 0.01
        ORDER BY ABS(SUM(debit) - SUM(credit)) DESC
        LIMIT 20
    ");
    $stmt->execute([$tenant_id]);
    $orphaned_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Check opening balance entries specifically
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(debit) as debits,
            SUM(credit) as credits,
            SUM(debit) - SUM(credit) as diff
        FROM accounting_ledger
        WHERE tenant_id = ? 
        AND transaction_type IN ('customer_opening', 'supplier_opening', 'Opening Balance', 'Stock Adjustment')
        GROUP BY transaction_type
    ");
    $stmt->execute([$tenant_id]);
    $opening_balance_check = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'ledger_totals' => $ledger_totals,
        'is_balanced' => abs($ledger_totals['difference']) < 0.01,
        'imbalanced_transaction_types' => $imbalanced_types,
        'orphaned_entries' => $orphaned_entries,
        'opening_balance_check' => $opening_balance_check,
        'recommendation' => abs($ledger_totals['difference']) > 0.01 
            ? 'Ledger is imbalanced. Check imbalanced_transaction_types and orphaned_entries for issues.'
            : 'Ledger debits=credits, but balance sheet may have sign issues.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
