<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../../../../includes/connection.php';
session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) die('Invoice ID is required');

try {
    $stmt = $pdo->prepare("
        SELECT
            si.*,
            c.customer_name,
            c.customer_code,
            c.address         AS customer_address,
            c.po_box_no       AS customer_pobox,
            c.strn            AS customer_trn,
            c.primary_phone   AS customer_mob,
            c.secondary_phone AS customer_phone,
            c.email           AS customer_email,
            co.company_name,
            co.email              AS company_email,
            co.phone              AS company_phone,
            co.mobile_no          AS company_mob,
            co.po_box_no          AS company_pobox,
            co.sales_tax_number   AS company_trn,
            co.address            AS company_address,
            co.city               AS company_city,
            co.state              AS company_state,
            co.country            AS company_country,
            co.logo_url           AS company_logo,
            b.branch_name,
            u.full_name           AS created_by_name,
            cur.code              AS currency_code,
            cur.symbol            AS currency_symbol,
            pr.project_name,
            COALESCE(rv.amount, 0) AS amount_paid
        FROM sale_invoice si
        LEFT JOIN customers  c   ON si.customer_id = c.id
        LEFT JOIN companies  co  ON si.company_id  = co.id
        LEFT JOIN branches   b   ON si.branch_id   = b.id
        LEFT JOIN employees  u   ON si.created_by  = u.id
        LEFT JOIN ledgerone_public.currencies cur ON cur.id = si.currency_id
        LEFT JOIN projects pr ON pr.id = si.project_id
        LEFT JOIN receive_voucher rv ON rv.bill_no = si.bill_no AND rv.tenant_id = si.tenant_id
        WHERE si.id = ? AND si.tenant_id = ?
    ");
    $stmt->execute([$invoice_id, $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) die('Invoice not found');

    $itemStmt = $pdo->prepare("
        SELECT sii.*, p.name AS product_name, u.uom_name
        FROM sale_invoice_items sii
        LEFT JOIN products p ON sii.product_id = p.id
        LEFT JOIN uom u      ON sii.uom_id     = u.id
        WHERE sii.sale_invoice_id = ? AND sii.tenant_id = ?
        ORDER BY COALESCE(sii.parent_row_id, sii.id), sii.id
    ");
    $itemStmt->execute([$invoice_id, $tenant_id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $taxStmt = $pdo->prepare("
        SELECT tax_name, rate_percentage, tax_amount
        FROM sale_invoice_taxes
        WHERE sale_invoice_id = ? AND tenant_id = ?
        ORDER BY id
    ");
    $taxStmt->execute([$invoice_id, $tenant_id]);
    $invoiceTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

function numberToWords(float $amount, string $currencyCode = 'AED'): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $map  = [
        'USD'=>['Dollars','Cents'],   'EUR'=>['Euros','Cents'],
        'GBP'=>['Pounds','Pence'],    'PKR'=>['Rupees','Paisa'],
        'INR'=>['Rupees','Paisa'],    'AED'=>['Dirhams','Fils'],
        'SAR'=>['Riyals','Halalah'],  'OMR'=>['Rials','Baisa'],
        'QAR'=>['Riyals','Dirhams'],  'KWD'=>['Dinars','Fils'],
        'BHD'=>['Dinars','Fils'],     'JPY'=>['Yen','Sen'],
        'CNY'=>['Yuan','Fen'],        'CAD'=>['Dollars','Cents'],
        'AUD'=>['Dollars','Cents'],   'CHF'=>['Francs','Rappen'],
        'TRY'=>['Lira','Kurus'],      'BDT'=>['Taka','Paisa'],
        'MYR'=>['Ringgit','Sen'],     'SGD'=>['Dollars','Cents'],
        'ZAR'=>['Rand','Cents'],      'NGN'=>['Naira','Kobo'],
        'EGP'=>['Pounds','Piastres'], 'NPR'=>['Rupees','Paisa'],
    ];
    [$maj, $min] = $map[strtoupper($currencyCode)] ?? ['Units','Subunits'];

    function _cvt(int $n, array $o, array $t): string {
        if ($n===0)        return '';
        if ($n<20)         return $o[$n].' ';
        if ($n<100)        return $t[(int)($n/10)].' '.($n%10?$o[$n%10].' ':'');
        if ($n<1000)       return $o[(int)($n/100)].' Hundred '._cvt($n%100,$o,$t);
        if ($n<100000)     return _cvt((int)($n/1000),$o,$t).'Thousand '._cvt($n%1000,$o,$t);
        if ($n<10000000)   return _cvt((int)($n/100000),$o,$t).'Lakh '._cvt($n%100000,$o,$t);
        return                    _cvt((int)($n/10000000),$o,$t).'Crore '._cvt($n%10000000,$o,$t);
    }

    $amount = round($amount, 2);
    $major  = (int)$amount;
    $minor  = (int)round(($amount - $major) * 100);
    $words  = trim(_cvt($major, $ones, $tens));
    $result = ($words ?: 'Zero').' '.$maj;
    if ($minor > 0) $result .= ' and '.trim(_cvt($minor,$ones,$tens)).' '.$min;
    return strtolower($result).' only';
}

$currencyCode  = $invoice['currency_code'] ?? 'AED';
$taxableAmount = (float)($invoice['total_bill']  ?? 0)
               - (float)($invoice['amount_paid'] ?? 0);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Tax Invoice <?php echo htmlspecialchars($invoice['bill_no']); ?></title>
<style>
/* ── COLOR PALETTE
   #7E8C6F  TAX INVOICE title strip
   #ABB697  column headers, branch strip, totals labels, name label
   #CDD5BF  value cells (Invoice No val, Date val, party field values, name value)
   #82886E  AMOUNT label cell specifically
── */

* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
    background: #d0d0d0;
}

.page {
    width: 210mm;
    margin: 8mm auto;
    background: #fff;
    border: 1px solid #888;
    padding: 6mm 8mm;
}

/* ── SHARED: all block-level sections share the outer border ── */
.inv-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid #000;
    border-top: none;
}
.inv-table td, .inv-table th {
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 11px;
    vertical-align: middle;
}

/* ── COLUMN WIDTHS (shared across meta, items, totals) ──
   col-sn   : 5%   Sr.No.
   col-desc : 38%  Description
   col-qty  : 10%  Qty
   col-tqty : 12%  T QTY
   col-price: 13%  PRICE
   col-amt  : 22%  Amount
   Total    : 100%
── */

/* ── INVOICE NO / DATE ── */
.meta-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid #000;
}
.meta-table td { border: 1px solid #000; padding: 3px 7px; font-size: 11px; }
.meta-lbl  { background: #ABB697; font-weight: bold; width: 80px; white-space: nowrap; }
.meta-val  { background: #CDD5BF; }
.meta-gap  { background: #fff; border-left: none !important; border-right: none !important; }

/* ── TAX INVOICE TITLE ── */
.title-strip {
    background: #7E8C6F;
    color: #fff;
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    padding: 5px 0;
    letter-spacing: 2px;
    border: 1px solid #000;
    border-top: none;
}

/* ── FROM / TO HEADER ── */
.from-to-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    border-top: none;
}
.from-to-table td {
    width: 50%;
    padding: 3px 8px;
    font-weight: bold;
    font-size: 11px;
    border-right: 1px solid #000;
    background: #ABB697;
}
.from-to-table td:last-child { border-right: none; }

/* ── PARTY TABLE ── */
.party-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    border-top: none;
}
.party-table > tbody > tr > td {
    width: 50%;
    padding: 0;
    vertical-align: top;
    border-right: 1px solid #000;
}
.party-table > tbody > tr > td:last-child { border-right: none; }

.party-name {
    font-weight: bold;
    font-size: 11px;
    text-align: center;
    padding: 4px 8px;
    border-bottom: 1px solid #000;
    background: #CDD5BF;
}
.party-row {
    display: table;
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1px solid #000;
}
.party-row:last-child { border-bottom: none; }
.party-row .pl {
    display: table-cell;
    width: 86px;
    font-weight: bold;
    padding: 2px 5px 2px 7px;
    font-size: 10px;
    border-right: 1px solid #000;
    white-space: nowrap;
    background: #ABB697;
}
.party-row .pv {
    display: table-cell;
    padding: 2px 5px;
    font-size: 10px;
    background: #CDD5BF;
}

/* ── BRANCH / PROJECT STRIP ── */
.branch-strip {
    background: #ABB697;
    color: #000;
    text-align: center;
    font-size: 12px;
    font-weight: bold;
    padding: 4px 8px;
    border: 1px solid #000;
    border-top: none;
    letter-spacing: 0.5px;
}

/* ── ITEMS + TOTALS (single shared table so colgroup guarantees column alignment) ── */
.items-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid #000;
    border-top: none;
}
.items-table th {
    background: #ABB697;
    color: #000;
    padding: 4px 5px;
    border: 1px solid #000;
    text-align: center;
    font-size: 10px;
    font-weight: bold;
}
.items-table td {
    border: 1px solid #000;
    padding: 3px 5px;
    font-size: 10px;
    vertical-align: middle;
}
.items-table td.c { text-align: center; }
.items-table td.r { text-align: right; }
.items-table td.l { text-align: left; }

/* totals rows live inside the same .items-table */
.t-label { background: #ABB697; font-weight: bold; font-size: 11px; padding: 3px 6px; }
.t-value { background: #CDD5BF; text-align: right; font-weight: bold; font-size: 11px; padding: 3px 6px; }

.t-amount .t-label { background: #82886E; color: #fff; }

.t-total td        { font-size: 12px; font-weight: bold; border-top: 2px solid #000; }
.t-total .t-label  { background: #ABB697; }
.t-total .t-value  { background: #CDD5BF; }

.t-words td        { font-size: 10px; background: #CDD5BF; }
.t-words td:first-child { background: #fff; }
.t-words-lbl       { font-weight: bold; }

/* ── NAME ROW ── */
.name-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    border-top: none;
}
.name-table td  { border: 1px solid #000; padding: 4px 7px; font-size: 11px; }
.name-lbl       { background: transparent; font-weight: bold; width: 55px; }
.name-val       { background: #CDD5BF; }

/* ── SIGNATURE SECTION ── */
.sig-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    border-top: none;
    margin-top: 5px;
}
.sig-table td {
    width: 50%;
    padding: 6px 10px;
    border: 1px solid #000;
    vertical-align: top;
    height: 65px;
}
.sig-party { font-weight: bold; font-size: 11px; margin-bottom: 28px; }
.sig-line  { border-top: 1px solid #555; padding-top: 3px; font-size: 10px; color: #333; display: inline-block; min-width: 130px; }

/* ── HEADER ── */
.inv-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 4px 0 10px;
    border-bottom: 1px solid #ddd;
    margin-bottom: 8px;
}
.inv-header-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.inv-header-left img {
    max-height: 60px;
    max-width: 120px;
    object-fit: contain;
    flex-shrink: 0;
}
.inv-company-info h2 {
    margin: 0 0 3px;
    font-size: 15px;
    font-weight: 800;
    color: #111;
    letter-spacing: 0.3px;
}
.inv-company-info p {
    margin: 1px 0;
    font-size: 10px;
    color: #444;
}
.inv-header-right {
    text-align: right;
    font-size: 10px;
    color: #444;
    line-height: 1.6;
}

/* ── WATERMARK ── */
.page {
    position: relative;
}
.page::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 55%;
    height: 55%;
    background-image: var(--wm-logo);
    background-repeat: no-repeat;
    background-size: contain;
    background-position: center;
    opacity: 0.07;
    pointer-events: none;
    z-index: 0;
}
/* keep all content above the watermark layer */
.page > * { position: relative; z-index: 1; }

@media print {
    body  { background: #fff; }
    .page { margin:0; border:none; padding: 4mm 6mm; width:100%; }
}
</style>
</head>
<?php
$logoFile = !empty($invoice['company_logo']) ? $invoice['company_logo'] : '';
$logoUrl  = $logoFile ? '../../../assets/uploads/company_logo/' . htmlspecialchars($logoFile) : '';
$logoAbsUrl = $logoFile ? '/ledgerone_erp/client/assets/uploads/company_logo/' . htmlspecialchars($logoFile) : '';
?>
<body>
<?php if ($logoAbsUrl): ?>
<style>
    .page { --wm-logo: url('<?php echo $logoAbsUrl; ?>'); }
</style>
<?php endif; ?>
<div class="page">

    <!-- HEADER -->
    <div class="inv-header">
        <!-- LEFT: logo + company name & address -->
        <div class="inv-header-left">
            <?php if ($logoUrl): ?>
            <img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlspecialchars($invoice['company_name']); ?>">
            <?php endif; ?>
            <div class="inv-company-info">
                <h2><?php echo htmlspecialchars($invoice['company_name']); ?></h2>
                <?php if (!empty($invoice['company_address'])): ?>
                <p><?php echo htmlspecialchars($invoice['company_address']); ?></p>
                <?php endif; ?>
                <?php
                $cityLine = implode(', ', array_filter([
                    $invoice['company_city']    ?? '',
                    $invoice['company_state']   ?? '',
                    $invoice['company_country'] ?? '',
                ]));
                if ($cityLine): ?>
                <p><?php echo htmlspecialchars($cityLine); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['company_pobox'])): ?>
                <p>P.O. Box: <?php echo htmlspecialchars($invoice['company_pobox']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <!-- RIGHT: email & phone -->
        <div class="inv-header-right">
            <?php if (!empty($invoice['company_email'])): ?>
            <div><?php echo htmlspecialchars($invoice['company_email']); ?></div>
            <?php endif; ?>
            <?php if (!empty($invoice['company_phone'])): ?>
            <div><?php echo htmlspecialchars($invoice['company_phone']); ?></div>
            <?php endif; ?>
            <?php if (!empty($invoice['company_mob']) && $invoice['company_mob'] !== $invoice['company_phone']): ?>
            <div><?php echo htmlspecialchars($invoice['company_mob']); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- INVOICE NO / DATE -->
    <table class="meta-table">
        <colgroup>
            <col style="width:80px">
            <col style="width:100px">
            <col>
            <col style="width:45px">
            <col style="width:90px">
        </colgroup>
        <tr>
            <td class="meta-lbl">Invoice No</td>
            <td class="meta-val"><?php echo htmlspecialchars($invoice['bill_no']); ?></td>
            <td class="meta-gap"></td>
            <td class="meta-lbl" style="text-align:right">Date</td>
            <td class="meta-val" style="text-align:right"><?php echo date('d/m/Y', strtotime($invoice['sale_date'])); ?></td>
        </tr>
    </table>

    <!-- TAX INVOICE TITLE -->
    <div class="title-strip">TAX INVOICE</div>

    <!-- FROM / TO HEADER -->
    <table class="from-to-table">
        <tr>
            <td>From:</td>
            <td>To:-</td>
        </tr>
    </table>

    <!-- PARTY DETAILS -->
    <table class="party-table">
        <tbody>
        <tr>
            <!-- FROM: Company -->
            <td>
                <div class="party-name"><?php echo htmlspecialchars($invoice['company_name']); ?></div>
                <div class="party-row"><span class="pl">P.O. Box No</span><span class="pv"><?php echo htmlspecialchars($invoice['company_pobox'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">TRN No</span><span class="pv"><?php echo htmlspecialchars($invoice['company_trn'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">Mob No</span><span class="pv"><?php echo htmlspecialchars($invoice['company_mob'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">Phone No</span><span class="pv"><?php echo htmlspecialchars($invoice['company_phone'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">Email</span><span class="pv"><?php echo htmlspecialchars($invoice['company_email'] ?? ''); ?></span></div>
            </td>
            <!-- TO: Customer -->
            <td>
                <div class="party-name"><?php echo htmlspecialchars($invoice['customer_name'] ?? ''); ?></div>
                <div class="party-row"><span class="pl">P.O. Box No</span><span class="pv"><?php echo htmlspecialchars($invoice['customer_pobox'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">TRN No</span><span class="pv"><?php echo htmlspecialchars($invoice['customer_trn'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">Mob No</span><span class="pv"><?php echo htmlspecialchars($invoice['customer_mob'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">Phone No</span><span class="pv"><?php echo htmlspecialchars($invoice['customer_phone'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">Email</span><span class="pv"><?php echo htmlspecialchars($invoice['customer_email'] ?? ''); ?></span></div>
                <div class="party-row"><span class="pl">PROJECT</span><span class="pv"><?php echo htmlspecialchars($invoice['project_name'] ?? ''); ?></span></div>
            </td>
        </tr>
        </tbody>
    </table>

    <!-- BRANCH STRIP -->
    <div class="branch-strip">
        <?php echo strtoupper(htmlspecialchars($invoice['branch_name'] ?? $invoice['company_name'])); ?>
    </div>

    <!-- ITEMS + TOTALS: single table with shared colgroup ensures perfect column alignment -->
    <table class="items-table">
        <colgroup>
            <col style="width:5%">   <!-- Sr.No. -->
            <col style="width:38%">  <!-- Description -->
            <col style="width:10%">  <!-- Qty -->
            <col style="width:12%">  <!-- T QTY -->
            <col style="width:13%">  <!-- PRICE -->
            <col style="width:22%">  <!-- Amount -->
        </colgroup>
        <thead>
            <tr>
                <th>Sr.No.</th>
                <th style="text-align:left">Description</th>
                <th>Qty</th>
                <th>T QTY</th>
                <th>PRICE</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $serial = 0;
            foreach ($items as $item):
                if ($item['parent_row_id']) continue;
                $serial++;
            ?>
            <tr>
                <td class="c"><?php echo $serial; ?></td>
                <td class="l"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td class="c"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                <td class="r"></td>
                <td class="r"><?php echo number_format((float)$item['sale_price'], 2); ?></td>
                <td class="r"><?php echo number_format((float)$item['net_amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = $serial; $i < 4; $i++): ?>
            <tr><td class="c">&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
        </tbody>
        <!-- TOTALS share the same colgroup: col 1 empty, cols 2-5 = label, col 6 = value -->
        <tfoot>
            <tr class="t-amount">
                <td></td>
                <td class="t-label" colspan="4">AMOUNT</td>
                <td class="t-value"><?php echo number_format((float)$invoice['total_bill'], 2); ?></td>
            </tr>
            <tr>
                <td></td>
                <td class="t-label" colspan="4">AMOUNT RECEIVED</td>
                <td class="t-value"><?php echo number_format((float)($invoice['amount_paid'] ?? 0), 2); ?></td>
            </tr>
            <tr>
                <td></td>
                <td class="t-label" colspan="4">TAXABLE AMOUNT</td>
                <td class="t-value"><?php echo number_format($taxableAmount, 2); ?></td>
            </tr>
            <?php foreach ($invoiceTaxes as $tax): ?>
            <?php if ((float)$tax['tax_amount'] > 0): ?>
            <tr>
                <td></td>
                <td class="t-label" colspan="4">
                    <?php echo htmlspecialchars($tax['tax_name']); ?>
                    <?php if ((float)$tax['rate_percentage'] > 0): ?>
                        <?php echo (float)$tax['rate_percentage']; ?>%
                    <?php endif; ?>
                </td>
                <td class="t-value"><?php echo number_format((float)$tax['tax_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if ((float)($invoice['shipping_fees'] ?? 0) > 0): ?>
            <tr>
                <td></td>
                <td class="t-label" colspan="4">SHIPPING</td>
                <td class="t-value"><?php echo number_format((float)$invoice['shipping_fees'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="t-total">
                <td></td>
                <td class="t-label" colspan="4">Total Amount</td>
                <td class="t-value"><?php echo number_format((float)$invoice['net_amount'] - (float)($invoice['amount_paid'] ?? 0), 2); ?></td>
            </tr>
            <tr class="t-words">
                <td></td>
                <td colspan="5"><span class="t-words-lbl">In Words: </span><?php echo htmlspecialchars(numberToWords((float)$invoice['net_amount'] - (float)($invoice['amount_paid'] ?? 0), $currencyCode)); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- NAME / REMARKS ROW -->
    <table class="name-table">
        <tr>
            <td class="name-lbl">Name:</td>
            <td class="name-val"><?php echo htmlspecialchars($invoice['remarks'] ?? ''); ?></td>
        </tr>
    </table>

    <!-- SIGNATURES -->
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-party"><?php echo htmlspecialchars($invoice['company_name']); ?></div>
                <span class="sig-line">STAMP &amp; SIGNATURE</span>
            </td>
            <td>
                <div class="sig-party"><?php echo htmlspecialchars($invoice['customer_name'] ?? ''); ?></div>
                <span class="sig-line">RECEIVED BY</span>
            </td>
        </tr>
    </table>

</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    const isPdf = new URLSearchParams(window.location.search).get('pdf') === '1';
    if (isPdf) {
        (async function() {
            await html2pdf().set({
                margin: 0,
                filename: 'TaxInvoice-<?php echo htmlspecialchars($invoice["bill_no"]); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(document.querySelector('.page')).save();
            window.close();
        })();
    } else {
        window.print();
    }
</script>
</body>
</html>
