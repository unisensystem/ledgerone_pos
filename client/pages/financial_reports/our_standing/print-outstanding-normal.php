<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'outstanding-balance.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$sales_officer_id = $_GET['sales_officer_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$customer_name = $_GET['customer_name'] ?? null;
$city_id = $_GET['city_id'] ?? null;
$area_id = $_GET['area_id'] ?? null;
$sort = $_GET['sort'] ?? 'high_to_low';

if (!$sales_officer_id) {
    die('DSR (sales officer) is required');
}

$report = buildOutstandingReport(
    $pdo, $tenant_id, $sales_officer_id, $company_id, $from_date, $to_date,
    $customer_name, $city_id, $area_id, $sort
);

// Get company info
$company_stmt = $pdo->prepare(
    "SELECT company_name, legal_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? "
    . ($company_id ? "AND id = ?" : "AND is_active = 1 ORDER BY id ASC")
    . " LIMIT 1"
);
$company_stmt->execute($company_id ? [$tenant_id, $company_id] : [$tenant_id]);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'company_name' => '', 'legal_name' => '', 'address' => '', 
    'phone' => '', 'email' => '', 'logo_url' => null
];

// Get city/area names if filtered
$city_name = '';
if ($city_id) {
    $stmt = $pdo->prepare("SELECT city_name FROM cities WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$city_id, $tenant_id]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);
    $city_name = $city['city_name'] ?? '';
}

$area_name = '';
if ($area_id) {
    $stmt = $pdo->prepare("SELECT area_name FROM areas WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$area_id, $tenant_id]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);
    $area_name = $area['area_name'] ?? '';
}

$dateLabel = $from_date ? "FROM {$from_date} TO {$to_date}" : "AS OF {$to_date}";
$symbol = 'Rs.';

function fmt($amount, $symbol) {
    return $symbol . ' ' . number_format(abs($amount), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding Report - <?php echo htmlspecialchars($report['dsr_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }

        .print-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-print {
            background: #1F7BFF;
            color: white;
        }

        .btn-print:hover {
            background: #1A6CDC;
        }

        .btn-close {
            background: #6c757d;
            color: white;
        }

        .btn-close:hover {
            background: #5a6268;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1F7BFF;
            padding-bottom: 20px;
        }

        .company-logo {
            max-width: 100px;
            max-height: 80px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #0E1A2B;
            margin-bottom: 5px;
        }

        .company-legal {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 8px;
        }

        .company-contact {
            font-size: 11px;
            color: #6B7280;
            line-height: 1.5;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #1F7BFF;
            margin: 20px 0 10px 0;
        }

        /* Report Info */
        .info-section {
            background: #F7F9FC;
            border-left: 4px solid #1F7BFF;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            margin-bottom: 8px;
            gap: 10px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #0E1A2B;
        }

        .info-value {
            color: #2F3B4C;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table thead {
            background: #0E1A2B;
            color: white;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #E1E6EE;
            font-size: 13px;
        }

        table tbody tr:hover {
            background: #F7F9FC;
        }

        .area-header {
            background: #E8F1FF;
            font-weight: bold;
            color: #1F7BFF;
            padding: 12px !important;
        }

        .text-right {
            text-align: right;
        }

        .area-subtotal {
            background: #F7F9FC;
            font-weight: 600;
            border-top: 1px dashed #C9CFDA;
            border-bottom: 1px solid #C9CFDA;
        }

        .grand-total {
            background: #0E1A2B;
            color: white;
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #1F7BFF;
        }

        .grand-total td {
            border-bottom: none;
            padding: 14px 12px;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            border-top: 2px solid #E1E6EE;
            padding-top: 20px;
            font-size: 11px;
            color: #6B7280;
            text-align: center;
        }

        .footer-line {
            margin: 8px 0;
        }

        .signature-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            margin-top: 40px;
            gap: 20px;
            text-align: center;
        }

        .signature-box {
            padding-top: 40px;
            border-top: 1px solid #333;
        }

        .signature-label {
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                max-width: 100%;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }

        .filters-applied {
            font-size: 11px;
            color: #6B7280;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #E1E6EE;
        }

        .filter-tag {
            display: inline-block;
            background: #E8F1FF;
            color: #1F7BFF;
            padding: 3px 8px;
            border-radius: 3px;
            margin-right: 8px;
            font-size: 10px;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">
            <span>🖨️ Print / Save as PDF</span>
        </button>
        <button class="btn btn-close" onclick="window.close()">
            <span>✕ Close</span>
        </button>
    </div>

    <div class="print-container">
        
        <!-- Header -->
        <div class="header">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" class="company-logo" alt="Company Logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars(strtoupper($company['company_name'] ?: $company['legal_name'])); ?></div>
            <?php if (!empty($company['legal_name']) && $company['legal_name'] !== $company['company_name']): ?>
                <div class="company-legal"><?php echo htmlspecialchars($company['legal_name']); ?></div>
            <?php endif; ?>
            <div class="company-contact">
                <?php if (!empty($company['address'])): ?>
                    <div><?php echo htmlspecialchars($company['address']); ?></div>
                <?php endif; ?>
                <?php if (!empty($company['phone'])): ?>
                    <div>Tel: <?php echo htmlspecialchars($company['phone']); ?></div>
                <?php endif; ?>
                <?php if (!empty($company['email'])): ?>
                    <div>Email: <?php echo htmlspecialchars($company['email']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Report Title -->
        <h1 class="report-title">OUTSTANDING REPORT</h1>

        <!-- Report Info -->
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">Period:</span>
                <span class="info-value"><?php echo htmlspecialchars($dateLabel); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">DSR (Sales Officer):</span>
                <span class="info-value"><?php echo htmlspecialchars($report['dsr_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Print Date:</span>
                <span class="info-value"><?php echo date('d-M-Y H:i:s'); ?></span>
            </div>

            <!-- Applied Filters -->
            <?php if ($city_name || $area_name || $customer_name || $sort !== 'high_to_low'): ?>
                <div class="filters-applied">
                    <strong>Filters Applied:</strong>
                    <?php if ($city_name): ?>
                        <span class="filter-tag">City: <?php echo htmlspecialchars($city_name); ?></span>
                    <?php endif; ?>
                    <?php if ($area_name): ?>
                        <span class="filter-tag">Area: <?php echo htmlspecialchars($area_name); ?></span>
                    <?php endif; ?>
                    <?php if ($customer_name): ?>
                        <span class="filter-tag">Search: <?php echo htmlspecialchars($customer_name); ?></span>
                    <?php endif; ?>
                    <?php if ($sort === 'low_to_high'): ?>
                        <span class="filter-tag">Sort: Low to High</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Data Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">S.#</th>
                    <th style="width: 57%;">Shop Name</th>
                    <th style="width: 35%; text-align: right;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['groups'] as $group): ?>
                    <!-- Area Header -->
                    <tr>
                        <td colspan="3" class="area-header">
                            <?php echo htmlspecialchars(strtoupper($group['area_name'])); ?>
                        </td>
                    </tr>

                    <!-- Customers -->
                    <?php foreach ($group['customers'] as $customer): ?>
                        <tr>
                            <td><?php echo $customer['s_no']; ?></td>
                            <td><?php echo htmlspecialchars($customer['shop_name']); ?></td>
                            <td class="text-right"><?php echo fmt($customer['balance'], $symbol); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Area Subtotal -->
                    <tr class="area-subtotal">
                        <td colspan="2">Area Total:</td>
                        <td class="text-right"><?php echo fmt($group['subtotal'], $symbol); ?></td>
                    </tr>
                <?php endforeach; ?>

                <!-- Grand Total -->
                <tr class="grand-total">
                    <td colspan="2">GRAND TOTAL:</td>
                    <td class="text-right"><?php echo fmt($report['grand_total'], $symbol); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-line">Thank you for your business!</div>
            <div class="footer-line">This is a computer-generated report.</div>
            <div class="footer-line" style="margin-top: 15px; font-size: 10px;">
                Generated by: <?php echo htmlspecialchars($user_id); ?> | 
                Printed: <?php echo date('d-M-Y H:i:s'); ?>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Verified By</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Approved By</div>
            </div>
        </div>

    </div>

</body>
</html>