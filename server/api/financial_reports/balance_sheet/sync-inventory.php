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

    // Calculate inventory value from stock_ledger using AVCO method (same as Stock Position Report)
    $stmt = $pdo->prepare("
        SELECT 
            SUM(stock_value) as inventory_value
        FROM (
            SELECT 
                (COALESCE(SUM(sl.qty_in), 0) - COALESCE(SUM(sl.qty_out), 0)) * AVG(sl.unit_cost) as stock_value
            FROM stock_ledger sl
            JOIN products p ON sl.product_id = p.id
            WHERE sl.tenant_id = ? AND p.product_type = 'physical' AND p.stock_affects = 1 AND p.parent_product_id IS NULL
            GROUP BY sl.product_id, sl.branch_id, sl.account_id
        ) as stock_data
    ");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch();
    $inventory_value = $result['inventory_value'] ?? 0;

    // Find inventory account (assuming it's under Assets)
    $stmt = $pdo->prepare("
        SELECT a.id 
        FROM accounts a
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        JOIN accounts_head ah ON sa.account_head_id = ah.id
        WHERE a.tenant_id = ? 
        AND (a.name LIKE '%Inventory%' OR a.name LIKE '%Stock%' OR a.id = 33)
        AND ah.name LIKE '%Asset%'
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $account = $stmt->fetch();
    
    if (!$account) {
        // Use account id 33 as fallback
        $inventory_account_id = 33;
    } else {
        $inventory_account_id = $account['id'];
    }

    // Get current inventory balance from ledger (excluding adjustments)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as current_balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = ? AND date <= ? AND transaction_type != 'Stock Adjustment'
    ");
    $stmt->execute([$tenant_id, $inventory_account_id, $end_date]);
    $ledger = $stmt->fetch();
    $current_balance = $ledger['current_balance'] ?? 0;

    // Calculate adjustment needed
    $adjustment = $inventory_value - $current_balance;

    // Delete all previous sync adjustments
    $stmt = $pdo->prepare("
        DELETE FROM accounting_ledger 
        WHERE tenant_id = ? AND transaction_type = 'Stock Adjustment'
    ");
    $stmt->execute([$tenant_id]);

    if (abs($adjustment) > 0.01) {
        if ($adjustment > 0) {
            // Debit Inventory
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'Stock Adjustment', 'stock_ledger', 0, ?, ?, 'Inventory sync adjustment', ?, 0)
            ");
            $stmt->execute([$tenant_id, $inventory_account_id, $end_date, abs($adjustment)]);
            
            // Credit Opening Balance Equity (account 90)
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'Stock Adjustment', 'stock_ledger', 0, 90, ?, 'Inventory sync adjustment', 0, ?)
            ");
            $stmt->execute([$tenant_id, $end_date, abs($adjustment)]);
        } else {
            // Credit Inventory
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'Stock Adjustment', 'stock_ledger', 0, ?, ?, 'Inventory sync adjustment', 0, ?)
            ");
            $stmt->execute([$tenant_id, $inventory_account_id, $end_date, abs($adjustment)]);
            
            // Debit Opening Balance Equity (account 90)
            $stmt = $pdo->prepare("
                INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'Stock Adjustment', 'stock_ledger', 0, 90, ?, 'Inventory sync adjustment', ?, 0)
            ");
            $stmt->execute([$tenant_id, $end_date, abs($adjustment)]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Inventory synced successfully',
        'data' => [
            'inventory_value' => $inventory_value,
            'previous_balance' => $current_balance,
            'adjustment' => $adjustment
        ],
        'debug' => [
            'inventory_account_id' => $inventory_account_id,
            'end_date' => $end_date
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}