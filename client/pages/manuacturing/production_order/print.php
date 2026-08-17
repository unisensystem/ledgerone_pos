<?php
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? 1;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$order_id = $_GET['id'] ?? null;

// Fetch order details
$stmt = $pdo->prepare("
    SELECT 
        po.*,
        p.code as product_code,
        p.name as product_name,
        b.branch_name,
        bom.bom_code,
        m.code as machine_code,
        m.machine_name
    FROM production_orders po
    JOIN products p ON po.product_id = p.id
    JOIN branches b ON po.branch_id = b.id
    JOIN bill_of_materials bom ON po.bom_id = bom.id
    LEFT JOIN machines m ON po.machine_id = m.id
    WHERE po.id = ? AND po.tenant_id = ?
");
$stmt->execute([$order_id, $tenant_id]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found');
}

// Fetch materials
$stmt = $pdo->prepare("
    SELECT 
        pom.*,
        p.code as material_code,
        p.name as material_name,
        u.uom_name
    FROM production_order_materials pom
    JOIN products p ON pom.material_id = p.id
    LEFT JOIN uom u ON pom.uom_id = u.id
    WHERE pom.production_order_id = ?
    ORDER BY p.code, u.id
");
$stmt->execute([$order_id]);
$materials = $stmt->fetchAll();

// Group materials by product
$materialsByProduct = [];
foreach ($materials as $mat) {
    if (!isset($materialsByProduct[$mat['material_id']])) {
        $materialsByProduct[$mat['material_id']] = [
            'code' => $mat['material_code'],
            'name' => $mat['material_name'],
            'units' => []
        ];
    }
    $materialsByProduct[$mat['material_id']]['units'][] = [
        'required_qty' => $mat['required_qty'],
        'issued_qty' => $mat['issued_qty'],
        'uom_name' => $mat['uom_name']
    ];
}

// Find max units
$maxUnits = 0;
foreach ($materialsByProduct as $product) {
    $unitCount = count($product['units']);
    if ($unitCount > $maxUnits) $maxUnits = $unitCount;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Order - <?php echo $order['order_no']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #fff;
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .info-item {
            display: flex;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        .info-value {
            flex: 1;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <h1>PRODUCTION ORDER</h1>
            <p>Order No: <?php echo $order['order_no']; ?></p>
        </div>

        <div class="order-info">
            <div class="info-item">
                <div class="info-label">Product:</div>
                <div class="info-value"><?php echo $order['product_code'] . ' - ' . $order['product_name']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">BOM:</div>
                <div class="info-value"><?php echo $order['bom_code'] . ' v' . $order['bom_version']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Branch:</div>
                <div class="info-value"><?php echo $order['branch_name']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Machine:</div>
                <div class="info-value"><?php echo $order['machine_name'] ? $order['machine_code'] . ' - ' . $order['machine_name'] : '-'; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Order Quantity:</div>
                <div class="info-value"><?php echo $order['order_qty']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Status:</div>
                <div class="info-value"><?php echo $order['status']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Start Date:</div>
                <div class="info-value"><?php echo $order['start_date'] ?: '-'; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">End Date:</div>
                <div class="info-value"><?php echo $order['end_date'] ?: '-'; ?></div>
            </div>
        </div>

        <div class="section-title">Material Requirements</div>
        <table>
            <thead>
                <tr>
                    <th>Material</th>
                    <?php for ($i = 0; $i < $maxUnits; $i++): ?>
                        <th>Required Qty</th>
                        <th>UOM</th>
                        <th>Issued Qty</th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materialsByProduct as $product): ?>
                <tr>
                    <td><?php echo $product['code'] . ' - ' . $product['name']; ?></td>
                    <?php for ($i = 0; $i < $maxUnits; $i++): ?>
                        <?php if ($i < count($product['units'])): ?>
                            <td><?php echo $product['units'][$i]['required_qty']; ?></td>
                            <td><?php echo $product['units'][$i]['uom_name']; ?></td>
                            <td><?php echo $product['units'][$i]['issued_qty']; ?></td>
                        <?php else: ?>
                            <td style="background:#f0f0f0; color:#999;">-</td>
                            <td style="background:#f0f0f0; color:#999;">-</td>
                            <td style="background:#f0f0f0; color:#999;">-</td>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="signature">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature">
                <div class="signature-line">Approved By</div>
            </div>
            <div class="signature">
                <div class="signature-line">Received By</div>
            </div>
        </div>
    </div>

    <div class="no-print" style="text-align:center; margin-top:20px;">
        <button onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer;">Print</button>
        <button onclick="window.close()" style="padding:10px 20px; font-size:16px; cursor:pointer; margin-left:10px;">Close</button>
    </div>
</body>
</html>
