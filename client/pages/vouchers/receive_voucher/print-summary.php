<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

// Same filter set as server/api/vouchers/receive_voucher/receive-list.php, mirrored here
// (this page queries directly, no pagination, since a print report needs every matching row).
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$recovery_officer_id = $_GET['recovery_officer_id'] ?? '';
$status = $_GET['status'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';
$area_id = $_GET['area_id'] ?? '';
$city_id = $_GET['city_id'] ?? '';
$voucher_number = $_GET['voucher_number'] ?? '';
$search = $_GET['search'] ?? '';

$where = ['rv.tenant_id = ?'];
$params = [$tenant_id];

if ($date_from) { $where[] = 'rv.voucher_date >= ?'; $params[] = $date_from; }
if ($date_to) { $where[] = 'rv.voucher_date <= ?'; $params[] = $date_to; }
if ($customer_id) { $where[] = 'rv.customer_id = ?'; $params[] = $customer_id; }
if ($recovery_officer_id) { $where[] = 'rv.recovery_officer_id = ?'; $params[] = $recovery_officer_id; }
if ($company_id) { $where[] = 'rv.company_id = ?'; $params[] = $company_id; }
if ($project_id) { $where[] = 'rv.project_id = ?'; $params[] = $project_id; }
if ($area_id) { $where[] = 'cu.area_id = ?'; $params[] = $area_id; }
if ($city_id) { $where[] = 'cu.city_id = ?'; $params[] = $city_id; }
if ($voucher_number) { $where[] = 'rv.voucher_number LIKE ?'; $params[] = '%' . $voucher_number . '%'; }
if ($search) {
    $where[] = '(rv.voucher_number LIKE ? OR cu.customer_name LIKE ? OR rv.bill_no LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT
        cu.id as customer_id,
        cu.customer_code,
        cu.customer_name,
        rv.amount,
        rv.payment_method_id,
        a.name as payment_method
    FROM receive_voucher rv
    LEFT JOIN customers cu ON rv.customer_id = cu.id
    LEFT JOIN accounts a ON rv.payment_method_id = a.id
    WHERE $whereClause
    ORDER BY cu.customer_name ASC, rv.voucher_date ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouped by customer + payment method (not just customer) - a customer who paid via both
// Cash and Cheque across different vouchers gets one row per method, so each row has a
// single, unambiguous payment method rather than trying to cram several into one cell.
$grouped = [];
$grandTotal = 0;
$paymentMethodTotals = [];
foreach ($rows as $row) {
    $methodLabel = $row['payment_method'] ?: 'Unknown';
    $key = ($row['customer_id'] ?: 'unassigned_' . $row['customer_name']) . '|' . ($row['payment_method_id'] ?: 'none');
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'code' => $row['customer_code'],
            'name' => $row['customer_name'] ?: 'Unknown Customer',
            'payment_method' => $methodLabel,
            'total' => 0,
        ];
    }
    $grouped[$key]['total'] += (float) $row['amount'];
    $grandTotal += (float) $row['amount'];

    if (!isset($paymentMethodTotals[$methodLabel])) {
        $paymentMethodTotals[$methodLabel] = 0;
    }
    $paymentMethodTotals[$methodLabel] += (float) $row['amount'];
}
arsort($paymentMethodTotals);

// Currency symbol (base currency), matching the pattern used elsewhere in this module
$stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    LIMIT 1
");
$stmt->execute([$tenant_id]);
$currencySymbol = $stmt->fetchColumn() ?: 'Rs';

// Resolve filter labels for the header - only shown when that filter was actually applied,
// so an unfiltered report doesn't get cluttered with "All Officers / All Areas" noise.
$recoveryOfficerName = null;
if ($recovery_officer_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$recovery_officer_id, $tenant_id]);
    $recoveryOfficerName = $stmt->fetchColumn() ?: null;
}

