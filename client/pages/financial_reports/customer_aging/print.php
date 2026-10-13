<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

// ── Currency ─────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency        = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

// ── User ─────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_name = $stmt->fetchColumn() ?: 'Unknown';

// ── GET params ───────────────────────────────────────────────────────────────
$company_id  = !empty($_GET['company_id'])  ? $_GET['company_id']  : null;
$customer_id = !empty($_GET['customer_id']) ? $_GET['customer_id'] : null;
$country_id  = !empty($_GET['country_id'])  ? $_GET['country_id']  : null;
$region_id   = !empty($_GET['region_id'])   ? $_GET['region_id']   : null;
$city_id     = !empty($_GET['city_id'])     ? $_GET['city_id']     : null;
$zone_id     = !empty($_GET['zone_id'])     ? $_GET['zone_id']     : null;
$area_id     = !empty($_GET['area_id'])     ? $_GET['area_id']     : null;
$date_range  = $_GET['date_range']  ?? 'all';
$date_from   = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to     = !empty($_GET['date_to'])   ? $_GET['date_to']   : null;
$auto_clear  = !empty($_GET['auto_clear']);

// ── Date condition ────────────────────────────────────────────────────────────
$dateCondition = '';
$dateParams    = [];
if ($date_from && $date_to) {
    $dateCondition = ' AND {date_col} BETWEEN ? AND ?';
    $dateParams    = [$date_from, $date_to];
} elseif ($date_range === '30days') {
    $dateCondition = ' AND {date_col} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
} elseif ($date_range === '90days') {
    $dateCondition = ' AND {date_col} >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
}
$siDateCond  = str_replace('{date_col}', 'si.sale_date',    $dateCondition);
$obiDateCond = str_replace('{date_col}', 'obi.invoice_date', $dateCondition);
$pvDateCond  = str_replace('{date_col}', 'pv.voucher_date', $dateCondition);

// ── Shared territory filter ───────────────────────────────────────────────────
$companyCondition  = $company_id  ? ' AND {company_col} = ?' : '';
$customerCondition = $customer_id ? ' AND c.id = ?'          : '';
$countryCondition  = $country_id  ? ' AND c.country_id = ?'  : '';
$regionCondition   = $region_id   ? ' AND c.region_id = ?'   : '';
$cityCondition     = $city_id     ? ' AND c.city_id = ?'     : '';
$zoneCondition     = $zone_id     ? ' AND c.city_zone_id = ?' : '';
$areaCondition     = $area_id     ? ' AND c.area_id = ?'     : '';

$sharedFilterSql    = $customerCondition . $countryCondition . $regionCondition
                    . $cityCondition . $zoneCondition . $areaCondition;
$sharedFilterParams = [];
if ($customer_id) $sharedFilterParams[] = $customer_id;
if ($country_id)  $sharedFilterParams[] = $country_id;
if ($region_id)   $sharedFilterParams[] = $region_id;
if ($city_id)     $sharedFilterParams[] = $city_id;
if ($zone_id)     $sharedFilterParams[] = $zone_id;
if ($area_id)     $sharedFilterParams[] = $area_id;

$siCompCond  = str_replace('{company_col}', 'si.company_id', $companyCondition);
$obiCompCond = str_replace('{company_col}', 'c.company_id',  $companyCondition);
$pvCompCond  = str_replace('{company_col}', 'c.company_id',  $companyCondition);

