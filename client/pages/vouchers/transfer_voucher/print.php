<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { header('Location: ../../auth/login.html'); exit(); }

$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id) { header('Location: transfer-list.php'); exit(); }

require_once '../../../../includes/connection.php';

function numberToWords($n) {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten',
             'Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($n == 0) return 'Zero';
    if ($n < 20) return $ones[$n];
    if ($n < 100) return $tens[intval($n/10)] . ($n%10 ? ' '.$ones[$n%10] : '');
    if ($n < 1000) return $ones[intval($n/100)] . ' Hundred' . ($n%100 ? ' '.numberToWords($n%100) : '');
    if ($n < 1000000) return numberToWords(intval($n/1000)) . ' Thousand' . ($n%1000 ? ' '.numberToWords($n%1000) : '');
    return numberToWords(intval($n/1000000)) . ' Million' . ($n%1000000 ? ' '.numberToWords($n%1000000) : '');
}
function amountInWords($amount, $main, $sub) {
    $amount = round($amount, 2);
    $int = intval($amount);
    $dec = round(($amount - $int) * 100);
    $w = numberToWords($int) . ' ' . ($main ?: 'Only');
    if ($dec > 0) $w .= ' and ' . numberToWords($dec) . ' ' . ($sub ?: 'Cents');
    return $w . ' Only';
}

