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
    $company_id  = !empty($_GET['company_id'])  ? $_GET['company_id']  : null;
    $customer_id = !empty($_GET['customer_id']) ? $_GET['customer_id'] : null;
    $country_id  = !empty($_GET['country_id'])  ? $_GET['country_id']  : null;
    $region_id   = !empty($_GET['region_id'])   ? $_GET['region_id']   : null;
    $city_id     = !empty($_GET['city_id'])     ? $_GET['city_id']     : null;
    $zone_id     = !empty($_GET['zone_id'])     ? $_GET['zone_id']     : null;
    $area_id     = !empty($_GET['area_id'])     ? $_GET['area_id']     : null;

    $date_range = $_GET['date_range'] ?? 'all';
    $date_from  = $_GET['date_from'] ?? null;
    $date_to    = $_GET['date_to'] ?? null;

    // View mode: 'invoices' (default, existing aging report) or 'unbilled'
    // (Receive Vouchers / Sale Returns that carry no bill_no / sale_invoice_no yet)
    $view_mode = $_GET['view_mode'] ?? 'invoices';

    // Get currency symbol
    $stmt = $pdo->prepare("
        SELECT c.symbol 
        FROM tenant_currencies tc 
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$tenant_id]);
    $currency = $stmt->fetch();
    $currency_symbol = $currency['symbol'] ?? '$';

    // ---- Build dynamic WHERE fragments ----
    // NOTE: assumes the `customers` table (aliased `c`) has
    // country_id, region_id, city_id, zone_id, area_id columns.
    // If those columns live in a separate address table instead,
    // adjust the column references below (e.g. addr.country_id) and
    // add the corresponding JOIN in both the sale_invoice and
    // opening_balance_invoices branches.

    $dateCondition = '';
    $dateParams = [];

    if ($date_from && $date_to) {
        $dateCondition = ' AND {date_col} BETWEEN ? AND ?';
        $dateParams = [$date_from, $date_to];
    } elseif ($date_range === '30days') {
        $dateCondition = ' AND {date_col} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
    } elseif ($date_range === '90days') {
        $dateCondition = ' AND {date_col} >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
    }

    $companyCondition  = $company_id  ? ' AND {company_col} = ?' : '';
    $customerCondition = $customer_id ? ' AND c.id = ?' : '';
    $countryCondition  = $country_id  ? ' AND c.country_id = ?' : '';
    $regionCondition   = $region_id   ? ' AND c.region_id = ?' : '';
    $cityCondition     = $city_id     ? ' AND c.city_id = ?' : '';
