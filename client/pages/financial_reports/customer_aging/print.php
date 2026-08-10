<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

$company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
$date_range = $_GET['date_range'] ?? 'all';
$date_from  = $_GET['date_from'] ?? null;
$date_to    = $_GET['date_to'] ?? null;
$bucket_filter = $_GET['bucket'] ?? 'all';

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

$siDateCond  = str_replace('{date_col}', 'si.sale_date', $dateCondition);
$obiDateCond = str_replace('{date_col}', 'obi.invoice_date', $dateCondition);
$siCompCond  = str_replace('{company_col}', 'si.company_id', $companyCondition);
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

$params = [$tenant_id, $tenant_id, $tenant_id];
$params = array_merge($params, $dateParams);
if ($company_id) $params[] = $company_id;
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

foreach ($invoices as $invoice) {
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

    // Apply bucket filter
    if ($bucket_filter !== 'all' && $bucket !== $bucket_filter) continue;

    $reportData[] = [
        'id'           => 0, // will re-index below
        'customerCode' => $invoice['customer_code'],
        'customerName' => $invoice['customer_name'],
        'address'      => $invoice['address'] ?? 'N/A',
        'invoiceNo'    => $invoice['bill_no'],
        'invoiceDate'  => $invoice['invoice_date'],
        'type'         => $invoice['invoice_type'],
        'amount'       => $outstanding,
        'daysOverdue'  => $days,
        'bucket'       => $bucket
    ];
}

// Re-index S# after bucket filter
foreach ($reportData as $i => &$row) {
    $row['id'] = $i + 1;
}
unset($row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Aging Report - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; color: #000; }

        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        .print-header h1 { font-size: 24px; margin-bottom: 5px; }
        .print-header p { font-size: 12px; color: #666; }

        .summary-section {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }
        .summary-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            border-left: 4px solid #1f7bff;
        }
        .summary-box.warning { border-left-color: #e8b23f; }
        .summary-box.error   { border-left-color: #e34f4f; }
        .summary-box .value  { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .summary-box .label  { font-size: 11px; color: #666; }
        .summary-box .count  { font-size: 10px; color: #999; margin-top: 3px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        .bucket-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-current { background: #e6f9f0; color: #2fbf71; }
        .badge-1-15    { background: #e8f1ff; color: #1f7bff; }
        .badge-16-30, .badge-31-45 { background: #fef6e6; color: #b8860b; }
        .badge-45plus  { background: #fde8e8; color: #e34f4f; }

        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
            color: #666;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #1f7bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover { background-color: #1a6cdc; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .summary-section { grid-template-columns: repeat(6, 1fr); }
            @page { margin: 1cm; }
        }

        @media (max-width: 900px) {
            .summary-section { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print Report</button>

    <div class="print-header">
        <h1>Customer Aging Report</h1>
        <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
        <?php
            $filterParts = [];
            if ($company_id) {
                $cs = $pdo->prepare("SELECT company_name FROM companies WHERE id = ?");
                $cs->execute([$company_id]);
                $cn = $cs->fetchColumn();
                if ($cn) $filterParts[] = 'Company: ' . htmlspecialchars($cn);
            }
            if ($date_from && $date_to) {
                $filterParts[] = 'Date: ' . $date_from . ' to ' . $date_to;
            } elseif ($date_range === '30days') {
                $filterParts[] = 'Date Range: Last 30 Days';
            } elseif ($date_range === '90days') {
                $filterParts[] = 'Date Range: Last 90 Days';
            }
            if ($bucket_filter !== 'all') {
                $bucketLabels = [
                    'current' => 'Current (Today)',
                    '1-15'    => '1 - 15 Days',
                    '16-30'   => '16 - 30 Days',
                    '31-45'   => '31 - 45 Days',
                    '45+'     => '45+ Days',
                ];
                $filterParts[] = 'Bucket: ' . ($bucketLabels[$bucket_filter] ?? $bucket_filter);
            }
            if (!empty($filterParts)):
        ?>
        <p style="margin-top:4px; font-size:11px; color:#444;">Filters: <?php echo implode(' &nbsp;|&nbsp; ', $filterParts); ?></p>
        <?php endif; ?>
    </div>

    <div class="summary-section">
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['total_outstanding'], 2); ?></div>
            <div class="label">Total Outstanding</div>
            <div class="count">Across all customers</div>
        </div>
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_current'], 2); ?></div>
            <div class="label">Current (Today)</div>
            <div class="count"><?php echo $summary['count_current']; ?> invoices</div>
        </div>
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_1_15'], 2); ?></div>
            <div class="label">1 - 15 Days</div>
            <div class="count"><?php echo $summary['count_1_15']; ?> invoices</div>
        </div>
        <div class="summary-box warning">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_16_30'], 2); ?></div>
            <div class="label">16 - 30 Days</div>
            <div class="count"><?php echo $summary['count_16_30']; ?> invoices</div>
        </div>
        <div class="summary-box warning">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_31_45'], 2); ?></div>
            <div class="label">31 - 45 Days</div>
            <div class="count"><?php echo $summary['count_31_45']; ?> invoices</div>
        </div>
        <div class="summary-box error">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_45'], 2); ?></div>
            <div class="label">45+ Days</div>
            <div class="count"><?php echo $summary['count_45']; ?> invoices</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>S#</th>
                <th>Customer Code</th>
                <th>Customer Name</th>
                <th>Address</th>
                <th>Invoice No</th>
                <th>Invoice Date</th>
                <th>Type</th>
                <th class="text-right">Amount</th>
                <th class="text-center">Days Overdue</th>
                <th class="text-center">Bucket</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reportData)): ?>
                <tr><td colspan="10" class="text-center">No invoices found</td></tr>
            <?php else: ?>
                <?php foreach ($reportData as $item): ?>
                    <?php
                        $badgeClass = 'badge-current';
                        if ($item['bucket'] === '1-15')  $badgeClass = 'badge-1-15';
                        elseif ($item['bucket'] === '16-30') $badgeClass = 'badge-16-30';
                        elseif ($item['bucket'] === '31-45') $badgeClass = 'badge-31-45';
                        elseif ($item['bucket'] === '45+')   $badgeClass = 'badge-45plus';
                        $bucketLabel = $item['bucket'] === 'current' ? 'Current' : $item['bucket'] . ' days';
                    ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['customerCode']); ?></td>
                        <td><?php echo htmlspecialchars($item['customerName']); ?></td>
                        <td><?php echo htmlspecialchars($item['address']); ?></td>
                        <td><?php echo htmlspecialchars($item['invoiceNo']); ?></td>
                        <td><?php echo htmlspecialchars($item['invoiceDate']); ?></td>
                        <td><?php echo htmlspecialchars($item['type']); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($item['amount'], 2); ?></td>
                        <td class="text-center"><?php echo $item['daysOverdue']; ?> days</td>
                        <td class="text-center"><span class="bucket-badge <?php echo $badgeClass; ?>"><?php echo $bucketLabel; ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="print-footer">
        <p>Customer Aging Report - LedgerOne ERP | Printed on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
</body>
</html>
