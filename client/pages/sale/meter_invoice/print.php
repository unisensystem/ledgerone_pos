<?php
require_once '../../../../includes/connection.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
$invoice_id = $_GET['id'] ?? null;

if (!$user_id || !$tenant_id || !$invoice_id) {
    header('Location: invoice-list.php');
    exit();
}

// Fetch company data
$stmt = $pdo->prepare("SELECT * FROM companies WHERE tenant_id = ? LIMIT 1");
$stmt->execute([$tenant_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch invoice data
$stmt = $pdo->prepare("
    SELECT 
        sdu.*,
        b.branch_name, b.branch_code,
        fs.station_name, fs.station_code,
        p.name as product_name, p.code as product_code,
        u.uom_name
    FROM station_daily_usage sdu
    INNER JOIN branches b ON sdu.branch_id = b.id
    INNER JOIN fueling_stations fs ON sdu.station_id = fs.id
    INNER JOIN products p ON sdu.product_id = p.id
    INNER JOIN uom u ON sdu.unit_id = u.id
    WHERE sdu.id = ? AND sdu.tenant_id = ?
");
$stmt->execute([$invoice_id, $tenant_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header('Location: invoice-list.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice['id']; ?> - FuelingSys ERP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; padding: 20px; background: #fff; }
        .print-container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #1f7bff; padding-bottom: 20px; }
        .header img { max-width: 150px; max-height: 80px; margin-bottom: 15px; }
        .header h1 { color: #1f7bff; font-size: 28px; margin-bottom: 5px; }
        .header p { color: #6b7280; font-size: 14px; }
        .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-section h3 { font-size: 14px; color: #6b7280; margin-bottom: 10px; text-transform: uppercase; }
        .info-section p { font-size: 16px; color: #0e1a2b; margin-bottom: 5px; }
        .info-section strong { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e1e6ee; }
        th { background: #f7f9fc; font-weight: 600; color: #0e1a2b; font-size: 14px; }
        td { color: #2f3b4c; font-size: 15px; }
        .totals { background: #f7f9fc; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 16px; }
        .total-row.grand { font-size: 20px; font-weight: 700; color: #1f7bff; border-top: 2px solid #1f7bff; padding-top: 10px; margin-top: 10px; }
        .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e1e6ee; }
        .no-print { margin-bottom: 20px; }
        .btn { padding: 10px 20px; background: #1f7bff; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin-right: 10px; }
        .btn:hover { background: #1a6cdc; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="no-print">
            <button class="btn" onclick="window.print()">Print Invoice</button>
            <button class="btn-secondary btn" onclick="window.close()">Close</button>
        </div>
        
        <div class="header">
            <?php if ($company['logo_url']): ?>
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Company Logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($company['company_name'] ?? 'FuelingSys ERP'); ?></h1>
            <p><?php echo htmlspecialchars($company['address'] ?? ''); ?></p>
            <?php if ($company['phone']): ?><p>Phone: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
            <?php if ($company['email']): ?><p>Email: <?php echo htmlspecialchars($company['email']); ?></p><?php endif; ?>
            <p style="margin-top: 10px; font-weight: 600;">Meter Reading Invoice</p>
        </div>
        
        <div class="invoice-info">
            <div class="info-section">
                <h3>Invoice Details</h3>
                <p><strong>Invoice #:</strong> <?php echo $invoice['id']; ?></p>
                <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($invoice['usage_date'])); ?></p>
                <p><strong>Recorded:</strong> <?php echo date('d M Y H:i', strtotime($invoice['recorded_at'])); ?></p>
            </div>
            <div class="info-section">
                <h3>Location Details</h3>
                <p><strong>Branch:</strong> <?php echo $invoice['branch_name']; ?></p>
                <p><strong>Station:</strong> <?php echo $invoice['station_name']; ?></p>
                <p><strong>Station Code:</strong> <?php echo $invoice['station_code']; ?></p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Opening Reading</th>
                    <th>Closing Reading</th>
                    <th>Qty Dispensed</th>
                    <th>Rate</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo $invoice['product_name']; ?></td>
                    <td><?php echo $invoice['uom_name']; ?></td>
                    <td><?php echo number_format($invoice['opening_reading'], 2); ?></td>
                    <td><?php echo $invoice['closing_reading'] ? number_format($invoice['closing_reading'], 2) : '-'; ?></td>
                    <td><?php echo $invoice['total_dispensed'] ? number_format($invoice['total_dispensed'], 2) : '-'; ?></td>
                    <td><?php echo $invoice['rate'] ? number_format($invoice['rate'], 3) : '-'; ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Total Quantity Dispensed:</span>
                <strong><?php echo $invoice['total_dispensed'] ? number_format($invoice['total_dispensed'], 2) : '0.00'; ?> <?php echo $invoice['uom_name']; ?></strong>
            </div>
            <div class="total-row grand">
                <span>Total Revenue Generated:</span>
                <strong><?php echo $invoice['total_revenue'] ? number_format($invoice['total_revenue'], 2) : '0.00'; ?></strong>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated invoice and does not require a signature.</p>
        </div>
    </div>
</body>
</html>
