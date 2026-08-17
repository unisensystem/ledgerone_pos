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
    // Get all accounts with their balances
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
        GROUP BY a.id, a.name, sa.name, sa.account_head_id
        HAVING ABS(COALESCE(SUM(al.debit), 0) - COALESCE(SUM(al.credit), 0)) > 0.01
        ORDER BY sa.account_head_id, a.name
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_debits = 0;
    $total_credits = 0;
    $assets_total = 0;
    $liabilities_total = 0;
    $equity_total = 0;
    $income_total = 0;
    $expense_total = 0;
    
    foreach ($accounts as $acc) {
        $total_debits += $acc['total_debit'];
        $total_credits += $acc['total_credit'];
        
        $head_id = $acc['account_head_id'];
        $balance = $acc['balance'];
        
        if ($head_id == 1) $assets_total += $balance;
        if ($head_id == 2) $liabilities_total += $balance;
        if ($head_id == 3) $equity_total += $balance;
        if ($head_id == 4) $income_total += $balance;
        if ($head_id == 5) $expense_total += $balance;
    }
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_debits' => $total_debits,
            'total_credits' => $total_credits,
            'difference' => $total_debits - $total_credits,
            'debits_equal_credits' => abs($total_debits - $total_credits) < 0.01
        ],
        'by_account_head' => [
            'assets' => $assets_total,
            'liabilities' => $liabilities_total,
            'equity' => $equity_total,
            'income' => $income_total,
            'expenses' => $expense_total
        ],
        'accounting_equation' => [
            'assets' => $assets_total,
            'liabilities_plus_equity' => $liabilities_total + $equity_total,
            'difference' => $assets_total - ($liabilities_total + $equity_total),
            'balances' => abs($assets_total - ($liabilities_total + $equity_total)) < 0.01
        ],
        'accounts' => $accounts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
