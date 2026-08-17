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
    $pdo->beginTransaction();
    
    // Get all account balances
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.name,
            sa.account_head_id,
            COALESCE(SUM(al.debit), 0) - COALESCE(SUM(al.credit), 0) as balance
        FROM accounts a
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        LEFT JOIN accounting_ledger al ON a.id = al.account_id AND al.tenant_id = ?
        WHERE (a.tenant_id = ? OR a.tenant_id = 0)
        GROUP BY a.id, a.name, sa.account_head_id
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $assets = 0;
    $liabilities = 0;
    $equity = 0;
    
    foreach ($accounts as $acc) {
        $balance = $acc['balance'];
        if ($acc['account_head_id'] == 1) $assets += $balance;
        if ($acc['account_head_id'] == 2) $liabilities += $balance;
        if ($acc['account_head_id'] == 3) $equity += $balance;
    }
    
    // Calculate imbalance: Assets should equal Liabilities + Equity
    $imbalance = $assets - ($liabilities + $equity);
    
    if (abs($imbalance) < 0.01) {
        echo json_encode([
            'success' => false,
            'message' => 'Balance sheet is already balanced. No adjustment needed.',
            'data' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
                'imbalance' => $imbalance
            ]
        ]);
        $pdo->rollBack();
        exit;
    }
    
    // Delete previous balance sheet adjustment entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Balance Sheet Adjustment'");
    $stmt->execute([$tenant_id]);
    
    // Post correcting entry to Opening Balance Equity (account 90)
    if ($imbalance > 0) {
        // Assets > Liabilities + Equity, so CREDIT Opening Balance Equity
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger 
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
            VALUES (?, 'Balance Sheet Adjustment', 'balance_sheet', 0, 90, CURDATE(), 'Adjustment to balance sheet', 0, ?)
        ");
        $stmt->execute([$tenant_id, abs($imbalance)]);
    } else {
        // Assets < Liabilities + Equity, so DEBIT Opening Balance Equity
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger 
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
            VALUES (?, 'Balance Sheet Adjustment', 'balance_sheet', 0, 90, CURDATE(), 'Adjustment to balance sheet', ?, 0)
        ");
        $stmt->execute([$tenant_id, abs($imbalance)]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Balance sheet adjustment posted successfully',
        'data' => [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity_before' => $equity,
            'imbalance' => $imbalance,
            'adjustment_amount' => abs($imbalance),
            'adjustment_type' => $imbalance > 0 ? 'Credit to Opening Balance Equity' : 'Debit to Opening Balance Equity',
            'equity_after' => $equity + $imbalance
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