// ── SQL ───────────────────────────────────────────────────────────────────────
$sql = "
    SELECT
        si.id,
        si.bill_no,
        si.sale_date AS invoice_date,
        si.net_amount,
        c.id AS customer_id_val,
        c.customer_code,
        c.customer_name,
        c.address,
        COALESCE(SUM(DISTINCT rv.amount), 0) AS paid_amount,
        COALESCE(SUM(DISTINCT sr.net_amount), 0) AS returned_amount,
        DATEDIFF(CURDATE(), si.sale_date) AS days_overdue,
        'Sale Invoice' AS invoice_type,
        COALESCE((
            SELECT SUM(rv2.amount)
            FROM receive_voucher rv2
            WHERE rv2.customer_id = c.id
            AND rv2.tenant_id = ?
            AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
        ), 0) AS unallocated_received
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
        obi.invoice_number AS bill_no,
        obi.invoice_date,
        obi.debit AS net_amount,
        c.id AS customer_id_val,
        c.customer_code,
        c.customer_name,
        c.address,
        COALESCE(SUM(DISTINCT rv.amount), 0) AS paid_amount,
        0 AS returned_amount,
        DATEDIFF(CURDATE(), obi.invoice_date) AS days_overdue,
        'Opening Balance' AS invoice_type,
        COALESCE((
            SELECT SUM(rv2.amount)
            FROM receive_voucher rv2
            WHERE rv2.customer_id = c.id
            AND rv2.tenant_id = ?
            AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
        ), 0) AS unallocated_received
    FROM opening_balance_invoices obi
    JOIN customers c ON obi.customer_id = c.id
    LEFT JOIN receive_voucher rv ON rv.bill_no = obi.invoice_number AND rv.tenant_id = ?
    WHERE obi.tenant_id = ?
    {$obiDateCond}
    {$obiCompCond}
    {$sharedFilterSql}
    GROUP BY obi.id
    HAVING (obi.debit - paid_amount) > 0

    UNION ALL

    -- Payment Voucher to Customer (an advance/charge paid out to the customer
    -- debits Trade Debtors, increasing what the customer owes — same treatment
    -- as the main customer-aging.php API and customer-ledger.php).
    SELECT
        pv.id,
        pv.voucher_number AS bill_no,
        pv.voucher_date AS invoice_date,
        pv.amount AS net_amount,
        c.id AS customer_id_val,
        c.customer_code,
        c.customer_name,
        c.address,
        0 AS paid_amount,
        0 AS returned_amount,
        DATEDIFF(CURDATE(), pv.voucher_date) AS days_overdue,
        'Payment Voucher (Advance to Customer)' AS invoice_type,
        COALESCE((
            SELECT SUM(rv2.amount)
            FROM receive_voucher rv2
            WHERE rv2.customer_id = c.id
            AND rv2.tenant_id = ?
            AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
        ), 0) AS unallocated_received
    FROM payment_voucher pv
    JOIN customers c ON pv.customer_id = c.id
    WHERE pv.tenant_id = ?
    {$pvDateCond}
    {$pvCompCond}
    {$sharedFilterSql}

    ORDER BY days_overdue DESC
";

// sale_invoice branch: unallocated.tenant_id, rv.tenant_id, sr.tenant_id, si.tenant_id, [date], [company], [shared]
$params = [$tenant_id, $tenant_id, $tenant_id, $tenant_id];
$params = array_merge($params, $dateParams);
if ($company_id) $params[] = $company_id;
$params = array_merge($params, $sharedFilterParams);

// opening_balance branch: unallocated.tenant_id, rv.tenant_id, obi.tenant_id, [date], [company], [shared]
$params[] = $tenant_id;
$params[] = $tenant_id;
$params[] = $tenant_id;
$params = array_merge($params, $dateParams);
if ($company_id) $params[] = $company_id;
$params = array_merge($params, $sharedFilterParams);

// payment_voucher branch: unallocated.tenant_id, pv.tenant_id, [date], [company], [shared]
$params[] = $tenant_id;
$params[] = $tenant_id;
$params = array_merge($params, $dateParams);
if ($company_id) $params[] = $company_id;
$params = array_merge($params, $sharedFilterParams);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── FIFO auto-clear simulation ────────────────────────────────────────────────
if ($auto_clear) {
    // Group by customer, sort oldest first, distribute unallocated FIFO
    $customerGroups = [];
    foreach ($invoices as $inv) {
        $key = $inv['customer_name'];
        if (!isset($customerGroups[$key])) {
            $customerGroups[$key] = ['unallocated' => (float)$inv['unallocated_received'], 'bills' => []];
        }
        $customerGroups[$key]['bills'][] = $inv;
    }
    foreach ($customerGroups as &$cg) {
        usort($cg['bills'], fn($a, $b) => strcmp($a['invoice_date'], $b['invoice_date']));
        $remaining = $cg['unallocated'];
        foreach ($cg['bills'] as &$bill) {
            $outstanding = $bill['net_amount'] - $bill['paid_amount'] - $bill['returned_amount'];
            $cleared     = min($remaining, $outstanding);
            $remaining  -= $cleared;
            $bill['sim_outstanding'] = $outstanding - $cleared;
            $bill['sim_cleared']     = $cleared;
            $bill['sim_status']      = $bill['sim_outstanding'] <= 0 ? 'cleared'
                                     : ($cleared > 0 ? 'partial' : 'pending');
        }
    }
    // Flatten back, drop fully cleared
    $invoices = [];
    foreach ($customerGroups as $cg) {
        foreach ($cg['bills'] as $bill) {
            if ($bill['sim_outstanding'] > 0) {
                $invoices[] = $bill;
            }
        }
    }
    usort($invoices, fn($a, $b) => $b['days_overdue'] - $a['days_overdue']);
}

