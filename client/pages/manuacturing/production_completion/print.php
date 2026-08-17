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

$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("
    SELECT 
        pc.*,
        po.order_no,
        b.branch_name,
        m.machine_name
    FROM production_completions pc
    JOIN production_orders po ON pc.production_order_id = po.id
    JOIN branches b ON pc.branch_id = b.id
    LEFT JOIN machines m ON pc.machine_id = m.id
    WHERE pc.id = ? AND pc.tenant_id = ?
");
$stmt->execute([$id, $tenant_id]);
$completion = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        cp.*,
        p.code as product_code,
        p.name as product_name,
        u.uom_name
    FROM completed_products cp
    JOIN products p ON cp.product_id = p.id
    LEFT JOIN uom u ON cp.uom_id = u.id
    WHERE cp.production_completion_id = ?
");
$stmt->execute([$id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        pcm.*,
        p.code as material_code,
        p.name as material_name,
        u.uom_name
    FROM production_completion_materials pcm
    JOIN products p ON pcm.material_id = p.id
    LEFT JOIN uom u ON pcm.uom_id = u.id
    WHERE pcm.production_completion_id = ?
");
$stmt->execute([$id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Completion - <?php echo $completion['completion_no']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { font-size: 14px; color: #666; }
        .info-section { margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .info-item { display: flex; }
        .info-label { font-weight: bold; width: 150px; }
        .info-value { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        .total-row { background: #f9f9f9; font-weight: bold; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature { text-align: center; }
        .signature-line { border-top: 1px solid #000; width: 200px; margin-top: 50px; padding-top: 5px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="padding: 10px 20px; margin-bottom: 20px; cursor: pointer;">Print</button>

    <div class="header">
        <h1>Production Completion</h1>
        <p>Completion No: <?php echo $completion['completion_no']; ?></p>
    </div>

    <div class="info-section">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Production Order:</div>
                <div class="info-value"><?php echo $completion['order_no']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Branch:</div>
                <div class="info-value"><?php echo $completion['branch_name']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Machine:</div>
                <div class="info-value"><?php echo $completion['machine_name'] ?? '-'; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Complete Date:</div>
                <div class="info-value"><?php echo $completion['complete_date']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Products:</div>
                <div class="info-value"><?php echo $completion['total_products']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Quantity:</div>
                <div class="info-value"><?php echo $completion['total_quantity']; ?></div>
            </div>
        </div>
    </div>

    <h3 style="margin-bottom: 10px;">Completed Products</h3>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Planned Qty</th>
                <th>Remaining Qty</th>
                <th>Completed Qty</th>
                <th>UOM</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><?php echo $p['product_code'] . ' - ' . $p['product_name']; ?></td>
                <td><?php echo $p['planned_qty']; ?></td>
                <td><?php echo $p['remaining_qty']; ?></td>
                <td><?php echo $p['completed_qty']; ?></td>
                <td><?php echo $p['uom_name']; ?></td>
                <td><?php echo number_format($p['unit_cost'], 2); ?></td>
                <td><?php echo number_format($p['total_cost'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="6" style="text-align: right;">Total Cost:</td>
                <td><?php echo number_format($completion['total_cost'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin-bottom: 10px; margin-top: 30px;">WIP Materials Consumed</h3>
    <table>
        <thead>
            <tr>
                <th>Material</th>
                <th>Consumed Qty</th>
                <th>UOM</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalMaterialCost = 0;
            foreach ($materials as $m): 
                $totalMaterialCost += $m['total_cost'];
            ?>
            <tr>
                <td><?php echo $m['material_code'] . ' - ' . $m['material_name']; ?></td>
                <td><?php echo number_format($m['consumed_qty'], 4); ?></td>
                <td><?php echo $m['uom_name']; ?></td>
                <td><?php echo number_format($m['unit_cost'], 2); ?></td>
                <td><?php echo number_format($m['total_cost'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Total Material Cost:</td>
                <td><?php echo number_format($totalMaterialCost, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature">
            <div class="signature-line">Prepared By</div>
        </div>
        <div class="signature">
            <div class="signature-line">Approved By</div>
        </div>
    </div>
</body>
</html>
