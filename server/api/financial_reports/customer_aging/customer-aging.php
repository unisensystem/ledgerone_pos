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

    $siDateCond = str_replace('{date_col}', 'si.sale_date', $dateCondition);
    $obiDateCond = str_replace('{date_col}', 'obi.invoice_date', $dateCondition);
    $siCompCond = str_replace('{company_col}', 'si.company_id', $companyCondition);
    $obiCompCond = str_replace('{company_col}', 'c.company_id', $companyCondition);

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
            'Sale Invoice' as invoice_type
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
            obi.debit as net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            COALESCE(SUM(DISTINCT rv.amount), 0) as paid_amount,
            0 as returned_amount,
            DATEDIFF(CURDATE(), obi.invoice_date) as days_overdue,
            'Opening Balance' as invoice_type
        FROM opening_balance_invoices obi
        JOIN customers c ON obi.customer_id = c.id
        LEFT JOIN receive_voucher rv ON rv.bill_no = obi.invoice_number AND rv.tenant_id = ?
        WHERE obi.tenant_id = ?
        {$obiDateCond}
        {$obiCompCond}
        {$sharedFilterSql}
        GROUP BY obi.id
        HAVING (obi.debit - paid_amount) > 0

        ORDER BY days_overdue DESC
    ";

    // sale_invoice branch params, in exact order they appear in the query:
    // rv.tenant_id, sr.tenant_id, si.tenant_id, [dateParams], [company_id], [sharedFilterParams]
    $params = [$tenant_id, $tenant_id, $tenant_id];
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, $sharedFilterParams);

    // opening_balance branch params:
    // rv.tenant_id, obi.tenant_id, [dateParams], [company_id], [sharedFilterParams]
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
            'id'           => $index + 1,
            'customerCode' => $invoice['customer_code'],
            'customerName' => $invoice['customer_name'],
            'address'      => $invoice['address'] ?? 'N/A',
            'invoiceNo'    => $invoice['bill_no'],
            'invoiceDate'  => $invoice['invoice_date'],
            'type'         => $invoice['invoice_type'],
            'amount'       => $currency_symbol . number_format($outstanding, 2),
            'daysOverdue'  => $days,
            'bucket'       => $bucket
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