$zoneCondition     = $zone_id     ? ' AND c.city_zone_id = ?' : '';
    $areaCondition     = $area_id     ? ' AND c.area_id = ?' : '';

    // Shared filter fragment + params (same for both branches since both join `customers c`)
    $sharedFilterSql = $customerCondition . $countryCondition . $regionCondition
                      . $cityCondition . $zoneCondition . $areaCondition;
    $sharedFilterParams = [];
    if ($customer_id) $sharedFilterParams[] = $customer_id;
    if ($country_id)  $sharedFilterParams[] = $country_id;
    if ($region_id)   $sharedFilterParams[] = $region_id;
    if ($city_id)     $sharedFilterParams[] = $city_id;
    if ($zone_id)     $sharedFilterParams[] = $zone_id;
    if ($area_id)     $sharedFilterParams[] = $area_id;

    // Signed net transfer-voucher amount for a customer (c.id), reused in both
    // branches of the default view. Sign convention: POSITIVE reduces the
    // customer's balance (they paid out, or someone paid in on their behalf),
    // NEGATIVE increases it (a customer-to-customer transfer landed on them
    // as a new charge).
    //   - customer is FROM party                              -> +amount (paid out)
    //   - customer is TO party, other side is a supplier       -> +amount (received/settled)
    //   - customer is TO party, other side is another customer -> -amount (new debt)
    $transferSubquery = "
                COALESCE((
                    SELECT SUM(
                        CASE 
                            WHEN tv.from_type = 'customer' AND tv.from_customer_id = c.id THEN tv.amount
                            WHEN tv.to_type = 'customer' AND tv.to_customer_id = c.id AND tv.from_type = 'supplier' THEN tv.amount
                            WHEN tv.to_type = 'customer' AND tv.to_customer_id = c.id AND tv.from_type = 'customer' THEN -tv.amount
                            ELSE 0
                        END
                    )
                    FROM transfer_voucher tv
                    WHERE tv.tenant_id = ?
                    AND (
                        (tv.from_type = 'customer' AND tv.from_customer_id = c.id)
                        OR (tv.to_type = 'customer' AND tv.to_customer_id = c.id)
                    )
                ), 0) as unallocated_transfer";

    // Total UNBILLED sale returns for this customer (no sale_invoice_no linked
    // yet). Billed returns are still subtracted per-invoice via the existing
    // sale_return JOIN below; this customer-level total covers the ones that
    // aren't tied to any invoice, so they always get netted off as well
    // (via the Auto-Clear pool on the frontend) regardless of bill_no.
    $returnSubquery = "
                COALESCE((
                    SELECT SUM(sr2.net_amount)
                    FROM sale_return sr2
                    WHERE sr2.customer_id = c.id
                    AND sr2.tenant_id = ?
                    AND sr2.status = 'Posted'
                    AND (sr2.sale_invoice_no IS NULL OR sr2.sale_invoice_no = '')
                ), 0) as unallocated_sale_return";

    // Net Journal Voucher adjustment for this customer (single + cross-party),
    // same logic as customer-ledger.php. Sign is flipped to match the
    // "positive reduces balance" convention used by the other unallocated_*
    // subqueries above: a net CREDIT adjustment (money owed reduced) is
    // positive here and nets off outstanding; a net DEBIT adjustment (new
    // charge added) is negative and increases it.
    $adjustmentSubquery = "
                COALESCE((
                    SELECT SUM(
                        CASE
                            WHEN jv.adj_mode = 'single'
                                OR (jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = c.id
                                    AND jvl.id = (SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2))
                                OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = c.id
                                    AND jvl.id = (SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2))
                            THEN jvl.credit - jvl.debit
                            ELSE 0
                        END
                    )
                    FROM journal_voucher jv
                    JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2
                    WHERE jv.tenant_id = ? AND jv.status = 'posted'
                    AND (
                        (jv.adj_mode = 'single' AND jv.party_type = 'customer' AND jv.party_id = c.id)
                        OR (jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = c.id)
                        OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = c.id)
                    )
                ), 0) as unallocated_adjustment";

    // ---- Unbilled view: Receive Vouchers + Sale Returns with no bill_no ----
    if ($view_mode === 'unbilled') {
        // receive_voucher columns used: id, voucher_number, customer_id, tenant_id,
        // company_id, amount, voucher_date, bill_no. sale_return columns used: id,
        // bill_no, customer_id, tenant_id, company_id, net_amount, sale_date,
        // sale_invoice_no, status (confirmed against customer-ledger.php).

        $rvDateCond = str_replace('{date_col}', 'rv.voucher_date', $dateCondition);
        $srDateCond = str_replace('{date_col}', 'sr.sale_date', $dateCondition);
        $rvCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);
        $srCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);

        $sql = "
            SELECT
                rv.id,
                rv.voucher_number as ref_no,
                rv.voucher_date as ref_date,
                rv.amount as net_amount,
                c.customer_code,
                c.customer_name,
                c.address,
                'Receive Voucher' as row_type
            FROM receive_voucher rv
            JOIN customers c ON rv.customer_id = c.id
            WHERE rv.tenant_id = ?
            AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
            {$rvDateCond}
            {$rvCompCond}
            {$sharedFilterSql}

            UNION ALL

            SELECT
                sr.id,
                sr.bill_no as ref_no,
                sr.sale_date as ref_date,
                sr.net_amount,
                c.customer_code,
                c.customer_name,
                c.address,
                'Sale Return' as row_type
            FROM sale_return sr
            JOIN customers c ON sr.customer_id = c.id
            WHERE sr.tenant_id = ? AND sr.status = 'Posted'
            AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = '')
            {$srDateCond}
            {$srCompCond}
            {$sharedFilterSql}

            ORDER BY ref_date DESC
        ";

        $params = [$tenant_id];
        $params = array_merge($params, $dateParams);
        if ($company_id) $params[] = $company_id;
        $params = array_merge($params, $sharedFilterParams);

        $params[] = $tenant_id;
        $params = array_merge($params, $dateParams);
        if ($company_id) $params[] = $company_id;
        $params = array_merge($params, $sharedFilterParams);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reportData = [];
        $totalUnbilled = 0;

        foreach ($rows as $index => $row) {
            $amount = (float)$row['net_amount'];
            $totalUnbilled += $amount;

            $reportData[] = [
                'id'             => $index + 1,
                'customerCode'   => $row['customer_code'],
                'customerName'   => $row['customer_name'],
                'address'        => $row['address'] ?? 'N/A',
                'invoiceNo'      => $row['ref_no'],
                'invoiceDate'    => $row['ref_date'],
                'type'           => $row['row_type'],
                'amount'         => $currency_symbol . number_format($amount, 2),
                'outstandingRaw' => $amount,
                'daysOverdue'    => null,
                'bucket'         => '-'
            ];
        }

        echo json_encode([
            'success' => true,
            'data'    => $reportData,
            'summary' => [
                'total_overdue' => $currency_symbol . number_format($totalUnbilled, 2),
                'bucket_15'     => $currency_symbol . '0.00', 'count_15' => 0,
                'bucket_25'     => $currency_symbol . '0.00', 'count_25' => 0,
                'bucket_45'     => $currency_symbol . '0.00', 'count_45' => 0,
            ],
            'currency_symbol' => $currency_symbol
        ]);
        exit;
    }

    // ---- Default view: existing invoices aging report ----

    $siDateCond = str_replace('{date_col}', 'si.sale_date', $dateCondition);
    $obiDateCond = str_replace('{date_col}', 'obi.invoice_date', $dateCondition);
    $siCompCond = str_replace('{company_col}', 'si.company_id', $companyCondition);
    $obiCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);
    // Unbilled Receive Voucher / Sale Return rows, folded into the default
    // list as their own credit rows (same treatment as opening credit above)
    // so a customer's advance/unbilled receipts show up in Aging/Recovery
    // without needing the separate view_mode=unbilled toggle.
    $rvDateCond = str_replace('{date_col}', 'rv.voucher_date', $dateCondition);
    $srDateCond = str_replace('{date_col}', 'sr.sale_date', $dateCondition);
    $rvCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);
    $srCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);
    // Payment Voucher (Payment to Customer) rows — an advance/charge paid out
    // to the customer debits Trade Debtors (see payment-add.php's
    // debit_account_id = 2 for payment_type='customer'), so it INCREASES what
    // the customer owes, same treatment as customer-ledger.php's
    // $prev_payment_vouchers. Folded in as its own positive-amount row, same
    // pattern as the unbilled Receive Voucher/Sale Return rows above.
    $pvDateCond = str_replace('{date_col}', 'pv.voucher_date', $dateCondition);
    $pvCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);

    $sql = "
        SELECT
            si.id,
            si.bill_no,
            si.sale_date as invoice_date,
            si.net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            COALESCE(SUM(DISTINCT rv.amount), 0) as paid_amount,
            COALESCE(SUM(DISTINCT sr.net_amount), 0) as returned_amount,
            DATEDIFF(CURDATE(), si.sale_date) as days_overdue,
            'Sale Invoice' as invoice_type,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
{$returnSubquery},
{$adjustmentSubquery},
{$transferSubquery}
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        LEFT JOIN receive_voucher rv ON rv.bill_no = si.bill_no AND rv.tenant_id = ?
        LEFT JOIN sale_return sr ON sr.sale_invoice_no = si.id AND sr.tenant_id = ? AND sr.status = 'Posted'
        WHERE si.tenant_id = ? AND si.status = 'Posted'
        {$siDateCond}
        {$siCompCond}
        {$sharedFilterSql}
        GROUP BY si.id
        HAVING (si.net_amount - paid_amount - returned_amount) > 0

        UNION ALL

        SELECT
            obi.id,
            obi.invoice_number as bill_no,
            obi.invoice_date,
            (obi.debit - obi.credit) as net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            COALESCE(SUM(DISTINCT rv.amount), 0) as paid_amount,
            0 as returned_amount,
            DATEDIFF(CURDATE(), obi.invoice_date) as days_overdue,
            (CASE WHEN obi.credit > 0 THEN 'Opening Balance (Credit)' ELSE 'Opening Balance' END) as invoice_type,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
{$returnSubquery},
{$adjustmentSubquery},
{$transferSubquery}
        FROM opening_balance_invoices obi
        JOIN customers c ON obi.customer_id = c.id
        LEFT JOIN receive_voucher rv ON rv.bill_no = obi.invoice_number AND rv.tenant_id = ?
        WHERE obi.tenant_id = ?
        {$obiDateCond}
        {$obiCompCond}
        {$sharedFilterSql}
        GROUP BY obi.id
        HAVING (net_amount - paid_amount) <> 0

        UNION ALL

        SELECT
            rv.id,
            rv.voucher_number as bill_no,
            rv.voucher_date as invoice_date,
            -rv.amount as net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            0 as paid_amount,
            0 as returned_amount,
            DATEDIFF(CURDATE(), rv.voucher_date) as days_overdue,
            'Unbilled Receipt (Credit)' as invoice_type,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
{$returnSubquery},
{$adjustmentSubquery},
{$transferSubquery}
        FROM receive_voucher rv
        JOIN customers c ON rv.customer_id = c.id
        WHERE rv.tenant_id = ?
        AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
        {$rvDateCond}
        {$rvCompCond}
        {$sharedFilterSql}

        UNION ALL

        SELECT
            sr.id,
            sr.bill_no as bill_no,
            sr.sale_date as invoice_date,
            -sr.net_amount as net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            0 as paid_amount,
            0 as returned_amount,
            DATEDIFF(CURDATE(), sr.sale_date) as days_overdue,
            'Unbilled Return (Credit)' as invoice_type,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
{$returnSubquery},
{$adjustmentSubquery},
{$transferSubquery}
        FROM sale_return sr
        JOIN customers c ON sr.customer_id = c.id
        WHERE sr.tenant_id = ? AND sr.status = 'Posted'
        AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = '')
        {$srDateCond}
        {$srCompCond}
        {$sharedFilterSql}

        UNION ALL

        SELECT
            pv.id,
            pv.voucher_number as bill_no,
            pv.voucher_date as invoice_date,
            pv.amount as net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            0 as paid_amount,
            0 as returned_amount,
            DATEDIFF(CURDATE(), pv.voucher_date) as days_overdue,
            'Payment Voucher (Advance to Customer)' as invoice_type,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
{$returnSubquery},
{$adjustmentSubquery},
{$transferSubquery}
        FROM payment_voucher pv
        JOIN customers c ON pv.customer_id = c.id
        WHERE pv.tenant_id = ?
        {$pvDateCond}
        {$pvCompCond}
        {$sharedFilterSql}

        ORDER BY days_overdue DESC
    ";

    // sale_invoice branch params, in exact order they appear in the query:
    // unallocated_received.tenant_id, unallocated_sale_return.tenant_id, unallocated_adjustment.tenant_id,
    // transfer_subquery.tenant_id, rv.tenant_id, sr.tenant_id, si.tenant_id, [dateParams], [company_id], [sharedFilterParams]
    $params = [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id];
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, $sharedFilterParams);

    // opening_balance branch params:
    // unallocated_received.tenant_id, unallocated_sale_return.tenant_id, unallocated_adjustment.tenant_id,
    // transfer_subquery.tenant_id, rv.tenant_id, obi.tenant_id, [dateParams], [company_id], [sharedFilterParams]
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, $sharedFilterParams);

    // unbilled Receive Voucher branch params:
    // unallocated_received.tenant_id, unallocated_sale_return.tenant_id, unallocated_adjustment.tenant_id,
    // transfer_subquery.tenant_id, rv.tenant_id (WHERE), [dateParams], [company_id], [sharedFilterParams]
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, $sharedFilterParams);

    // unbilled Sale Return branch params: same pattern
    // unallocated_received.tenant_id, unallocated_sale_return.tenant_id, unallocated_adjustment.tenant_id,
    // transfer_subquery.tenant_id, sr.tenant_id (WHERE), [dateParams], [company_id], [sharedFilterParams]
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, $sharedFilterParams);

    // Payment Voucher (Payment to Customer) branch params: same pattern
    // unallocated_received.tenant_id, unallocated_sale_return.tenant_id, unallocated_adjustment.tenant_id,
    // transfer_subquery.tenant_id, pv.tenant_id (WHERE), [dateParams], [company_id], [sharedFilterParams]
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, $sharedFilterParams);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reportData = [];

    // Bucket scheme aligned with the frontend's cumulative bucket cards
    // (15+, 25+, 45+ days overdue) instead of the old fixed-range scheme.
    $summary = [
        'total_overdue' => 0,
        'bucket_15' => 0, 'count_15' => 0,
        'bucket_25' => 0, 'count_25' => 0,
        'bucket_45' => 0, 'count_45' => 0,
    ];

    foreach ($invoices as $index => $invoice) {
        $outstanding = $invoice['net_amount'] - $invoice['paid_amount'] - $invoice['returned_amount'];
        $days = (int)$invoice['days_overdue'];

        $summary['total_overdue'] += $outstanding;

        if ($days >= 45) {
            $bucket = '45+';
            $summary['bucket_45'] += $outstanding;
            $summary['count_45']++;
        } elseif ($days >= 25) {
            $bucket = '25+';
            $summary['bucket_25'] += $outstanding;
            $summary['count_25']++;
        } elseif ($days >= 15) {
            $bucket = '15+';
            $summary['bucket_15'] += $outstanding;
            $summary['count_15']++;
        } else {
            $bucket = 'current';
        }

        $reportData[] = [
            'id'                  => $index + 1,
            'customerCode'        => $invoice['customer_code'],
            'customerName'        => $invoice['customer_name'],
            'address'             => $invoice['address'] ?? 'N/A',
            'invoiceNo'           => $invoice['bill_no'],
            'invoiceDate'         => $invoice['invoice_date'],
            'type'                => $invoice['invoice_type'],
            'amount'              => $currency_symbol . number_format($outstanding, 2),
            'outstandingRaw'      => $outstanding,
            'unallocatedReceived' => (float)$invoice['unallocated_received'],
            'unallocatedTransfer' => (float)$invoice['unallocated_transfer'],
            'unallocatedSaleReturn' => (float)$invoice['unallocated_sale_return'],
            'unallocatedAdjustment' => (float)$invoice['unallocated_adjustment'],
            'daysOverdue'         => $days,
            'bucket'              => $bucket
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $reportData,
        'summary' => [
            'total_overdue' => $currency_symbol . number_format($summary['total_overdue'], 2),
            'bucket_15'     => $currency_symbol . number_format($summary['bucket_15'], 2),
            'bucket_25'     => $currency_symbol . number_format($summary['bucket_25'], 2),
            'bucket_45'     => $currency_symbol . number_format($summary['bucket_45'], 2),
            'count_15'      => $summary['count_15'],
            'count_25'      => $summary['count_25'],
            'count_45'      => $summary['count_45'],
        ],
        'currency_symbol' => $currency_symbol
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}