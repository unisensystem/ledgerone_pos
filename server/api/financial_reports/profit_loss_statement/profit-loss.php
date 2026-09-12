<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'currency-converter.php';

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

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$branch_id = $_GET['branch_id'] ?? null;
$company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
$project_id = !empty($_GET['project_id']) ? $_GET['project_id'] : null;
$container_id = !empty($_GET['container_id']) ? $_GET['container_id'] : null;
$target_currency_id = $_GET['currency_id'] ?? null;

if (!$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
    exit;
}

/**
 * Build a reference-table based filter for accounting_ledger rows.
 *
 * FIX (vs. previous version):
 *  - Previously, if BOTH company_id and container_id were supplied, the
 *    container_id branch completely REPLACED the company_id filter string,
 *    so the company filter silently stopped applying.
 *  - Previously, the container_id branch had no fallback for ledger rows
 *    whose reference_table isn't one of the four linked tables (e.g.
 *    'payroll_entries', 'production_expenses', manual journal entries).
 *    That meant selecting a container would make salary / other non
 *    invoice-linked entries vanish from the report entirely, even if the
 *    sub_account classification was correct.
 *
 * This version ANDs the two filters together (each still an OR across the
 * 4 reference tables) and gives BOTH filters the same "not a filterable
 * reference table" fallback, so unrelated ledger entries are never
 * silently dropped just because a company/container filter is active.
 */
function buildReferenceFilter($alias, $company_id, $container_id, $tenant_id, array &$params) {
    $tables = ['sale_invoice', 'purchase_invoice', 'payment_voucher', 'receive_voucher'];
    $sql = '';

    if ($company_id) {
        $parts = [];
        foreach ($tables as $t) {
            $parts[] = "($alias.reference_table = '$t' AND $alias.reference_id IN (SELECT id FROM $t WHERE company_id = ?))";
            $params[] = $company_id;
        }
        // Ledger rows not tied to any company-scoped reference table (payroll,
        // production expenses, manual journal entries, etc.) can't be filtered
        // by company, so they must remain included rather than dropped.
        $parts[] = "$alias.reference_table IS NULL";
        $parts[] = "$alias.reference_table NOT IN ('" . implode("','", $tables) . "')";
        $sql .= " AND (" . implode(' OR ', $parts) . ")";
    }

    if ($container_id) {
        $parts = [];
        foreach ($tables as $t) {
            $parts[] = "($alias.reference_table = '$t' AND $alias.reference_id IN (SELECT id FROM $t WHERE container_id = ? AND tenant_id = ?))";
            $params[] = $container_id;
            $params[] = $tenant_id;
        }
        $parts[] = "$alias.reference_table IS NULL";
        $parts[] = "$alias.reference_table NOT IN ('" . implode("','", $tables) . "')";
        $sql .= " AND (" . implode(' OR ', $parts) . ")";
    }

    return $sql;
}

