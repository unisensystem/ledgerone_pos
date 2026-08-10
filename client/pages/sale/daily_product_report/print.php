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

// ---- Same filters as the API (all optional; blank = no filter) ----
$from_date   = $_GET['from_date'] ?? null;
$to_date     = $_GET['to_date'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;
$branch_id   = $_GET['branch_id'] ?? null;
$product_id  = $_GET['product_id'] ?? null;

// Company / tenant name for the print header (kept separate from the main try/catch
// below on purpose: if this table/column name doesn't match your schema yet, the
// report should still print with a placeholder title instead of failing outright).
$company_name = 'COMPANY NAME';
try {
    $company_sql = "SELECT company_name FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1";
    $company_stmt = $pdo->prepare($company_sql);
    $company_stmt->execute([$tenant_id]);
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
    if ($company && !empty($company['company_name'])) {
        $company_name = $company['company_name'];
    }
} catch (Exception $e) {
    error_log('Print report - tenant name lookup skipped: ' . $e->getMessage());
}

try {
    // Base currency symbol (same pattern as stock-position.php)
    $currency_sql = "SELECT c.symbol FROM tenant_currencies tc
                     JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
                     WHERE tc.tenant_id = ? AND tc.is_base_currency = 1";
    $currency_stmt = $pdo->prepare($currency_sql);
    $currency_stmt->execute([$tenant_id]);
    $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
    $currency_symbol = $currency['symbol'] ?? '$';

    // ---- Same query as the JSON API, unfiltered = all sales ----
    $sql = "SELECT
                si.id as invoice_id,
                si.bill_no,
                si.sale_date,
                c.customer_name,
                p.code as product_code,
                p.name as product_name,
                sii.quantity,
                COALESCE(u.uom_name, '') as unit_symbol,
                sii.sale_price,
                sii.net_amount
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND sii.tenant_id = si.tenant_id
            JOIN products p ON sii.product_id = p.id AND sii.tenant_id = p.tenant_id
            JOIN customers c ON si.customer_id = c.id
            LEFT JOIN uom u ON sii.uom_id = u.id
            WHERE sii.tenant_id = ?
              AND si.status = 'Posted'";

    $params = [$tenant_id];

    if ($from_date) { $sql .= " AND si.sale_date >= ?"; $params[] = $from_date; }
    if ($to_date)   { $sql .= " AND si.sale_date <= ?"; $params[] = $to_date; }
    if ($customer_id) { $sql .= " AND si.customer_id = ?"; $params[] = $customer_id; }
    if ($branch_id)   { $sql .= " AND si.branch_id = ?"; $params[] = $branch_id; }
    if ($product_id)  { $sql .= " AND p.id = ?"; $params[] = $product_id; }

    $sql .= " ORDER BY si.sale_date ASC, si.bill_no ASC, sii.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Daily Sale Report Print Error: ' . $e->getMessage());
    die('Error generating report: ' . $e->getMessage());
}

// Logged-in user's name (shown where the sample says "ALL USER") — kept out of the
// main try/catch so a mismatched users table/column doesn't break the report.
$user_label = 'ALL USER';
try {
    $user_sql = "SELECT full_name FROM users WHERE id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_row && !empty($user_row['full_name'])) {
        $user_label = $user_row['full_name'];
    }
} catch (Exception $e) {
    error_log('Print report - user name lookup skipped: ' . $e->getMessage());
}

// ---- Group flat rows into per-invoice blocks (same logic as the JS) ----
$groups = [];
$indexByInvoice = [];
foreach ($rows as $row) {
    $key = $row['invoice_id'];
    if (!isset($indexByInvoice[$key])) {
        $indexByInvoice[$key] = count($groups);
        $groups[] = [
            'bill_no'       => $row['bill_no'],
            'sale_date'     => $row['sale_date'],
            'customer_name' => $row['customer_name'],
            'items'         => [],
            'total_amount'  => 0,
        ];
    }
    $idx = $indexByInvoice[$key];
    $groups[$idx]['items'][] = $row;
    $groups[$idx]['total_amount'] += (float)$row['net_amount'];
}