try {
    $stmt = $pdo->prepare("
        SELECT
            tv.id, tv.voucher_number, tv.voucher_date, tv.amount, tv.description,
            tv.cheque_no, tv.cheque_date, tv.slip_no, tv.attachment,
            tv.from_type, tv.to_type,
            fc.customer_name  AS from_customer_name,  fc.customer_code  AS from_customer_code,
            fs.supplier_name  AS from_supplier_name,  fs.supplier_code  AS from_supplier_code,
            tc.customer_name  AS to_customer_name,    tc.customer_code  AS to_customer_code,
            ts.supplier_name  AS to_supplier_name,    ts.supplier_code  AS to_supplier_code,
            a.name            AS payment_method,
            cur.name          AS currency_name,
            cur.symbol        AS currency_symbol,
            cur.main_unit_name AS currency_main_unit,
            cur.sub_unit_name  AS currency_sub_unit,
            ba.bank_name, ba.account_number,
            co.company_name, co.address, co.phone, co.email, co.logo_url
        FROM transfer_voucher tv
        LEFT JOIN customers  fc ON tv.from_customer_id = fc.id
        LEFT JOIN suppliers  fs ON tv.from_supplier_id = fs.id
        LEFT JOIN customers  tc ON tv.to_customer_id   = tc.id
        LEFT JOIN suppliers  ts ON tv.to_supplier_id   = ts.id
        LEFT JOIN accounts   a  ON tv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies cur ON tv.currency_id = cur.id
        LEFT JOIN bank_accounts ba ON tv.bank_account_id = ba.id
        LEFT JOIN companies  co ON tv.company_id = co.id
        WHERE tv.id = ? AND tv.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$v) { header('Location: transfer-list.php'); exit(); }

    // from/to display
    $from_name = $v['from_type'] === 'customer'
        ? ($v['from_customer_code'] . ' - ' . $v['from_customer_name'])
        : ($v['from_supplier_code'] . ' - ' . $v['from_supplier_name']);
    $to_name = $v['to_type'] === 'customer'
        ? ($v['to_customer_code'] . ' - ' . $v['to_customer_name'])
        : ($v['to_supplier_code'] . ' - ' . $v['to_supplier_name']);

    if (empty($v['company_name'])) {
        $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$tenant_id]);
        $fb = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fb) { $v = array_merge($v, $fb); }
    }
} catch (Exception $e) { header('Location: transfer-list.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transfer Voucher - <?php echo htmlspecialchars($v['voucher_number']); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size:12px; line-height:1.5; color:#333; background:#fff; }
        .print-container { max-width:800px; margin:20px auto; padding:24px; border:1px solid #ddd; }
        .header { text-align:center; margin-bottom:24px; border-bottom:2px solid #333; padding-bottom:14px; }
        .company-name { font-size:22px; font-weight:bold; margin-bottom:4px; }
        .document-title { font-size:17px; font-weight:bold; margin-top:10px; letter-spacing:1px; }
        .voucher-info { display:flex; justify-content:space-between; margin-bottom:20px; gap:20px; }
        .info-section { flex:1; }
        .info-row { margin-bottom:7px; }
        .label { font-weight:bold; display:inline-block; width:130px; }
        .transfer-box { display:flex; align-items:center; gap:16px; margin:20px 0; }
        .party-box { flex:1; border:1.5px solid #333; border-radius:6px; padding:12px 16px; }
        .party-box .party-type { font-size:10px; font-weight:bold; text-transform:uppercase; color:#666; margin-bottom:4px; }
        .party-box .party-name { font-size:14px; font-weight:bold; }
        .arrow { font-size:28px; color:#333; flex-shrink:0; }
        .details-table { width:100%; border-collapse:collapse; margin:16px 0; }
        .details-table th, .details-table td { border:1px solid #333; padding:8px 10px; text-align:left; }
        .details-table th { background:#f5f5f5; font-weight:bold; width:160px; }
        .amount-section { margin-top:20px; text-align:right; }
        .total-amount { font-size:15px; font-weight:bold; border:2px solid #333; padding:10px 16px; display:inline-block; margin-top:8px; }
        .amount-words { margin-top:8px; font-style:italic; font-size:12px; }
        .signatures { margin-top:44px; display:flex; justify-content:space-between; }
        .signature-box { text-align:center; width:180px; }
        .signature-line { border-top:1px solid #333; margin-top:48px; padding-top:5px; font-size:11px; }
        .footer-note { text-align:center; margin-top:28px; font-size:10px; color:#888; border-top:1px solid #ddd; padding-top:8px; }
        .print-btn { background:#1f7bff; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; margin-bottom:16px; font-size:13px; }
        @media print { .no-print { display:none; } .print-container { margin:0; border:none; } }
    </style>
</head>
<body>
<div class="print-container">
    <button class="print-btn no-print" onclick="window.print()"><i>🖨</i> Print Voucher</button>

    <div class="header">
        <?php if (!empty($v['logo_url'])): ?>
        <div style="margin-bottom:8px;">
            <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($v['logo_url']); ?>" alt="Logo" style="max-height:70px; max-width:180px;">
        </div>
        <?php endif; ?>
        <div class="company-name"><?php echo htmlspecialchars($v['company_name'] ?? 'LedgerOne ERP'); ?></div>
        <?php if (!empty($v['address'])): ?><div style="font-size:11px; margin:4px 0;"><?php echo htmlspecialchars($v['address']); ?></div><?php endif; ?>
        <?php if (!empty($v['phone']) || !empty($v['email'])): ?>
        <div style="font-size:11px; margin:4px 0;">
            <?php if (!empty($v['phone'])): ?>Phone: <?php echo htmlspecialchars($v['phone']); ?><?php endif; ?>
            <?php if (!empty($v['phone']) && !empty($v['email'])): ?> | <?php endif; ?>
            <?php if (!empty($v['email'])): ?>Email: <?php echo htmlspecialchars($v['email']); ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="document-title">TRANSFER VOUCHER</div>
    </div>

    <div class="voucher-info">
        <div class="info-section">
            <div class="info-row"><span class="label">Voucher No:</span><?php echo htmlspecialchars($v['voucher_number']); ?></div>
            <div class="info-row"><span class="label">Date:</span><?php echo date('d-M-Y', strtotime($v['voucher_date'])); ?></div>
        </div>
        <div class="info-section">
            <div class="info-row"><span class="label">Payment Method:</span><?php echo htmlspecialchars($v['payment_method'] ?? 'N/A'); ?></div>
            <div class="info-row"><span class="label">Currency:</span><?php echo htmlspecialchars($v['currency_name'] ?? 'N/A'); ?></div>
        </div>
    </div>

    <!-- Transfer Arrow Visual -->
    <div class="transfer-box">
        <div class="party-box">
            <div class="party-type">From (Payer) — <?php echo ucfirst($v['from_type']); ?></div>
            <div class="party-name"><?php echo htmlspecialchars($from_name); ?></div>
        </div>
        <div class="arrow">&#8594;</div>
        <div class="party-box">
            <div class="party-type">To (Receiver) — <?php echo ucfirst($v['to_type']); ?></div>
            <div class="party-name"><?php echo htmlspecialchars($to_name); ?></div>
        </div>
    </div>

    <table class="details-table">
        <?php if (!empty($v['bank_name'])): ?>
        <tr><th>Bank Account</th><td><?php echo htmlspecialchars($v['bank_name'] . ' - ' . $v['account_number']); ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($v['cheque_no'])): ?>
        <tr><th>Cheque No</th><td><?php echo htmlspecialchars($v['cheque_no']); ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($v['cheque_date']) && $v['cheque_date'] !== '0000-00-00'): ?>
        <tr><th>Cheque Date</th><td><?php echo date('d-M-Y', strtotime($v['cheque_date'])); ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($v['slip_no'])): ?>
        <tr><th>Slip No</th><td><?php echo htmlspecialchars($v['slip_no']); ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($v['description'])): ?>
        <tr><th>Description</th><td><?php echo htmlspecialchars($v['description']); ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($v['attachment'])): ?>
    <div style="margin:16px 0;">
        <strong>Attachment:</strong><br>
        <?php
            $attachPath = '../../../assets/uploads/transfer_voucher/' . $v['attachment'];
            $ext = strtolower(pathinfo($v['attachment'], PATHINFO_EXTENSION));
        ?>
        <?php if ($ext === 'pdf'): ?>
            <div style="margin-top:8px;">
                <a href="<?php echo $attachPath; ?>" target="_blank"
                   style="display:inline-flex; align-items:center; gap:6px; background:#1f7bff; color:#fff; padding:8px 16px; border-radius:4px; text-decoration:none; font-size:12px; font-weight:bold;">
                    &#128196; View PDF Attachment
                </a>
            </div>
            <div style="margin-top:10px; border:1px solid #ddd; border-radius:4px; overflow:hidden; max-width:600px; height:300px;">
                <iframe src="<?php echo $attachPath; ?>" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        <?php else: ?>
            <div style="margin-top:8px;">
                <img src="<?php echo $attachPath; ?>" alt="Slip Attachment"
                     style="max-width:400px; max-height:300px; border:1px solid #ddd; border-radius:4px; display:block; cursor:pointer;"
                     onclick="this.style.maxWidth=this.style.maxWidth==='100%'?'400px':'100%'" title="Click to toggle size">
                <div style="font-size:10px; color:#888; margin-top:4px;">Click image to enlarge</div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="amount-section">
        <div class="total-amount">
            Total Amount: <?php echo htmlspecialchars($v['currency_symbol'] ?? '') . number_format($v['amount'], 2); ?>
        </div>
        <div class="amount-words">
            Amount in Words: <?php echo amountInWords($v['amount'], $v['currency_main_unit'] ?? '', $v['currency_sub_unit'] ?? ''); ?>
        </div>
    </div>

    <div class="signatures">
        <div class="signature-box"><div class="signature-line">Prepared By</div></div>
        <div class="signature-box"><div class="signature-line">Approved By</div></div>
        <div class="signature-box"><div class="signature-line">Received By</div></div>
    </div>

    <div class="footer-note"><i>This is a System Generated Voucher</i></div>
</div>
</body>
</html>
