<?php
require_once '../../../../includes/dashboard.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    die('Invoice ID is required');
}

try {
    // Get invoice data
    $stmt = $pdo->prepare("
        SELECT 
            si.*,
            c.customer_name,
            c.customer_code,
            c.primary_phone,
            co.company_name,
            b.branch_name
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN companies co ON si.company_id = co.id
        LEFT JOIN branches b ON si.branch_id = b.id
        WHERE si.id = ? AND si.tenant_id = ?
    ");
    $stmt->execute([$invoice_id, $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die('Invoice not found');
    }

    // Get items
    $itemStmt = $pdo->prepare("
        SELECT 
            sii.*,
            p.name as product_name,
            u.uom_name
        FROM sale_invoice_items sii
        LEFT JOIN products p ON sii.product_id = p.id
        LEFT JOIN uom u ON sii.uom_id = u.id
        WHERE sii.sale_invoice_id = ? AND sii.tenant_id = ?
        ORDER BY COALESCE(sii.parent_row_id, sii.id), sii.id
    ");
    $itemStmt->execute([$invoice_id, $tenant_id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt <?php echo $invoice['bill_no']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            width: 80mm;
            margin: 0 auto;
            background: white;
        }
        .receipt {
            padding: 5mm;
            width: 80mm;
        }
        .header {
            text-align: center;
            margin-bottom: 5mm;
            border-bottom: 1px dashed #000;
            padding-bottom: 5mm;
        }
        .header-title {
            font-weight: bold;
            font-size: 11px;
        }
        .header-info {
            font-size: 9px;
            margin: 2px 0;
        }
        .customer-section {
            margin-bottom: 5mm;
            border-bottom: 1px dashed #000;
            padding-bottom: 5mm;
            font-size: 9px;
        }
        .label {
            display: inline-block;
            width: 25mm;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5mm 0;
            border-bottom: 1px dashed #000;
            padding-bottom: 5mm;
        }
        .items-table th {
            border-bottom: 1px solid #000;
            padding: 2px 0;
            font-weight: bold;
            font-size: 9px;
            text-align: left;
        }
        .items-table td {
            padding: 2px 0;
            font-size: 9px;
        }
        .qty { width: 12mm; text-align: right; }
        .price { width: 15mm; text-align: right; }
        .total { width: 15mm; text-align: right; }
        .item-name {
            font-weight: bold;
        }
        .totals-section {
            border-bottom: 1px dashed #000;
            padding-bottom: 5mm;
            margin-bottom: 5mm;
        }
        .total-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            margin: 2px 0;
            font-size: 9px;
        }
        .total-row.grand {
            font-weight: bold;
            font-size: 10px;
            border-top: 1px solid #000;
            padding-top: 2px;
        }
        .footer {
            text-align: center;
            font-size: 8px;
            margin-top: 5mm;
        }
        @media print {
            body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="header-title"><?php echo htmlspecialchars($invoice['company_name']); ?></div>
            <div class="header-info">SALES RECEIPT</div>
            <div class="header-info">Bill: <?php echo htmlspecialchars($invoice['bill_no']); ?></div>
            <div class="header-info">Date: <?php echo date('d-m-Y H:i', strtotime($invoice['sale_date'])); ?></div>
        </div>

        <!-- Customer -->
        <div class="customer-section">
            <div><span class="label">Customer:</span> <?php echo htmlspecialchars(substr($invoice['customer_name'], 0, 30)); ?></div>
            <div><span class="label">Phone:</span> <?php echo htmlspecialchars($invoice['primary_phone']); ?></div>
        </div>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 35mm;">Item</th>
                    <th class="qty">Qty</th>
                    <th class="price">Price</th>
                    <th class="total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item):
                    if ($item['parent_row_id']) continue;
                ?>
                <tr>
                    <td class="item-name"><?php echo htmlspecialchars(substr($item['product_name'], 0, 20)); ?></td>
                    <td class="qty"><?php echo number_format($item['quantity'], 0); ?></td>
                    <td class="price"><?php echo number_format($item['sale_price'], 0); ?></td>
                    <td class="total"><?php echo number_format($item['net_amount'], 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <div>Bill Amount:</div>
                <div><?php echo number_format($invoice['total_bill'], 2); ?></div>
            </div>
            <?php if ($invoice['total_discount_amount'] > 0): ?>
            <div class="total-row">
                <div>Discount:</div>
                <div>-<?php echo number_format($invoice['total_discount_amount'], 2); ?></div>
            </div>
            <?php endif; ?>
            <div class="total-row grand">
                <div>TOTAL:</div>
                <div><?php echo number_format($invoice['net_amount'], 2); ?></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Thank You for Your Purchase</div>
            <div><?php echo date('d-m-Y H:i'); ?></div>
        </div>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>