// ── Bucket + summary ──────────────────────────────────────────────────────────
$summary = [
    'total_overdue' => 0,
    'bucket_15' => 0, 'count_15' => 0,
    'bucket_25' => 0, 'count_25' => 0,
    'bucket_45' => 0, 'count_45' => 0,
];

$reportData = [];
foreach ($invoices as $i => $inv) {
    $outstanding = $auto_clear
        ? $inv['sim_outstanding']
        : ($inv['net_amount'] - $inv['paid_amount'] - $inv['returned_amount']);
    $days = (int)$inv['days_overdue'];

    $summary['total_overdue'] += $outstanding;
    if ($days >= 45)      { $bucket = '45+'; $summary['bucket_45'] += $outstanding; $summary['count_45']++; }
    elseif ($days >= 25)  { $bucket = '25+'; $summary['bucket_25'] += $outstanding; $summary['count_25']++; }
    elseif ($days >= 15)  { $bucket = '15+'; $summary['bucket_15'] += $outstanding; $summary['count_15']++; }
    else                  { $bucket = 'current'; }

    $reportData[] = [
        'sn'           => $i + 1,
        'customerCode' => $inv['customer_code'],
        'customerName' => $inv['customer_name'],
        'address'      => $inv['address'] ?? '',
        'invoiceNo'    => $inv['bill_no'],
        'invoiceDate'  => $inv['invoice_date'],
        'type'         => $inv['invoice_type'],
        'amount'       => $outstanding,
        'daysOverdue'  => $days,
        'bucket'       => $bucket,
        'simStatus'    => $inv['sim_status'] ?? null,
        'simCleared'   => $inv['sim_cleared'] ?? 0,
    ];
}