$areaName = null;
if ($area_id) {
    $stmt = $pdo->prepare("SELECT area_name FROM areas WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$area_id, $tenant_id]);
    $areaName = $stmt->fetchColumn() ?: null;
}

$cityName = null;
if ($city_id) {
    $stmt = $pdo->prepare("SELECT city_name FROM cities WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$city_id, $tenant_id]);
    $cityName = $stmt->fetchColumn() ?: null;
}

function formatPrintDate($d) {
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}

$dateLabel = null;
if ($date_from && $date_to && $date_from === $date_to) {
    $dateLabel = formatPrintDate($date_from);
} elseif ($date_from && $date_to) {
    $dateLabel = formatPrintDate($date_from) . ' to ' . formatPrintDate($date_to);
} elseif ($date_from) {
    $dateLabel = 'From ' . formatPrintDate($date_from);
} elseif ($date_to) {
    $dateLabel = 'Up to ' . formatPrintDate($date_to);
}

$generatedOn = date('d/m/Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receive Voucher Summary</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #111;
        margin: 0;
        padding: 24px;
        background: #f2f2f2;
    }
    .sheet {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 28px 32px;
        border: 1px solid #ccc;
    }
    .toolbar {
        max-width: 800px;
        margin: 0 auto 12px auto;
        text-align: right;
    }
    .toolbar button {
        background: #1f7bff;
        color: #fff;
        border: none;
        padding: 8px 18px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
    }
    .report-title {
        text-align: center;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .report-subtitle {
        text-align: center;
        font-size: 12px;
        color: #666;
        margin-bottom: 18px;
    }
    .meta-box {
        border: 1px solid #333;
        margin-bottom: 16px;
    }
    .meta-row {
        display: flex;
        border-bottom: 1px solid #333;
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-label {
        width: 160px;
        flex-shrink: 0;
        font-weight: 700;
        font-size: 12px;
        padding: 6px 10px;
        border-right: 1px solid #333;
        text-transform: uppercase;
        background: #f7f7f7;
    }
    .meta-value {
        flex: 1;
        font-size: 13px;
        padding: 6px 10px;
        font-weight: 600;
    }
    .payment-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }
    .payment-summary-box {
        flex: 1;
        min-width: 120px;
        border: 1px solid #333;
        border-radius: 4px;
        padding: 8px 12px;
        text-align: center;
    }
    .payment-summary-box.grand-total-box {
        background: #f0f0f0;
        border-width: 2px;
    }
    .payment-summary-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #555;
        margin-bottom: 4px;
    }
    .payment-summary-value {
        font-size: 15px;
        font-weight: 700;
    }
    table.summary-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    table.summary-table th, table.summary-table td {
        border: 1px solid #333;
        padding: 7px 10px;
    }
    table.summary-table th {
        background: #f0f0f0;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.4px;
        text-align: left;
    }
    table.summary-table td.amount, table.summary-table th.amount {
        text-align: right;
        white-space: nowrap;
    }
    tr.total-row td {
        font-weight: 700;
        border-top: 2px solid #333;
    }
    .empty-note {
        text-align: center;
        color: #888;
        padding: 30px 0;
    }
    .footer-note {
        margin-top: 18px;
        font-size: 11px;
        color: #888;
        text-align: right;
    }
    @media print {
        body { background: #fff; padding: 0; }
        .sheet { border: none; max-width: none; padding: 0; }
        .toolbar { display: none; }
    }
</style>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="sheet">
        <div class="report-title">Receive Voucher Summary</div>
        <div class="report-subtitle">Customer-wise collection totals</div>

        <div class="meta-box">
            <?php if ($dateLabel): ?>
            <div class="meta-row">
                <div class="meta-label">Date</div>
                <div class="meta-value"><?= htmlspecialchars($dateLabel) ?></div>
            </div>
            <?php endif; ?>

            <div class="meta-row">
                <div class="meta-label">Recovery Officer</div>
                <div class="meta-value"><?= htmlspecialchars($recoveryOfficerName ?: 'All Officers') ?></div>
            </div>

            <?php if ($areaName): ?>
            <div class="meta-row">
                <div class="meta-label">Area</div>
                <div class="meta-value"><?= htmlspecialchars($areaName) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($cityName): ?>
            <div class="meta-row">
                <div class="meta-label">City</div>
                <div class="meta-value"><?= htmlspecialchars($cityName) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($voucher_number): ?>
            <div class="meta-row">
                <div class="meta-label">Voucher #</div>
                <div class="meta-value"><?= htmlspecialchars($voucher_number) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="empty-note">No receive vouchers match the selected filters.</div>
        <?php else: ?>
        <div class="payment-summary">
            <?php foreach ($paymentMethodTotals as $methodName => $methodTotal): ?>
            <div class="payment-summary-box">
                <div class="payment-summary-label"><?= htmlspecialchars($methodName) ?></div>
                <div class="payment-summary-value"><?= $currencySymbol . ' ' . number_format($methodTotal, 2) ?></div>
            </div>
            <?php endforeach; ?>
            <div class="payment-summary-box grand-total-box">
                <div class="payment-summary-label">Grand Total</div>
                <div class="payment-summary-value"><?= $currencySymbol . ' ' . number_format($grandTotal, 2) ?></div>
            </div>
        </div>

        <table class="summary-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Payment Method</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped as $g): ?>
                <tr>
                    <td><?= htmlspecialchars(($g['code'] ? $g['code'] . ' - ' : '') . $g['name']) ?></td>
                    <td><?= htmlspecialchars($g['payment_method']) ?></td>
                    <td class="amount"><?= $currencySymbol . ' ' . number_format($g['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="2">Total</td>
                    <td class="amount"><?= $currencySymbol . ' ' . number_format($grandTotal, 2) ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="footer-note">Generated <?= htmlspecialchars($generatedOn) ?></div>
    </div>
</body>
</html>
