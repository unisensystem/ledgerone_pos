<?php
require_once '../../../../includes/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo 'Invalid BOM ID'; exit(); }

// Fetch BOM
$stmt = $pdo->prepare("
    SELECT bom.*, p.code AS fg_code, p.name AS fg_name
    FROM bill_of_materials bom
    JOIN products p ON bom.finished_good_id = p.id
    WHERE bom.id = ? AND bom.tenant_id = ?
");
$stmt->execute([$id, $tenant_id]);
$bom = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bom) { echo 'BOM not found'; exit(); }

// Fetch materials
$stmt = $pdo->prepare("
    SELECT bm.quantity, p.code AS material_code, p.name AS material_name, u.uom_name AS unit_name
    FROM bom_materials bm
    JOIN products p ON bm.raw_material_id = p.id
    JOIN uom u ON bm.unit_id = u.id
    WHERE bm.bom_id = ?
    ORDER BY p.code
");
$stmt->execute([$id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch company info
$stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$tenant_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BOM - <?= htmlspecialchars($bom['bom_code']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }

        .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 16mm 16mm 12mm; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid #1F7BFF; }
        .company-name { font-size: 20px; font-weight: 700; color: #0E1A2B; }
        .company-meta { font-size: 11px; color: #555; margin-top: 4px; line-height: 1.6; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 22px; font-weight: 700; color: #1F7BFF; letter-spacing: 1px; }
        .doc-title .bom-code { font-size: 13px; color: #555; margin-top: 4px; font-family: monospace; }

        /* Info grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 32px; margin-bottom: 24px; background: #F7F9FC; border-radius: 8px; padding: 14px 18px; border: 1px solid #E1E6EE; }
        .info-row { display: flex; gap: 8px; }
        .info-label { font-weight: 600; color: #555; min-width: 110px; font-size: 12px; }
        .info-value { color: #0E1A2B; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge.active { background: #E3F5EB; color: #0B5A2E; }
        .badge.inactive { background: #FEF1F1; color: #B12B2B; }

        /* Section title */
        .section-title { font-size: 13px; font-weight: 700; color: #0E1A2B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #E1E6EE; }

        /* Materials table */
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        thead tr { background: #1F7BFF; color: #fff; }
        th { padding: 9px 12px; text-align: left; font-weight: 600; font-size: 12px; }
        td { padding: 8px 12px; border-bottom: 1px solid #E1E6EE; color: #2F3B4C; }
        tbody tr:nth-child(even) { background: #F7F9FC; }
        tbody tr:last-child td { border-bottom: none; }
        .qty-cell { text-align: right; font-weight: 600; }
        .unit-cell { color: #555; }

        /* Remarks */
        .remarks-box { margin-top: 20px; background: #FFFBF0; border: 1px solid #F0D080; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #5a4a00; }
        .remarks-box strong { display: block; margin-bottom: 4px; }

        /* Footer */
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #E1E6EE; display: flex; justify-content: space-between; font-size: 11px; color: #888; }

        /* Print controls */
        .print-controls { position: fixed; top: 16px; right: 16px; display: flex; gap: 8px; z-index: 100; }
        .btn-print, .btn-pdf { padding: 8px 18px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-print { background: #1F7BFF; color: #fff; }
        .btn-pdf { background: #E53935; color: #fff; }
        .btn-back { padding: 8px 18px; border: 1px solid #D6DBE4; border-radius: 6px; font-size: 13px; background: #fff; cursor: pointer; color: #2F3B4C; }

        @media print {
            .print-controls { display: none; }
            .page { padding: 10mm 12mm; }
            body { background: #fff; }
        }
    </style>
</head>
<body>

<div class="print-controls">
    <button class="btn-back" onclick="window.location.href='list.php'">← Back</button>
    <button class="btn-pdf" onclick="printAsPDF()">⬇ Save PDF</button>
    <button class="btn-print" onclick="window.print()">🖨 Print</button>
</div>

<div class="page">

    <!-- Header -->
    <div class="header">
        <div>
            <div class="company-name"><?= htmlspecialchars($company['company_name'] ?? 'Company Name') ?></div>
            <div class="company-meta">
                <?= htmlspecialchars($company['address'] ?? '') ?><br>
                <?php if (!empty($company['phone'])): ?>📞 <?= htmlspecialchars($company['phone']) ?> &nbsp;<?php endif; ?>
                <?php if (!empty($company['email'])): ?>✉ <?= htmlspecialchars($company['email']) ?><?php endif; ?>
            </div>
        </div>
        <div class="doc-title">
            <h1>BILL OF MATERIALS</h1>
            <div class="bom-code"><?= htmlspecialchars($bom['bom_code']) ?></div>
        </div>
    </div>

    <!-- BOM Info -->
    <div class="info-grid">
        <div class="info-row">
            <span class="info-label">BOM Code</span>
            <span class="info-value"><?= htmlspecialchars($bom['bom_code']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Version</span>
            <span class="info-value">v<?= htmlspecialchars($bom['version']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Finished Good</span>
            <span class="info-value"><?= htmlspecialchars($bom['fg_code'] . ' - ' . $bom['fg_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Status</span>
            <span class="info-value">
                <span class="badge <?= $bom['is_active'] ? 'active' : 'inactive' ?>">
                    <?= $bom['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Base Qty</span>
            <span class="info-value"><?= htmlspecialchars($bom['bom_base_qty']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Batch Locked</span>
            <span class="info-value"><?= $bom['batch_locked'] ? 'Yes' : 'No' ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Created</span>
            <span class="info-value"><?= date('d M Y', strtotime($bom['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Last Updated</span>
            <span class="info-value"><?= date('d M Y', strtotime($bom['updated_at'])) ?></span>
        </div>
    </div>

    <!-- Raw Materials -->
    <div class="section-title">Raw Materials (<?= count($materials) ?>)</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Material Code</th>
                <th>Material Name</th>
                <th style="text-align:right;">Quantity</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($materials as $i => $mat): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($mat['material_code']) ?></td>
                <td><?= htmlspecialchars($mat['material_name']) ?></td>
                <td class="qty-cell"><?= number_format($mat['quantity'], 2) ?></td>
                <td class="unit-cell"><?= htmlspecialchars($mat['unit_name']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($materials)): ?>
            <tr><td colspan="5" style="text-align:center; color:#888; padding:16px;">No materials found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($bom['remarks'])): ?>
    <div class="remarks-box">
        <strong>Remarks</strong>
        <?= nl2br(htmlspecialchars($bom['remarks'])) ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <span>Generated: <?= date('d M Y, h:i A') ?></span>
        <span><?= htmlspecialchars($company['company_name'] ?? '') ?> · BOM <?= htmlspecialchars($bom['bom_code']) ?></span>
    </div>

</div>

<script>
function printAsPDF() {
    document.title = 'BOM-<?= htmlspecialchars($bom['bom_code']) ?>';
    window.print();
}
</script>
</body>
</html>