// ── Filter label helpers ──────────────────────────────────────────────────────
$filterParts = [];
if ($company_id) {
    $s = $pdo->prepare("SELECT company_name FROM companies WHERE id = ?");
    $s->execute([$company_id]);
    $cn = $s->fetchColumn();
    if ($cn) $filterParts[] = 'Company: ' . htmlspecialchars($cn);
}
if ($customer_id) {
    $s = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ?");
    $s->execute([$customer_id]);
    $cn = $s->fetchColumn();
    if ($cn) $filterParts[] = 'Customer: ' . htmlspecialchars($cn);
}
if ($date_from && $date_to) {
    $filterParts[] = 'Date: ' . date('d M Y', strtotime($date_from)) . ' – ' . date('d M Y', strtotime($date_to));
} elseif ($date_range === '30days') {
    $filterParts[] = 'Last 30 Days';
} elseif ($date_range === '90days') {
    $filterParts[] = 'Last 90 Days';
}
if ($auto_clear) $filterParts[] = 'Auto-Clear: ON';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Aging Report - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; background: #fff; }

        .print-container { max-width: 1100px; margin: 0 auto; padding: 14px; }

        .print-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
        .print-header .report-title { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .print-header .sub          { font-size: 10px; color: #555; margin-top: 3px; }

        .filter-bar {
            display: flex; flex-wrap: wrap; gap: 4px 16px;
            background: #f5f5f5; border: 1px solid #ddd;
            padding: 5px 10px; margin-bottom: 8px; font-size: 9px;
        }
        .filter-bar strong { color: #000; }
        .filter-bar span   { color: #555; }

        .summary-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; margin-bottom: 10px; }
        .summary-box { border: 1px solid #ddd; border-left: 3px solid #1f7bff; padding: 5px 10px; text-align: center; }
        .summary-box.warning { border-left-color: #e8a23f; }
        .summary-box.error   { border-left-color: #e34f4f; }
        .summary-box .s-label { font-size: 9px; color: #666; margin-bottom: 2px; }
        .summary-box .s-value { font-size: 12px; font-weight: bold; }
        .summary-box .s-count { font-size: 9px; color: #999; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #f0f0f0; border: 1px solid #bbb; padding: 5px 4px; font-size: 9px; font-weight: bold; text-align: center; }
        td { border: 1px solid #ccc; padding: 4px; font-size: 11px; vertical-align: middle; }
        tr:nth-child(even) td { background: #fafafa; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        .bucket-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: 600; }
        .badge-current { background: #e6f9f0; color: #2fbf71; }
        .badge-15      { background: #e8f1ff; color: #1f7bff; }
        .badge-25      { background: #fef6e6; color: #b8860b; }
        .badge-45      { background: #fde8e8; color: #e34f4f; }

        .status-partial { color: #e8a23f; font-weight: 600; }
        .status-pending { color: #555; }

        .print-footer { border-top: 1px solid #ddd; padding-top: 6px; margin-top: 6px; text-align: center; font-size: 9px; color: #888; }

        .no-print { position: fixed; top: 16px; right: 16px; display: flex; gap: 8px; z-index: 999; }
        .btn-print { padding: 9px 18px; background: #1f7bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-close { padding: 9px 18px; background: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .print-container { padding: 8px; }
            @page { size: A4 landscape; margin: 10mm; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; Print</button>
    <button class="btn-close" onclick="window.close()">&#10005; Close</button>
</div>

<div class="print-container">

    <div class="print-header">
        <div class="report-title">
            Customer Aging Report
            <?php if ($auto_clear): ?><span style="font-size:10px;background:#1f7bff;color:#fff;padding:2px 8px;border-radius:10px;vertical-align:middle;margin-left:6px;">Auto-Clear</span><?php endif; ?>
        </div>
        <?php if (!empty($filterParts)): ?>
        <div class="sub">Filters: <?php echo implode(' &nbsp;|&nbsp; ', $filterParts); ?></div>
        <?php endif; ?>
        <div class="sub">Generated by <?php echo htmlspecialchars($user_name); ?> &bull; <?php echo date('d M Y, h:i A'); ?></div>
    </div>

    <div class="summary-row">
        <div class="summary-box">
            <div class="s-label">Total Outstanding</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($summary['total_overdue'], 2); ?></div>
            <div class="s-count"><?php echo count($reportData); ?> invoice(s)</div>
        </div>
        <div class="summary-box">
            <div class="s-label">15+ Days</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($summary['bucket_15'], 2); ?></div>
            <div class="s-count"><?php echo $summary['count_15']; ?> invoice(s)</div>
        </div>
        <div class="summary-box warning">
            <div class="s-label">25+ Days</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($summary['bucket_25'], 2); ?></div>
            <div class="s-count"><?php echo $summary['count_25']; ?> invoice(s)</div>
        </div>
        <div class="summary-box error">
            <div class="s-label">45+ Days</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($summary['bucket_45'], 2); ?></div>
            <div class="s-count"><?php echo $summary['count_45']; ?> invoice(s)</div>
        </div>
    </div>

    <?php if (empty($reportData)): ?>
        <div style="text-align:center;padding:30px;color:#888;font-size:13px;">No overdue invoices found.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width:2%">S#</th>
                <th style="width:6%">Customer Code</th>
                <th style="width:12%">Customer Name</th>
                <th style="width:12%">Address</th>
                <th style="width:6%">Invoice No</th>
                <th style="width:5%">Date</th>
                <th style="width:6%">Type</th>
                <th class="text-right" style="width:7%">Amount</th>
                <?php if ($auto_clear): ?><th class="text-right" style="width:6%">Auto-Cleared</th><?php endif; ?>
                <th class="text-center" style="width:5%">Days</th>
                <th class="text-center" style="width:6%">Bucket</th>
                <?php if ($auto_clear): ?><th class="text-center" style="width:5%">Status</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reportData as $item):
            $badgeClass = match($item['bucket']) {
                '15+' => 'badge-15',
                '25+' => 'badge-25',
                '45+' => 'badge-45',
                default => 'badge-current',
            };
            $bucketLabel = $item['bucket'] === 'current' ? 'Current' : $item['bucket'] . ' days';
        ?>
            <tr>
                <td class="text-center"><?php echo $item['sn']; ?></td>
                <td><?php echo htmlspecialchars($item['customerCode']); ?></td>
                <td><?php echo htmlspecialchars($item['customerName']); ?></td>
                <td><?php echo htmlspecialchars($item['address']); ?></td>
                <td><?php echo htmlspecialchars($item['invoiceNo']); ?></td>
                <td><?php echo !empty($item['invoiceDate']) ? date('d-m-y', strtotime($item['invoiceDate'])) : ''; ?></td>
                <td><?php echo htmlspecialchars($item['type']); ?></td>
                <td class="text-right"><?php echo $currency_symbol . number_format($item['amount'], 2); ?></td>
                <?php if ($auto_clear): ?>
                <td class="text-right"><?php echo $item['simCleared'] > 0 ? $currency_symbol . number_format($item['simCleared'], 2) : '—'; ?></td>
                <?php endif; ?>
                <td class="text-center"><?php echo $item['daysOverdue']; ?></td>
                <td class="text-center"><span class="bucket-badge <?php echo $badgeClass; ?>"><?php echo $bucketLabel; ?></span></td>
                <?php if ($auto_clear): ?>
                <td class="text-center <?php echo $item['simStatus'] ? 'status-' . $item['simStatus'] : ''; ?>">
                    <?php echo $item['simStatus'] ? ucfirst($item['simStatus']) : ''; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="print-footer">
        LedgerOne ERP &bull; Customer Aging Report &bull; This is a computer-generated document
    </div>

</div>
</body>
</html>
