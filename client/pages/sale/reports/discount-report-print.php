<?php
require_once '../../../../includes/connection.php';

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    die('Unauthorized');
}

// Get filters from URL
$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;
$customerId = $_GET['customerId'] ?? null;
$productId = $_GET['productId'] ?? null;
$branchId = $_GET['branchId'] ?? null;
$companyId = $_GET['companyId'] ?? null;

try {
    // Fetch data with filters
    $query = "
        SELECT 
            si.bill_no, 
            si.sale_date, 
            c.customer_code, 
            c.customer_name,
            p.code, 
            p.name, 
            sii.quantity, 
            sii.sale_price, 
            sii.gross_amount,
            sii.discount_percent, 
            sii.discount_amount, 
            sii.extra_discount_amount,
            sii.tax_percent, 
            sii.tax_amount, 
            sii.net_amount, 
            cur.symbol,
            COALESCE(
                ROUND(
                    (sii.discount_amount / 
                    NULLIF(sii.gross_amount, 0)) * 100, 3
                ), 0
            ) as calculated_disc_percent,
            COALESCE(
                ROUND(
                    (sii.extra_discount_amount / 
                    NULLIF((sii.gross_amount - sii.discount_amount), 0)) * 100, 3
                ), 0
            ) as calculated_extra_disc_percent
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        WHERE si.tenant_id = ? AND si.status = 'Posted'
    ";

    $params = [$tenant_id];

    if ($startDate) { 
        $query .= " AND si.sale_date >= ?"; 
        $params[] = $startDate; 
    }
    if ($endDate) { 
        $query .= " AND si.sale_date <= ?"; 
        $params[] = $endDate; 
    }
    if ($customerId) { 
        $query .= " AND si.customer_id = ?"; 
        $params[] = $customerId; 
    }
    if ($productId) { 
        $query .= " AND sii.product_id = ?"; 
        $params[] = $productId; 
    }
    if ($branchId) { 
        $query .= " AND si.branch_id = ?"; 
        $params[] = $branchId; 
    }
    if ($companyId) { 
        $query .= " AND si.company_id = ?"; 
        $params[] = $companyId; 
    }

    $query .= " ORDER BY si.sale_date DESC, si.bill_no DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totals = [
        'gross' => 0, 
        'disc' => 0, 
        'extra_disc' => 0, 
        'tax' => 0, 
        'net' => 0,
        'count' => count($data)
    ];

    foreach ($data as $row) {
        $totals['gross'] += floatval($row['gross_amount']);
        $totals['disc'] += floatval($row['discount_amount']);
        $totals['extra_disc'] += floatval($row['extra_discount_amount']);
        $totals['tax'] += floatval($row['tax_amount']);
        $totals['net'] += floatval($row['net_amount']);
    }

} catch (Exception $e) {
    die('Error loading report data: ' . htmlspecialchars($e->getMessage()));
}

$currencySymbol = !empty($data) ? $data[0]['symbol'] : '₨';
$reportDate = date('d-M-Y');
$reportTime = date('H:i:s');
if ($startDate) $reportDate .= ' to ' . date('d-M-Y', strtotime($endDate ?? date('Y-m-d')));

