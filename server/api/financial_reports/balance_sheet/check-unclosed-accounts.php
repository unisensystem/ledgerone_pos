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
    // Check for unclosed income/expense accounts
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.name,
            sa.name as sub_account,
            sa.account_head_id,
            COALESCE(SUM(al.debit), 0) as total_debit,
            COALESCE(SUM(al.credit), 0) as total_credit,
            COALESCE(SUM(al.debit), 0) - COALESCE(SUM(al.credit), 0) as balance
        FROM accounts a
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        LEFT JOIN accounting_ledger al ON a.id = al.account_id AND al.tenant_id = ?
        WHERE (a.tenant_id = ? OR a.tenant_id = 0)
        AND sa.account_head_id IN (4, 5, 6)
        GROUP BY a.id, a.name, sa.name, sa.account_head_id
        HAVING ABS(COALESCE(SUM(al.debit), 0) - COALESCE(SUM(al.credit), 0)) > 0.01
        ORDER BY sa.account_head_id, a.name
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $unclosed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_income = 0;
    $total_expenses = 0;
    $total_cogs = 0;
    
    foreach ($unclosed as $acc) {
        $balance = $acc['balance'];
        if ($acc['account_head_id'] == 4) $total_income += $balance;
        if ($acc['account_head_id'] == 5) $total_expenses += $balance;
        if ($acc['account_head_id'] == 6) $total_cogs += $balance;
    }
    
    echo json_encode([
        'success' => true,
        'unclosed_accounts' => $unclosed,
        'summary' => [
            'total_income' => $total_income,
            'total_expenses' => $total_expenses,
            'total_cogs' => $total_cogs,
            'net_income' => -$total_income + $total_cogs + $total_expenses
        ],
        'note' => 'These accounts should be closed to Retained Earnings. Click "Close Period" button.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
