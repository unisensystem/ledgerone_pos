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
            c.address,
            c.primary_phone,
            c.email,
            co.company_name,
            co.logo,
            co.address as company_address,
            co.phone as company_phone,
            b.branch_name,
            b.branch_code,
            u.full_name as created_by_name
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN companies co ON si.company_id = co.id
        LEFT JOIN branches b ON si.branch_id = b.id
        LEFT JOIN employees u ON si.created_by = u.id
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
            p.code as product_code,
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
    <title>Invoice <?php echo $invoice['bill_no']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            background: #f5f5f5;
        }
        .page {
            width: 210mm;
            height: 297mm;
            margin: 10mm auto;
            padding: 15mm;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .company-info {
            font-weight: bold;
            font-size: 14px;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
        }
        .invoice-no {
            text-align: right;
            font-size: 11px;
        }
        .invoice-no div {
            margin: 2px 0;
        }
        .section {
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .section-left, .section-right {
            font-size: 11px;
        }
        .section-left div, .section-right div {
            margin: 2px 0;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        .items-table th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-weight: bold;
        }
        .items-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: right;
        }
        .items-table td:first-child,
        .items-table td:nth-child(2) {
            text-align: left;
        }
        .totals {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin: 15px 0;
        }
        .totals-left {
            font-size: 11px;
        }
        .totals-right {
            font-size: 11px;
        }
        .totals-right-item {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin: 3px 0;
            padding: 3px;
            border-bottom: 1px solid #ddd;
        }
        .totals-right-item:last-child {
            border-bottom: 2px solid #000;
            font-weight: bold;
            font-size: 12px;
        }
        .footer {
            margin-top: 30px;
            border-top: 2px solid #000;
            padding-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            font-size: 11px;
            text-align: center;
        }
        .footer div {
            border-top: 1px solid #000;
            padding-top: 30px;
            min-height: 40px;
        }
        @media print {
            body {
                background: white;
            }
            .page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-row">
                <div class="company-info">
                    <?php echo htmlspecialchars($invoice['company_name']); ?>
                    <div style="font-size: 10px; font-weight: normal;">
                        <?php echo htmlspecialchars($invoice['company_address']); ?>
                    </div>
                </div>
                <div class="title">SALE INVOICE</div>
                <div class="invoice-no">
                    <div><span class="label">Bill #:</span> <?php echo htmlspecialchars($invoice['bill_no']); ?></div>
                    <div><span class="label">Date:</span> <?php echo date('d-m-Y', strtotime($invoice['sale_date'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Customer & Branch Info -->
        <div class="section">
            <div class="section-left">
                <div><span class="label">Customer:</span> <?php echo htmlspecialchars($invoice['customer_code'] . ' - ' . $invoice['customer_name']); ?></div>
                <div><span class="label">Address:</span> <?php echo htmlspecialchars($invoice['address']); ?></div>
                <div><span class="label">Phone:</span> <?php echo htmlspecialchars($invoice['primary_phone']); ?></div>
            </div>
            <div class="section-right">
                <div><span class="label">Branch:</span> <?php echo htmlspecialchars($invoice['branch_name']); ?></div>
                <div><span class="label">Invoice Type:</span> <?php echo htmlspecialchars($invoice['invoice_type']); ?></div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="25%">Product</th>
                    <th width="10%">Unit</th>
                    <th width="10%">Qty</th>
                    <th width="12%">Unit Price</th>
                    <th width="12%">Discount</th>
                    <th width="12%">Tax</th>
                    <th width="14%">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $serial = 0;
                foreach ($items as $item): 
                    if ($item['parent_row_id']) continue; // Skip child items
                    $serial++;
                ?>
                <tr>
                    <td><?php echo $serial; ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['uom_name']); ?></td>
                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                    <td><?php echo number_format($item['sale_price'], 2); ?></td>
                    <td><?php echo number_format($item['discount_amount'], 2); ?></td>
                    <td><?php echo number_format($item['tax_amount'], 2); ?></td>
                    <td><?php echo number_format($item['net_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-left">
                <div><strong>Amount in Words:</strong></div>
                <div style="min-height: 30px; padding: 5px; border: 1px solid #ddd;">
                    <?php 
                    function numberToWords($num) {
                        $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine');
                        $teens = array('Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
                        $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');
                        
                        if ($num == 0) return 'Zero';
                        
                        $words = '';
                        while ($num > 0) {
                            if ($num >= 1000000) {
                                $words .= numberToWords(intval($num / 1000000)) . ' Million ';
                                $num %= 1000000;
                            } elseif ($num >= 1000) {
                                $words .= numberToWords(intval($num / 1000)) . ' Thousand ';
                                $num %= 1000;
                            } elseif ($num >= 100) {
                                $words .= $ones[intval($num / 100)] . ' Hundred ';
                                $num %= 100;
                            } elseif ($num >= 20) {
                                $words .= $tens[intval($num / 10)];
                                $num %= 10;
                                if ($num > 0) $words .= ' ' . $ones[$num];
                                $num = 0;
                            } else {
                                if ($num >= 10) {
                                    $words .= $teens[$num - 10];
                                } else {
                                    $words .= $ones[$num];
                                }
                                $num = 0;
                            }
                        }
                        return trim($words);
                    }
                    echo numberToWords(intval($invoice['net_amount'])) . ' Rupees only';
                    ?>
                </div>
            </div>
            <div class="totals-right">
                <div class="totals-right-item">
                    <div>Total Bill:</div>
                    <div><?php echo number_format($invoice['total_bill'], 2); ?></div>
                </div>
                <div class="totals-right-item">
                    <div>Discount:</div>
                    <div><?php echo number_format($invoice['total_discount_amount'], 2); ?></div>
                </div>
                <div class="totals-right-item">
                    <div>Shipping:</div>
                    <div><?php echo number_format($invoice['shipping_fees'] ?? 0, 2); ?></div>
                </div>
                <div class="totals-right-item">
                    <div>Net Amount:</div>
                    <div><?php echo number_format($invoice['net_amount'], 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>
                <strong>Authorized By</strong>
            </div>
            <div>
                <strong>Checked By</strong>
            </div>
            <div>
                <strong>Received By</strong>
            </div>
        </div>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>
