<?php
/**
 * Customer Statement Report - API (FIXED v7)
 * ---------------------------------------------------------------
 *
 * FIX 1: Customers with opening balance but NO transactions
 *        ab appear karengi WITH FROM_DATE
 * FIX 2: Proper totals calculation opening balance + sale invoice
 * FIX 3: Empty date field ab bhara hua hai from_date ke sath
 * FIX 4: total_closing_balance ab har customer ke closing balance
 *        ko SUM kar raha hai
 * FIX 5: City / Zone / Area / Salesman filters MULTI-SELECT support
 *        (comma-separated ids, e.g. city_id=3,7,10).
 * FIX 6: action=salesmen - dropdown ke liye employees list.
 * FIX 7: TRANSFER VOUCHER column (TO = +, FROM = -).
 * FIX 8: ADJUSTMENT column (signed net).
 *        Sources:
 *        a) Receive Voucher ki adjust_amount
 *           receive-edit/add ledger: Trade Debtors CR = amount + adjust_amount
 *           => customer ka balance adjust_amount se bhi kam hota hai  (-)
 *        b) Balance Adjustment (journal_voucher, adj_mode single/cross)
 *           adjustment-add.php lines insert karta hai:
 *             1st line (lowest id) = Party 1, 2nd line = Party 2 (cross)
 *           Debit  => customer balance barhta hai  (+)
 *           Credit => customer balance kam hota hai (-)
 *           (adj_type journal_voucher me save nahi hota, is liye direction
 *            line ke debit/credit se nikalti hai)
 * FIX 9 (NEW): PAYMENT VOUCHER column ("Payment to Customer").
 *        payment_voucher table se wo rows jin me customer_id set ho aur
 *        supplier_id NULL ho (Customer Ledger ke same filter).
 *        Customer ko paisa wapas dene se customer ka receivable barhta hai
 *        => DEBIT (+), Sale Invoice ki tarah.
 *
 * LOGIC:
 * Opening Balance
 *     = Static Opening
 *     + Sale Invoices before From Date
 *     - Receive Vouchers before From Date
 *     - Sale Returns before From Date
 *     + Payment Vouchers (to customer) before From Date
 *     +/- Transfer Vouchers before From Date
 *     +/- Adjustments before From Date (RV adjust_amount = -, JV = +/-)
 *
 * Closing Balance
 *     = Opening + Sale Invoice - Receive Voucher - Sale Return
 *       + Payment Voucher
 *       +/- Transfer Voucher (net) +/- Adjustment (net)
 */

require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

/**
 * Helper: comma-separated id string se clean integer array banata hai.
 * "3,7,10" -> [3, 7, 10]. Empty/invalid values discard ho jati hain.
 */
function parseIdList($commaSeparated) {
    if (empty($commaSeparated)) {
        return [];
    }
    $ids = array_map('intval', explode(',', $commaSeparated));
    $ids = array_filter($ids, function ($v) { return $v > 0; });
    return array_values(array_unique($ids));
}

/**
 * Helper: given a column name and an array of ids, ek "AND col IN (?,?,?)"
 * SQL fragment + params array return karta hai. Empty array pe empty
 * fragment deta hai (koi filter nahi lagta).
 */
