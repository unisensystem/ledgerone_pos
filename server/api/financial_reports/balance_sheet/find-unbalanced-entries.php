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
    // Find transactions where debits != credits
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            reference_table,
            reference_id,
            COUNT(*) as entry_count,
            SUM(debit) as total_debit,
            SUM(credit) as total_credit,
            SUM(debit) - SUM(credit) as difference
        FROM accounting_ledger
        WHERE tenant_id = ?
        GROUP BY transaction_type, reference_table, reference_id
        HAVING ABS(SUM(debit) - SUM(credit)) > 0.01
        ORDER BY ABS(SUM(debit) - SUM(credit)) DESC
    ");
    $stmt->execute([$tenant_id]);
    $unbalanced = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unbalanced_transactions' => $unbalanced,
        'total_unbalanced' => count($unbalanced),
        'total_difference' => array_sum(array_column($unbalanced, 'difference'))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
