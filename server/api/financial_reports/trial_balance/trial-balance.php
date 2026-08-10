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

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as account_id,
            a.name as account_name,
            a.sub_account_id,
            sa.id as sub_id,
            sa.name as sub_name,
            sa.parent_id,
            COALESCE(sa.account_head_id, sa2.account_head_id, sa3.account_head_id) as head_id,
            ah.name as head_name,
            COALESCE(SUM(l.debit), 0) as total_debit,
            COALESCE(SUM(l.credit), 0) as total_credit
        FROM accounting_ledger l
        JOIN accounts a ON l.account_id = a.id AND (a.tenant_id = ? OR a.tenant_id = 0)
        LEFT JOIN sub_accounts sa ON a.sub_account_id = sa.id AND sa.id != 1 AND (sa.tenant_id = ? OR sa.tenant_id = 0)
        LEFT JOIN sub_accounts sa2 ON sa.parent_id = sa2.id AND sa2.id != 1 AND (sa2.tenant_id = ? OR sa2.tenant_id = 0)
        LEFT JOIN sub_accounts sa3 ON sa2.parent_id = sa3.id AND sa3.id != 1 AND (sa3.tenant_id = ? OR sa3.tenant_id = 0)
        LEFT JOIN accounts_head ah ON COALESCE(sa.account_head_id, sa2.account_head_id, sa3.account_head_id) = ah.id AND (ah.tenant_id = ? OR ah.tenant_id = 0)
        WHERE l.tenant_id = ? AND (a.sub_account_id IS NULL OR a.sub_account_id != 1)
        GROUP BY a.id, a.name, a.sub_account_id, sa.id, sa.name, sa.parent_id, sa.account_head_id, sa2.account_head_id, sa3.account_head_id, ah.name
        ORDER BY ah.id, sa.id, a.id
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $headMap = [];
    $subMap = [];
    $accountMap = [];

    foreach ($rows as $row) {
        $headId = $row['head_id'];
        
        if (!isset($headMap[$headId])) {
            $headMap[$headId] = [
                'code' => str_pad($headId, 4, '0', STR_PAD_LEFT),
                'name' => $row['head_name'] ?: 'Uncategorized',
                'level' => 0,
                'debit' => 0,
                'credit' => 0,
                'isParent' => true,
                'children' => []
            ];
        }

        $subId = $row['sub_id'] ?: 0;
        
        if ($subId) {
            
            if (!isset($subMap[$subId])) {
                $level = $row['parent_id'] ? 2 : 1;
                $subMap[$subId] = [
                    'code' => str_pad($subId, 4, '0', STR_PAD_LEFT),
                    'name' => $row['sub_name'] ?: 'Uncategorized',
                    'level' => $level,
                    'debit' => 0,
                    'credit' => 0,
                    'isParent' => true,
                    'head_id' => $headId,
                    'parent_id' => $row['parent_id'],
                    'children' => []
                ];
            }

            if ($row['account_id']) {
                $accountId = $row['account_id'];
                $debit = (float)$row['total_debit'];
                $credit = (float)$row['total_credit'];
                
                if (!isset($accountMap[$accountId])) {
                    $accountMap[$accountId] = [
                        'code' => str_pad($row['account_id'], 4, '0', STR_PAD_LEFT),
                        'name' => $row['account_name'],
                        'level' => $subMap[$subId]['level'] + 1,
                        'debit' => $debit,
                        'credit' => $credit,
                        'isParent' => false,
                        'sub_id' => $subId
                    ];

                    $subMap[$subId]['debit'] += $debit;
                    $subMap[$subId]['credit'] += $credit;
                    $headMap[$headId]['debit'] += $debit;
                    $headMap[$headId]['credit'] += $credit;
                }
            }
        } else {
            // Account without sub_account - add directly under head
            if ($row['account_id']) {
                $accountId = $row['account_id'];
                $debit = (float)$row['total_debit'];
                $credit = (float)$row['total_credit'];
                
                if (!isset($accountMap[$accountId])) {
                    $accountMap[$accountId] = [
                        'code' => str_pad($row['account_id'], 4, '0', STR_PAD_LEFT),
                        'name' => $row['account_name'],
                        'level' => 1,
                        'debit' => $debit,
                        'credit' => $credit,
                        'isParent' => false,
                        'head_id' => $headId
                    ];

                    $headMap[$headId]['debit'] += $debit;
                    $headMap[$headId]['credit'] += $credit;
                }
            }
        }
    }

    $final = [];
    $added = [];
    
    foreach ($headMap as $headId => $head) {
        $final[] = $head;
        $added['h_' . $headId] = true;
        
        // Add accounts directly under head (no sub_account)
        foreach ($accountMap as $accountId => $account) {
            if (array_key_exists('head_id', $account) && $account['head_id'] === $headId && !isset($added['a_' . $accountId])) {
                $final[] = $account;
                $added['a_' . $accountId] = true;
            }
        }
        
        // Add sub_accounts and their accounts
        foreach ($subMap as $subId => $sub) {
            if ($sub['head_id'] === $headId && !$sub['parent_id'] && !isset($added['s_' . $subId])) {
                $final[] = $sub;
                $added['s_' . $subId] = true;
                
                // Add accounts under this sub
                foreach ($accountMap as $accountId => $account) {
                    if (array_key_exists('sub_id', $account) && $account['sub_id'] === $subId && !isset($added['a_' . $accountId])) {
                        $final[] = $account;
                        $added['a_' . $accountId] = true;
                    }
                }
                
                // Add child sub-accounts
                foreach ($subMap as $childSubId => $childSub) {
                    if ($childSub['parent_id'] === $subId && !isset($added['s_' . $childSubId])) {
                        $final[] = $childSub;
                        $added['s_' . $childSubId] = true;
                        
                        // Add accounts under child sub
                        foreach ($accountMap as $accountId => $account) {
                            if (array_key_exists('sub_id', $account) && $account['sub_id'] === $childSubId && !isset($added['a_' . $accountId])) {
                                $final[] = $account;
                                $added['a_' . $accountId] = true;
                            }
                        }
                    }
                }
            }
        }
        
        // Add orphaned child sub-accounts (parent not in subMap)
        foreach ($subMap as $orphanSubId => $orphanSub) {
            if ($orphanSub['head_id'] === $headId && $orphanSub['parent_id'] && !isset($added['s_' . $orphanSubId])) {
                // Check if parent exists in subMap
                $parentExists = false;
                foreach ($subMap as $checkSub) {
                    if ($checkSub['head_id'] === $headId && !$checkSub['parent_id']) {
                        $parentExists = true;
                        break;
                    }
                }
                
                if (!$parentExists) {
                    $final[] = $orphanSub;
                    $added['s_' . $orphanSubId] = true;
                    
                    foreach ($accountMap as $accountId => $account) {
                        if (array_key_exists('sub_id', $account) && $account['sub_id'] === $orphanSubId && !isset($added['a_' . $accountId])) {
                            $final[] = $account;
                            $added['a_' . $accountId] = true;
                        }
                    }
                }
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $final]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