function buildInClause($column, array $ids) {
    if (empty($ids)) {
        return ['sql' => '', 'params' => []];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return [
        'sql'    => " AND {$column} IN ({$placeholders})",
        'params' => $ids
    ];
}

/**
 * Helper: customer ki Balance Adjustment (journal_voucher) entries, signed.
 *   signed > 0  => Debit  => customer balance barhta hai
 *   signed < 0  => Credit => customer balance kam hota hai
 *
 * Party 1 = journal_voucher_line ki pehli line (lowest id)
 * Party 2 = journal_voucher_line ki doosri line (cross mode)
 *
 * Date filters (sab optional):
 *   $from   => voucher_date >= from
 *   $to     => voucher_date <= to
 *   $before => voucher_date <  before   (opening balance ke liye)
 */
function fetchCustomerJvAdjustments(PDO $pdo, $tenant_id, $cid, $from = null, $to = null, $before = null) {
    $sql = "
        SELECT
            jv.voucher_date,
            jv.voucher_number,
            CASE
                WHEN jv.party_type = 'customer' AND jv.party_id = :cid_a THEN
                    (SELECT l.debit - l.credit
                       FROM journal_voucher_line l
                      WHERE l.voucher_id = jv.id
                        AND l.tenant_id  = jv.tenant_id
                      ORDER BY l.id ASC
                      LIMIT 1)
                ELSE
                    (SELECT l.debit - l.credit
                       FROM journal_voucher_line l
                      WHERE l.voucher_id = jv.id
                        AND l.tenant_id  = jv.tenant_id
                      ORDER BY l.id ASC
                      LIMIT 1 OFFSET 1)
            END AS signed_amount
        FROM journal_voucher jv
        WHERE jv.tenant_id = :tenant
          AND jv.status = 'posted'
          AND jv.adj_mode IN ('single', 'cross')
          AND (
                (jv.party_type = 'customer' AND jv.party_id = :cid_b)
             OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = :cid_c)
          )
    ";

    $params = [
        ':cid_a'  => $cid,
        ':tenant' => $tenant_id,
        ':cid_b'  => $cid,
        ':cid_c'  => $cid
    ];

    if ($from) {
        $sql .= " AND jv.voucher_date >= :from_d";
        $params[':from_d'] = $from;
    }
    if ($to) {
        $sql .= " AND jv.voucher_date <= :to_d";
        $params[':to_d'] = $to;
    }
    if ($before) {
        $sql .= " AND jv.voucher_date < :before_d";
        $params[':before_d'] = $before;
    }

    $sql .= " ORDER BY jv.voucher_date";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {

    $currency_sql = "
        SELECT c.symbol
        FROM tenant_currencies tc
        JOIN ledgerone_public.currencies c
            ON tc.currency_id = c.id
        WHERE tc.tenant_id = ?
          AND tc.is_base_currency = 1
        LIMIT 1
    ";

    $currency_stmt = $pdo->prepare($currency_sql);
    $currency_stmt->execute([$tenant_id]);
    $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
    $currency_symbol = $currency['symbol'] ?? '$';

    $action = $_GET['action'] ?? 'statement';

    switch ($action) {

        case 'customers':
            $sql = "
                SELECT
                    id,
                    customer_name
                FROM customers
                WHERE tenant_id = ?
                  AND status = 'ACTIVE'
                ORDER BY customer_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $customers
            ]);
            break;

        case 'cities':
            $sql = "
                SELECT 
                    id, 
                    city_name AS name
                FROM cities
                WHERE tenant_id = ?
                ORDER BY city_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $cities
            ]);
            break;

        case 'zones':
            // Multiple city ids accept karta hai (comma-separated).
            // city_id na diya jaye to TENANT KE SAB zones return hote hain.
            $city_ids = parseIdList($_GET['city_id'] ?? null);

            $sql    = "
                SELECT 
                    id, 
                    city_zone_name AS name
                FROM city_zones
                WHERE tenant_id = ?
            ";
            $params = [$tenant_id];

            $in = buildInClause('city_id', $city_ids);
            $sql .= $in['sql'];
            $params = array_merge($params, $in['params']);

            $sql .= " ORDER BY city_zone_name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $zones
            ]);
            break;

        case 'areas':
            // Multiple zone ids accept karta hai (comma-separated).
            // city_zone_id na diya jaye to TENANT KE SAB areas return hote hain.
            $city_zone_ids = parseIdList($_GET['city_zone_id'] ?? null);

            $sql    = "
                SELECT 
                    id, 
                    area_name AS name
                FROM areas
                WHERE tenant_id = ?
            ";
            $params = [$tenant_id];

            $in = buildInClause('city_zone_id', $city_zone_ids);
            $sql .= $in['sql'];
            $params = array_merge($params, $in['params']);

            $sql .= " ORDER BY area_name";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $areas
            ]);
            break;

        case 'salesmen':
            // Salesman dropdown ke liye employees list.
            // NOTE: designation/role column pata nahi hai, isliye abhi
            // tenant ke sab employees dikh rahe hain.
            $sql = "
                SELECT
                    id,
                    full_name AS name
                FROM employees
                WHERE tenant_id = ?
                ORDER BY full_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            $salesmen = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $salesmen
            ]);
            break;

        case 'statement':

            $customer_id = isset($_GET['customer_id']) && $_GET['customer_id'] !== ''
                ? (int)$_GET['customer_id']
                : null;

            $from_date = !empty($_GET['from_date']) ? $_GET['from_date'] : null;
            $to_date   = !empty($_GET['to_date'])   ? $_GET['to_date']   : null;

            // City / Zone / Area / Salesman - MULTI-SELECT (comma-separated ids)
            $city_ids      = parseIdList($_GET['city_id']      ?? null);
            $city_zone_ids = parseIdList($_GET['city_zone_id'] ?? null);
            $area_ids      = parseIdList($_GET['area_id']      ?? null);
            $salesman_ids  = parseIdList($_GET['salesman_id']  ?? null);

            if ($from_date && $to_date && $from_date > $to_date) {
                echo json_encode([
                    'success' => false,
                    'message' => 'From Date cannot be greater than To Date.'
                ]);
                exit;
            }

            // Get Customers
            $params = [$tenant_id];
            $where  = "tenant_id = ? AND status = 'ACTIVE'";

            if ($customer_id) {
                $where   .= " AND id = ?";
                $params[] = $customer_id;
            }

            $city_in = buildInClause('city_id', $city_ids);
            $where  .= $city_in['sql'];
            $params  = array_merge($params, $city_in['params']);

            $zone_in = buildInClause('city_zone_id', $city_zone_ids);
            $where  .= $zone_in['sql'];
            $params  = array_merge($params, $zone_in['params']);

            $area_in = buildInClause('area_id', $area_ids);
            $where  .= $area_in['sql'];
            $params  = array_merge($params, $area_in['params']);

            // Salesman filter - customers.associated_sales_officer_id
            $salesman_in = buildInClause('associated_sales_officer_id', $salesman_ids);
            $where      .= $salesman_in['sql'];
            $params      = array_merge($params, $salesman_in['params']);

            $cust_sql = "
                SELECT
                    id,
                    customer_name,
                    opening_debit_amount,
                    opening_credit_amount
                FROM customers
                WHERE {$where}
                ORDER BY customer_name
            ";

            $cust_stmt = $pdo->prepare($cust_sql);
            $cust_stmt->execute($params);
            $customers = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$customers) {
                echo json_encode([
                    'success'         => true,
                    'currency'        => $currency_symbol,
                    'customer_name'   => 'All Customers',
                    'from_date'       => $from_date,
                    'to_date'         => $to_date,
                    'opening_balance' => 0,
                    'closing_balance' => 0,
                    'total_sale_invoice' => 0,
                    'total_receive_voucher' => 0,
                    'total_sale_return' => 0,
                    'total_payment_voucher' => 0,
                    'total_transfer_voucher' => 0,
                    'total_adjustment' => 0,
                    'rows'            => []
                ]);
                exit;
            }

            $all_rows = [];
            $total_opening_balance = 0;
            $total_closing_balance = 0;
            $total_sale_invoice = 0;
            $total_receive_voucher = 0;
            $total_sale_return = 0;
            $total_payment_voucher = 0;  // Payment to Customer (Debit, +)
            $total_transfer_voucher = 0; // signed net (TO = +, FROM = -)
            $total_adjustment = 0;       // signed net (RV adjust = -, JV Dr = +, JV Cr = -)

            foreach ($customers as $customer) {

                $cid           = (int)$customer['id'];
                $customer_name = $customer['customer_name'];

                $static_opening =
                    (float)$customer['opening_debit_amount']
                    -
                    (float)$customer['opening_credit_amount'];

                $bbf = $static_opening;

                if ($from_date) {
                    $si_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(net_amount), 0)
                        FROM sale_invoice
                        WHERE tenant_id = ?
                          AND customer_id = ?
                          AND status = 'Posted'
                          AND sale_date < ?
                    ");
                    $si_stmt->execute([$tenant_id, $cid, $from_date]);
                    $bbf += (float)$si_stmt->fetchColumn();

                    // Receive vouchers before from_date: amount aur adjust_amount alag
                    $rv_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0) AS amt,
                               COALESCE(SUM(adjust_amount), 0) AS adj
                        FROM receive_voucher
                        WHERE tenant_id = ?
                          AND customer_id = ?
                          AND voucher_date < ?
                    ");
                    $rv_stmt->execute([$tenant_id, $cid, $from_date]);
                    $rv_before = $rv_stmt->fetch(PDO::FETCH_ASSOC);
                    $bbf -= (float)$rv_before['amt'];
                    $bbf -= (float)$rv_before['adj'];   // RV adjustment (-)

                    $sr_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(net_amount), 0)
                        FROM sale_return
                        WHERE tenant_id = ?
                          AND customer_id = ?
                          AND status = 'Posted'
                          AND sale_date < ?
                    ");
                    $sr_stmt->execute([$tenant_id, $cid, $from_date]);
                    $bbf -= (float)$sr_stmt->fetchColumn();

                    // Payment vouchers (Payment to Customer) before from_date: Debit = +
                    $pv_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payment_voucher
                        WHERE tenant_id = ?
                          AND customer_id IS NOT NULL
                          AND supplier_id IS NULL
                          AND customer_id = ?
                          AND voucher_date < ?
                    ");
                    $pv_stmt->execute([$tenant_id, $cid, $from_date]);
                    $bbf += (float)$pv_stmt->fetchColumn();

                    // Transfer vouchers before from_date: TO = +, FROM = -
                    $tv_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(
                            CASE
                                WHEN to_customer_id = ?   THEN amount
                                WHEN from_customer_id = ? THEN -amount
                                ELSE 0
                            END
                        ), 0)
                        FROM transfer_voucher
                        WHERE tenant_id = ?
                          AND (to_customer_id = ? OR from_customer_id = ?)
                          AND voucher_date < ?
                    ");
                    $tv_stmt->execute([$cid, $cid, $tenant_id, $cid, $cid, $from_date]);
                    $bbf += (float)$tv_stmt->fetchColumn();

                    // Balance Adjustment (journal voucher) before from_date: Dr = +, Cr = -
                    foreach (fetchCustomerJvAdjustments($pdo, $tenant_id, $cid, null, null, $from_date) as $jv) {
                        $bbf += (float)$jv['signed_amount'];
                    }
                }

                $total_opening_balance += $bbf;

                $days = [];

                $makeDay = function ($date) use (&$days) {
                    if (!isset($days[$date])) {
                        $days[$date] = [
                            'sale_invoice'     => 0,
                            'receive_voucher'  => 0,
                            'sale_return'      => 0,
                            'payment_voucher'  => 0,
                            'transfer_voucher' => 0,
                            'adjustment'       => 0,
                            'si_bills'         => [],
                            'rv_bills'         => [],
                            'sr_bills'         => [],
                            'pv_bills'         => [],
                            'tv_bills'         => [],
                            'adj_bills'        => []
                        ];
                    }
                };

                // SALE INVOICES
                $si_sql = "
                    SELECT sale_date, bill_no, net_amount
                    FROM sale_invoice
                    WHERE tenant_id = ?
                      AND customer_id = ?
                      AND status = 'Posted'
                ";
                $si_params = [$tenant_id, $cid];

                if ($from_date) {
                    $si_sql .= " AND sale_date >= ?";
                    $si_params[] = $from_date;
                }
                if ($to_date) {
                    $si_sql .= " AND sale_date <= ?";
                    $si_params[] = $to_date;
                }

                $si_sql .= " ORDER BY sale_date";

                $si_stmt = $pdo->prepare($si_sql);
                $si_stmt->execute($si_params);

                foreach ($si_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['sale_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['sale_invoice'] += (float)$row['net_amount'];
                    if (isset($row['bill_no']) && $row['bill_no'] !== '') {
                        $days[$d]['si_bills'][] = $row['bill_no'];
                    }
                }

                // RECEIVE VOUCHERS (amount => Receive Voucher column,
                // adjust_amount => Adjustment column, negative)
                $rv_sql = "
                    SELECT voucher_date, voucher_number, amount, adjust_amount
                    FROM receive_voucher
                    WHERE tenant_id = ?
                      AND customer_id = ?
                ";
                $rv_params = [$tenant_id, $cid];

                if ($from_date) {
                    $rv_sql .= " AND voucher_date >= ?";
                    $rv_params[] = $from_date;
                }
                if ($to_date) {
                    $rv_sql .= " AND voucher_date <= ?";
                    $rv_params[] = $to_date;
                }

                $rv_sql .= " ORDER BY voucher_date";

                $rv_stmt = $pdo->prepare($rv_sql);
                $rv_stmt->execute($rv_params);

                foreach ($rv_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['voucher_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['receive_voucher'] += (float)$row['amount'];
                    if (isset($row['voucher_number']) && $row['voucher_number'] !== '') {
                        $days[$d]['rv_bills'][] = $row['voucher_number'];
                    }

                    // RV adjustment => Adjustment column (balance kam hota hai)
                    $rv_adj = (float)($row['adjust_amount'] ?? 0);
                    if ($rv_adj != 0) {
                        $days[$d]['adjustment'] -= $rv_adj;
                        if (isset($row['voucher_number']) && $row['voucher_number'] !== '') {
                            $days[$d]['adj_bills'][] = $row['voucher_number'];
                        }
                    }
                }

                // SALE RETURNS
                $sr_sql = "
                    SELECT sale_date, bill_no, net_amount
                    FROM sale_return
                    WHERE tenant_id = ?
                      AND customer_id = ?
                      AND status = 'Posted'
                ";
                $sr_params = [$tenant_id, $cid];

                if ($from_date) {
                    $sr_sql .= " AND sale_date >= ?";
                    $sr_params[] = $from_date;
                }
                if ($to_date) {
                    $sr_sql .= " AND sale_date <= ?";
                    $sr_params[] = $to_date;
                }

                $sr_sql .= " ORDER BY sale_date";

                $sr_stmt = $pdo->prepare($sr_sql);
                $sr_stmt->execute($sr_params);

                foreach ($sr_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['sale_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['sale_return'] += (float)$row['net_amount'];
                    if (isset($row['bill_no']) && $row['bill_no'] !== '') {
                        $days[$d]['sr_bills'][] = $row['bill_no'];
                    }
                }

                // PAYMENT VOUCHERS (Payment to Customer)
                // Customer ko paisa wapas diya => customer balance barhta hai (Debit, +)
                $pv_sql = "
                    SELECT voucher_date, voucher_number, amount
                    FROM payment_voucher
                    WHERE tenant_id = ?
                      AND customer_id IS NOT NULL
                      AND supplier_id IS NULL
                      AND customer_id = ?
                ";
                $pv_params = [$tenant_id, $cid];

                if ($from_date) {
                    $pv_sql .= " AND voucher_date >= ?";
                    $pv_params[] = $from_date;
                }
                if ($to_date) {
                    $pv_sql .= " AND voucher_date <= ?";
                    $pv_params[] = $to_date;
                }

                $pv_sql .= " ORDER BY voucher_date";

                $pv_stmt = $pdo->prepare($pv_sql);
                $pv_stmt->execute($pv_params);

                foreach ($pv_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['voucher_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['payment_voucher'] += (float)$row['amount'];
                    if (isset($row['voucher_number']) && $row['voucher_number'] !== '') {
                        $days[$d]['pv_bills'][] = $row['voucher_number'];
                    }
                }

                // TRANSFER VOUCHERS
                // Customer TO party  => + (debit, balance barhta hai)
                // Customer FROM party => - (credit, balance kam hota hai)
                $tv_sql = "
                    SELECT voucher_date, voucher_number, amount,
                           from_customer_id, to_customer_id
                    FROM transfer_voucher
                    WHERE tenant_id = ?
                      AND (from_customer_id = ? OR to_customer_id = ?)
                ";
                $tv_params = [$tenant_id, $cid, $cid];

                if ($from_date) {
                    $tv_sql .= " AND voucher_date >= ?";
                    $tv_params[] = $from_date;
                }
                if ($to_date) {
                    $tv_sql .= " AND voucher_date <= ?";
                    $tv_params[] = $to_date;
                }

                $tv_sql .= " ORDER BY voucher_date";

                $tv_stmt = $pdo->prepare($tv_sql);
                $tv_stmt->execute($tv_params);

                foreach ($tv_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['voucher_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);

                    $signed = ((int)$row['to_customer_id'] === $cid)
                        ? (float)$row['amount']
                        : -(float)$row['amount'];

                    $days[$d]['transfer_voucher'] += $signed;
                    if (isset($row['voucher_number']) && $row['voucher_number'] !== '') {
                        $days[$d]['tv_bills'][] = $row['voucher_number'];
                    }
                }

                // BALANCE ADJUSTMENTS (journal voucher, single + cross)
                // Debit => + (balance barhta hai), Credit => - (balance kam hota hai)
                foreach (fetchCustomerJvAdjustments($pdo, $tenant_id, $cid, $from_date, $to_date, null) as $row) {
                    $d = $row['voucher_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);

                    $days[$d]['adjustment'] += (float)$row['signed_amount'];
                    if (isset($row['voucher_number']) && $row['voucher_number'] !== '') {
                        $days[$d]['adj_bills'][] = $row['voucher_number'];
                    }
                }

                ksort($days);

                $running = $bbf;
                $customer_has_transactions = false;

                foreach ($days as $date => $d) {

                    $opening = $running;
                    $closing = $opening
                        + $d['sale_invoice']
                        - $d['receive_voucher']
                        - $d['sale_return']
                        + $d['payment_voucher']
                        + $d['transfer_voucher']
                        + $d['adjustment'];

                    $running = $closing;
                    $customer_has_transactions = true;

                    $all_rows[] = [
                        'date'             => $date,
                        'customer_id'      => $cid,
                        'customer_name'    => $customer_name,
                        'opening_balance'  => round($opening, 2),
                        'sale_invoice'     => round($d['sale_invoice'], 2),
                        'receive_voucher'  => round($d['receive_voucher'], 2),
                        'sale_return'      => round($d['sale_return'], 2),
                        'payment_voucher'  => round($d['payment_voucher'], 2),
                        'transfer_voucher' => round($d['transfer_voucher'], 2),
                        'adjustment'       => round($d['adjustment'], 2),
                        'closing_balance'  => round($closing, 2),
                        'invoice_nos'      => implode(', ', array_unique(array_filter($d['si_bills']))),
                        'voucher_nos'      => implode(', ', array_unique(array_filter($d['rv_bills']))),
                        'return_nos'       => implode(', ', array_unique(array_filter($d['sr_bills']))),
                        'payment_nos'      => implode(', ', array_unique(array_filter($d['pv_bills']))),
                        'transfer_nos'     => implode(', ', array_unique(array_filter($d['tv_bills']))),
                        'adjustment_nos'   => implode(', ', array_unique(array_filter($d['adj_bills'])))
                    ];

                    // Add to totals
                    $total_sale_invoice     += (float)$d['sale_invoice'];
                    $total_receive_voucher  += (float)$d['receive_voucher'];
                    $total_sale_return      += (float)$d['sale_return'];
                    $total_payment_voucher  += (float)$d['payment_voucher'];
                    $total_transfer_voucher += (float)$d['transfer_voucher'];
                    $total_adjustment       += (float)$d['adjustment'];
                }

                // Agar customer ke paas opening balance hai but koi transaction nahi
                // to from_date show hoga (ya aaj ki date agar from_date nahi hai)
                if (!$customer_has_transactions && $bbf != 0) {
                    $display_date = $from_date ?? date('Y-m-d');

                    $all_rows[] = [
                        'date'             => $display_date,
                        'customer_id'      => $cid,
                        'customer_name'    => $customer_name,
                        'opening_balance'  => round($bbf, 2),
                        'sale_invoice'     => 0,
                        'receive_voucher'  => 0,
                        'sale_return'      => 0,
                        'payment_voucher'  => 0,
                        'transfer_voucher' => 0,
                        'adjustment'       => 0,
                        'closing_balance'  => round($bbf, 2),
                        'invoice_nos'      => '',
                        'voucher_nos'      => '',
                        'return_nos'       => '',
                        'payment_nos'      => '',
                        'transfer_nos'     => '',
                        'adjustment_nos'   => ''
                    ];

                    $running = $bbf;
                }

                // SUM (+=) - har customer ka closing balance total mein add hota hai
                $total_closing_balance += $running;
            }

            // Sort by Date then Customer
            usort($all_rows, function ($a, $b) {
                $dateCompare = strcmp($a['date'], $b['date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcasecmp($a['customer_name'], $b['customer_name']);
            });

            if ($customer_id) {
                $response_customer_name = $customers[0]['customer_name'];
            } else {
                $response_customer_name = 'All Customers';
            }

            echo json_encode([
                'success'                => true,
                'currency'               => $currency_symbol,
                'customer_name'          => $response_customer_name,
                'customer_id'            => $customer_id,
                'from_date'              => $from_date,
                'to_date'                => $to_date,
                'customer_count'         => count($customers),
                'opening_balance'        => round($total_opening_balance, 2),
                'closing_balance'        => round($total_closing_balance, 2),
                'total_sale_invoice'     => round($total_sale_invoice, 2),
                'total_receive_voucher'  => round($total_receive_voucher, 2),
                'total_sale_return'      => round($total_sale_return, 2),
                'total_payment_voucher'  => round($total_payment_voucher, 2),
                'total_transfer_voucher' => round($total_transfer_voucher, 2),
                'total_adjustment'       => round($total_adjustment, 2),
                'rows'                   => $all_rows
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Customer Statement API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>