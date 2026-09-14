<?php
require_once '../../../../includes/connection.php';

session_start();

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/
$currency_stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c
        ON tc.currency_id = c.id
    WHERE tc.tenant_id = ?
      AND tc.is_base_currency = 1
");
$currency_stmt->execute([$tenant_id]);

$currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
$currency_symbol = $currency['symbol'] ?? '$';


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
| NOTE: project_id, sort_by, sort_order and hide_no_movement are NEW —
| these now mirror exactly what the on-screen "Item Stock Movement" tab
| filters/sorts/hides, so Print shows the same data the user is looking at.
|--------------------------------------------------------------------------
*/
$product_id = $_GET['product_id'] ?? null;
$branch_id  = $_GET['branch_id'] ?? null;
$vendor_id  = $_GET['vendor_id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
$from_date  = $_GET['from_date'] ?? null;
$to_date    = $_GET['to_date'] ?? null;
$sort_by    = $_GET['sort_by'] ?? '';
$sort_order = $_GET['sort_order'] ?? 'asc';
$hide_no_movement = isset($_GET['hide_no_movement']) && $_GET['hide_no_movement'] === '1';

$allowed_sort_fields = ['opening', 'purchase', 'sale', 'credit_note', 'debit_note', 'closing'];
if (!in_array($sort_by, $allowed_sort_fields, true)) {
    $sort_by = '';
}
if (!in_array($sort_order, ['asc', 'desc'], true)) {
    $sort_order = 'asc';
}


/*
|--------------------------------------------------------------------------
| IMPORTANT:
| Product/UOM conversion
|--------------------------------------------------------------------------
*/
$conversion_sql = "
    COALESCE(
        CASE
            WHEN u.unit_scope = 'universal'
                THEN u.conversion_factor
            ELSE puc.conversion_factor
        END,
        1
    )
";


/*
|--------------------------------------------------------------------------
| OPENING BALANCE
|--------------------------------------------------------------------------
*/

$bbf_by_product = [];


if ($from_date) {

    $bbf_sql = "
        SELECT
            sl.product_id,

            COALESCE(
                SUM(
                    CASE
                        WHEN sl.transaction_date < ?
                            THEN
                                (sl.qty_in * {$conversion_sql})
                                -
                                (sl.qty_out * {$conversion_sql})

                        WHEN sl.transaction_type = 'Opening Stock'
                             AND sl.transaction_date = ?
                            THEN
                                (sl.qty_in * {$conversion_sql})
                                -
                                (sl.qty_out * {$conversion_sql})

                        ELSE 0
                    END
                ),
                0
            ) AS bbf

        FROM stock_ledger sl

        JOIN products p
            ON sl.product_id = p.id
           AND sl.tenant_id = p.tenant_id

        LEFT JOIN uom u
            ON sl.unit_id = u.id

        LEFT JOIN product_uom_conversions puc
            ON p.id = puc.product_id
           AND sl.unit_id = puc.uom_id

        LEFT JOIN purchase_invoice pi
            ON sl.reference_table = 'purchase_invoice'
           AND sl.reference_id = pi.id

        LEFT JOIN sale_invoice si
            ON sl.reference_table = 'sale_invoice'
           AND sl.reference_id = si.id

        WHERE sl.tenant_id = ?
          AND p.product_type = 'physical'
          AND (
                sl.transaction_date < ?
                OR (
                    sl.transaction_type = 'Opening Stock'
                    AND sl.transaction_date = ?
                )
          )
    ";

    $bbf_params = [
        $from_date,
        $from_date,
        $tenant_id,
        $from_date,
        $from_date
    ];

    if ($product_id) {
        $bbf_sql .= " AND sl.product_id = ?";
        $bbf_params[] = $product_id;
    }

    if ($branch_id) {
        $bbf_sql .= " AND sl.branch_id = ?";
        $bbf_params[] = $branch_id;
    }

    if ($vendor_id) {
        $bbf_sql .= " AND p.vendor_id = ?";
        $bbf_params[] = $vendor_id;
    }

    // NEW: Project filter (needs the pi/si joins added above)
    if ($project_id) {
        $bbf_sql .= " AND (pi.project_id = ? OR si.project_id = ?)";
        $bbf_params[] = $project_id;
        $bbf_params[] = $project_id;
    }

    $bbf_sql .= " GROUP BY sl.product_id";

    $bbf_stmt = $pdo->prepare($bbf_sql);
    $bbf_stmt->execute($bbf_params);

    foreach ($bbf_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bbf_by_product[$row['product_id']] = (float) $row['bbf'];
    }
}

else {

    $opening_sql = "
        SELECT
            sl.product_id,

            COALESCE(
                SUM(
                    (sl.qty_in * {$conversion_sql})
                    -
                    (sl.qty_out * {$conversion_sql})
                ),
                0
            ) AS opening_qty

        FROM stock_ledger sl

        JOIN products p
            ON sl.product_id = p.id
           AND sl.tenant_id = p.tenant_id

        LEFT JOIN uom u
            ON sl.unit_id = u.id

        LEFT JOIN product_uom_conversions puc
            ON p.id = puc.product_id
           AND sl.unit_id = puc.uom_id

        LEFT JOIN purchase_invoice pi
            ON sl.reference_table = 'purchase_invoice'
           AND sl.reference_id = pi.id

        LEFT JOIN sale_invoice si
            ON sl.reference_table = 'sale_invoice'
           AND sl.reference_id = si.id

        WHERE sl.tenant_id = ?
          AND p.product_type = 'physical'
          AND sl.transaction_type = 'Opening Stock'
    ";

    $opening_params = [$tenant_id];

    if ($product_id) {
        $opening_sql .= " AND sl.product_id = ?";
        $opening_params[] = $product_id;
    }

    if ($branch_id) {
        $opening_sql .= " AND sl.branch_id = ?";
        $opening_params[] = $branch_id;
    }

    if ($vendor_id) {
        $opening_sql .= " AND p.vendor_id = ?";
        $opening_params[] = $vendor_id;
    }

    // NEW: Project filter
    if ($project_id) {
        $opening_sql .= " AND (pi.project_id = ? OR si.project_id = ?)";
        $opening_params[] = $project_id;
        $opening_params[] = $project_id;
    }

    $opening_sql .= " GROUP BY sl.product_id";

    $opening_stmt = $pdo->prepare($opening_sql);
    $opening_stmt->execute($opening_params);

    foreach ($opening_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bbf_by_product[$row['product_id']] = (float) $row['opening_qty'];
    }
}


/*
|--------------------------------------------------------------------------
| PERIOD MOVEMENTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        sl.product_id,

        p.name AS product_name,

        COALESCE(
            base_u.uom_name,
            u.uom_name,
            'L'
        ) AS unit_symbol,

        COALESCE(
            SUM(
                CASE
                    WHEN sl.reference_table = 'purchase_invoice'
                        THEN sl.qty_in * {$conversion_sql}
                    ELSE 0
                END
            ),
            0
        ) AS purchase,

        COALESCE(
            SUM(
                CASE
                    WHEN sl.reference_table = 'sale_invoice'
                        THEN sl.qty_out * {$conversion_sql}
                    ELSE 0
                END
            ),
            0
        ) AS sale,

        COALESCE(
            SUM(
                CASE
                    WHEN sl.reference_table IN ('sale_return', 'credit_note')
                        THEN sl.qty_in * {$conversion_sql}
                    ELSE 0
                END
            ),
            0
        ) AS credit_note,

        COALESCE(
            SUM(
                CASE
                    WHEN sl.reference_table IN ('purchase_return', 'debit_note')
                        THEN sl.qty_out * {$conversion_sql}
                    ELSE 0
                END
            ),
            0
        ) AS debit_note

    FROM stock_ledger sl

    JOIN products p
        ON sl.product_id = p.id
       AND sl.tenant_id = p.tenant_id

    LEFT JOIN uom u
        ON sl.unit_id = u.id

    LEFT JOIN uom base_u
        ON COALESCE(u.base_unit_id, u.id) = base_u.id

    LEFT JOIN product_uom_conversions puc
        ON p.id = puc.product_id
       AND sl.unit_id = puc.uom_id

    LEFT JOIN purchase_invoice pi
        ON sl.reference_table = 'purchase_invoice'
       AND sl.reference_id = pi.id

    LEFT JOIN sale_invoice si
        ON sl.reference_table = 'sale_invoice'
       AND sl.reference_id = si.id

    WHERE sl.tenant_id = ?
      AND p.product_type = 'physical'
";

$params = [$tenant_id];

if ($product_id) {
    $sql .= " AND sl.product_id = ?";
    $params[] = $product_id;
}

if ($branch_id) {
    $sql .= " AND sl.branch_id = ?";
    $params[] = $branch_id;
}

if ($vendor_id) {
    $sql .= " AND p.vendor_id = ?";
    $params[] = $vendor_id;
}

// NEW: Project filter
if ($project_id) {
    $sql .= " AND (pi.project_id = ? OR si.project_id = ?)";
    $params[] = $project_id;
    $params[] = $project_id;
}

if ($from_date) {
    $sql .= " AND sl.transaction_date >= ?";
    $params[] = $from_date;
}

if ($to_date) {
    $sql .= " AND sl.transaction_date <= ?";
    $params[] = $to_date;
}

// Opening Stock must not be counted as period movement (matches the tab's logic)
$sql .= " AND sl.transaction_type <> 'Opening Stock'";

$sql .= "
    GROUP BY
        sl.product_id,
        p.name,
        unit_symbol

    ORDER BY
        p.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BUILD MOVEMENT REPORT
|--------------------------------------------------------------------------
*/

$movement = [];
$seen = [];

foreach ($rows as $row) {

    $pid = $row['product_id'];
    $seen[$pid] = true;

    $opening = $bbf_by_product[$pid] ?? 0;

    $purchase    = (float) $row['purchase'];
    $sale        = (float) $row['sale'];
    $credit_note = (float) $row['credit_note'];
    $debit_note  = (float) $row['debit_note'];

    $closing = $opening + $purchase + $credit_note - $sale - $debit_note;

    $movement[] = [
        'product_id'   => $pid,
        'product_name' => $row['product_name'],
        'unit_symbol'  => $row['unit_symbol'] ?: 'L',
        'opening'      => $opening,
        'purchase'     => $purchase,
        'sale'         => $sale,
        'credit_note'  => $credit_note,
        'debit_note'   => $debit_note,
        'closing'      => $closing
    ];
}

// Products having ONLY opening stock (no movement in the period)
foreach ($bbf_by_product as $pid => $opening) {

    if (isset($seen[$pid])) {
        continue;
    }

    $name_stmt = $pdo->prepare("
        SELECT name FROM products WHERE id = ? AND tenant_id = ? LIMIT 1
    ");
    $name_stmt->execute([$pid, $tenant_id]);
    $pname = $name_stmt->fetchColumn() ?: 'Unknown';

    $unit_stmt = $pdo->prepare("
        SELECT COALESCE(base_u.uom_name, u.uom_name, 'L') AS unit_symbol
        FROM stock_ledger sl
        LEFT JOIN uom u ON sl.unit_id = u.id
        LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
        WHERE sl.tenant_id = ? AND sl.product_id = ? AND sl.transaction_type = 'Opening Stock'
        ORDER BY sl.id ASC
        LIMIT 1
    ");
    $unit_stmt->execute([$tenant_id, $pid]);
    $unit_symbol = $unit_stmt->fetchColumn() ?: 'L';

    $movement[] = [
        'product_id'   => $pid,
        'product_name' => $pname,
        'unit_symbol'  => $unit_symbol,
        'opening'      => $opening,
        'purchase'     => 0,
        'sale'         => 0,
        'credit_note'  => 0,
        'debit_note'   => 0,
        'closing'      => $opening
    ];
}


/*
|--------------------------------------------------------------------------
| NEW: Hide No Movement — same rule as the on-screen toggle
|--------------------------------------------------------------------------
*/
if ($hide_no_movement) {
    $movement = array_values(array_filter($movement, function ($row) {
        return (float)$row['purchase']    !== 0.0
            || (float)$row['sale']        !== 0.0
            || (float)$row['credit_note'] !== 0.0
            || (float)$row['debit_note']  !== 0.0;
    }));
}


/*
|--------------------------------------------------------------------------
| Sort — same rule as the on-screen "Sort By" / "Order" filters
|--------------------------------------------------------------------------
*/
if ($sort_by) {
    usort($movement, function ($a, $b) use ($sort_by, $sort_order) {
        $diff = (float)$a[$sort_by] - (float)$b[$sort_by];
        if ($diff === 0.0) return 0;
        $result = $diff > 0 ? 1 : -1;
        return $sort_order === 'desc' ? -$result : $result;
    });
} else {
    usort($movement, fn($a, $b) => strcmp($a['product_name'], $b['product_name']));
}


/*
|--------------------------------------------------------------------------
| Period Text
|--------------------------------------------------------------------------
*/

$periodText = 'All Dates';

if ($from_date && $to_date) {
    $periodText = date('d-M-Y', strtotime($from_date)) . ' to ' . date('d-M-Y', strtotime($to_date));
} elseif ($to_date) {
    $periodText = 'As on ' . date('d-M-Y', strtotime($to_date));
} elseif ($from_date) {
    $periodText = 'From ' . date('d-M-Y', strtotime($from_date));
}


/*
|--------------------------------------------------------------------------
| Totals (computed AFTER hide-no-movement filter + sort, so they match
| exactly what's displayed below)
|--------------------------------------------------------------------------
*/

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
        td:first-child, th:first-child { text-align: left; }
        td:nth-child(2), th:nth-child(2) { text-align: right; }
        .total-row { font-weight: bold; background: #e8e8e8; }
        .no-data { text-align: center; padding: 30px; color: #666; }
        .print-btn { margin-bottom: 15px; }
        .print-btn button { padding: 8px 18px; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .print-btn button:hover { background: #1d4ed8; }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
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
            <td><?= number_format((float)$row['opening'], 2) ?></td>
            <td><?= number_format((float)$row['purchase'], 2) ?></td>
            <td><?= number_format((float)$row['sale'], 2) ?></td>
            <td><?= number_format((float)$row['credit_note'], 2) ?></td>
            <td><?= number_format((float)$row['debit_note'], 2) ?></td>
            <td><?= number_format((float)$row['closing'], 2) ?></td>
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

</body>
</html>