/*
 * IMPORTANT:
 * The normal print page is opened in a new tab from the frontend, so JS/localStorage
 * is available here. The frontend stores hidden column keys in:
 *     discountReportHiddenColumns
 *
 * We render the complete report server-side, then use JS to remove hidden columns
 * before print. This keeps Print Current View consistent with the Hide/Show Columns
 * selection on the main report.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Report - Print</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #20242a;
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        .page {
            width: 100%;
            margin: 0 auto;
            padding: 8mm;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1f2937;
        }

        .brand {
            min-width: 0;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .5px;
            color: #111827;
        }

        .brand-subtitle {
            margin-top: 3px;
            font-size: 9px;
            color: #6b7280;
        }

        .report-meta {
            text-align: right;
            font-size: 9px;
            color: #4b5563;
            line-height: 1.7;
            white-space: nowrap;
        }

        .report-meta strong {
            color: #111827;
        }

        .filter-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 18px;
            margin: 9px 0 10px;
            padding: 7px 9px;
            border: 1px solid #d9dee5;
            background: #f7f8fa;
            border-radius: 5px;
            color: #4b5563;
            font-size: 8.5px;
        }

        .filter-summary span {
            white-space: nowrap;
        }

        .filter-summary strong {
            color: #1f2937;
        }

        .table-wrap {
            width: 100%;
            overflow: visible;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
            font-size: 8.5px;
        }

        thead {
            display: table-header-group;
        }

        thead th {
            padding: 6px 5px;
            background: #1f2937;
            color: #fff;
            border: 1px solid #111827;
            font-size: 8px;
            font-weight: 700;
            text-align: left;
            white-space: nowrap;
            vertical-align: middle;
        }

        thead th.num {
            text-align: right;
        }

        tbody td {
            padding: 5px;
            border: 1px solid #e1e5ea;
            vertical-align: middle;
            color: #252a31;
            word-break: break-word;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .code {
            font-weight: 700;
            color: #1f2937;
        }

        .subtext {
            margin-top: 1px;
            color: #6b7280;
            font-size: 7.5px;
        }

        .num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .total-label {
            text-align: right !important;
            font-weight: 800;
        }

        tfoot {
            display: table-footer-group;
        }

        .totals-row td {
            padding: 6px 5px;
            background: #e5e7eb !important;
            color: #111827;
            border: 1px solid #9ca3af;
            border-top: 2px solid #374151;
            font-weight: 800;
        }

        .empty-state {
            padding: 35px 15px;
            text-align: center;
            color: #6b7280;
            border: 1px solid #d9dee5;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 10px;
            padding-top: 7px;
            border-top: 1px solid #d1d5db;
            color: #8a919b;
            font-size: 7.5px;
        }

        .footer span:last-child {
            text-align: right;
        }

        /* Hidden columns are applied after the page loads from localStorage. */
        .print-hidden {
            display: none !important;
        }

        @media screen {
            body {
                background: #eef1f5;
            }

            .page {
                max-width: 1400px;
                margin: 18px auto;
                padding: 18px;
                background: #fff;
                box-shadow: 0 8px 30px rgba(15, 23, 42, .08);
            }
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }

            html, body {
                background: #fff;
            }

            .page {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .report-header {
                break-inside: avoid;
            }

            .filter-summary {
                break-inside: avoid;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }
        }
    </style>
</head>

