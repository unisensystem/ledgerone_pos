<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$end_date = $input['end_date'] ?? date('Y-m-d');

try {
    $pdo->beginTransaction();

    // Calculate net income from Income (4), COGS (6), and Expense (5) accounts
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            COALESCE(SUM(al.debit), 0) - COALESCE(SUM(al.credit), 0) as balance,
            sa.account_head_id,
            sa.parent_id
        FROM accounts a
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        LEFT JOIN accounting_ledger al ON a.id = al.account_id AND al.date <= ? AND al.tenant_id = ?
        WHERE (a.tenant_id = ? OR a.tenant_id = 0)
        GROUP BY a.id, sa.account_head_id, sa.parent_id
    ");
    $stmt->execute([$end_date, $tenant_id, $tenant_id]);
    $accounts_summary = $stmt->fetchAll();
    
    // Get sub_accounts hierarchy
    $stmt = $pdo->query("SELECT id, account_head_id, parent_id FROM sub_accounts");
    $sub_accounts_map = [];
    foreach ($stmt->fetchAll() as $sa) {
        $sub_accounts_map[$sa['id']] = $sa;
    }
    
    function findAccountHeadId($sub_account_id, $sub_accounts_map) {
        if (!isset($sub_accounts_map[$sub_account_id])) return null;
        $sa = $sub_accounts_map[$sub_account_id];
        if ($sa['account_head_id']) return $sa['account_head_id'];
        if ($sa['parent_id']) return findAccountHeadId($sa['parent_id'], $sub_accounts_map);
        return null;
    }
    
    $total_income = 0;
    $total_expenses = 0;
    $total_cogs = 0;
    $income_accounts = [];
    $expense_accounts = [];
    $cogs_accounts = [];
    
    foreach ($accounts_summary as $acc) {
        $head_id = $acc['account_head_id'];
        if (!$head_id && $acc['parent_id']) {
            $head_id = findAccountHeadId($acc['parent_id'], $sub_accounts_map);
        }
        $balance = $acc['balance'];
        
        // Income has credit balance (negative), Expenses/COGS have debit balance (positive)
        if ($head_id == 4 && abs($balance) > 0.01) {
            $total_income += abs($balance);
            $income_accounts[] = ['id' => $acc['id'], 'balance' => $balance, 'head_id' => $head_id];
        }
        if ($head_id == 5 && $acc['id'] != 19 && abs($balance) > 0.01) {
            // Expenses excluding Purchases
            $total_expenses += abs($balance);
            $expense_accounts[] = ['id' => $acc['id'], 'balance' => $balance, 'head_id' => $head_id];
        }
        if ($head_id == 6 && abs($balance) > 0.01) {
            $total_cogs += abs($balance);
            $cogs_accounts[] = ['id' => $acc['id'], 'balance' => $balance, 'head_id' => $head_id];
        }
    }
    
    // Calculate COGS from sold quantities (same as P&L)
    $stmt = $pdo->prepare("
        SELECT p.id, SUM(sdu.total_dispensed) as qty_sold
        FROM station_daily_usage sdu
        JOIN products p ON sdu.product_id = p.id
        WHERE sdu.tenant_id = ? AND sdu.usage_date <= ?
        GROUP BY p.id
    ");
    $stmt->execute([$tenant_id, $end_date]);
    $sold_items = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT p.id, SUM(sii.quantity) as qty_sold
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        WHERE si.tenant_id = ? AND si.sale_date <= ? AND p.product_type = 'physical' AND p.subcategory_id NOT IN (12, 13, 14)
        GROUP BY p.id
    ");
    $stmt->execute([$tenant_id, $end_date]);
    $other_sold = $stmt->fetchAll();
    $sold_items = array_merge($sold_items, $other_sold);
    
    $calculated_cogs = 0;
    foreach ($sold_items as $item) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(
                (COALESCE(SUM(pii.net_amount), 0) + COALESCE((SELECT SUM(opening_qty * opening_price) FROM stock_opening WHERE product_id = ? AND tenant_id = ?), 0))
                / NULLIF(COALESCE(SUM(pii.quantity), 0) + COALESCE((SELECT SUM(opening_qty) FROM stock_opening WHERE product_id = ? AND tenant_id = ?), 0), 0)
            , 0) as avg_cost
            FROM purchase_invoice pi
            JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
            WHERE pii.product_id = ? AND pi.tenant_id = ? AND pi.purchase_date <= ?
        ");
        $stmt->execute([$item['id'], $tenant_id, $item['id'], $tenant_id, $item['id'], $tenant_id, $end_date]);
        $avg_cost = $stmt->fetch()['avg_cost'];
        $calculated_cogs += $avg_cost * $item['qty_sold'];
    }
    
    $total_cogs = $calculated_cogs;
    
    $net_income = $total_income - $total_cogs - $total_expenses;


    // Check if period is already closed
    if (abs($net_income) < 0.01) {
        echo json_encode([
            'success' => false,
            'message' => 'Period already closed. No income or expense accounts have balances.'
        ]);
        $pdo->rollBack();
        exit;
    }

    // Delete previous closing entries
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger 
        WHERE tenant_id = ? AND transaction_type = 'Period Close'
    ");
    $stmt->execute([$tenant_id]);

    if (abs($net_income) > 0.01) {
        // Close Income accounts to Retained Earnings
        foreach ($accounts_summary as $account) {
            $head_id = $account['account_head_id'] ?: findAccountHeadId($account['parent_id'], $sub_accounts_map);
            $balance = $account['balance'];
            
            if ($head_id === 4 && abs($balance) > 0.01) {
                $stmt = $pdo->prepare("
                    INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'Period Close', 'period_close', 0, ?, ?, 'Close income to retained earnings', ?, 0)
                ");
                $stmt->execute([$tenant_id, $account['id'], $end_date, abs($balance)]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'Period Close', 'period_close', 0, 93, ?, 'Close income to retained earnings', 0, ?)
                ");
                $stmt->execute([$tenant_id, $end_date, abs($balance)]);
            }
            
            if ($head_id === 5 && $account['id'] != 19 && abs($balance) > 0.01) {
                $stmt = $pdo->prepare("
                    INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'Period Close', 'period_close', 0, ?, ?, 'Close expense to retained earnings', 0, ?)
                ");
                $stmt->execute([$tenant_id, $account['id'], $end_date, abs($balance)]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'Period Close', 'period_close', 0, 93, ?, 'Close expense to retained earnings', ?, 0)
                ");
                $stmt->execute([$tenant_id, $end_date, abs($balance)]);
            }
        }
        
        // Close COGS (calculated amount only - the sold portion)
        if (abs($total_cogs) > 0.01) {
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'Period Close', 'period_close', 0, 19, ?, 'Close COGS to retained earnings', 0, ?)
            ");
            $stmt->execute([$tenant_id, $end_date, $total_cogs]);
            
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'Period Close', 'period_close', 0, 93, ?, 'Close COGS to retained earnings', ?, 0)
            ");
            $stmt->execute([$tenant_id, $end_date, $total_cogs]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Period closed successfully',
        'data' => [
            'total_income' => $total_income,
            'total_cogs' => $total_cogs,
            'total_expenses' => $total_expenses,
            'net_income' => $net_income,
            'income_accounts' => $income_accounts,
            'cogs_accounts' => $cogs_accounts,
            'expense_accounts' => $expense_accounts
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}