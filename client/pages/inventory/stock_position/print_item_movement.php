<?php
require_once '../../../../includes/connection.php';

session_start();
$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

// Currency
$currency_stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$currency_stmt->execute([$tenant_id]);
$currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
$currency_symbol = $currency['symbol'] ?? '$';

// Filters
$product_id = $_GET['product_id'] ?? null;
$branch_id  = $_GET['branch_id'] ?? null;
$vendor_id  = $_GET['vendor_id'] ?? null;
$from_date  = $_GET['from_date'] ?? null;
$to_date    = $_GET['to_date'] ?? null;

// ========== Opening (before from_date) ==========
$bbf_by_product = [];
if ($from_date) {
    $bbf_sql = "SELECT 
                    sl.product_id, 
                    COALESCE(SUM(sl.qty_in - sl.qty_out), 0) as bbf
                FROM stock_ledger sl
                JOIN products p ON sl.product_id = p.id AND sl.tenant_id = p.tenant_id
                WHERE sl.tenant_id = ? 
                  AND p.product_type = 'physical' 
                  AND sl.transaction_date < ?";
    $bbf_params = [$tenant_id, $from_date];

    if ($product_id) { $bbf_sql .= " AND sl.product_id = ?"; $bbf_params[] = $product_id; }
    if ($branch_id)  { $bbf_sql .= " AND sl.branch_id = ?";  $bbf_params[] = $branch_id; }
    if ($vendor_id)  { $bbf_sql .= " AND p.vendor_id = ?";   $bbf_params[] = $vendor_id; }

    $bbf_sql .= " GROUP BY sl.product_id";
    $bbf_stmt = $pdo->prepare($bbf_sql);
    $bbf_stmt->execute($bbf_params);
    foreach ($bbf_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bbf_by_product[$row['product_id']] = (float) $row['bbf'];
    }
}

// ========== Period movements ==========
$sql = "SELECT
            sl.product_id,
            p.name as product_name,
            COALESCE(base_u.uom_name, u.uom_name, 'L') as unit_symbol,
            COALESCE(SUM(CASE WHEN sl.reference_table = 'purchase_invoice' THEN sl.qty_in ELSE 0 END), 0) as purchase,
            COALESCE(SUM(CASE WHEN sl.reference_table = 'sale_invoice' THEN sl.qty_out ELSE 0 END), 0) as sale,
            COALESCE(SUM(CASE WHEN sl.reference_table IN ('sale_return','credit_note') THEN sl.qty_in ELSE 0 END), 0) as credit_note,
            COALESCE(SUM(CASE WHEN sl.reference_table IN ('purchase_return','debit_note') THEN sl.qty_out ELSE 0 END), 0) as debit_note
        FROM stock_ledger sl
        JOIN products p ON sl.product_id = p.id AND sl.tenant_id = p.tenant_id
        LEFT JOIN uom u ON sl.unit_id = u.id
        LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
        WHERE sl.tenant_id = ? AND p.product_type = 'physical'";

$params = [$tenant_id];
if ($product_id) { $sql .= " AND sl.product_id = ?"; $params[] = $product_id; }
if ($branch_id)  { $sql .= " AND sl.branch_id = ?";  $params[] = $branch_id; }
if ($vendor_id)  { $sql .= " AND p.vendor_id = ?";   $params[] = $vendor_id; }
if ($from_date)  { $sql .= " AND sl.transaction_date >= ?"; $params[] = $from_date; }
if ($to_date)    { $sql .= " AND sl.transaction_date <= ?"; $params[] = $to_date; }

$sql .= " GROUP BY sl.product_id, p.name, unit_symbol ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$movement = [];
$seen = [];

foreach ($rows as $row) {
    $pid = $row['product_id'];
    $seen[$pid] = true;

    $opening     = $bbf_by_product[$pid] ?? 0;
    $purchase    = (float) $row['purchase'];
    $sale        = (float) $row['sale'];
    $credit_note = (float) $row['credit_note'];
    $debit_note  = (float) $row['debit_note'];
    $closing     = $opening + $purchase + $credit_note - $sale - $debit_note;

    $movement[] = [
        'product_name' => $row['product_name'],
        'opening'      => $opening,
        'purchase'     => $purchase,
        'sale'         => $sale,
        'credit_note'  => $credit_note,
        'debit_note'   => $debit_note,
        'closing'      => $closing
    ];
}

