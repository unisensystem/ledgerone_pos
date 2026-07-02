<?php
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo 'Invalid quotation ID'; exit; }

// Load quotation header
$stQ = $pdo->prepare("
    SELECT q.*,
           c.customer_name, c.customer_code, c.primary_phone, c.address AS customer_address, c.email AS customer_email,
           CONCAT(e.full_name) AS salesman_name,
           pt.term_name AS payment_term,
           p.project_name, p.project_code AS proj_code, ci.city_name AS project_city
    FROM quotations q
    LEFT JOIN customers     c  ON c.id  = q.customer_id    AND c.tenant_id  = q.tenant_id
    LEFT JOIN employees     e  ON e.id  = q.salesman_id    AND e.tenant_id  = q.tenant_id
    LEFT JOIN payment_terms pt ON pt.id = q.payment_term_id
    LEFT JOIN projects      p  ON p.id  = q.project_id
    LEFT JOIN cities        ci ON ci.id = p.city_id
    WHERE q.id = ? AND q.tenant_id = ? AND q.is_deleted = 0
");
$stQ->execute([$id, $tenant_id]);
$q = $stQ->fetch(PDO::FETCH_ASSOC);
if (!$q) { echo 'Quotation not found'; exit; }

// Load items
$stI = $pdo->prepare("
    SELECT qi.*, p.code AS product_code, p.name AS product_name,
           u.uom_name AS unit_name, s.subcategory_name
    FROM quotation_items qi
    LEFT JOIN products     p ON p.id  = qi.product_id AND p.tenant_id = qi.tenant_id
    LEFT JOIN uom          u ON u.id  = qi.unit_id
    LEFT JOIN subcategories s ON s.id = p.subcategory_id AND s.tenant_id = p.tenant_id
    WHERE qi.quotation_id = ? AND qi.tenant_id = ?
    ORDER BY qi.sort_order
");
$stI->execute([$id, $tenant_id]);
$items = $stI->fetchAll(PDO::FETCH_ASSOC);

// Load company info
$stC = $pdo->prepare("SELECT * FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stC->execute([$tenant_id]);
$company = $stC->fetch(PDO::FETCH_ASSOC) ?: [];

// Load base currency
$stCur = $pdo->prepare("
    SELECT c.code, c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON c.id = tc.currency_id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    LIMIT 1
");
$stCur->execute([$tenant_id]);
$baseCurrency   = $stCur->fetch(PDO::FETCH_ASSOC);
$currencyCode   = $baseCurrency['code']   ?? 'PKR';
$currencySymbol = $baseCurrency['symbol'] ?? 'Rs';

// Build company location string
$locationParts = array_filter([
    $company['city']    ?? '',
    $company['state']   ?? '',
    $company['country'] ?? '',
]);
$locationStr = implode(', ', $locationParts);

function numberToWords(float $amount, string $currencyCode = 'PKR'): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
              'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
              'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

    // [major unit, minor unit]
    $map = [
        'USD' => ['Dollars',  'Cents'],    'EUR' => ['Euros',    'Cents'],
        'GBP' => ['Pounds',   'Pence'],    'PKR' => ['Rupees',   'Paisa'],
        'INR' => ['Rupees',   'Paisa'],    'AED' => ['Dirhams',  'Fils'],
        'SAR' => ['Riyals',   'Halalah'],  'OMR' => ['Rials',    'Baisa'],
        'QAR' => ['Riyals',   'Dirhams'],  'KWD' => ['Dinars',   'Fils'],
        'BHD' => ['Dinars',   'Fils'],     'JPY' => ['Yen',      'Sen'],
        'CNY' => ['Yuan',     'Fen'],      'CAD' => ['Dollars',  'Cents'],
        'AUD' => ['Dollars',  'Cents'],    'CHF' => ['Francs',   'Rappen'],
        'TRY' => ['Lira',     'Kurus'],    'BDT' => ['Taka',     'Paisa'],
        'MYR' => ['Ringgit',  'Sen'],      'SGD' => ['Dollars',  'Cents'],
        'ZAR' => ['Rand',     'Cents'],    'NGN' => ['Naira',    'Kobo'],
        'EGP' => ['Pounds',   'Piastres'], 'NPR' => ['Rupees',   'Paisa'],
    ];
    [$majorUnit, $minorUnit] = $map[strtoupper($currencyCode)] ?? ['Units', 'Subunits'];

    function convert(int $n, array $ones, array $tens): string {
        if ($n === 0)      return '';
        if ($n < 20)       return $ones[$n] . ' ';
        if ($n < 100)      return $tens[(int)($n/10)] . ' ' . ($n%10 ? $ones[$n%10].' ' : '');
        if ($n < 1000)     return $ones[(int)($n/100)] . ' Hundred ' . convert($n%100, $ones, $tens);
        if ($n < 100000)   return convert((int)($n/1000), $ones, $tens) . 'Thousand ' . convert($n%1000, $ones, $tens);
        if ($n < 10000000) return convert((int)($n/100000), $ones, $tens) . 'Lakh ' . convert($n%100000, $ones, $tens);
        return                    convert((int)($n/10000000), $ones, $tens) . 'Crore ' . convert($n%10000000, $ones, $tens);
    }

    $amount = round($amount, 2);
    $major  = (int)$amount;
    $minor  = (int)round(($amount - $major) * 100);
    $words  = trim(convert($major, $ones, $tens));
    $result = strtoupper($currencyCode) . ' ' . ($words ?: 'Zero') . ' ' . $majorUnit;
    if ($minor > 0) {
        $result .= ' and ' . trim(convert($minor, $ones, $tens)) . ' ' . $minorUnit;
    }
    return $result . ' Only';
}

function fmtDate($d) {
    if (!$d) return '—';
    $parts = explode('-', $d);
    return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $d;
}
function fmtNum($n) {
    return number_format((float)$n, 2, '.', ',');
}

// ── Build meta items — only include fields that have data ──────
$smap = ['draft'=>'s-draft','approved'=>'s-approved','converted'=>'s-converted','rejected'=>'s-rejected'];
$scls = $smap[$q['status']] ?? 's-draft';

$metaItems = [];
$metaItems[] = ['Quotation No.', htmlspecialchars($q['quotation_number']),         'text'];
$metaItems[] = ['Date',          fmtDate($q['quotation_date']),                    'text'];
if (!empty($q['valid_till']))    $metaItems[] = ['Valid Till',     fmtDate($q['valid_till']),                             'text'];
$metaItems[]                                  = ['Status', '<span class="status-badge '.$scls.'">'.ucfirst($q['status']).'</span>', 'html'];
if (!empty($q['customer_name'])) $metaItems[] = ['Customer',      htmlspecialchars($q['customer_name']),                 'text'];
if (!empty($q['contact_person']))$metaItems[] = ['Contact Person', htmlspecialchars($q['contact_person']),               'text'];
if (!empty($q['payment_term'])) $metaItems[]  = ['Payment Terms', htmlspecialchars($q['payment_term']),                  'text'];
if (!empty($q['salesman_name'])) $metaItems[] = ['Salesman',      htmlspecialchars($q['salesman_name']),                 'text'];
if (!empty($q['party_type']))   $metaItems[]  = ['Party Type',    htmlspecialchars($q['party_type']),                    'text'];
if (!empty($q['project_name'])) {
    $projLabel = htmlspecialchars($q['proj_code'] . ' - ' . $q['project_name']);
    if (!empty($q['project_city'])) $projLabel .= htmlspecialchars(' - ' . $q['project_city']);
    $metaItems[] = ['Project', $projLabel, 'text'];
}

// Pad to even count so the 2-column grid always has complete rows
if (count($metaItems) % 2 !== 0) $metaItems[] = ['', '', 'empty'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>Quotation <?= htmlspecialchars($q['quotation_number']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11.5px; color: #1a1a2e; background:#fff; }

/* ── PAGE LAYOUT ── */
.page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 14mm 18mm; display: flex; flex-direction: column; }

/* ── COMPANY HEADER ── */
.co-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 2.5px solid #1f7bff; }
.co-name    { font-size: 20px; font-weight: 800; color: #0e1a2b; letter-spacing: .3px; }
.co-slogan  { font-size: 10.5px; color: #1f7bff; font-style: italic; margin-top: 2px; font-weight: 500; }
.co-sub     { font-size: 10px; color: #6b7280; margin-top: 2px; }
.co-contact { text-align: right; font-size: 10.5px; color: #4b5563; line-height: 1.7; }

/* ── DOC TITLE BAND ── */
.doc-band      { background: #1f7bff; color: #fff; text-align: center; padding: 7px 0; border-radius: 5px; margin-bottom: 12px; }
.doc-band h2   { font-size: 15px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
.doc-band span { font-size: 11px; opacity: .85; }

/* ── META GRID ── */
.meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid #d1d9e6; border-radius: 6px; overflow: hidden; margin-bottom: 12px; }
.meta-box { padding: 7px 12px; }
.meta-box:nth-child(odd)  { background: #f8fafc; border-right: 1px solid #d1d9e6; }
.meta-box:nth-child(even) { background: #fff; }
.meta-box + .meta-box     { border-top: 1px solid #ecf0f6; }
.meta-box:nth-child(2)    { border-top: none; }
.meta-lbl { font-size: 9.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
.meta-val { font-size: 11.5px; color: #0e1a2b; font-weight: 600; margin-top: 1px; }

/* ── SECTION LABEL ── */
.sec-lbl { font-size: 9.5px; font-weight: 800; color: #1f7bff; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 5px; padding-bottom: 3px; border-bottom: 1.5px solid #dbeafe; }

/* ── ITEMS TABLE ── */
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10.5px; }
.items-table thead tr { background: #1f7bff; }
.items-table thead th { padding: 7px 7px; text-align: left; font-size: 9.5px; font-weight: 700; color: #fff; letter-spacing: .3px; white-space: nowrap; }
.items-table thead th.r { text-align: right; }
.items-table thead th.c { text-align: center; }
.items-table tbody tr { border-bottom: 1px solid #e8edf4; }
.items-table tbody tr:nth-child(even) { background: #f8fafc; }
.items-table tbody tr:last-child { border-bottom: 2px solid #1f7bff; }
.items-table tbody td { padding: 6px 7px; vertical-align: top; color: #2f3b4c; }
.items-table tbody td.r { text-align: right; }
.items-table tbody td.c { text-align: center; }
.item-code { font-family: "Courier New", monospace; font-size: 9.5px; color: #6b7280; }
.item-name { font-weight: 600; color: #0e1a2b; }
.item-desc { font-size: 9.5px; color: #6b7280; margin-top: 1px; }

/* ── TOTALS ── */
.totals-section { display: flex; justify-content: flex-end; margin-bottom: 14px; }
.totals-table { min-width: 260px; border-collapse: collapse; }
.totals-table tr td { padding: 4px 10px; font-size: 11px; }
.totals-table tr td:first-child { color: #5a6472; font-weight: 600; }
.totals-table tr td:last-child  { text-align: right; font-weight: 700; color: #0e1a2b; font-family: "Courier New", monospace; }
.totals-table tr.grand-row td   { background: #1f7bff; color: #fff !important; font-size: 12.5px; font-weight: 800; padding: 6px 10px; }
.totals-table tr.divider td     { border-top: 1.5px solid #d1d9e6; padding-top: 5px; }

/* ── AMOUNT IN WORDS ── */
.words-box { border: 1px solid #d1d9e6; border-radius: 5px; padding: 7px 12px; margin-bottom: 12px; background: #f8fafc; display: flex; gap: 8px; align-items: baseline; }
.words-lbl { font-size: 9.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
.words-val { font-size: 11px; color: #0e1a2b; font-weight: 600; font-style: italic; }

/* ── TERMS & CONDITIONS ── */
.terms-box  { border: 1px solid #d1d9e6; border-radius: 5px; padding: 8px 12px; margin-bottom: 12px; background: #f8fafc; }
.terms-text { font-size: 10px; color: #4b5563; line-height: 1.7; white-space: pre-wrap; }

/* ── FOOTER NOTE ── */
.footer-note { border-left: 3px solid #1f7bff; padding: 7px 12px; background: #f0f6ff; border-radius: 0 5px 5px 0; margin-bottom: 14px; font-size: 10.5px; color: #2f3b4c; font-style: italic; line-height: 1.6; }

/* ── SIGNATURE ROW ── */
.sig-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: auto; padding-top: 16px; border-top: 1px solid #d1d9e6; }
.sig-box  { text-align: center; }
.sig-line { border-top: 1.5px solid #9aa1ae; margin-bottom: 5px; padding-top: 40px; }
.sig-lbl  { font-size: 9.5px; color: #6b7280; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }

/* ── STATUS BADGE ── */
.status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.s-draft      { background: #fef9c3; color: #a16207; }
.s-approved   { background: #ecfdf5; color: #065f46; }
.s-converted  { background: #eff6ff; color: #1d4ed8; }
.s-rejected   { background: #fef2f2; color: #991b1b; }

/* ── PRINT ── */
@media print {
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .no-print { display: none !important; }
  .page { margin: 0; padding: 10mm 12mm 14mm; }
}
</style>
</head>
<body>

<!-- Print / Close buttons -->
<div class="no-print" style="text-align:right;padding:10px 20px;background:#f0f3f8;border-bottom:1px solid #d1d9e6">
  <button onclick="window.print()" style="background:#1f7bff;color:#fff;border:none;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;margin-right:8px">
    🖨 Print
  </button>
  <button onclick="window.close()" style="background:#eff2f7;border:1px solid #c9cfda;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">
    Close
  </button>
</div>

<div class="page">

  <!-- ── Company Header ── -->
  <div class="co-header">
    <div>
      <div class="co-name"><?= htmlspecialchars($company['company_name'] ?? 'LedgerOne ERP') ?></div>
      <?php if (!empty($company['slogan'])): ?>
      <div class="co-slogan"><?= htmlspecialchars($company['slogan']) ?></div>
      <?php endif; ?>
      <?php if (!empty($company['address'])): ?>
      <div class="co-sub"><?= htmlspecialchars($company['address']) ?></div>
      <?php endif; ?>
      <?php if (!empty($locationStr)): ?>
      <div class="co-sub"><?= htmlspecialchars($locationStr) ?></div>
      <?php endif; ?>
    </div>
    <div class="co-contact">
      <?php if (!empty($company['phone'])): ?>Phone: <?= htmlspecialchars($company['phone']) ?><br><?php endif; ?>
      <?php if (!empty($company['email'])): ?>Email: <?= htmlspecialchars($company['email']) ?><br><?php endif; ?>
      <?php if (!empty($company['website'])): ?><?= htmlspecialchars($company['website']) ?><?php endif; ?>
    </div>
  </div>

  <!-- ── Document Title ── -->
  <div class="doc-band">
    <h2>Quotation / Price Offer</h2>
    <span><?= htmlspecialchars($q['quotation_number']) ?></span>
  </div>

  <!-- ── Meta Grid (only populated fields rendered) ── -->
  <div class="meta-grid">
    <?php foreach ($metaItems as $m): ?>
    <div class="meta-box">
      <?php if ($m[2] !== 'empty'): ?>
      <div class="meta-lbl"><?= $m[0] ?></div>
      <div class="meta-val"><?= $m[1] ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Items Table ── -->
  <div class="sec-lbl">Quotation Items</div>
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:28px" class="c">#</th>
        <th style="width:90px">Code</th>
        <th>Item / Description</th>
        <th style="width:50px" class="r">Qty</th>
        <th style="width:60px">Unit</th>
        <th style="width:72px" class="r">Rate</th>
        <th style="width:44px" class="r">Tax%</th>
        <th style="width:72px" class="r">Tax Amt</th>
        <th style="width:82px" class="r">Amount</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
      <tr><td colspan="9" style="text-align:center;padding:16px;color:#9aa1ae">No items</td></tr>
    <?php else: ?>
      <?php foreach ($items as $i => $it): ?>
      <tr>
        <td class="c" style="color:#9aa1ae"><?= $i + 1 ?></td>
        <td class="item-code"><?= htmlspecialchars($it['item_code'] ?: ($it['product_code'] ?? '—')) ?></td>
        <td>
          <?php $displayName = trim($it['item_name'] ?? '') ?: trim($it['product_name'] ?? ''); ?>
          <div class="item-name"><?= htmlspecialchars($displayName ?: '—') ?></div>
          <?php if (!empty(trim($it['description'] ?? ''))): ?>
          <div class="item-desc"><?= htmlspecialchars($it['description']) ?></div>
          <?php endif; ?>
        </td>
        <td class="r"><?= fmtNum($it['quantity']) ?></td>
        <td><?= htmlspecialchars($it['unit_name'] ?? '—') ?></td>
        <td class="r"><?= fmtNum($it['rate']) ?></td>
        <td class="r"><?= fmtNum($it['tax_pct']) ?>%</td>
        <td class="r"><?= fmtNum($it['tax_amt']) ?></td>
        <td class="r" style="font-weight:700"><?= fmtNum($it['line_total']) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- ── Totals ── -->
  <div class="totals-section">
    <table class="totals-table">
      <tr>
        <td>Subtotal</td>
        <td><?= fmtNum($q['subtotal']) ?></td>
      </tr>
      <?php if ($q['discount_pct'] > 0 || $q['discount_amt'] > 0): ?>
      <tr>
        <td>Discount (<?= fmtNum($q['discount_pct']) ?>%)</td>
        <td>-<?= fmtNum($q['discount_amt']) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($q['invoice_tax_pct'] > 0 || $q['invoice_tax_amt'] > 0): ?>
      <tr>
        <td>Tax (<?= fmtNum($q['invoice_tax_pct']) ?>%)</td>
        <td><?= fmtNum($q['invoice_tax_amt']) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="divider grand-row">
        <td>Grand Total</td>
        <td><?= fmtNum($q['grand_total']) ?></td>
      </tr>
    </table>
  </div>

  <!-- ── Amount in Words ── -->
  <div class="words-box">
    <span class="words-lbl">Amount in Words:</span>
    <span class="words-val"><?= htmlspecialchars(numberToWords((float)$q['grand_total'], $currencyCode)) ?></span>
  </div>

  <!-- ── Terms & Conditions ── -->
  <?php if (!empty($q['terms_conditions'])): ?>
  <div class="sec-lbl">Terms &amp; Conditions</div>
  <div class="terms-box">
    <div class="terms-text"><?= htmlspecialchars($q['terms_conditions']) ?></div>
  </div>
  <?php endif; ?>

  <!-- ── Remarks ── -->
  <?php if (!empty($q['remarks'])): ?>
  <div style="margin-bottom:10px">
    <div class="sec-lbl">Remarks</div>
    <div style="font-size:10.5px;color:#4b5563;line-height:1.6"><?= htmlspecialchars($q['remarks']) ?></div>
  </div>
  <?php endif; ?>

  <!-- ── Footer Note ── -->
  <?php if (!empty($q['footer_note'])): ?>
  <div class="footer-note"><?= htmlspecialchars($q['footer_note']) ?></div>
  <?php endif; ?>

  <!-- ── Signature Row ── -->
  <div class="sig-row">
    <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Authorized By</div></div>
    <div class="sig-box"><div class="sig-line"></div><div class="sig-lbl">Customer Signature</div></div>
  </div>

</div><!-- /page -->
</body>
</html>