<body>
<div class="page">

    <header class="report-header">
        <div class="brand">
            <div class="brand-title">DISCOUNT REPORT</div>
            <div class="brand-subtitle">Sales discount analysis by customer and product</div>
        </div>

        <div class="report-meta">
            <div><strong>Period:</strong> <?php echo htmlspecialchars($reportDate); ?></div>
            <div><strong>Generated:</strong> <?php echo htmlspecialchars($reportTime); ?></div>
            <div><strong>Total Records:</strong> <?php echo (int)$totals['count']; ?></div>
        </div>
    </header>

    <div class="filter-summary">
        <?php if ($startDate || $endDate): ?>
            <span><strong>Date:</strong> <?php echo htmlspecialchars($startDate ?: 'All'); ?> — <?php echo htmlspecialchars($endDate ?: 'All'); ?></span>
        <?php endif; ?>

        <?php if ($customerId): ?>
            <span><strong>Customer:</strong> Filtered</span>
        <?php endif; ?>

        <?php if ($productId): ?>
            <span><strong>Product:</strong> Filtered</span>
        <?php endif; ?>

        <?php if ($branchId): ?>
            <span><strong>Branch:</strong> Filtered</span>
        <?php endif; ?>

        <?php if ($companyId): ?>
            <span><strong>Company:</strong> Filtered</span>
        <?php endif; ?>
    </div>

    <?php if (empty($data)): ?>

        <div class="empty-state">
            No records found for the selected period and filters.
        </div>

    <?php else: ?>

        <div class="table-wrap">
            <table id="printReportTable">
                <thead>
                    <tr>
                        <th data-col="date">Date</th>
                        <th data-col="billno">Bill No</th>
                        <th data-col="customer">Customer</th>
                        <th data-col="product">Product</th>
                        <th data-col="qty" class="num">Qty</th>
                        <th data-col="price" class="num">Price</th>
                        <th data-col="gross" class="num">Gross Amt</th>
                        <th data-col="discPercent" class="num">Disc %</th>
                        <th data-col="discAmt" class="num">Disc Amt</th>
                        <th data-col="extraDiscPercent" class="num">Extra Disc %</th>
                        <th data-col="extraDiscAmt" class="num">Extra Disc Amt</th>
                        <th data-col="taxPercent" class="num">Tax %</th>
                        <th data-col="net" class="num">Net Amt</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td data-col="date"><?php echo htmlspecialchars(date('d-M-y', strtotime($row['sale_date']))); ?></td>

                        <td data-col="billno">
                            <span class="code"><?php echo htmlspecialchars($row['bill_no']); ?></span>
                        </td>

                        <td data-col="customer">
                            <div class="code"><?php echo htmlspecialchars($row['customer_code']); ?></div>
                            <div class="subtext"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                        </td>

                        <td data-col="product">
                            <div class="code"><?php echo htmlspecialchars($row['code']); ?></div>
                            <div class="subtext"><?php echo htmlspecialchars($row['name']); ?></div>
                        </td>

                        <td data-col="qty" class="num"><?php echo number_format((float)$row['quantity'], 2); ?></td>

                        <td data-col="price" class="num">
                            <?php echo htmlspecialchars($row['symbol']) . ' ' . number_format((float)$row['sale_price'], 2); ?>
                        </td>

                        <td data-col="gross" class="num">
                            <?php echo number_format((float)$row['gross_amount'], 2); ?>
                        </td>

                        <td data-col="discPercent" class="num">
                            <?php echo number_format((float)$row['calculated_disc_percent'], 2); ?>%
                        </td>

                        <td data-col="discAmt" class="num">
                            <?php echo number_format((float)$row['discount_amount'], 2); ?>
                        </td>

                        <td data-col="extraDiscPercent" class="num">
                            <?php echo number_format((float)$row['calculated_extra_disc_percent'], 2); ?>%
                        </td>

                        <td data-col="extraDiscAmt" class="num">
                            <?php echo number_format((float)$row['extra_discount_amount'], 2); ?>
                        </td>

                        <td data-col="taxPercent" class="num">
                            <?php echo number_format((float)$row['tax_percent'], 2); ?>%
                        </td>

                        <td data-col="net" class="num">
                            <strong><?php echo number_format((float)$row['net_amount'], 2); ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

                <tfoot>
                    <tr class="totals-row">
                        <td data-total-label colspan="6" class="total-label">TOTALS:</td>
                        <td data-total="gross" class="num"><?php echo number_format($totals['gross'], 2); ?></td>
                        <td data-total-spacer="discPercent"></td>
                        <td data-total="disc" class="num"><?php echo number_format($totals['disc'], 2); ?></td>
                        <td data-total-spacer="extraDiscPercent"></td>
                        <td data-total="extraDisc" class="num"><?php echo number_format($totals['extra_disc'], 2); ?></td>
                        <td data-total-spacer="taxPercent"></td>
                        <td data-total="net" class="num"><strong><?php echo number_format($totals['net'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

    <?php endif; ?>

    <footer class="footer">
        <span>LedgerOne ERP • Discount Report</span>
        <span>Generated <?php echo htmlspecialchars($reportDate . ' ' . $reportTime); ?> • Confidential</span>
    </footer>

</div>

<script>
(function () {
    /*
     * Same storage key used by discount-report.js.
     * If a user hides a column on the main report, it will NOT appear here.
     */
    const STORAGE_KEY = 'discountReportHiddenColumns';

    const columnOrder = [
        'date',
        'billno',
        'customer',
        'product',
        'qty',
        'price',
        'gross',
        'discPercent',
        'discAmt',
        'extraDiscPercent',
        'extraDiscAmt',
        'taxPercent',
        'net'
    ];

    function getHiddenColumns() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function applyHiddenColumns() {
        const hidden = new Set(getHiddenColumns());
        const table = document.getElementById('printReportTable');

        if (!table) return;

        // Hide header/body cells.
        table.querySelectorAll('[data-col]').forEach(function (cell) {
            const key = cell.getAttribute('data-col');
            cell.classList.toggle('print-hidden', hidden.has(key));
        });

        // Hide footer cells tied to a specific column.
        table.querySelectorAll('[data-total], [data-total-spacer]').forEach(function (cell) {
            const key = cell.getAttribute('data-total') || cell.getAttribute('data-total-spacer');
            cell.classList.toggle('print-hidden', hidden.has(key));
        });

        /*
         * Rebuild the TOTALS label colspan so it always ends immediately before
         * the first visible numeric total column.
         */
        const label = table.querySelector('[data-total-label]');
        if (label) {
            const labelKeys = ['date', 'billno', 'customer', 'product', 'qty', 'price'];
            const visibleLabelCount = labelKeys.filter(function (key) {
                return !hidden.has(key);
            }).length;

            label.colSpan = Math.max(1, visibleLabelCount);
        }
    }

    // Apply before printing. Also useful when the browser opens the print page slowly.
    applyHiddenColumns();

    window.addEventListener('beforeprint', applyHiddenColumns);

    // This page is intentionally print-ready rather than auto-printing immediately,
    // so the hidden-column state has time to apply.
    window.addEventListener('load', function () {
        applyHiddenColumns();
        setTimeout(function () {
            window.print();
        }, 80);
    });
})();
</script>

</body>
</html>
