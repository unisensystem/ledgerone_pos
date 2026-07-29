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
    $company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
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

    // Build date condition
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

    $companyCondition = $company_id ? ' AND {company_col} = ?' : '';

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
        GROUP BY obi.id
        HAVING (obi.debit - paid_amount) > 0

        ORDER BY days_overdue DESC
    ";

    // sale_invoice query: rv.tenant_id, sr.tenant_id, si.tenant_id, [dateParams], [company_id]
    $params = [$tenant_id, $tenant_id, $tenant_id];
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;
    // opening_balance query: rv.tenant_id, obi.tenant_id, [dateParams], [company_id]
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params = array_merge($params, $dateParams);
    if ($company_id) $params[] = $company_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reportData = [];
    $summary = [
        'total_outstanding' => 0,
        'bucket_current' => 0, 'count_current' => 0,
        'bucket_1_15'    => 0, 'count_1_15'    => 0,
        'bucket_16_30'   => 0, 'count_16_30'   => 0,
        'bucket_31_45'   => 0, 'count_31_45'   => 0,
        'bucket_45'      => 0, 'count_45'       => 0,
    ];

    foreach ($invoices as $index => $invoice) {
        $outstanding = $invoice['net_amount'] - $invoice['paid_amount'] - $invoice['returned_amount'];
        $days = (int)$invoice['days_overdue'];

        if ($days <= 0) {
            $bucket = 'current';
            $summary['bucket_current'] += $outstanding;
            $summary['count_current']++;
        } elseif ($days <= 15) {
            $bucket = '1-15';
            $summary['bucket_1_15'] += $outstanding;
            $summary['count_1_15']++;
        } elseif ($days <= 30) {
            $bucket = '16-30';
            $summary['bucket_16_30'] += $outstanding;
            $summary['count_16_30']++;
        } elseif ($days <= 45) {
            $bucket = '31-45';
            $summary['bucket_31_45'] += $outstanding;
            $summary['count_31_45']++;
        } else {
            $bucket = '45+';
            $summary['bucket_45'] += $outstanding;
            $summary['count_45']++;
        }

        $summary['total_outstanding'] += $outstanding;

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
            'total_outstanding' => $currency_symbol . number_format($summary['total_outstanding'], 2),
            'bucket_current'    => $currency_symbol . number_format($summary['bucket_current'], 2),
            'bucket_1_15'       => $currency_symbol . number_format($summary['bucket_1_15'], 2),
            'bucket_16_30'      => $currency_symbol . number_format($summary['bucket_16_30'], 2),
            'bucket_31_45'      => $currency_symbol . number_format($summary['bucket_31_45'], 2),
            'bucket_45'         => $currency_symbol . number_format($summary['bucket_45'], 2),
            'count_current'     => $summary['count_current'],
            'count_1_15'        => $summary['count_1_15'],
            'count_16_30'       => $summary['count_16_30'],
            'count_31_45'       => $summary['count_31_45'],
            'count_45'          => $summary['count_45'],
        ],
        'currency_symbol' => $currency_symbol
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}