$grand_qty = 0;
$grand_amount = 0;
foreach ($rows as $row) {
    $grand_qty += (float)$row['quantity'];
    $grand_amount += (float)$row['net_amount'];
}

function fmtDateShort($dateStr) {
    if (!$dateStr) return '';
    $t = strtotime($dateStr);
    if (!$t) return $dateStr;
    return date('d/m/y', $t);
}

$from_label = $from_date ? fmtDateShort($from_date) : fmtDateShort(date('Y-m-d'));
$to_label   = $to_date ? fmtDateShort($to_date) : fmtDateShort(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<title>Daily Product Sale Print</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    @page { size: A4; margin: 22mm; }
    * { box-sizing: border-box; }
    body {
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        margin: 0;
        padding: 0;
        background: #fff;
    }
    .sheet {
        max-width: 900px;
        margin: 0 auto;
        padding: 10px 15px 30px;
    }
    .toolbar {
        text-align: center;
        margin-bottom: 20px;
        display: flex;
        justify-content: center;
        gap: 10px;
    }
    .toolbar button {
        font-family: Arial, sans-serif;
        font-size: 13px;
        padding: 9px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        color: #fff;
    }
    .btn-print   { background: #007bff; }
    .btn-excel   { background: #1e7e34; }
    .btn-cancel  { background: #6c757d; }
    .btn-back    { background: #495057; }
    .print-header {
        text-align: center;
        margin-bottom: 6px;
    }
    .print-header h1 {
        margin: 0;
        font-size: 30px;
        letter-spacing: 7px;
        font-weight: bold;
    }
    .print-header h2 {
        margin: 10px 0 0;
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .meta-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        font-style: italic;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 5px 0;
        margin: 14px 0 0;
    }
    .meta-row .center {
        flex: 1;
        text-align: center;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 12.5px;
        border: 1px solid #000;
    }
    col.c-sno     { width: 8%; }
    col.c-code    { width: 12%; }
    col.c-detail  { width: 37%; }
    col.c-price   { width: 12%; }
    col.c-qty     { width: 13%; }
    col.c-amount  { width: 18%; }
    thead th, tbody td {
        border: 1px solid #000;
    }
    thead th {
        padding: 8px 8px;
        text-align: left;
        font-weight: bold;
        background-color: #eef1f5;
    }
    thead th.num, td.num {
        text-align: right;
    }
    tbody td, thead th {
        color: #000;
    }
    tbody td {
        padding: 5px 8px;
        vertical-align: top;
        line-height: 1.4;
        word-wrap: break-word;
    }
    tr.invoice-header td.bill-no {
        text-align: right;
    }
    tr.total-row td {
        font-weight: bold;
    }
    .grand-total {
        margin-top: 14px;
        border-top: 2px solid #000;
        padding-top: 8px;
        font-weight: bold;
        font-size: 13px;
        text-align: right;
    }
    .no-print {
        display: block;
    }
    @media print {
        .no-print { display: none; }
        .sheet { padding-left: 25px; padding-right: 25px; }
    }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">Print</button>
    <button class="btn-excel" onclick="exportToExcel()">Export to Excel</button>
    <button class="btn-cancel" onclick="window.close()">Cancel</button>
    <button class="btn-back" onclick="window.location.href='index.php'">Back to Page</button>
</div>

<div class="sheet">

<div class="print-header">
    <h1><?= htmlspecialchars(strtoupper($company_name)) ?></h1>
    <h2>DAILY PRODUCT SALE</h2>
</div>

<div class="meta-row">
    <div>FROM DATE <?= htmlspecialchars($from_label) ?> TO <?= htmlspecialchars($to_label) ?></div>
    <div class="center"><?= htmlspecialchars(strtoupper($user_label)) ?></div>
    <div>Page # - 1/1</div>
</div>

<table id="reportTable">
    <colgroup>
        <col class="c-sno">
        <col class="c-code">
        <col class="c-detail">
        <col class="c-price">
        <col class="c-qty">
        <col class="c-amount">
    </colgroup>
    <thead>
        <tr>
            <th>S. No.</th>
            <th>Code</th>
            <th>Detail</th>
            <th class="num">Price</th>
            <th class="num">Qty</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($groups)): ?>
        <tr><td colspan="6" style="text-align:center; padding:20px;">No records found</td></tr>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <tr class="invoice-header">
                <td></td>
                <td colspan="4"><?= htmlspecialchars(fmtDateShort($group['sale_date'])) ?>&nbsp;&nbsp;<strong><?= htmlspecialchars($group['customer_name']) ?></strong></td>
                <td class="bill-no"><?= htmlspecialchars($group['bill_no']) ?></td>
            </tr>
            <?php foreach ($group['items'] as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['product_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="num"><?= number_format((float)$row['sale_price'], 2) ?></td>
                    <td class="num"><?= number_format((float)$row['quantity'], 2) ?> <?= htmlspecialchars($row['unit_symbol']) ?></td>
                    <td class="num"><?= number_format((float)$row['net_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4"></td>
                <td class="num">Total</td>
                <td class="num"><?= number_format($group['total_amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($groups)): ?>
<div class="grand-total">
    Grand Total: <?= count($groups) ?> invoice(s), Qty <?= number_format($grand_qty, 2) ?>, Amount <?= htmlspecialchars($currency_symbol) ?><?= number_format($grand_amount, 2) ?>
</div>
<?php endif; ?>

</div>

<script>
    // Same grouped data the PHP just rendered, handed to JS so Export to Excel
    // produces exactly what's on screen without re-fetching anything.
    const reportGroups = <?= json_encode(array_map(function ($g) {
        return [
            'date' => fmtDateShort($g['sale_date']),
            'customer' => $g['customer_name'],
            'bill_no' => $g['bill_no'],
            'total' => $g['total_amount'],
            'items' => array_map(function ($r) {
                return [
                    'code' => $r['product_code'] ?? '-',
                    'name' => $r['product_name'],
                    'price' => (float)$r['sale_price'],
                    'qty' => (float)$r['quantity'],
                    'unit' => $r['unit_symbol'],
                    'amount' => (float)$r['net_amount'],
                ];
            }, $g['items']),
        ];
    }, $groups)) ?>;

    function exportToExcel() {
        if (typeof XLSX === 'undefined') {
            alert('Excel export library failed to load. Please check your internet connection and reload the page.');
            return;
        }
        if (!reportGroups.length) {
            alert('No data available to export.');
            return;
        }

        const rows = [];
        reportGroups.forEach(group => {
            rows.push({ 'S.No': '', 'Code': '', 'Detail': `${group.date}  ${group.customer}`, 'Price': '', 'Qty': '', 'Amount': group.bill_no });
            group.items.forEach((item, idx) => {
                rows.push({
                    'S.No': idx + 1,
                    'Code': item.code,
                    'Detail': item.name,
                    'Price': item.price.toFixed(2),
                    'Qty': `${item.qty.toFixed(2)} ${item.unit || ''}`.trim(),
                    'Amount': item.amount.toFixed(2)
                });
            });
            rows.push({ 'S.No': '', 'Code': '', 'Detail': '', 'Price': '', 'Qty': 'Total', 'Amount': group.total.toFixed(2) });
        });

        const worksheet = XLSX.utils.json_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Daily Product Sale');
        XLSX.writeFile(workbook, `daily_product_sale_report_${new Date().toISOString().slice(0, 10)}.xlsx`);
    }
</script>

</body>
</html>