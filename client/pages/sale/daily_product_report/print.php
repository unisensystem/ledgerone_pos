<?php
// Path convention: pages/sales/daily_product_sale_report/print.php
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// ---- Same filters as the JSON API (all optional; blank = no filter) ----
$from_date   = $_GET['from_date'] ?? null;
$to_date     = $_GET['to_date'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;
$branch_id   = $_GET['branch_id'] ?? null;
$product_id  = $_GET['product_id'] ?? null;

// Company header (address/phone shown under the name, like the sample report)
$company_name = 'COMPANY NAME';
$company_address = '';
$company_phone = '';
try {
    $company_stmt = $pdo->prepare("SELECT company_name, address, phone FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $company_stmt->execute([$tenant_id]);
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        if (!empty($company['company_name'])) $company_name = $company['company_name'];
        $company_address = $company['address'] ?? '';
        $company_phone = $company['phone'] ?? '';
    }
} catch (Exception $e) {
    error_log('Print report - company lookup skipped: ' . $e->getMessage());
}

try {
    $currency_stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc
                     JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
                     WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
    $currency_stmt->execute([$tenant_id]);
    $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
    $currency_symbol = $currency['symbol'] ?? '$';

    // ---- Pass 1: invoice headers (same logic as the JSON API) ----
    $sql = "SELECT
                si.id AS invoice_id,
                si.bill_no,
                si.sale_date,
                si.bilty_no,
                si.invoice_type,
                si.net_amount,
                c.customer_name,
                c.primary_phone,
                c.strn,
                COALESCE((
                    SELECT SUM(rv.amount) FROM receive_voucher rv
                    WHERE rv.tenant_id = si.tenant_id AND rv.bill_no = si.bill_no
                ), 0) AS paid_amount,
                COALESCE((
                    SELECT SUM(sit.tax_amount) FROM sale_invoice_taxes sit
                    WHERE sit.tenant_id = si.tenant_id AND sit.sale_invoice_id = si.id
                ), 0) AS invoice_level_tax
            FROM sale_invoice si
            JOIN customers c ON si.customer_id = c.id
            WHERE si.tenant_id = ?
              AND si.status = 'Posted'";

    $params = [$tenant_id];
    if ($from_date) { $sql .= " AND si.sale_date >= ?"; $params[] = $from_date; }
    if ($to_date)   { $sql .= " AND si.sale_date <= ?"; $params[] = $to_date; }
    if ($customer_id) { $sql .= " AND si.customer_id = ?"; $params[] = $customer_id; }
    if ($branch_id)   { $sql .= " AND si.branch_id = ?"; $params[] = $branch_id; }
    if ($product_id)  { $sql .= " AND EXISTS (SELECT 1 FROM sale_invoice_items sii WHERE sii.sale_invoice_id = si.id AND sii.tenant_id = si.tenant_id AND sii.product_id = ?)"; $params[] = $product_id; }

    $sql .= " ORDER BY si.sale_date DESC, si.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoiceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $invoices = [];
    $invoiceIds = [];
    foreach ($invoiceRows as $row) {
        $netAmount = (float)$row['net_amount'];
        $paidAmount = (float)$row['paid_amount'];
        $balance = round($netAmount - $paidAmount, 2);
        if ($balance <= 0.001) {
            $status = 'Paid';
        } elseif ($paidAmount > 0) {
            $status = 'Partial';
        } else {
            $status = 'Unpaid';
        }

        $invoices[$row['invoice_id']] = [
            'bill_no' => $row['bill_no'],
            'sale_date' => $row['sale_date'],
            'ref_no' => $row['bilty_no'],
            'customer_name' => $row['customer_name'],
            'phone' => $row['primary_phone'],
            'tax_no' => $row['strn'],
            'payment_type' => $row['invoice_type'],
            'status' => $status,
            'total_amount' => $netAmount,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balance,
            'invoice_level_tax' => (float)$row['invoice_level_tax'],
            'items' => [],
            'sub_total' => 0,
            'item_tax_total' => 0,
        ];
        $invoiceIds[] = (int)$row['invoice_id'];
    }

    // ---- Pass 2: items for every matched invoice ----
    if (!empty($invoiceIds)) {
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $itemStmt = $pdo->prepare("SELECT
                sii.sale_invoice_id, p.name AS product_name, sii.batch_no,
                sii.quantity, COALESCE(u.uom_name, '') AS unit_symbol, sii.sale_price,
                sii.discount_percent, sii.gross_amount, sii.discount_amount,
                sii.trade_offer_amount, sii.tax_amount
            FROM sale_invoice_items sii
            JOIN products p ON sii.product_id = p.id AND sii.tenant_id = p.tenant_id
            LEFT JOIN uom u ON sii.uom_id = u.id
            WHERE sii.tenant_id = ? AND sii.sale_invoice_id IN ($placeholders)
            ORDER BY sii.sale_invoice_id ASC, sii.id ASC");
        $itemStmt->execute(array_merge([$tenant_id], $invoiceIds));
        $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itemRows as $item) {
            $invId = $item['sale_invoice_id'];
            if (!isset($invoices[$invId])) continue;
            $amount = (float)$item['gross_amount'] - (float)$item['discount_amount'] - (float)$item['trade_offer_amount'];
            $invoices[$invId]['items'][] = [
                'name' => $item['product_name'],
                'batch' => $item['batch_no'],
                'qty' => (float)$item['quantity'],
                'unit' => $item['unit_symbol'],
                'price' => (float)$item['sale_price'],
                'discount_percent' => (float)$item['discount_percent'],
                'amount' => $amount,
            ];
            $invoices[$invId]['sub_total'] += $amount;
            $invoices[$invId]['item_tax_total'] += (float)$item['tax_amount'];
        }
    }

    $grand_total = 0;
    foreach ($invoices as &$inv) {
        $inv['sub_total'] = round($inv['sub_total'], 2);
        $inv['tax'] = round($inv['item_tax_total'] + $inv['invoice_level_tax'], 2);
        $inv['round_off'] = round($inv['total_amount'] - ($inv['sub_total'] + $inv['tax']), 2);
        $grand_total += $inv['total_amount'];
    }
    unset($inv);
    $invoices = array_values($invoices);

} catch (Exception $e) {
    error_log('Sale Report Print Error: ' . $e->getMessage());
    die('Error generating report: ' . $e->getMessage());
}

$user_label = 'All Users';

function fmtDate($dateStr) {
    if (!$dateStr) return '';
    $t = strtotime($dateStr);
    if (!$t) return $dateStr;
    return date('d/m/Y', $t);
}

$from_label = $from_date ? fmtDate($from_date) : fmtDate(date('Y-01-01'));
$to_label   = $to_date ? fmtDate($to_date) : fmtDate(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<title>Sale Report Print</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    @page { size: A4; margin: 15mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0; padding: 0; background: #fff; font-size: 12px; }
    .sheet { max-width: 950px; margin: 0 auto; padding: 10px 15px 30px; }
    .toolbar { text-align: center; margin-bottom: 20px; display: flex; justify-content: center; gap: 10px; }
    .toolbar button { font-size: 13px; padding: 9px 20px; border: none; border-radius: 4px; cursor: pointer; color: #fff; }
    .btn-print { background: #007bff; }
    .btn-excel { background: #1e7e34; }
    .btn-cancel { background: #6c757d; }
    .btn-back { background: #495057; }
    .print-header { text-align: center; margin-bottom: 4px; }
    .print-header h1 { margin: 0; font-size: 20px; font-weight: bold; }
    .print-header .addr { font-size: 11px; color: #333; margin-top: 2px; }
    .print-header h2 { margin: 10px 0 6px; font-size: 15px; font-weight: bold; text-decoration: underline; }
    .meta-row { display: flex; justify-content: space-between; font-size: 11px; margin: 6px 0 12px; }

    .sale-block { border: 1px solid #000; margin-bottom: 12px; page-break-inside: avoid; }
    table.header-table, table.items-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    table.header-table td, table.header-table th,
    table.items-table td, table.items-table th { border: 1px solid #000; padding: 4px 6px; text-align: left; }
    table.header-table th { background: #eef1f5; font-weight: bold; white-space: nowrap; }
    table.items-table th { background: #f5f5f5; font-weight: bold; }
    .num { text-align: right; }
    .totals-row td { font-weight: bold; }
    .sub-lines { padding: 4px 8px; text-align: right; font-size: 11px; }
    .sub-lines div { margin: 2px 0; }

    .grand-total { margin-top: 16px; border-top: 2px solid #000; padding-top: 8px; font-weight: bold; font-size: 14px; text-align: right; }
    .footer-note { margin-top: 20px; font-size: 10px; color: #666; }
    .no-print { display: block; }
    @media print {
        .no-print { display: none; }
        .sheet { padding-left: 0; padding-right: 0; }
    }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">Print</button>
    <button class="btn-excel" onclick="exportToExcel()">Export to Excel</button>
    <button class="btn-cancel" onclick="window.close()">Cancel</button>
    <button class="btn-back" onclick="window.location.href='daily-product-sale.php'">Back to Page</button>
</div>

<div class="sheet">

<div class="print-header">
    <h1><?= htmlspecialchars(strtoupper($company_name)) ?></h1>
    <?php if ($company_address || $company_phone): ?>
        <div class="addr">
            <?= $company_address ? 'Address: ' . htmlspecialchars($company_address) : '' ?>
            <?= ($company_address && $company_phone) ? ', ' : '' ?>
            <?= $company_phone ? 'Ph. no.: ' . htmlspecialchars($company_phone) : '' ?>
        </div>
    <?php endif; ?>
    <h2>Sale Report</h2>
</div>

<div class="meta-row">
    <div>Username: <?= htmlspecialchars($user_label) ?></div>
    <div>Duration: From <?= htmlspecialchars($from_label) ?> to <?= htmlspecialchars($to_label) ?></div>
</div>

<?php if (empty($invoices)): ?>
    <div style="text-align:center; padding: 24px;">No records found</div>
<?php else: ?>
    <?php foreach ($invoices as $inv): ?>
        <div class="sale-block">
            <table class="header-table">
                <tr>
                    <th>Date</th><th>Bill No</th><th>Ref No</th><th>Party Name</th><th>Phone No</th>
                    <th>Tax No</th><th>Txn Type</th><th>Status</th><th class="num">Total Amount</th>
                    <th>Payment Type</th><th class="num">Received/Paid</th><th class="num">Balance</th>
                </tr>
                <tr>
                    <td><?= htmlspecialchars(fmtDate($inv['sale_date'])) ?></td>
                    <td><?= htmlspecialchars($inv['bill_no']) ?></td>
                    <td><?= htmlspecialchars($inv['ref_no'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                    <td><?= htmlspecialchars($inv['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($inv['tax_no'] ?? '-') ?></td>
                    <td>Sale</td>
                    <td><?= htmlspecialchars($inv['status']) ?></td>
                    <td class="num"><?= number_format($inv['total_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($inv['payment_type']) ?></td>
                    <td class="num"><?= number_format($inv['paid_amount'], 2) ?></td>
                    <td class="num"><?= number_format($inv['balance_amount'], 2) ?></td>
                </tr>
            </table>
            <table class="items-table">
                <tr>
                    <th>Item Name</th><th>Batch</th><th class="num">Quantity</th>
                    <th class="num">Price/Unit</th><th class="num">Discount</th><th class="num">Amount</th>
                </tr>
                <?php foreach ($inv['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['batch'] ?? '-') ?></td>
                        <td class="num"><?= number_format($item['qty'], 2) ?> <?= htmlspecialchars($item['unit']) ?></td>
                        <td class="num"><?= number_format($item['price'], 2) ?></td>
                        <td class="num"><?= number_format($item['discount_percent'], 1) ?>%</td>
                        <td class="num"><?= number_format($item['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <div class="sub-lines">
                <div>Sub Total: <?= number_format($inv['sub_total'], 2) ?></div>
                <div>Tax: <?= number_format($inv['tax'], 2) ?></div>
                <div>Round off: <?= number_format($inv['round_off'], 2) ?></div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="grand-total">
        Total Sale: <?= htmlspecialchars($currency_symbol) ?><?= number_format($grand_total, 2) ?>
    </div>
<?php endif; ?>

<div class="footer-note">Generated on <?= date('M d, Y \a\t h:i A') ?></div>

</div>

<script>
    const reportInvoices = <?= json_encode(array_map(function ($inv) {
        return [
            'date' => fmtDate($inv['sale_date']),
            'bill_no' => $inv['bill_no'],
            'ref_no' => $inv['ref_no'],
            'customer' => $inv['customer_name'],
            'phone' => $inv['phone'],
            'tax_no' => $inv['tax_no'],
            'status' => $inv['status'],
            'total' => $inv['total_amount'],
            'payment_type' => $inv['payment_type'],
            'paid' => $inv['paid_amount'],
            'balance' => $inv['balance_amount'],
            'sub_total' => $inv['sub_total'],
            'tax' => $inv['tax'],
            'round_off' => $inv['round_off'],
            'items' => array_map(function ($item) {
                return [
                    'name' => $item['name'], 'batch' => $item['batch'], 'qty' => $item['qty'],
                    'unit' => $item['unit'], 'price' => $item['price'],
                    'discount_percent' => $item['discount_percent'], 'amount' => $item['amount'],
                ];
            }, $inv['items']),
        ];
    }, $invoices)) ?>;

    function exportToExcel() {
        if (typeof XLSX === 'undefined') {
            alert('Excel export library failed to load. Please check your internet connection and reload the page.');
            return;
        }
        if (!reportInvoices.length) {
            alert('No data available to export.');
            return;
        }

        const rows = [];
        reportInvoices.forEach(inv => {
            rows.push({
                'Date': inv.date, 'Bill No': inv.bill_no, 'Ref No': inv.ref_no || '',
                'Party Name': inv.customer, 'Phone No': inv.phone || '', 'Tax No': inv.tax_no || '',
                'Status': inv.status, 'Total Amount': inv.total.toFixed(2), 'Payment Type': inv.payment_type,
                'Received/Paid': inv.paid.toFixed(2), 'Balance': inv.balance.toFixed(2),
                'Item Name': '', 'Batch': '', 'Quantity': '', 'Price/Unit': '', 'Discount': '', 'Amount': ''
            });
            inv.items.forEach(item => {
                rows.push({
                    'Date': '', 'Bill No': '', 'Ref No': '', 'Party Name': '', 'Phone No': '', 'Tax No': '',
                    'Status': '', 'Total Amount': '', 'Payment Type': '', 'Received/Paid': '', 'Balance': '',
                    'Item Name': item.name, 'Batch': item.batch || '',
                    'Quantity': `${item.qty.toFixed(2)} ${item.unit || ''}`.trim(),
                    'Price/Unit': item.price.toFixed(2),
                    'Discount': `${item.discount_percent.toFixed(1)}%`,
                    'Amount': item.amount.toFixed(2)
                });
            });
            rows.push({
                'Date': '', 'Bill No': '', 'Ref No': '', 'Party Name': '', 'Phone No': '', 'Tax No': '',
                'Status': '', 'Total Amount': '', 'Payment Type': '', 'Received/Paid': '', 'Balance': '',
                'Item Name': '', 'Batch': '', 'Quantity': '',
                'Price/Unit': `Sub Total ${inv.sub_total.toFixed(2)} / Tax ${inv.tax.toFixed(2)} / Round off ${inv.round_off.toFixed(2)}`,
                'Discount': '', 'Amount': ''
            });
        });

        const worksheet = XLSX.utils.json_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Sale Report');
        XLSX.writeFile(workbook, `sale_report_${new Date().toISOString().slice(0, 10)}.xlsx`);
    }
</script>

</body>
</html>
