<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? null;

try {
    // Get all account heads
    $stmt = $pdo->query("SELECT id, name FROM accounts_head ORDER BY id");
    $account_heads = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get all sub_accounts with recursive hierarchy
    $stmt = $pdo->query("
        WITH RECURSIVE sub_hierarchy AS (
            SELECT id, name, account_head_id, parent_id, account_head_id as resolved_head_id
            FROM sub_accounts
            WHERE account_head_id IS NOT NULL
            
            UNION ALL
            
            SELECT s.id, s.name, s.account_head_id, s.parent_id, sh.resolved_head_id
            FROM sub_accounts s
            INNER JOIN sub_hierarchy sh ON s.parent_id = sh.id
            WHERE s.account_head_id IS NULL
        )
        SELECT id, name, account_head_id, parent_id, resolved_head_id FROM sub_hierarchy
    ");
    $sub_accounts_all = $stmt->fetchAll();
    
    // Build sub_account map
    $sub_accounts_map = [];
    foreach ($sub_accounts_all as $sa) {
        $sub_accounts_map[$sa['id']] = $sa;
    }

    // Get soft opening balances if start_date is provided
    $soft_opening = [];
    if ($start_date) {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                COALESCE(SUM(al.debit), 0) - COALESCE(SUM(al.credit), 0) as opening_balance
            FROM accounts a
            LEFT JOIN accounting_ledger al ON a.id = al.account_id AND al.date < ? AND al.tenant_id = ?
            WHERE (a.tenant_id = ? OR a.tenant_id = 0) AND a.sub_account_id != 1
            GROUP BY a.id
        ");
        $stmt->execute([$start_date, $tenant_id, $tenant_id]);
        $soft_opening = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Get account balances from ledger
    $date_condition = $start_date ? "al.date >= ? AND al.date <= ?" : "al.date <= ?";
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.name,
            a.sub_account_id,
            sa.name as sub_account_name,
            COALESCE(SUM(al.debit), 0) as total_debit,
            COALESCE(SUM(al.credit), 0) as total_credit
        FROM accounts a
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        LEFT JOIN accounting_ledger al ON a.id = al.account_id AND {$date_condition} AND al.tenant_id = ?
        WHERE (a.tenant_id = ? OR a.tenant_id = 0) AND a.sub_account_id != 1
        GROUP BY a.id, a.name, a.sub_account_id, sa.name
        ORDER BY a.name
    ");
    if ($start_date) {
        $stmt->execute([$start_date, $end_date, $tenant_id, $tenant_id]);
    } else {
        $stmt->execute([$end_date, $tenant_id, $tenant_id]);
    }
    $accounts = $stmt->fetchAll();

    // Build balance sheet structure: head_id -> sub_account_id -> accounts
    $balance_sheet = [];
    
    // Track totals for validation
    $total_assets_balance = 0;
    $total_liabilities_balance = 0;
    $total_equity_balance = 0;

    foreach ($accounts as $account) {
        $period_balance = $account['total_debit'] - $account['total_credit'];
        $opening_balance = $soft_opening[$account['id']] ?? 0;
        $balance = $opening_balance + $period_balance;
        
        // Get account_head_id from resolved hierarchy
        $head_id = isset($sub_accounts_map[$account['sub_account_id']]) 
            ? (int)$sub_accounts_map[$account['sub_account_id']]['resolved_head_id']
            : 0;
        
        // Fallback: determine head_id by account type if not resolved
        if ($head_id === 0) {
            if ($account['id'] == 2) $head_id = 1; // Trade Debtors = Asset
            elseif ($account['id'] == 14) $head_id = 2; // Trade Creditors = Liability
            elseif ($account['id'] == 33) $head_id = 1; // Inventory = Asset
            elseif ($account['id'] == 120) $head_id = 1; // Bank = Asset
        }
        
        // Reclassify based on actual balance sign
        // If Trade Debtors has credit balance (negative), it's a liability (customer advances)
        if ($account['id'] == 2 && $balance < 0) {
            $head_id = 2; // Move to Liabilities
        }
        // If Trade Creditors has debit balance (positive), it's an asset (supplier advances)
        if ($account['id'] == 14 && $balance > 0) {
            $head_id = 1; // Move to Assets
        }
        
        $item = [
            'id' => $account['id'],
            'name' => $account['name'],
            'sub_account' => $account['sub_account_name'],
            'balance' => $balance
        ];
        
        // Only include Assets (1), Liabilities (2), and Equity (3)
        if (!in_array($head_id, [1, 2, 3])) {
            continue;
        }
        
        // Track raw balances for validation
        if ($head_id === 1) {
            $total_assets_balance += $balance;
        } elseif ($head_id === 2) {
            $total_liabilities_balance += $balance;
        } elseif ($head_id === 3) {
            $total_equity_balance += $balance;
        }
        
        // Skip accounts with zero balance
        if (abs($balance) < 0.01) {
            continue;
        }
        
        // For balance sheet display: show absolute values
        // Assets normally have debit balance (positive)
        // Liabilities normally have credit balance (negative, so use abs)
        // Equity normally has credit balance (negative, so use abs)
        
        if ($head_id === 1) {
            // Assets: debit-normal, show as positive
            $item['balance'] = abs($balance);
        } elseif ($head_id === 2) {
            // Liabilities: credit-normal, display magnitude positively
            $item['balance'] = abs($balance);
        } else {
            // Equity: credit-normal = positive (capital/retained earnings).
            // Debit balance means accumulated losses — keep negative so it displays correctly.
            $item['balance'] = $balance < 0 ? abs($balance) : -$balance;
        }
        
        $sub_account_id = $account['sub_account_id'];
        
        if (!isset($balance_sheet[$head_id])) {
            $balance_sheet[$head_id] = [];
        }
        if (!isset($balance_sheet[$head_id][$sub_account_id])) {
            $balance_sheet[$head_id][$sub_account_id] = [
                'name' => $account['sub_account_name'],
                'accounts' => []
            ];
        }
        
        $balance_sheet[$head_id][$sub_account_id]['accounts'][] = $item;
    }

    // Calculate opening balance totals for debugging
    $opening_debits = 0;
    $opening_credits = 0;
    
    echo json_encode([
        'success' => true,
        'data' => $balance_sheet,
        'end_date' => $end_date,
        'debug' => [
            'account_heads' => $account_heads,
            'sample_accounts' => array_slice($accounts, 0, 5),
            'account_1_cash' => array_values(array_filter($accounts, function($a) { return $a['id'] == 1; }))[0] ?? null,
            'account_14_creditors' => array_values(array_filter($accounts, function($a) { return $a['id'] == 14; }))[0] ?? null,
            'account_28_salaries' => array_values(array_filter($accounts, function($a) { return $a['id'] == 28; }))[0] ?? null,
            'ledger_summary' => [
                'total_debits' => array_sum(array_column($accounts, 'total_debit')),
                'total_credits' => array_sum(array_column($accounts, 'total_credit'))
            ],
            'opening_balance_summary' => [
                'opening_debits' => $opening_debits,
                'opening_credits' => $opening_credits,
                'difference' => $opening_debits - $opening_credits
            ],
            'balance_validation' => [
                'assets_raw' => $total_assets_balance,
                'liabilities_raw' => $total_liabilities_balance,
                'equity_raw' => $total_equity_balance,
                'equation_check' => $total_assets_balance - ($total_liabilities_balance + $total_equity_balance),
                'note' => 'Raw balances before sign flip. Liabilities and Equity should be negative. Equation check should be 0.'
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
