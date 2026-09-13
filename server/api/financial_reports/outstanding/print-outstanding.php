<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'outstanding-balance.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    die('Unauthorized');
}

// Get parameters
$sales_officer_id = $_GET['sales_officer_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$customer_name = $_GET['customer_name'] ?? null;
$city_id = $_GET['city_id'] ?? null;
$area_id = $_GET['area_id'] ?? null;
$sort = $_GET['sort'] ?? 'high_to_low';

if (!$sales_officer_id) {
    die('DSR is required');
}

try {
    // Get DSR name
    $stmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$sales_officer_id, $tenant_id]);
    $dsr = $stmt->fetch(PDO::FETCH_ASSOC);
    $dsr_name = $dsr['full_name'] ?? 'Unknown';

    // Get city name if selected
    $city_name = '';
    if ($city_id) {
        $stmt = $pdo->prepare("SELECT city_name FROM cities WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$city_id, $tenant_id]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);
        $city_name = $city['city_name'] ?? '';
    }

    // Get area name if selected
    $area_name = '';
    if ($area_id) {
        $stmt = $pdo->prepare("SELECT area_name FROM areas WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$area_id, $tenant_id]);
        $area = $stmt->fetch(PDO::FETCH_ASSOC);
        $area_name = $area['area_name'] ?? '';
    }

    // Build the report
    $report = buildOutstandingReport(
        $pdo, $tenant_id, $sales_officer_id, $company_id, $from_date, $to_date,
        $customer_name, $city_id, $area_id, $sort
    );

    $dateLabel = $from_date 
        ? "FROM DATE {$from_date} TO {$to_date}"
        : "AS OF {$to_date}";

    $currency = 'Rs.';

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding Report - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        .print-container {
            max-width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 11px;
            margin: 2px 0;
        }

        .info {
            margin-bottom: 10px;
            font-size: 11px;
        }

        .info-row {
            margin-bottom: 2px;
        }

        .info-row strong {
            display: inline-block;
            width: 50px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 11px;
        }

        table th {
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding: 4px 2px;
            font-size: 10px;
        }

        table td {
            padding: 3px 2px;
            border-bottom: 1px dotted #ddd;
        }

        table td:nth-child(1) {
            width: 15%;
            text-align: center;
        }

        table td:nth-child(2) {
            width: 55%;
        }

        table td:nth-child(3) {
            width: 30%;
            text-align: right;
        }

        .area-header {
            background: #f5f5f5;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding: 4px 2px !important;
        }

        .area-total {
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px solid #000;
            padding: 3px 2px !important;
        }

        .grand-total {
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 4px 2px !important;
            font-size: 12px;
        }

        .area-total td:nth-child(1),
        .area-total td:nth-child(2) {
            text-align: right;
        }

        .grand-total td:nth-child(1),
        .grand-total td:nth-child(2) {
            text-align: right;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 10px;
            border-top: 1px solid #000;
            padding-top: 8px;
            color: #666;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .print-container {
                max-width: 100%;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }

        .no-print {
            text-align: center;
            margin-bottom: 15px;
        }

        .no-print button {
            padding: 8px 20px;
            font-size: 12px;
            cursor: pointer;
            background: #1F7BFF;
            color: white;
            border: none;
            border-radius: 4px;
        }

        .no-print button:hover {
            background: #1A6CDC;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
        <button onclick="window.close()" style="margin-left: 5px; background: #6B7280;">Close</button>
    </div>

    <div class="print-container">
        <div class="header">
            <h1>OUTSTANDING REPORT</h1>
            <p><?php echo htmlspecialchars($dsr_name); ?></p>
            <p><?php echo $dateLabel; ?></p>
        </div>

        <div class="info">
            <div class="info-row"><strong>DSR:</strong> <?php echo htmlspecialchars($dsr_name); ?></div>
            <div class="info-row"><strong>Date:</strong> <?php echo date('d-M-Y'); ?></div>
            <?php if ($city_name): ?>
            <div class="info-row"><strong>City:</strong> <?php echo htmlspecialchars($city_name); ?></div>
            <?php endif; ?>
            <?php if ($area_name): ?>
            <div class="info-row"><strong>Area:</strong> <?php echo htmlspecialchars($area_name); ?></div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Shop Name</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $serial = 1;
                foreach ($report['groups'] as $group):
                ?>
                    <tr>
                        <td colspan="3" class="area-header"><?php echo htmlspecialchars($group['area_name']); ?></td>
                    </tr>
                    <?php foreach ($group['customers'] as $customer): ?>
                    <tr>
                        <td><?php echo $serial++; ?></td>
                        <td><?php echo htmlspecialchars($customer['shop_name']); ?></td>
                        <td><?php echo $currency . number_format(abs($customer['balance']), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="area-total">
                        <td colspan="2">Subtotal</td>
                        <td><?php echo $currency . number_format(abs($group['subtotal']), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="grand-total">
                    <td colspan="2">GRAND TOTAL</td>
                    <td><?php echo $currency . number_format(abs($report['grand_total']), 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Printed on: <?php echo date('d-M-Y H:i'); ?></p>
        </div>
    </div>
</body>
</html>