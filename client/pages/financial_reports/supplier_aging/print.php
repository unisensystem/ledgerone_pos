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

$company_id = $_GET['company_id'] ?? null;

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

// Fetch aging data
$stmt = $pdo->prepare("
    SELECT 
        pi.id,
        pi.bill_no,
        pi.purchase_date,
        pi.net_amount,
        s.supplier_code,
        s.supplier_name,
        s.address,
        COALESCE(SUM(pv.amount), 0) as paid_amount,
        DATEDIFF(CURDATE(), pi.purchase_date) as days_overdue,
        'Purchase Invoice' as invoice_type
    FROM purchase_invoice pi
    JOIN suppliers s ON pi.supplier_id = s.id
    LEFT JOIN payment_voucher pv ON pv.bill_no = pi.bill_no AND pv.tenant_id = ?
    WHERE pi.tenant_id = ?
    " . ($company_id ? " AND pi.company_id = ?" : "") . "
    GROUP BY pi.id
    HAVING (pi.net_amount - paid_amount) > 0 AND DATEDIFF(CURDATE(), pi.purchase_date) >= 15
    
    ORDER BY days_overdue DESC
");
$params = [$tenant_id, $tenant_id];
if ($company_id) $params[] = $company_id;
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reportData = [];
$summary = [
    'total_overdue' => 0,
    'bucket_15' => 0,
    'bucket_25' => 0,
    'bucket_45' => 0,
    'count_15' => 0,
    'count_25' => 0,
    'count_45' => 0
];

foreach ($invoices as $index => $invoice) {
    $outstanding = $invoice['net_amount'] - $invoice['paid_amount'];
    $days = (int)$invoice['days_overdue'];
    
    $bucket = '<15';
    if ($days >= 45) {
        $bucket = '45+';
        $summary['bucket_45'] += $outstanding;
        $summary['count_45']++;
    } elseif ($days >= 35) {
        $bucket = '35+';
    } elseif ($days >= 25) {
        $bucket = '25+';
        $summary['bucket_25'] += $outstanding;
        $summary['count_25']++;
    } elseif ($days >= 15) {
        $bucket = '15+';
        $summary['bucket_15'] += $outstanding;
        $summary['count_15']++;
    }

    $summary['total_overdue'] += $outstanding;

    $reportData[] = [
        'id' => $index + 1,
        'supplierCode' => $invoice['supplier_code'],
        'supplierName' => $invoice['supplier_name'],
        'address' => $invoice['address'] ?? 'N/A',
        'invoiceNo' => $invoice['bill_no'],
        'type' => $invoice['invoice_type'],
        'amount' => $outstanding,
        'daysOverdue' => $days,
        'bucket' => $bucket
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Aging Report - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #000;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .print-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .print-header p {
            font-size: 12px;
            color: #666;
        }
        
        .summary-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .summary-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        .summary-box .value {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary-box .label {
            font-size: 11px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
            color: #666;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            @page {
                margin: 1cm;
            }
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
        
        .print-btn:hover {
            background-color: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print Report</button>
    
    <div class="print-header">
        <h1>Supplier Aging Report</h1>
        <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
    
    <div class="summary-section">
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['total_overdue'], 2); ?></div>
            <div class="label">Total Overdue</div>
        </div>
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_15'], 2); ?></div>
            <div class="label">15+ Days (<?php echo $summary['count_15']; ?> invoices)</div>
        </div>
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_25'], 2); ?></div>
            <div class="label">25+ Days (<?php echo $summary['count_25']; ?> invoices)</div>
        </div>
        <div class="summary-box">
            <div class="value"><?php echo $currency_symbol . number_format($summary['bucket_45'], 2); ?></div>
            <div class="label">45+ Days (<?php echo $summary['count_45']; ?> invoices)</div>
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
                <th>Type</th>
                <th class="text-right">Amount</th>
                <th class="text-center">Days Overdue</th>
                <th class="text-center">Bucket</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reportData)): ?>
                <tr>
                    <td colspan="9" class="text-center">No overdue invoices found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reportData as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['supplierCode']); ?></td>
                        <td><?php echo htmlspecialchars($item['supplierName']); ?></td>
                        <td><?php echo htmlspecialchars($item['address']); ?></td>
                        <td><?php echo htmlspecialchars($item['invoiceNo']); ?></td>
                        <td><?php echo htmlspecialchars($item['type']); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($item['amount'], 2); ?></td>
                        <td class="text-center"><?php echo $item['daysOverdue']; ?> days</td>
                        <td class="text-center"><?php echo $item['bucket']; ?> days</td>
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
