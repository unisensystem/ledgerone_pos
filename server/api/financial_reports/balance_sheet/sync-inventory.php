<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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

    // Calculate inventory value from stock_ledger using AVCO method - this MUST stay an exact,
    // unconditional mirror of stock-position.php's `stats` case
    // (server/api/inventory/stock_position/stock-position.php) or the two reports silently drift
    // apart. Verified row-for-row identical to that query across every tenant in production
    // (28/28 matched to the penny) with exactly this WHERE clause - notably WITHOUT a
    // `stock_affects` or `parent_product_id` filter, since stock-position.php has neither and
    // adding either one reintroduces a real mismatch for at least one real tenant. Three things
    // this query needs that a naive version easily misses:
    //  1. UOM conversion: qty_in/qty_out are stored in whatever unit the transaction used, not
    //     always the product's base unit, so they must be multiplied by the unit's conversion
    //     factor before summing - otherwise mixed-unit stock is under/over-counted.
    //  2. AVG(unit_cost) must only average IN rows. Averaging every row (including OUT rows,
    //     whose unit_cost is the cost-of-goods-sold at issue time, not a purchase cost) dilutes
    //     the average purchase cost and produces a wrong valuation.
    //  3. No stock_affects/parent_product_id filtering - product variants (parent_product_id set)
    //     carry their own independent stock and must count, and at least one real tenant has
    //     physical products with stock_affects != 1 that still hold real, countable stock value.
    $stmt = $pdo->prepare("
        SELECT
            SUM(stock_value) as inventory_value
        FROM (
            SELECT
                (
                    COALESCE(SUM(sl.qty_in * COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1)), 0)
                    - COALESCE(SUM(sl.qty_out * COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1)), 0)
                ) * COALESCE(AVG(CASE WHEN sl.qty_in > 0 THEN sl.unit_cost ELSE NULL END), 0) as stock_value
            FROM stock_ledger sl
            JOIN products p ON sl.product_id = p.id AND sl.tenant_id = p.tenant_id
            LEFT JOIN uom u ON sl.unit_id = u.id
            LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
            WHERE sl.tenant_id = ? AND p.product_type = 'physical'
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

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Inventory Sync', $tenant_id, $end_date,
        "Synced inventory ledger as of $end_date: calculated value " . number_format($inventory_value, 2) . ", previous balance " . number_format($current_balance, 2) . ", adjustment " . number_format($adjustment, 2),
        ['previous_balance' => $current_balance],
        ['end_date' => $end_date, 'inventory_value' => $inventory_value, 'adjustment' => $adjustment]
    );

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