// Products with only opening
foreach ($bbf_by_product as $pid => $opening) {
    if (isset($seen[$pid])) continue;
    $name_stmt = $pdo->prepare("SELECT name FROM products WHERE id = ? AND tenant_id = ?");
    $name_stmt->execute([$pid, $tenant_id]);
    $pname = $name_stmt->fetchColumn() ?: 'Unknown';

    $movement[] = [
        'product_name' => $pname,
        'opening'      => $opening,
        'purchase'     => 0,
        'sale'         => 0,
        'credit_note'  => 0,
        'debit_note'   => 0,
        'closing'      => $opening
    ];
}

usort($movement, fn($a, $b) => strcmp($a['product_name'], $b['product_name']));

// Period text
$periodText = 'All Dates';
if ($from_date && $to_date) {
    $periodText = date('d-M-Y', strtotime($from_date)) . ' to ' . date('d-M-Y', strtotime($to_date));
} elseif ($to_date) {
    $periodText = 'As on ' . date('d-M-Y', strtotime($to_date));
} elseif ($from_date) {
    $periodText = 'From ' . date('d-M-Y', strtotime($from_date));
}

// Totals
$totalOpening    = array_sum(array_column($movement, 'opening'));
$totalPurchase   = array_sum(array_column($movement, 'purchase'));
$totalSale       = array_sum(array_column($movement, 'sale'));
$totalCreditNote = array_sum(array_column($movement, 'credit_note'));
$totalDebitNote  = array_sum(array_column($movement, 'debit_note'));
$totalClosing    = array_sum(array_column($movement, 'closing'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Item Stock Movement - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #000; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 12px; }
        .header h1 { font-size: 20px; margin-bottom: 4px; }
        .header p { font-size: 13px; color: #555; }
        .info { margin-bottom: 16px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .info span { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 7px 8px; text-align: right; }
        th { background: #f0f0f0; font-weight: bold; text-align: center; }
        td:nth-child(1), td:nth-child(2),
        th:nth-child(1), th:nth-child(2) { text-align: left; }
        .total-row { font-weight: bold; background: #e8e8e8; }
        .no-data { text-align: center; padding: 30px; color: #666; }
        .print-btn { margin-bottom: 15px; }
        .print-btn button {
            padding: 8px 18px; background: #2563eb; color: #fff;
            border: none; border-radius: 4px; cursor: pointer; font-size: 13px;
        }
        .print-btn button:hover { background: #1d4ed8; }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-btn">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()" style="background:#6b7280; margin-left:8px;">Close</button>
    </div>

    <div class="header">
        <h1>Item Stock Movement Report</h1>
        <p>Period: <?= htmlspecialchars($periodText) ?></p>
    </div>

    <div class="info">
        <span><strong>Generated:</strong> <?= date('d-M-Y h:i A') ?></span>
        <span><strong>Total Products:</strong> <?= count($movement) ?></span>
    </div>

    <?php if (empty($movement)): ?>
        <div class="no-data">No data found for the selected filters.</div>
    <?php else: ?>
       <table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Opening</th>
            <th>Purchase</th>
            <th>Sale</th>
            <th>Credit Note</th>
            <th>Debit Note</th>
            <th>Closing</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($movement as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['product_name']) ?></td>
            <td><?= number_format($row['opening'], 2) ?></td>
            <td><?= number_format($row['purchase'], 2) ?></td>
            <td><?= number_format($row['sale'], 2) ?></td>
            <td><?= number_format($row['credit_note'], 2) ?></td>
            <td><?= number_format($row['debit_note'], 2) ?></td>
            <td><?= number_format($row['closing'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>Total (<?= count($movement) ?> products)</td>
            <td><?= number_format($totalOpening, 2) ?></td>
            <td><?= number_format($totalPurchase, 2) ?></td>
            <td><?= number_format($totalSale, 2) ?></td>
            <td><?= number_format($totalCreditNote, 2) ?></td>
            <td><?= number_format($totalDebitNote, 2) ?></td>
            <td><?= number_format($totalClosing, 2) ?></td>
        </tr>
    </tbody>
</table>
    <?php endif; ?>

    <script>
        // Optional: auto print
        // window.onload = () => window.print();
    </script>
</body>
</html>