try {
    // Initialize currency converter
    $converter = new CurrencyConverter($pdo, $tenant_id);

    // Get base currency
    $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
    $stmt->execute([$tenant_id]);
    $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
    $base_currency_id = $baseCurrency['currency_id'];

    // If no target currency specified, use base currency
    if (!$target_currency_id) {
        $target_currency_id = $base_currency_id;
    }

    // Build branch filter condition
    $branch_condition = '';
    $branch_params = [];
    if ($branch_id && $branch_id !== 'all') {
        $branch_condition = ' AND (b.id = ? OR b.parent_branch_id = ?)';
        $branch_params = [$branch_id, $branch_id];
    }

    // Build project condition
    $project_sql_sale = '';
    $project_sql_purchase = '';
    if ($project_id) {
        $project_sql_sale = ' AND si.project_id = ?';
        $project_sql_purchase = ' AND pi.project_id = ?';
    }

    // Build container condition (for sale/purchase invoice item queries)
    $container_sql_sale = '';
    $container_sql_purchase = '';
    if ($container_id) {
        $container_sql_sale = ' AND si.container_id = ?';
        $container_sql_purchase = ' AND pi.container_id = ?';
    }

    // ---------------------------------------------------------------
    // Sales Revenue from station_daily_usage (fuel products)
    // ---------------------------------------------------------------
    $sql = "
        SELECT
            p.name as account,
            SUM(sdu.total_revenue) as amount
        FROM station_daily_usage sdu
        JOIN products p ON sdu.product_id = p.id
        JOIN branches b ON sdu.branch_id = b.id
        WHERE sdu.tenant_id = ?
        AND sdu.usage_date BETWEEN ? AND ?
        $branch_condition
        GROUP BY p.id, p.name
        ORDER BY amount DESC
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    $stmt->execute($params);
    $sales_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Additional Sales Revenue from sale_invoice (physical products)
    $sql = "
        SELECT
            p.name as account,
            sii.net_amount as amount,
            si.currency_id
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        AND p.product_type = 'physical'
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        $project_sql_sale
        $container_sql_sale
        ORDER BY p.name
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($project_id) $params[] = $project_id;
    if ($container_id) $params[] = $container_id;
    $stmt->execute($params);
    $other_sales_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group and convert currency
    $other_sales = [];
    foreach ($other_sales_raw as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }

        $account = $row['account'];
        if (!isset($other_sales[$account])) {
            $other_sales[$account] = ['account' => $account, 'amount' => 0];
        }
        $other_sales[$account]['amount'] += $amount;
    }
    $other_sales = array_values($other_sales);

    // Merge both arrays
    $sales_revenue = array_merge($sales_revenue, $other_sales);

    // ---------------------------------------------------------------
    // Service Revenue
    // ---------------------------------------------------------------
    $sql = "
        SELECT
            p.name as account,
            sii.net_amount as amount,
            si.currency_id
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        AND p.product_type = 'service'
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        $project_sql_sale
        $container_sql_sale
        ORDER BY p.name
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($project_id) $params[] = $project_id;
    if ($container_id) $params[] = $container_id;
    $stmt->execute($params);
    $service_revenue_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group and convert currency
    $service_revenue = [];
    foreach ($service_revenue_raw as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }

        $account = $row['account'];
        if (!isset($service_revenue[$account])) {
            $service_revenue[$account] = ['account' => $account, 'amount' => 0];
        }
        $service_revenue[$account]['amount'] += $amount;
    }
    $service_revenue = array_values($service_revenue);

    // ---------------------------------------------------------------
    // Rent Income (with currency from rent_management)
    // ---------------------------------------------------------------
    $sql = "SELECT 'Rent Income' as account, al.credit as amount, rm.currency_id
            FROM accounting_ledger al
            JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management'
            WHERE al.tenant_id = ? AND al.account_id = 147
            AND al.date BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $start_date, $end_date]);
    $rent_income_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rent_income = [];
    $total_rent = 0;
    foreach ($rent_income_raw as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $total_rent += $amount;
    }

    if ($total_rent > 0) {
        $rent_income[] = ['account' => 'Rent Income', 'amount' => $total_rent];
    }

    // ---------------------------------------------------------------
    // Cost of Goods Sold: quantities sold from station_daily_usage
    // ---------------------------------------------------------------
    $sql = "
        SELECT
            p.id,
            p.name as account,
            SUM(sdu.total_dispensed) as qty_sold
        FROM station_daily_usage sdu
        JOIN products p ON sdu.product_id = p.id
        JOIN branches b ON sdu.branch_id = b.id
        WHERE sdu.tenant_id = ?
        AND sdu.usage_date BETWEEN ? AND ?
        $branch_condition
        GROUP BY p.id, p.name
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    $stmt->execute($params);
    $sold_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sold quantities from sale_invoice for physical products
    $sql = "
        SELECT
            p.id,
            p.name as account,
            SUM(sii.quantity) as qty_sold
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        AND p.product_type = 'physical'
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        $project_sql_sale
        $container_sql_sale
        GROUP BY p.id, p.name
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($project_id) $params[] = $project_id;
    if ($container_id) $params[] = $container_id;
    $stmt->execute($params);
    $other_sold = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge both arrays
    $sold_items = array_merge($sold_items, $other_sold);

    $cogs = [];
    foreach ($sold_items as $item) {
        // Average cost INCLUDING opening stock, with currency conversion
        $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
        $stmt = $pdo->prepare("
            SELECT
                pii.net_amount,
                pii.quantity,
                pi.currency_id
            FROM purchase_invoice pi
            JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
            WHERE pii.product_id = ?
            AND pi.tenant_id = ?
            AND pi.purchase_date <= ?
            " . ($company_id ? " AND pi.company_id = ?" : "") . "
            $project_sql_purchase
            $container_sql_purchase
        ");
        $params_cogs = [$item['id'], $tenant_id, $end_date];
        if ($company_id) $params_cogs[] = $company_id;
        if ($project_id) $params_cogs[] = $project_id;
        if ($container_id) $params_cogs[] = $container_id;
        $stmt->execute($params_cogs);
        $purchase_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_cost = 0;
        $total_qty = 0;

        foreach ($purchase_data as $purchase) {
            $cost = $purchase['net_amount'];
            $from_currency = $purchase['currency_id'] ?? $base_currency_id;
            if ($from_currency != $target_currency_id) {
                $cost = $converter->convert($cost, $from_currency, $target_currency_id);
            }
            $total_cost += $cost;
            $total_qty += $purchase['quantity'];
        }

        // Add opening stock (assume base currency)
        $stmt = $pdo->prepare("
            SELECT SUM(so.opening_qty * so.opening_price) as opening_cost, SUM(so.opening_qty) as opening_qty
            FROM stock_opening so
            JOIN products p ON so.product_id = p.id
            WHERE so.product_id = ?
            AND so.tenant_id = ?
            $stock_opening_filter
        ");
        $params_opening = [$item['id'], $tenant_id];
        if ($company_id) $params_opening[] = $company_id;
        $stmt->execute($params_opening);
        $opening_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($opening_data) {
            $opening_cost = $opening_data['opening_cost'] ?? 0;
            $opening_qty = $opening_data['opening_qty'] ?? 0;

            if ($base_currency_id != $target_currency_id) {
                $opening_cost = $converter->convert($opening_cost, $base_currency_id, $target_currency_id);
            }

            $total_cost += $opening_cost;
            $total_qty += $opening_qty;
        }

        $avg_cost = $total_qty > 0 ? $total_cost / $total_qty : 0;
        $qty_sold = (float)$item['qty_sold'];
        $cogs_amount = $avg_cost * $qty_sold;

        if ($cogs_amount > 0) {
            $cogs[] = [
                'account' => $item['account'],
                'amount' => $cogs_amount
            ];
        }
    }

    // ---------------------------------------------------------------
    // Operating Expenses from accounting_ledger
    //
    // UPDATED FIX: Include accounts with sub_account_id 96 or 94
    // (Salaries Expense) even if they don't have account_head_id = 5
    //
    // NOTE ON CLASSIFICATION: this query picks up ledger rows whose
    // account rolls up (via sub_accounts.account_head_id, falling back to
    // the parent sub-account's head) to account_head_id = 5 ("Expense"),
    // OR has sub_account_id 96 (for Salaries that are misconfigured).
    // ---------------------------------------------------------------
    $params_exp = [$tenant_id, $start_date, $end_date];
    $expense_filter = buildReferenceFilter('al', $company_id, $container_id, $tenant_id, $params_exp);

    $stmt = $pdo->prepare("
        SELECT
            a.name as account,
            al.debit,
            al.credit,
            al.reference_table,
            al.reference_id
        FROM accounting_ledger al
        JOIN accounts a ON al.account_id = a.id
        LEFT JOIN sub_accounts sa ON a.sub_account_id = sa.id
        LEFT JOIN sub_accounts sa_parent ON sa.parent_id = sa_parent.id
        WHERE al.tenant_id = ?
        AND al.date BETWEEN ? AND ?
        AND (
            COALESCE(sa.account_head_id, sa_parent.account_head_id) = 5
            OR a.sub_account_id IN (96, 94)
        )
        AND al.account_id != 19
        $expense_filter
        ORDER BY a.name
    ");
    $stmt->execute($params_exp);
    $expenses_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group and convert expenses
    $expenses = [];
    $ref_currency_cache = [];
    foreach ($expenses_raw as $row) {
        $amount = $row['debit'] - $row['credit'];
        if ($amount <= 0) continue;

        $currency_id = $base_currency_id;
        if ($row['reference_table'] && $row['reference_id']) {
            $tables = ['sale_invoice', 'purchase_invoice', 'payment_voucher', 'receive_voucher'];
            if (in_array($row['reference_table'], $tables)) {
                $cache_key = $row['reference_table'] . ':' . $row['reference_id'];
                if (!array_key_exists($cache_key, $ref_currency_cache)) {
                    try {
                        $stmt_curr = $pdo->prepare("SELECT currency_id FROM {$row['reference_table']} WHERE id = ?");
                        $stmt_curr->execute([$row['reference_id']]);
                        $curr_data = $stmt_curr->fetch(PDO::FETCH_ASSOC);
                        $ref_currency_cache[$cache_key] = ($curr_data && $curr_data['currency_id']) ? $curr_data['currency_id'] : null;
                    } catch (Exception $e) {
                        error_log('Currency lookup error: ' . $e->getMessage());
                        $ref_currency_cache[$cache_key] = null;
                    }
                }
                if ($ref_currency_cache[$cache_key]) {
                    $currency_id = $ref_currency_cache[$cache_key];
                }
            }
        }

        if ($currency_id != $target_currency_id) {
            $amount = $converter->convert($amount, $currency_id, $target_currency_id);
        }

        $account = $row['account'];
        if (!isset($expenses[$account])) {
            $expenses[$account] = ['account' => $account, 'amount' => 0];
        }
        $expenses[$account]['amount'] += $amount;
    }
    $expenses = array_values($expenses);

    // ---------------------------------------------------------------
    // Production Expenses (base currency; no company/container column
    // on production_expenses, so no filter applied here - unchanged)
    // ---------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT
            a.name as account,
            pea.amount,
            pe.created_at
        FROM production_expenses pe
        JOIN production_expense_accounts pea ON pe.id = pea.production_expense_id
        JOIN accounts a ON pea.expense_account_id = a.id
        WHERE pe.tenant_id = ?
        AND DATE(pe.created_at) BETWEEN ? AND ?
        ORDER BY a.name
    ");
    $stmt->execute([$tenant_id, $start_date, $end_date]);
    $production_expenses_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($production_expenses_raw as $row) {
        $amount = $row['amount'];
        if ($amount <= 0) continue;

        if ($base_currency_id != $target_currency_id) {
            $amount = $converter->convert($amount, $base_currency_id, $target_currency_id);
        }

        $account = $row['account'];
        if (!isset($expenses[$account])) {
            $expenses[$account] = ['account' => $account, 'amount' => 0];
        }
        $expenses[$account]['amount'] += $amount;
    }
    $expenses = array_values($expenses);

    // ---------------------------------------------------------------
    // Other Income (e.g. Purchase Discounts Received, account 103)
    // ---------------------------------------------------------------
    $params_inc = [$tenant_id, $start_date, $end_date];
    $income_filter = buildReferenceFilter('al', $company_id, $container_id, $tenant_id, $params_inc);

    $stmt = $pdo->prepare("
        SELECT
            a.name as account,
            al.credit,
            al.reference_table,
            al.reference_id
        FROM accounting_ledger al
        JOIN accounts a ON al.account_id = a.id
        WHERE al.tenant_id = ?
        AND al.date BETWEEN ? AND ?
        AND a.id = 103
        $income_filter
    ");
    $stmt->execute($params_inc);
    $other_income_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group and convert other income
    $other_income = [];
    foreach ($other_income_raw as $row) {
        $amount = $row['credit'];
        if ($amount <= 0) continue;

        $currency_id = $base_currency_id;
        if ($row['reference_table'] && $row['reference_id']) {
            $tables = ['sale_invoice', 'purchase_invoice', 'payment_voucher', 'receive_voucher'];
            if (in_array($row['reference_table'], $tables)) {
                try {
                    $stmt_curr = $pdo->prepare("SELECT currency_id FROM {$row['reference_table']} WHERE id = ?");
                    $stmt_curr->execute([$row['reference_id']]);
                    $curr_data = $stmt_curr->fetch(PDO::FETCH_ASSOC);
                    if ($curr_data && $curr_data['currency_id']) $currency_id = $curr_data['currency_id'];
                } catch (Exception $e) {
                    error_log('Currency lookup error: ' . $e->getMessage());
                }
            }
        }

        if ($currency_id != $target_currency_id) {
            $amount = $converter->convert($amount, $currency_id, $target_currency_id);
        }

        $account = $row['account'];
        if (!isset($other_income[$account])) {
            $other_income[$account] = ['account' => $account, 'amount' => 0];
        }
        $other_income[$account]['amount'] += $amount;
    }
    $other_income = array_values($other_income);

    // Convert amounts to float
    foreach ($sales_revenue as &$item) {
        $item['amount'] = (float)$item['amount'];
    }
    foreach ($service_revenue as &$item) {
        $item['amount'] = (float)$item['amount'];
    }
    foreach ($rent_income as &$item) {
        $item['amount'] = (float)$item['amount'];
    }
    foreach ($cogs as &$item) {
        $item['amount'] = (float)$item['amount'];
    }
    foreach ($expenses as &$item) {
        $item['amount'] = (float)$item['amount'];
    }
    foreach ($other_income as &$item) {
        $item['amount'] = (float)$item['amount'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'sales_revenue' => $sales_revenue,
            'service_revenue' => $service_revenue,
            'rent_income' => $rent_income,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'other_income' => $other_income
        ],
        'debug' => [
            'sales_revenue_count' => count($sales_revenue),
            'service_revenue_count' => count($service_revenue),
            'sold_items' => $sold_items ?? [],
            'cogs_count' => count($cogs),
            'expenses_count' => count($expenses),
            'target_currency_id' => $target_currency_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'other_sales_raw_count' => count($other_sales_raw ?? []),
            'service_revenue_raw_count' => count($service_revenue_raw ?? []),
            'expenses_raw_count' => count($expenses_raw ?? [])
        ]
    ]);

} catch (Exception $e) {
    error_log('Profit Loss Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>