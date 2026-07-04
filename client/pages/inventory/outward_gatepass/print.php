<?php
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$gatepass_id = $_GET['id'] ?? null;

if (!$gatepass_id) {
    die('Gatepass ID is required');
}

// Fetch gatepass data
require_once '../../../../includes/connection.php';

// Fetch company data
$stmt = $pdo->prepare("
    SELECT 
        ig.gatepass_code,
        ig.date,
        s.customer_name,
        s.address as customer_address,
        s.primary_phone as customer_phone,
        b.branch_name,
        b.address as branch_address,
        c.company_name,
        c.legal_name,
        c.email,
        c.phone,
        c.address,
        c.city,
        c.state,
        c.zipcode,
        c.logo_url
    FROM outward_gatepass ig
    LEFT JOIN customers s ON ig.supplier_id = s.id
    LEFT JOIN branches b ON ig.branch_id = b.id
    LEFT JOIN companies c ON ig.company_id = c.id
    WHERE ig.id = ? AND ig.tenant_id = ?
");
$stmt->execute([$gatepass_id, $tenant_id]);
$gatepass = $stmt->fetch();

if (!$gatepass) {
    die('Gatepass not found');
}

// Fallback to first active company if no company in gatepass
if (empty($gatepass['company_name'])) {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tenant_id]);
    $fallback = $stmt->fetch();
    if ($fallback) {
        $gatepass['company_name'] = $fallback['company_name'];
        $gatepass['address'] = $fallback['address'];
        $gatepass['phone'] = $fallback['phone'];
        $gatepass['email'] = $fallback['email'];
    }
}

// Fetch items
$stmt = $pdo->prepare("
    SELECT 
        p.name as product_name,
        u.uom_name as unit_name,
        igi.quantity
    FROM outward_gatepass_items igi
    LEFT JOIN products p ON igi.product_id = p.id
    LEFT JOIN uom u ON igi.unit_id = u.id
    WHERE igi.gatepass_id = ? AND igi.tenant_id = ?
");
$stmt->execute([$gatepass_id, $tenant_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Outward Gatepass - <?php echo htmlspecialchars($gatepass['gatepass_code']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
        }

        .print-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 30px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 20px;
            font-weight: normal;
            color: #333;
        }

        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            border: 1px solid #000;
            padding: 15px;
        }

        .info-box h3 {
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }

        .info-box p {
            font-size: 13px;
            line-height: 1.6;
            margin: 5px 0;
        }

        .info-box strong {
            display: inline-block;
            width: 120px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }

        .items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 13px;
        }

        .items-table td {
            font-size: 13px;
        }

        .items-table .text-center {
            text-align: center;
        }

        .items-table .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
            font-size: 12px;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .no-print button {
            padding: 10px 30px;
            font-size: 16px;
            cursor: pointer;
            background-color: #1f7bff;
            color: white;
            border: none;
            border-radius: 5px;
            margin: 0 10px;
        }

        .no-print button:hover {
            background-color: #1a6cdc;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }

            .print-container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">✖ Close</button>
    </div>

    <div class="print-container">
        <div class="header">
            <h1>OUTWARD GATEPASS</h1>
            <h2><?php echo htmlspecialchars($gatepass['company_name'] ?? 'LedgerOne ERP'); ?></h2>
            <?php if (!empty($gatepass['address']) || !empty($gatepass['phone'])): ?>
            <p style="font-size: 12px; margin-top: 5px; color: #666;">
                <?php if (!empty($gatepass['address'])) echo htmlspecialchars($gatepass['address']); ?>
                <?php if (!empty($gatepass['phone'])) echo ' | Tel: ' . htmlspecialchars($gatepass['phone']); ?>
                <?php if (!empty($gatepass['email'])) echo ' | Email: ' . htmlspecialchars($gatepass['email']); ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <div class="info-box">
                <h3>Gatepass Details</h3>
                <p><strong>OGP Code:</strong> <?php echo htmlspecialchars($gatepass['gatepass_code']); ?></p>
                <p><strong>Date:</strong> <?php echo date('d-M-Y', strtotime($gatepass['date'])); ?></p>
            </div>

            <div class="info-box">
                <h3>Branch Details</h3>
                <p><strong>Branch:</strong> <?php echo htmlspecialchars($gatepass['branch_name']); ?></p>
                <p><?php echo htmlspecialchars($gatepass['branch_address'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <div class="info-box">
            <h3>Customer Details</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($gatepass['customer_name']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($gatepass['customer_address'] ?? 'N/A'); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($gatepass['customer_phone'] ?? 'N/A'); ?></p>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" width="50">S#</th>
                    <th>Product Name</th>
                    <th class="text-center" width="150">Unit</th>
                    <th class="text-right" width="120">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_qty = 0;
                foreach ($items as $index => $item): 
                    $total_qty += $item['quantity'];
                ?>
                <tr>
                    <td class="text-center"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($item['unit_name']); ?></td>
                    <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" class="text-right"><strong>Total Quantity:</strong></td>
                    <td class="text-right"><strong><?php echo number_format($total_qty, 2); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div class="signature-box">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Checked By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Received By</div>
            </div>
        </div>
    </div>
</body>
</html>
