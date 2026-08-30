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

try {
    // Initialize currency converter
    $converter = new CurrencyConverter($pdo, $tenant_id);
    
    // Get base currency
    $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
    $stmt->execute([$tenant_id]);
    $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
    $base_currency_id = $baseCurrency['currency_id'];
    
    // Get filters
    $date_range = $_GET['date_range'] ?? 'month';
    $target_currency_id = $_GET['currency_id'] ?? $base_currency_id;
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    $description = $_GET['description'] ?? '';
    $account = $_GET['account'] ?? 'all';
    $method = $_GET['method'] ?? 'all';
    $bank_account = $_GET['bank_account'] ?? 'all';
    $type = $_GET['type'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, intval($_GET['limit'] ?? 15));
    $offset = ($page - 1) * $limit;
    $company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;

    // Build date condition
    $date_conditions = [];
    $params = [];
    
    switch ($date_range) {
        case 'today':
            $date_conditions = [
                "AND rv.voucher_date = CURDATE()",
                "AND pv.voucher_date = CURDATE()", 
                "AND sdu.usage_date = CURDATE()",
                "AND pdc.cheque_date = CURDATE()",
                "AND jv.voucher_date = CURDATE()",
                "AND sr.sale_date = CURDATE()",
                "AND ev.date = CURDATE()",
                "AND pr.purchase_date = CURDATE()",
                "AND pe.payroll_date = CURDATE()",
                "AND al.date = CURDATE()",
                "AND pex.created_at >= CURDATE()"
            ];
            break;
        case 'week':
            $date_conditions = [
                "AND rv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND pv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND sdu.usage_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND pdc.cheque_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND jv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND sr.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND ev.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND pr.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND pe.payroll_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND al.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                "AND pex.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            ];
            break;
        case 'month':
            $date_conditions = [
                "AND rv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND pv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND sdu.usage_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND pdc.cheque_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND jv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND sr.sale_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND ev.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND pr.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND pe.payroll_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND al.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
                "AND pex.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"
            ];
            break;
        case 'quarter':
            $date_conditions = [
                "AND rv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND pv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND sdu.usage_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND pdc.cheque_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND jv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND sr.sale_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND ev.date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND pr.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND pe.payroll_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND al.date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
                "AND pex.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"
            ];
            break;
        case 'year':
            $date_conditions = [
                "AND rv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND pv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND sdu.usage_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND pdc.cheque_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND jv.voucher_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND sr.sale_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND ev.date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND pr.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND pe.payroll_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND al.date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
                "AND pex.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"
            ];
            break;
        case 'custom':
            if ($from_date && $to_date) {
                $date_conditions = [
                    "AND rv.voucher_date BETWEEN ? AND ?",
                    "AND pv.voucher_date BETWEEN ? AND ?",
                    "AND sdu.usage_date BETWEEN ? AND ?",
                    "AND pdc.cheque_date BETWEEN ? AND ?",
                    "AND jv.voucher_date BETWEEN ? AND ?",
                    "AND sr.sale_date BETWEEN ? AND ?",
                    "AND ev.date BETWEEN ? AND ?",
                    "AND pr.purchase_date BETWEEN ? AND ?",
                    "AND pe.payroll_date BETWEEN ? AND ?",
                    "AND al.date BETWEEN ? AND ?",
                    "AND DATE(pex.created_at) BETWEEN ? AND ?"
                ];
                // Build params array properly
                $params = [];
                // Query 1: receive_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 2: payment_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 3: station_daily_usage/revenue_split (NO company_id)
                $params[] = $tenant_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 4: post_dated_cheques
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 5: journal_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 6: sale_return
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 7: expense_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 8: payroll_entries
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 9: rent
                $params[] = $tenant_id;
                $params[] = $from_date;
                $params[] = $to_date;
                // Query 10: production_expenses (NO company_id)
                $params[] = $tenant_id;
                $params[] = $from_date;
                $params[] = $to_date;
            } else {
                $date_conditions = ["", "", "", "", "", "", "", "", "", "", ""];
                $params = [];
                // Query 1: receive_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 2: payment_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 3: station_daily_usage/revenue_split (NO company_id)
                $params[] = $tenant_id;
                // Query 4: post_dated_cheques
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 5: journal_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 6: sale_return
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 7: expense_voucher
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 8: payroll_entries
                $params[] = $tenant_id;
                if ($company_id) $params[] = $company_id;
                // Query 9: rent
                $params[] = $tenant_id;
                // Query 10: production_expenses (NO company_id)
                $params[] = $tenant_id;
            }
            break;
        default:
            $date_conditions = ["", "", "", "", "", "", "", "", "", "", ""];
            $params = array_fill(0, 11, $tenant_id);
    }
    
    // For non-custom ranges, just add tenant_id for each query
    if ($date_range !== 'custom') {
        $params = [];
        // Query 1: receive_voucher
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 2: payment_voucher
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 3: station_daily_usage/revenue_split (NO company_id)
        $params[] = $tenant_id;
        // Query 4: post_dated_cheques
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 5: journal_voucher
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 6: sale_return
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 7: expense_voucher
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 8: payroll_entries
        $params[] = $tenant_id;
        if ($company_id) $params[] = $company_id;
        // Query 9: rent
        $params[] = $tenant_id;
        // Query 10: production_expenses (NO company_id)
        $params[] = $tenant_id;
    }

    // Get cash flow transactions from receive_voucher, payment_voucher and station_daily_usage
    $sql = "
        SELECT 
            rv.voucher_date as date,
            CAST('Receipt' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(c.customer_name, 'Customer') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(rv.voucher_number AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(pm.name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(COALESCE(ba.bank_name, '-') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            rv.amount as inflow,
            0 as outflow,
            rv.currency_id
        FROM receive_voucher rv
        LEFT JOIN customers c ON rv.customer_id = c.id
        LEFT JOIN accounts pm ON rv.payment_method_id = pm.id
        LEFT JOIN bank_accounts ba ON rv.bank_account_id = ba.id
        WHERE rv.tenant_id = ? 
        AND NOT EXISTS (
            SELECT 1 FROM post_dated_cheques pdc 
            WHERE pdc.reference_table = 'receive_voucher' 
            AND pdc.reference_id = rv.id
        )
        " . ($company_id ? " AND rv.company_id = ?" : "") . "
        {$date_conditions[0]}
        
        UNION ALL
        
        SELECT 
            pv.voucher_date as date,
            CAST('Payment' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(s.supplier_name, 'Supplier') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(pv.voucher_number AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(pm.name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(COALESCE(ba.bank_name, '-') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            0 as inflow,
            pv.amount as outflow,
            pv.currency_id
        FROM payment_voucher pv
        LEFT JOIN suppliers s ON pv.supplier_id = s.id
        LEFT JOIN accounts pm ON pv.payment_method_id = pm.id
        LEFT JOIN bank_accounts ba ON pv.bank_account_id = ba.id
        WHERE pv.tenant_id = ? 
        AND NOT EXISTS (
            SELECT 1 FROM post_dated_cheques pdc 
            WHERE pdc.reference_table = 'payment_voucher' 
            AND pdc.reference_id = pv.id
        )
        " . ($company_id ? " AND pv.company_id = ?" : "") . "
        {$date_conditions[1]}
        
        UNION ALL
        
        SELECT 
            sdu.usage_date as date,
            CAST(CONCAT('Fuel Sales - ', p.name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST('Fuel Sales' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(CONCAT('FUEL-', rs.id) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(CASE WHEN rs.account_id = 1 THEN 'Cash' ELSE 'Bank' END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(CASE WHEN rs.account_id = 1 THEN '-' ELSE a.name END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            rs.amount as inflow,
            0 as outflow,
            NULL as currency_id
        FROM revenue_split rs
        JOIN station_daily_usage sdu ON rs.station_daily_usage_id = sdu.id
        LEFT JOIN products p ON sdu.product_id = p.id
        LEFT JOIN accounts a ON rs.account_id = a.id
        WHERE rs.tenant_id = ? AND rs.amount > 0 {$date_conditions[2]}
        
        UNION ALL
        
        SELECT 
            pdc.cheque_date as date,
            CAST(CONCAT('PDC - ', pdc.transaction_type) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(CASE 
                WHEN pdc.transaction_type = 'Received' THEN COALESCE(c.customer_name, 'Customer')
                WHEN pdc.transaction_type = 'Payment' THEN COALESCE(s.supplier_name, 'Supplier')
                ELSE 'Expense'
            END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(pdc.cheque_no AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST('Cheque' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(COALESCE(ba.bank_name, '-') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            CASE WHEN pdc.transaction_type = 'Received' THEN pdc.amount ELSE 0 END as inflow,
            CASE WHEN pdc.transaction_type IN ('Payment', 'Expense') THEN pdc.amount ELSE 0 END as outflow,
            NULL as currency_id
        FROM post_dated_cheques pdc
        LEFT JOIN customers c ON pdc.customer_id = c.id
        LEFT JOIN suppliers s ON pdc.supplier_id = s.id
        LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id
        WHERE pdc.tenant_id = ? AND pdc.status = 'Approved'
        " . ($company_id ? " AND pdc.company_id = ?" : "") . "
        {$date_conditions[3]}
        
        UNION ALL
        
        SELECT 
            sr.sale_date as date,
            CAST(CONCAT('Sale Return - ', sr.bill_no) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(c.customer_name, 'Customer') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(sr.bill_no AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(sr.payment_method AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(COALESCE(ba.bank_name, '-') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            0 as inflow,
            sr.amount_refunded as outflow,
            sr.currency_id
        FROM sale_return sr
        LEFT JOIN customers c ON sr.customer_id = c.id
        LEFT JOIN bank_accounts ba ON sr.bank_account_id = ba.id
        WHERE sr.tenant_id = ? 
        AND sr.status = 'Posted'
        AND sr.amount_refunded > 0
        " . ($company_id ? " AND sr.company_id = ?" : "") . "
        {$date_conditions[5]}
        
        UNION ALL
        
        SELECT 
            ev.date as date,
            CAST(COALESCE(ev.description, 'Expense') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(a.name, 'Expense') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(ev.voucher_no AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(pm.name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(COALESCE(ba.bank_name, '-') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            0 as inflow,
            evl.amount as outflow,
            NULL as currency_id
        FROM expense_voucher_line evl
        JOIN expense_voucher ev ON evl.voucher_id = ev.id
        LEFT JOIN accounts a ON evl.account_id = a.id
        LEFT JOIN accounts pm ON evl.payment_method_id = pm.id
        LEFT JOIN bank_accounts ba ON evl.bank_account_id = ba.id
        WHERE evl.tenant_id = ? 
        AND evl.cheque_date IS NULL
        AND ev.is_cash_flow = 1
        " . ($company_id ? " AND ev.company_id = ?" : "") . "
        {$date_conditions[6]}
        
        UNION ALL
        
        SELECT 
            pe.payroll_date as date,
            CAST(CONCAT('Payroll - ', pei.type, ' - ', COALESCE(e.full_name, 'Employee')) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(e.full_name, 'Employee') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(pe.payroll_code AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(pm.name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(COALESCE(ba.bank_name, '-') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            CASE WHEN pei.type = 'Advance Return' THEN pei.amount ELSE 0 END as inflow,
            CASE WHEN pei.type != 'Advance Return' THEN pei.amount ELSE 0 END as outflow,
            NULL as currency_id
        FROM payroll_entries_items pei
        JOIN payroll_entries pe ON pei.payroll_id = pe.id
        LEFT JOIN employees e ON CAST(pei.employee_id AS CHAR CHARACTER SET utf8mb4) = CAST(e.employee_id AS CHAR CHARACTER SET utf8mb4) AND e.tenant_id = pei.tenant_id
        LEFT JOIN accounts pm ON pei.payment_method_id = pm.id
        LEFT JOIN bank_accounts ba ON pei.bank_account_id = ba.id
        WHERE pei.tenant_id = ? 
        AND pei.cheque_date IS NULL
        " . ($company_id ? " AND pe.company_id = ?" : "") . "
        {$date_conditions[8]}
        
        UNION ALL
        
        SELECT 
            jv.voucher_date as date,
            CAST(COALESCE(jv.description, 'Journal Entry') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(opposing_acc.name, 'Journal') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(jv.voucher_number AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST(CASE 
                WHEN cash_line.account_id = 1 THEN 'Cash'
                WHEN bank_acc.sub_account_id = 75 THEN 'Bank'
                ELSE 'Journal'
            END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST(CASE 
                WHEN bank_acc.sub_account_id = 75 THEN bank_acc.name
                ELSE '-'
            END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            cash_line.debit as inflow,
            cash_line.credit as outflow,
            NULL as currency_id
        FROM journal_voucher jv
        JOIN journal_voucher_line cash_line ON jv.id = cash_line.voucher_id
        JOIN accounts cash_acc ON cash_line.account_id = cash_acc.id
        LEFT JOIN journal_voucher_line opposing_line ON jv.id = opposing_line.voucher_id 
            AND opposing_line.id != cash_line.id
            AND ((cash_line.debit > 0 AND opposing_line.credit > 0) OR (cash_line.credit > 0 AND opposing_line.debit > 0))
        LEFT JOIN accounts opposing_acc ON opposing_line.account_id = opposing_acc.id
        LEFT JOIN accounts bank_acc ON cash_line.account_id = bank_acc.id
        WHERE jv.tenant_id = ? 
        AND jv.status = 'posted'
        AND (cash_line.account_id = 1 OR cash_acc.sub_account_id = 75 OR cash_line.account_id IN (78, 79))
        " . ($company_id ? " AND jv.company_id = ?" : "") . "
        {$date_conditions[4]}
        
        UNION ALL
        
        SELECT 
            al.date as date,
            CAST(al.description AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST(COALESCE(c.customer_name, 'Customer') AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(CONCAT('RENT-', rm.id) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST('Cash' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST('-' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            al.debit as inflow,
            0 as outflow,
            rm.currency_id
        FROM accounting_ledger al
        JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management'
        LEFT JOIN customers c ON rm.customer_id = c.id
        WHERE al.tenant_id = ? AND al.account_id IN (1, 7) AND al.debit > 0
        {$date_conditions[9]}
        
        UNION ALL
        
        SELECT 
            DATE(pex.created_at) as date,
            CAST(CONCAT('Production Expense - ', pex.id) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            CAST('Production Expense' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as account,
            CAST(CONCAT('PROD-', pex.id) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as reference,
            CAST('Cash' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as method,
            CAST('-' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as bank_account,
            0 as inflow,
            pex.total_amount as outflow,
            NULL as currency_id
        FROM production_expenses pex
        WHERE pex.tenant_id = ?
        {$date_conditions[10]}
        
        ORDER BY date DESC, inflow DESC, outflow DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert currency for all transactions
    foreach ($all_transactions as &$transaction) {
        $from_currency = $transaction['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $transaction['inflow'] = $converter->convert($transaction['inflow'], $from_currency, $target_currency_id);
            $transaction['outflow'] = $converter->convert($transaction['outflow'], $from_currency, $target_currency_id);
        }
    }
    unset($transaction);

    // Apply additional filters
    if ($description) {
        $all_transactions = array_filter($all_transactions, function($t) use ($description) {
            return stripos($t['description'], $description) !== false;
        });
    }

    if ($account && $account !== 'all') {
        $all_transactions = array_filter($all_transactions, function($t) use ($account) {
            return stripos($t['account'], $account) !== false;
        });
    }

    if ($method !== 'all') {
        $stmt = $pdo->prepare("SELECT name FROM accounts WHERE id = ?");
        $stmt->execute([$method]);
        $method_result = $stmt->fetch();
        $selected_method = $method_result['name'] ?? '';
        
        $all_transactions = array_filter($all_transactions, function($t) use ($selected_method) {
            return stripos($t['method'], $selected_method) !== false;
        });
    }
    
    if ($bank_account !== 'all') {
        $stmt = $pdo->prepare("SELECT bank_name FROM bank_accounts WHERE id = ?");
        $stmt->execute([$bank_account]);
        $bank_name_result = $stmt->fetch();
        $selected_bank_name = $bank_name_result['bank_name'] ?? '';
        
        $all_transactions = array_filter($all_transactions, function($t) use ($selected_bank_name) {
            return $t['bank_account'] !== '-' && stripos($t['bank_account'], $selected_bank_name) !== false;
        });
    }

    if ($type === 'inflow') {
        $all_transactions = array_filter($all_transactions, function($t) {
            return $t['inflow'] > 0;
        });
    } elseif ($type === 'outflow') {
        $all_transactions = array_filter($all_transactions, function($t) {
            return $t['outflow'] > 0;
        });
    }
    
    // Get total count before pagination
    $total_records = count($all_transactions);
    $total_pages = ceil($total_records / $limit);
    
    // Apply pagination
    $transactions = array_slice($all_transactions, $offset, $limit);

    // Get opening balances
    if ($bank_account !== 'all') {
        // Specific bank account selected
        $stmt = $pdo->prepare("SELECT opening_balance, credit_amount FROM bank_accounts WHERE tenant_id = ? AND id = ? AND is_active = 1" . ($company_id ? " AND (company_id = ? OR company_id IS NULL)" : ""));
        $params_bank = [$tenant_id, $bank_account];
        if ($company_id) $params_bank[] = $company_id;
        $stmt->execute($params_bank);
        $bank_result = $stmt->fetch();
        $bank_opening = $bank_result['opening_balance'] ?? 0;
        $bank_credit = $bank_result['credit_amount'] ?? 0;
        $cash_opening = 0; // No cash when filtering by bank
        
        error_log("DEBUG [Specific Bank]: bank_opening=$bank_opening, bank_credit=$bank_credit");
    } else {
        // All accounts
        $stmt = $pdo->prepare("SELECT SUM(opening_balance) as total_opening, SUM(credit_amount) as total_credit FROM bank_accounts WHERE tenant_id = ? AND is_active = 1" . ($company_id ? " AND (company_id = ? OR company_id IS NULL)" : ""));
        $params_bank = [$tenant_id];
        if ($company_id) $params_bank[] = $company_id;
        $stmt->execute($params_bank);
        $bank_result = $stmt->fetch();
        $bank_opening = $bank_result['total_opening'] ?? 0;
        $bank_credit = $bank_result['total_credit'] ?? 0;
        
        error_log("DEBUG [All Banks]: total_opening=$bank_opening, total_credit=$bank_credit");
        
        $stmt = $pdo->prepare("SELECT opening_amount FROM cash_opening WHERE tenant_id = ?" . ($company_id ? " AND (company_id = ? OR company_id IS NULL)" : "") . " ORDER BY id DESC LIMIT 1");
        $params_cash = [$tenant_id];
        if ($company_id) $params_cash[] = $company_id;
        $stmt->execute($params_cash);
        $cash_result = $stmt->fetch();
        $cash_opening = $cash_result['opening_amount'] ?? 0;
        
        error_log("DEBUG [Cash]: cash_opening=$cash_opening");
    }
    
    $bank_opening_balance = $bank_opening - $bank_credit;
    
    error_log("DEBUG [Calculated]: bank_opening_balance=$bank_opening_balance, cash_opening=$cash_opening");
    
    // Convert opening balances from base currency to target currency
    if ($base_currency_id != $target_currency_id) {
        $cash_opening = $converter->convert($cash_opening, $base_currency_id, $target_currency_id);
        $bank_opening_balance = $converter->convert($bank_opening_balance, $base_currency_id, $target_currency_id);
        
        error_log("DEBUG [After Currency Conversion]: cash_opening=$cash_opening, bank_opening_balance=$bank_opening_balance");
    }
    
    // Calculate soft opening (transactions before from_date)
    $soft_opening_cash = 0;
    $soft_opening_bank = 0;
    if ($date_range === 'custom' && $from_date) {
        // Build the WHERE clause for bank account filtering
        $bank_filter = '';
        if ($bank_account !== 'all') {
            $bank_filter = " AND ba.id = $bank_account";
        }
        
        // When filtering by specific bank, we want total inflow-outflow for that bank only
        // When showing all, we want to separate cash vs bank
        $select_clause = ($bank_account !== 'all') 
            ? "COALESCE(SUM(inflow - outflow), 0) as bank_balance, 0 as cash_balance"
            : "COALESCE(SUM(CASE WHEN bank_account = '-' THEN inflow - outflow ELSE 0 END), 0) as cash_balance,
               COALESCE(SUM(CASE WHEN bank_account != '-' THEN inflow - outflow ELSE 0 END), 0) as bank_balance";
        
        $soft_sql = "
            SELECT $select_clause
            FROM (
                SELECT rv.amount as inflow, 0 as outflow, COALESCE(ba.bank_name, '-') as bank_account
                FROM receive_voucher rv
                LEFT JOIN bank_accounts ba ON rv.bank_account_id = ba.id
                WHERE rv.tenant_id = ? AND rv.voucher_date < ?
                " . ($company_id ? " AND rv.company_id = ?" : "") . "
                $bank_filter
                AND NOT EXISTS (
                    SELECT 1 FROM post_dated_cheques pdc 
                    WHERE pdc.reference_table = 'receive_voucher' 
                    AND pdc.reference_id = rv.id
                )
                
                UNION ALL
                
                SELECT 0 as inflow, pv.amount as outflow, COALESCE(ba.bank_name, '-') as bank_account
                FROM payment_voucher pv
                LEFT JOIN bank_accounts ba ON pv.bank_account_id = ba.id
                WHERE pv.tenant_id = ? AND pv.voucher_date < ?
                " . ($company_id ? " AND pv.company_id = ?" : "") . "
                $bank_filter
                AND NOT EXISTS (
                    SELECT 1 FROM post_dated_cheques pdc 
                    WHERE pdc.reference_table = 'payment_voucher' 
                    AND pdc.reference_id = pv.id
                )
                
                UNION ALL
                
                SELECT rs.amount as inflow, 0 as outflow, CASE WHEN rs.account_id = 1 THEN '-' ELSE a.name END as bank_account
                FROM revenue_split rs
                JOIN station_daily_usage sdu ON rs.station_daily_usage_id = sdu.id
                LEFT JOIN accounts a ON rs.account_id = a.id
                WHERE rs.tenant_id = ? AND rs.amount > 0 AND sdu.usage_date < ?
                " . ($bank_account !== 'all' ? " AND 1=0" : "") . "
                
                UNION ALL
                
                SELECT 
                    CASE WHEN pdc.transaction_type = 'Received' THEN pdc.amount ELSE 0 END as inflow,
                    CASE WHEN pdc.transaction_type IN ('Payment', 'Expense') THEN pdc.amount ELSE 0 END as outflow,
                    COALESCE(ba.bank_name, '-') as bank_account
                FROM post_dated_cheques pdc
                LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id
                WHERE pdc.tenant_id = ? AND pdc.status = 'Approved' AND pdc.cheque_date < ?
                " . ($company_id ? " AND pdc.company_id = ?" : "") . "
                $bank_filter
                
                UNION ALL
                
                SELECT 
                    jvl.debit as inflow,
                    jvl.credit as outflow,
                    CASE WHEN bank_acc.sub_account_id = 75 THEN bank_acc.name ELSE '-' END as bank_account
                FROM journal_voucher jv
                JOIN journal_voucher_line jvl ON jv.id = jvl.voucher_id
                JOIN accounts a ON jvl.account_id = a.id
                LEFT JOIN accounts bank_acc ON jvl.account_id = bank_acc.id
                " . ($bank_account !== 'all' ? "JOIN bank_accounts ba ON jvl.account_id = ba.account_id AND ba.id = $bank_account" : "") . "
                WHERE jv.tenant_id = ? AND jv.status = 'posted' AND jv.voucher_date < ?
                " . ($company_id ? " AND jv.company_id = ?" : "") . "
                AND (jvl.account_id = 1 OR a.sub_account_id = 75 OR jvl.account_id IN (78, 79))
                
                UNION ALL
                
                SELECT 
                    0 as inflow,
                    sr.amount_refunded as outflow,
                    COALESCE(ba.bank_name, '-') as bank_account
                FROM sale_return sr
                LEFT JOIN bank_accounts ba ON sr.bank_account_id = ba.id
                WHERE sr.tenant_id = ? AND sr.status = 'Posted' AND sr.amount_refunded > 0 AND sr.sale_date < ?
                " . ($company_id ? " AND sr.company_id = ?" : "") . "
                $bank_filter
                
                UNION ALL
                
                SELECT 
                    0 as inflow,
                    evl.amount as outflow,
                    COALESCE(ba.bank_name, '-') as bank_account
                FROM expense_voucher_line evl
                JOIN expense_voucher ev ON evl.voucher_id = ev.id
                LEFT JOIN bank_accounts ba ON evl.bank_account_id = ba.id
                WHERE evl.tenant_id = ? AND evl.cheque_date IS NULL AND ev.is_cash_flow = 1 AND ev.date < ?
                " . ($company_id ? " AND ev.company_id = ?" : "") . "
                $bank_filter
                
                UNION ALL
                
                SELECT 
                    CASE WHEN pei.type = 'Advance Return' THEN pei.amount ELSE 0 END as inflow,
                    CASE WHEN pei.type != 'Advance Return' THEN pei.amount ELSE 0 END as outflow,
                    COALESCE(ba.bank_name, '-') as bank_account
                FROM payroll_entries_items pei
                JOIN payroll_entries pe ON pei.payroll_id = pe.id
                LEFT JOIN employees e ON pei.employee_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci AND e.tenant_id = pei.tenant_id
                LEFT JOIN bank_accounts ba ON pei.bank_account_id = ba.id
                WHERE pei.tenant_id = ? AND pei.cheque_date IS NULL AND pe.payroll_date < ?
                " . ($company_id ? " AND pe.company_id = ?" : "") . "
                $bank_filter
            ) as soft_transactions
        ";
        $soft_stmt = $pdo->prepare($soft_sql);
        $params_soft = [$tenant_id, $from_date];
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $params_soft = array_merge($params_soft, [$tenant_id, $from_date]);
        if ($company_id) $params_soft[] = $company_id;
        $soft_stmt->execute($params_soft);
        $soft_result = $soft_stmt->fetch();
        
        // DEBUG: Log the actual query for inspection
        error_log("DEBUG [Soft Opening SQL]: " . substr($soft_sql, 0, 500));
        error_log("DEBUG [Soft Opening Params Count]: " . count($params_soft));
        
        // When filtering by specific bank, only use bank_balance from soft opening
        if ($bank_account !== 'all') {
            $soft_opening_cash = 0;
            $soft_opening_bank = $soft_result['bank_balance'] ?? 0;
        } else {
            $soft_opening_cash = $soft_result['cash_balance'] ?? 0;
            $soft_opening_bank = $soft_result['bank_balance'] ?? 0;
        }
        
        error_log("DEBUG [Soft Opening Query Result]: cash_balance=" . ($soft_result['cash_balance'] ?? 0) . ", bank_balance=" . ($soft_result['bank_balance'] ?? 0));
        error_log("DEBUG [Soft Opening Final]: soft_opening_cash=$soft_opening_cash, soft_opening_bank=$soft_opening_bank, bank_account_filter=$bank_account, from_date=$from_date");
        
        // Convert soft opening from base currency to target currency
        if ($base_currency_id != $target_currency_id) {
            $soft_opening_cash = $converter->convert($soft_opening_cash, $base_currency_id, $target_currency_id);
            $soft_opening_bank = $converter->convert($soft_opening_bank, $base_currency_id, $target_currency_id);
        }
    }
    
    // Re-index array after filtering
    $all_transactions = array_values($all_transactions);
    
    // Calculate running balances for all transactions
    $cash_balance = $cash_opening + $soft_opening_cash;
    $bank_balance = $bank_opening_balance + $soft_opening_bank;
    
    foreach ($all_transactions as &$transaction) {
        $transaction['inflow'] = (float)$transaction['inflow'];
        $transaction['outflow'] = (float)$transaction['outflow'];
        
        // When filtering by specific bank, only update bank_balance for that bank's transactions
        if ($bank_account !== 'all') {
            // All filtered transactions are for this bank, so update bank_balance
            $bank_balance += $transaction['inflow'] - $transaction['outflow'];
        } else {
            // Update cash or bank balance based on transaction type
            $cash_balance += ($transaction['bank_account'] === '-') ? ($transaction['inflow'] - $transaction['outflow']) : 0;
            $bank_balance += ($transaction['bank_account'] !== '-') ? ($transaction['inflow'] - $transaction['outflow']) : 0;
        }
        
        $transaction['cash_balance'] = $cash_balance;
        $transaction['bank_balance'] = $bank_balance;
    }
    
    // Get paginated transactions
    $transactions = array_slice($all_transactions, $offset, $limit);

    // Calculate totals from all transactions
    $total_inflow = array_sum(array_column($all_transactions, 'inflow'));
    $total_outflow = array_sum(array_column($all_transactions, 'outflow'));
    
    // Get currency symbol for target currency
    $stmt = $pdo->prepare("SELECT symbol FROM ledgerone_public.currencies WHERE id = ?");
    $stmt->execute([$target_currency_id]);
    $currency = $stmt->fetch();
    $currency_symbol = $currency['symbol'] ?? 'Rs';
    
    // Calculate balances for summary
    $opening_balance = $cash_opening + $bank_opening_balance + $soft_opening_cash + $soft_opening_bank;
    $closing_balance = $opening_balance + $total_inflow - $total_outflow;
    
    error_log("DEBUG [Final Balances]: opening_balance=$opening_balance (cash=$cash_opening + bank=$bank_opening_balance + soft_cash=$soft_opening_cash + soft_bank=$soft_opening_bank), closing_balance=$closing_balance");

    // Prepare debug info
    $debug_info = [
        'bank_opening_raw' => $bank_opening ?? 0,
        'bank_credit_raw' => $bank_credit ?? 0,
        'bank_opening_balance' => $bank_opening_balance ?? 0,
        'cash_opening' => $cash_opening ?? 0,
        'soft_opening_cash' => $soft_opening_cash ?? 0,
        'soft_opening_bank' => $soft_opening_bank ?? 0,
        'combined_opening' => $opening_balance,
        'base_currency_id' => $base_currency_id,
        'target_currency_id' => $target_currency_id
    ];

    echo json_encode([
        'success' => true,
        'data' => array_values($transactions),
        'totals' => [
            'inflow' => $total_inflow,
            'outflow' => $total_outflow,
            'cash_balance' => $cash_balance,
            'bank_balance' => $bank_balance
        ],
        'balances' => [
            'opening_balance' => $opening_balance,
            'total_inflow' => $total_inflow,
            'total_outflow' => $total_outflow,
            'closing_balance' => $closing_balance
        ],
        'currency_symbol' => $currency_symbol,
        'count' => count($transactions),
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'limit' => $limit
        ],
        'debug' => $debug_info
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}