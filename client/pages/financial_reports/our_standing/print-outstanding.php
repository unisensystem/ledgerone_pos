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

if (!$sales_officer_id) {
    die('DSR (sales officer) is required');
}

$report = buildOutstandingReport(
    $pdo, $tenant_id, $sales_officer_id, $company_id, $from_date, $to_date,
    $customer_name, $city_id, $area_id
);

// Company header — same companies table used across the app's print views.
// There's no "default company" flag, so with no company filter we fall back
// to the tenant's first active company by id.
$company_stmt = $pdo->prepare(
    "SELECT company_name, legal_name, address, phone, logo_url FROM companies WHERE tenant_id = ? "
    . ($company_id ? "AND id = ?" : "AND is_active = 1 ORDER BY id ASC")
    . " LIMIT 1"
);
$company_stmt->execute($company_id ? [$tenant_id, $company_id] : [$tenant_id]);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC) ?: ['company_name' => '', 'legal_name' => '', 'address' => '', 'phone' => '', 'logo_url' => null];

$dateLabel = $from_date ? "FROM DATE {$from_date} TO {$to_date}" : "AS OF {$to_date}";
$symbol = '';

function fmt($amount, $symbol) {
    return $symbol . number_format(abs($amount), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding - <?php echo htmlspecialchars($report['dsr_name']); ?></title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            color: #000;
            background: #f5f5f5;
            padding: 10px;
            font-weight: bold;
        }

        .thermal-receipt {
            width: 80mm;
            max-width: 302px;
            margin: 0 auto;
            background: white;
            padding: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }

        .company-logo {
            max-width: 80px;
            max-height: 60px;
            margin: 0 auto 5px;
            display: block;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .company-info {
            font-size: 12px;
            margin-bottom: 2px;
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .invoice-header {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .items-table {
            width: 100%;
            margin: 8px 0;
            font-size: 12px;
            font-weight: bold;
            border-collapse: collapse;
        }

        .items-table th {
            border-bottom: 1px solid #000;
            padding: 4px 0;
            text-align: left;
            font-weight: bold;
        }

        .items-table td {
            padding: 4px 0;
            font-weight: bold;
        }

        .area-row td {
            padding-top: 8px;
            border-bottom: 1px dashed #000;
        }

        .subtotal-row td {
            border-top: 1px dotted #000;
            padding-top: 4px;
        }

        .grand-total-row td {
            border-top: 2px solid #000;
            padding-top: 5px;
            font-size: 13px;
        }

        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .print-btn:hover { background: #0056b3; }

        .close-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            margin-top: 8px;
        }

        .close-btn:hover { background: #5a6268; }

        .footer {
            font-size: 11px;
            margin-top: 10px;
            font-weight: bold;
        }

        .signature-line {
            margin-top: 25px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 3px;
            width: 60%;
            margin-left: auto;
            margin-right: auto;
            font-size: 10px;
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>

    <div class="thermal-receipt">
        <div class="text-center">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company['logo_url']); ?>" class="company-logo" alt="Logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars(strtoupper($company['company_name'] ?: $company['legal_name'])); ?></div>
            <?php if (!empty($company['address'])): ?>
                <div class="company-info"><?php echo htmlspecialchars($company['address']); ?></div>
            <?php endif; ?>
            <?php if (!empty($company['phone'])): ?>
                <div class="company-info">Cell #. <?php echo htmlspecialchars($company['phone']); ?></div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>
        <div class="text-center invoice-header">OUT STANDING</div>
        <div class="divider"></div>

        <div class="info-row">
            <span>Period:</span>
            <span class="bold"><?php echo htmlspecialchars($dateLabel); ?></span>
        </div>
        <div class="info-row">
            <span>DSR:</span>
            <span class="bold"><?php echo htmlspecialchars($report['dsr_name']); ?></span>
        </div>

        <div class="divider"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:15%;">S.#</th>
                    <th style="width:55%;">Shop Name</th>
                    <th style="width:30%;" class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['groups'] as $group): ?>
                    <tr class="area-row">
                        <td colspan="3"><?php echo htmlspecialchars(strtoupper($group['area_name'])); ?></td>
                    </tr>
                    <?php foreach ($group['customers'] as $c): ?>
                        <tr>
                            <td><?php echo $c['s_no']; ?></td>
                            <td><?php echo htmlspecialchars($c['shop_name']); ?></td>
                            <td class="text-right"><?php echo fmt($c['balance'], $symbol); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal-row">
                        <td colspan="2">Total:</td>
                        <td class="text-right"><?php echo fmt($group['subtotal'], $symbol); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="grand-total-row">
                    <td colspan="2">Grand Total:</td>
                    <td class="text-right"><?php echo fmt($report['grand_total'], $symbol); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="footer text-center">
            <div style="margin-top: 5px;">Thank you for your business!</div>
            <div style="margin-top: 8px; font-size: 10px;">Printed: <span><?php echo htmlspecialchars(date('m/d/Y, h:i:s A')); ?></span></div>

           

            <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 8px; font-size: 10px; line-height: 1.6;">
                <div style="font-weight: bold;">Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711</div>
                <div>+92 335 3789981</div>
                <div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div>
            </div>
        </div>

        <div class="no-print text-center" style="margin-top:16px;">
            <button class="close-btn" onclick="window.close()">Close</button>
        </div>
    </div>

</body>
</html>