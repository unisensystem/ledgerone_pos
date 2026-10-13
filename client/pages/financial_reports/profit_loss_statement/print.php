<?php
// Professional print view for the Profit & Loss Statement.
//
// Deliberately does NOT re-run the P&L calculation (multi-currency
// conversion, per-product average-cost COGS, several accounting_ledger
// classification rules — see server/api/financial_reports/profit_loss_statement/
// profit-loss.php). Duplicating that query here would risk exactly the kind
// of screen/print drift bug already fixed once this session for the Recovery
// Sheet report. Instead, profit-loss.js snapshots the already-computed
// report (data + totals + filter labels + chart images) into sessionStorage
// right before opening this page, so what prints is guaranteed to match what
// was on screen.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

// Company letterhead
$company_name    = 'Company';
$company_address = '';
$company_phone   = '';
$company_email   = '';
$company_logo    = '';
try {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tenant_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        $company_name    = $company['company_name'] ?? $company_name;
        $company_address = $company['address']      ?? '';
        $company_phone   = $company['phone']         ?? '';
        $company_email   = $company['email']         ?? '';
        $company_logo    = $company['logo_url']      ?? '';
    }
} catch (Exception $e) {
    error_log('P&L print - company lookup skipped: ' . $e->getMessage());
}

// Generated-by user name
$user_name = 'Unknown';
try {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['full_name'])) {
        $user_name = $user['full_name'];
    }
} catch (Exception $e) {
    error_log('P&L print - user lookup skipped: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Statement - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page { size: A4 portrait; margin: 15mm; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            color: #000;
            background: #fff;
        }

        .sheet {
            max-width: 900px;
            margin: 0 auto;
            padding: 14px 15px 30px;
        }

        /* Toolbar */
        .no-print {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .no-print button {
            font-family: Arial, sans-serif;
            font-size: 13px;
            padding: 9px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: #fff;
        }
        .btn-print { background: #007bff; }
        .btn-pdf { background: #1e7e34; }
        .btn-close { background: #6c757d; }
        .btn-back { background: #495057; }

        /* Header */
        .print-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .print-header img {
            max-height: 55px;
            max-width: 160px;
            margin-bottom: 6px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .print-header .company-name { font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 3px; }
        .print-header .company-sub  { font-size: 10px; color: #444; margin-bottom: 1px; }
        .print-header .report-title { font-size: 15px; font-weight: bold; margin-top: 8px; }
        .print-header .report-period { font-size: 11px; color: #333; margin-top: 2px; }

        /* Filter bar */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 18px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 10px;
        }
        .filter-bar span { color: #555; }
        .filter-bar strong { color: #000; }

        /* Summary boxes */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-bottom: 14px;
        }
        .summary-box {
            border: 1px solid #ddd;
            border-left: 3px solid #1f7bff;
            padding: 6px 10px;
            text-align: center;
        }
        .summary-box.green  { border-left-color: #2fbf71; }
        .summary-box.orange { border-left-color: #e8a23f; }
        .summary-box.profit { border-left-color: #2fbf71; }
        .summary-box.loss   { border-left-color: #dc3545; }
        .summary-box .s-label { font-size: 9px; color: #666; margin-bottom: 2px; text-transform: uppercase; }
        .summary-box .s-value { font-size: 13px; font-weight: bold; }

        /* Charts */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .charts-row img { width: 100%; border: 1px solid #ddd; }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th {
            background: #f0f0f0;
            border: 1px solid #bbb;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: bold;
            text-align: left;
        }
        th.num, td.num { text-align: right; }
        td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            font-size: 11.5px;
            vertical-align: middle;
        }
        tr.section-row td {
            background: #eef1f5;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: .3px;
            text-transform: uppercase;
            padding-top: 6px;
            padding-bottom: 6px;
        }
        tr.subtotal-row td {
            font-weight: bold;
            border-top: 1px solid #999;
        }
        tr.total-row td {
            font-weight: bold;
            font-size: 13px;
            background: #f7f7f7;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        .positive { color: #1a7a35; }
        .negative { color: #b3261e; }

        .generated-by {
            text-align: center;
            font-size: 9px;
            color: #888;
            margin-top: 16px;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-data a { color: #007bff; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .sheet { padding: 8px; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; Print</button>
    <button class="btn-pdf" onclick="downloadAsPdf()">&#11015; Download PDF</button>
    <button class="btn-close" onclick="window.close()">&#10005; Close</button>
</div>

<div class="sheet" id="sheet">
    <div class="no-data" id="noDataState" style="display:none;">
        No report data found. Please generate a report first, then click "Print Report".
    </div>

    <div id="printContent" style="display:none;">
        <!-- Header -->
        <div class="print-header">
            <?php if ($company_logo): ?>
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company_logo); ?>" alt="Logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company_name); ?></div>
            <?php if ($company_address): ?>
                <div class="company-sub"><?php echo htmlspecialchars($company_address); ?></div>
            <?php endif; ?>
            <?php if ($company_phone || $company_email): ?>
                <div class="company-sub">
                    <?php if ($company_phone): ?>Phone: <?php echo htmlspecialchars($company_phone); ?><?php endif; ?>
                    <?php if ($company_phone && $company_email): ?>&nbsp;|&nbsp;<?php endif; ?>
                    <?php if ($company_email): ?>Email: <?php echo htmlspecialchars($company_email); ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="report-title">Profit &amp; Loss Statement</div>
            <div class="report-period" id="periodLabel"></div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar" id="filterBar"></div>

        <!-- Summary -->
        <div class="summary-row" id="summaryRow"></div>

        <!-- Charts -->
        <div class="charts-row" id="chartsRow" style="display:none;"></div>

        <!-- Table -->
        <table>
            <thead>
                <tr>
                    <th style="width:55%">Account</th>
                    <th class="num" style="width:25%">Amount</th>
                    <th class="num" style="width:20%">% of Revenue</th>
                </tr>
            </thead>
            <tbody id="plTableBody"></tbody>
        </table>

        <div class="generated-by" id="generatedBy"></div>
    </div>
</div>

<script>
const userName = <?php echo json_encode($user_name); ?>;

function formatCurrency(symbol, amount) {
    return symbol + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function calculatePercentage(amount, totalRevenue) {
    if (totalRevenue === 0 || totalRevenue < 100) return 'N/A';
    const percentage = ((amount / totalRevenue) * 100).toFixed(2);
    const percentValue = parseFloat(percentage);
    if (percentValue > 100 || percentValue < -100) return '>100%';
    return percentage + '%';
}

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

(function render() {
    let payload = null;
    try {
        const raw = sessionStorage.getItem('plPrintData');
        if (raw) payload = JSON.parse(raw);
    } catch (e) {
        console.error('Could not read report data:', e);
    }

    if (!payload) {
        document.getElementById('noDataState').style.display = 'block';
        return;
    }

    document.getElementById('printContent').style.display = 'block';

    const symbol = payload.currencySymbol || '';
    const { data, totals, filters, detailLevel } = payload;

    // Period + filter bar
    document.getElementById('periodLabel').textContent = payload.periodLabel || '';
    document.getElementById('filterBar').innerHTML = `
        <div><strong>Company:</strong> <span>${esc(filters.company)}</span></div>
        <div><strong>Branch:</strong> <span>${esc(filters.branch)}</span></div>
        <div><strong>Currency:</strong> <span>${esc(filters.currency)}</span></div>
        <div><strong>Project:</strong> <span>${esc(filters.project)}</span></div>
        <div><strong>Container:</strong> <span>${esc(filters.container)}</span></div>
        <div><strong>Detail Level:</strong> <span>${esc(detailLevel)}</span></div>
    `;

    // Summary boxes — same 6 metrics as the on-screen summary cards
    const summaryBoxes = [
        { label: 'Total Revenue', value: formatCurrency(symbol, totals.totalRevenue), cls: '' },
        { label: 'Total COGS', value: formatCurrency(symbol, totals.totalCOGS), cls: '' },
        { label: 'Profit Margin %', value: totals.profitMargin.toFixed(2) + '%', cls: '' },
        { label: 'Gross Profit', value: formatCurrency(symbol, totals.grossProfit), cls: totals.grossProfit >= 0 ? 'green' : 'loss' },
        { label: 'Total Expenses', value: formatCurrency(symbol, totals.totalExpenses), cls: 'orange' },
        { label: 'Net Profit/Loss', value: formatCurrency(symbol, Math.abs(totals.netProfit)), cls: totals.netProfit >= 0 ? 'profit' : 'loss' }
    ];
    document.getElementById('summaryRow').innerHTML = summaryBoxes.map(b => `
        <div class="summary-box ${b.cls}">
            <div class="s-label">${esc(b.label)}</div>
            <div class="s-value">${b.value}</div>
        </div>
    `).join('');

    // Charts (already rendered as PNG data URLs on the report screen)
    if (payload.charts && (payload.charts.revenue || payload.charts.expense)) {
        const chartsRow = document.getElementById('chartsRow');
        chartsRow.style.display = 'grid';
        chartsRow.innerHTML = [
            payload.charts.revenue ? `<img src="${payload.charts.revenue}" alt="Revenue Breakdown">` : '',
            payload.charts.expense ? `<img src="${payload.charts.expense}" alt="Expense Breakdown">` : ''
        ].join('');
    }

    // Table — mirrors profit-loss.js's generateReport() table-building exactly,
    // so the printed figures are identical to what was on screen.
    let html = '';

    const section = (title, items, totalLabel, totalAmount, isNegative) => {
        html += `<tr class="section-row"><td colspan="3">${esc(title)}</td></tr>`;
        if (detailLevel !== 'category') {
            (items || []).forEach(item => {
                const cls = isNegative ? 'negative' : 'positive';
                const amt = isNegative ? `(${formatCurrency(symbol, item.amount)})` : formatCurrency(symbol, item.amount);
                html += `<tr><td style="padding-left:20px;">${esc(item.account)}</td><td class="num ${cls}">${amt}</td><td class="num">${calculatePercentage(item.amount, totals.totalRevenue)}</td></tr>`;
            });
        }
        const cls = isNegative ? 'negative' : 'positive';
        const amt = isNegative ? `(${formatCurrency(symbol, totalAmount)})` : formatCurrency(symbol, totalAmount);
        html += `<tr class="subtotal-row"><td>${esc(totalLabel)}</td><td class="num ${cls}">${amt}</td><td class="num">${calculatePercentage(totalAmount, totals.totalRevenue)}</td></tr>`;
    };

    section('Sales Revenue', data.sales_revenue, 'Total Sales Revenue', totals.totalSalesRevenue, false);
    section('Service Revenue', data.service_revenue, 'Total Service Revenue', totals.totalServiceRevenue, false);
    if (totals.totalRentIncome > 0) {
        section('Rent Income', data.rent_income, 'Total Rent Income', totals.totalRentIncome, false);
    }

    html += `<tr class="subtotal-row"><td><strong>Total Revenue</strong></td><td class="num positive">${formatCurrency(symbol, totals.totalRevenue)}</td><td class="num">100.00%</td></tr>`;

    section('Cost of Goods Sold', data.cogs, 'Total Cost of Goods Sold', totals.totalCOGS, true);

    html += `<tr class="subtotal-row"><td><strong>Gross Profit</strong></td><td class="num ${totals.grossProfit >= 0 ? 'positive' : 'negative'}">${totals.grossProfit >= 0 ? formatCurrency(symbol, totals.grossProfit) : `(${formatCurrency(symbol, Math.abs(totals.grossProfit))})`}</td><td class="num">${calculatePercentage(totals.grossProfit, totals.totalRevenue)}</td></tr>`;

    section('Operating Expenses', data.expenses, 'Total Operating Expenses', totals.totalExpenses, true);

    if (totals.totalOtherIncome > 0) {
        section('Other Income', data.other_income, 'Total Other Income', totals.totalOtherIncome, false);
    }

    html += `<tr class="total-row"><td><strong>NET PROFIT/LOSS</strong></td><td class="num ${totals.netProfit >= 0 ? 'positive' : 'negative'}">${totals.netProfit >= 0 ? formatCurrency(symbol, totals.netProfit) : `(${formatCurrency(symbol, Math.abs(totals.netProfit))})`}</td><td class="num">${calculatePercentage(totals.netProfit, totals.totalRevenue)}</td></tr>`;

    document.getElementById('plTableBody').innerHTML = html;

    document.getElementById('generatedBy').textContent =
        `LedgerOne ERP  •  Profit & Loss Statement  •  Generated by ${userName} on ${new Date(payload.generatedAt).toLocaleString()}`;

    // ?pdf=1 (used by the Export PDF button on the report page) auto-downloads
    // and closes, instead of requiring the user to click Download PDF here too.
    if (new URLSearchParams(window.location.search).get('pdf') === '1') {
        setTimeout(() => downloadAsPdf(true), 500);
    }
})();

function downloadAsPdf(closeAfter) {
    const element = document.getElementById('sheet');
    const filename = `profit-loss-statement_${new Date().toISOString().slice(0, 10)}.pdf`;
    html2pdf().set({
        margin: 0.4,
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    }).from(element).save().then(() => {
        if (closeAfter) window.close();
    });
}
</script>

</body>
</html>
