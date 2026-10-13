<?php
// Print view for the Profit & Loss Detail Report (per active tab).
//
// Reads the already-computed active-tab data from sessionStorage (stashed by
// profit-loss-detail.js right before opening this page) instead of a
// PHP-embedded query-string payload — the old `?data=<json>&...` URL could
// grow past browser/server URL-length limits for larger item/customer/
// invoice tables, silently truncating or failing to open. sessionStorage has
// no such limit and keeps Print/Export PDF/Export CSV all working from the
// exact same snapshot of what's on screen.
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
$company_city    = '';
$company_state   = '';
$company_country = '';
$company_phone   = '';
$company_email   = '';
$company_logo    = '';
try {
    $stmt = $pdo->prepare("SELECT company_name, address, city, state, country, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tenant_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        $company_name    = $company['company_name'] ?? $company_name;
        $company_address = $company['address']      ?? '';
        $company_city    = $company['city']         ?? '';
        $company_state   = $company['state']         ?? '';
        $company_country = $company['country']       ?? '';
        $company_phone   = $company['phone']         ?? '';
        $company_email   = $company['email']         ?? '';
        $company_logo    = $company['logo_url']      ?? '';
    }
} catch (Exception $e) {
    error_log('P&L Detail print - company lookup skipped: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Detail Report - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { font-family: Arial, sans-serif; padding: 20px; color: #000; }

        .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
        .company-logo { max-width: 150px; max-height: 80px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .company-details { font-size: 12px; color: #333; line-height: 1.6; }

        .report-title { font-size: 20px; font-weight: bold; margin: 20px 0 10px 0; text-align: center; }
        .report-period { text-align: center; font-size: 14px; margin-bottom: 20px; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th { background-color: #f0f0f0; padding: 10px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
        td { padding: 8px 10px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #f5f5f5; }
        .positive { color: #2fbf71; }
        .negative { color: #e34f4f; }
        .partner-note { font-size: 11px; color: #555; margin-bottom: 10px; }

        .print-footer { margin-top: 40px; text-align: center; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }

        .no-data { text-align: center; padding: 60px 20px; color: #666; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            @page { margin: 15mm; }
        }

        .toolbar { position: fixed; top: 20px; right: 20px; display: flex; gap: 8px; }
        .toolbar button {
            padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;
            font-size: 14px; color: #fff;
        }
        .btn-print { background-color: #1f7bff; }
        .btn-pdf { background-color: #1e7e34; }
        .btn-close { background-color: #6c757d; }
    </style>
</head>
<body>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <div class="toolbar no-print">
        <button class="btn-print" onclick="window.print()">Print</button>
        <button class="btn-pdf" onclick="downloadAsPdf()">Download PDF</button>
        <button class="btn-close" onclick="window.close()">Close</button>
    </div>

    <div id="noDataState" class="no-data" style="display:none;">
        No report data found. Please generate a report first, then click "Print Report".
    </div>

    <div id="printSheet" style="display:none;">
        <div class="print-header">
            <?php if ($company_logo): ?>
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo" class="company-logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company_name); ?></div>
            <div class="company-details">
                <?php
                $locationParts = array_filter([$company_address, $company_city, $company_state, $company_country]);
                if ($locationParts) echo htmlspecialchars(implode(', ', $locationParts));
                ?>
                <br>
                <?php if ($company_phone): ?>Phone: <?php echo htmlspecialchars($company_phone); ?><?php endif; ?>
                <?php if ($company_phone && $company_email): ?> | <?php endif; ?>
                <?php if ($company_email): ?>Email: <?php echo htmlspecialchars($company_email); ?><?php endif; ?>
            </div>
        </div>

        <div class="report-title" id="reportTitle"></div>
        <div class="report-period" id="reportPeriod"></div>
        <div id="reportContent"></div>

        <div class="print-footer">
            <p>Generated on <?php echo date('F d, Y h:i A'); ?> | LedgerOne ERP System</p>
        </div>
    </div>

    <script>
        const REPORT_TITLES = {
            itemwise: 'Item-wise Profit & Loss Report',
            categorywise: 'Category-wise Profit & Loss Report',
            customerwise: 'Customer-wise Profit & Loss Report',
            supplierwise: 'Supplier-wise Profit & Loss Report',
            partnerwise: 'Partner-wise Profit Distribution Report',
            invoicewise: 'Invoice-wise Profit & Loss Report'
        };

        function fmtDateShort(dateStr) {
            const d = new Date(dateStr);
            if (isNaN(d)) return dateStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        let currencySymbol = '';
        function formatCurrency(amount) {
            return currencySymbol + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function renderReport(reportType, reportData) {
            const container = document.getElementById('reportContent');
            let html = '';

            if (reportType === 'itemwise') {
                html = `<table><thead><tr><th>Product</th><th class="text-right">Qty Sold</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th><th class="text-right">Margin %</th></tr></thead><tbody>`;
                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;
                reportData.forEach(item => {
                    const revenue = parseFloat(item.revenue);
                    const cogs = parseFloat(item.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;
                    totalRevenue += revenue; totalCOGS += cogs; totalProfit += profit;
                    html += `<tr><td>${esc(item.product_name)}</td><td class="text-right">${parseFloat(item.qty_sold).toFixed(2)}</td><td class="text-right">${formatCurrency(revenue)}</td><td class="text-right">${formatCurrency(cogs)}</td><td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td><td class="text-right">${margin}%</td></tr>`;
                });
                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `<tr class="total-row"><td><strong>TOTAL</strong></td><td class="text-right"></td><td class="text-right">${formatCurrency(totalRevenue)}</td><td class="text-right">${formatCurrency(totalCOGS)}</td><td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td><td class="text-right">${totalMargin}%</td></tr></tbody></table>`;

            } else if (reportType === 'categorywise') {
                html = `<table><thead><tr><th>Category</th><th class="text-right">Products</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th><th class="text-right">Margin %</th></tr></thead><tbody>`;
                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;
                reportData.forEach(category => {
                    const revenue = parseFloat(category.revenue);
                    const cogs = parseFloat(category.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;
                    totalRevenue += revenue; totalCOGS += cogs; totalProfit += profit;
                    html += `<tr><td>${esc(category.category_name || 'Uncategorized')}</td><td class="text-right">${category.product_count}</td><td class="text-right">${formatCurrency(revenue)}</td><td class="text-right">${formatCurrency(cogs)}</td><td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td><td class="text-right">${margin}%</td></tr>`;
                });
                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `<tr class="total-row"><td><strong>TOTAL</strong></td><td class="text-right"></td><td class="text-right">${formatCurrency(totalRevenue)}</td><td class="text-right">${formatCurrency(totalCOGS)}</td><td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td><td class="text-right">${totalMargin}%</td></tr></tbody></table>`;

            } else if (reportType === 'customerwise') {
                html = `<table><thead><tr><th>Customer</th><th class="text-right">Invoices</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th><th class="text-right">Margin %</th></tr></thead><tbody>`;
                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;
                reportData.forEach(customer => {
                    const revenue = parseFloat(customer.revenue);
                    const cogs = parseFloat(customer.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;
                    totalRevenue += revenue; totalCOGS += cogs; totalProfit += profit;
                    html += `<tr><td>${esc(customer.customer_name)}</td><td class="text-right">${customer.invoice_count}</td><td class="text-right">${formatCurrency(revenue)}</td><td class="text-right">${formatCurrency(cogs)}</td><td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td><td class="text-right">${margin}%</td></tr>`;
                });
                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `<tr class="total-row"><td><strong>TOTAL</strong></td><td class="text-right"></td><td class="text-right">${formatCurrency(totalRevenue)}</td><td class="text-right">${formatCurrency(totalCOGS)}</td><td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td><td class="text-right">${totalMargin}%</td></tr></tbody></table>`;

            } else if (reportType === 'supplierwise') {
                html = `<table><thead><tr><th>Code</th><th>Supplier</th><th class="text-right">Purchases</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th><th class="text-right">Margin %</th></tr></thead><tbody>`;
                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;
                reportData.forEach(supplier => {
                    const revenue = parseFloat(supplier.revenue);
                    const cogs = parseFloat(supplier.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : '—';
                    totalRevenue += revenue; totalCOGS += cogs; totalProfit += profit;
                    html += `<tr><td>${esc(supplier.supplier_code || '—')}</td><td>${esc(supplier.supplier_name)}</td><td class="text-right">${supplier.purchase_count}</td><td class="text-right">${formatCurrency(revenue)}</td><td class="text-right">${formatCurrency(cogs)}</td><td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td><td class="text-right">${margin}${margin !== '—' ? '%' : ''}</td></tr>`;
                });
                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : '0.00';
                html += `<tr class="total-row"><td colspan="2"><strong>TOTAL</strong></td><td class="text-right"></td><td class="text-right">${formatCurrency(totalRevenue)}</td><td class="text-right">${formatCurrency(totalCOGS)}</td><td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td><td class="text-right">${totalMargin}%</td></tr></tbody></table>`;

            } else if (reportType === 'partnerwise') {
                const partners = reportData.partners || [];
                const totalProfit = parseFloat(reportData.totalProfit) || 0;

                html = `<div class="partner-note">Distribution is calculated on the Net Profit/Loss of the selected period (Revenue &minus; COGS &minus; Expenses).</div>
                    <table><thead><tr><th>Partner (Capital Account)</th><th class="text-right">Share %</th><th class="text-right">Profit Share</th></tr></thead><tbody>`;

                let totalPercentage = 0, totalDistributed = 0;
                partners.forEach(partner => {
                    const percentage = parseFloat(partner.percentage) || 0;
                    const share = totalProfit * (percentage / 100);
                    totalPercentage += percentage; totalDistributed += share;
                    html += `<tr><td>${esc(partner.account_name)}</td><td class="text-right">${percentage.toFixed(2)}%</td><td class="text-right ${share >= 0 ? 'positive' : 'negative'}">${formatCurrency(share)}</td></tr>`;
                });

                const ownerPercentage = 100 - totalPercentage;
                const ownerShare = totalProfit - totalDistributed;

                html += `<tr class="total-row"><td><strong>Remaining / Owner's Share</strong></td><td class="text-right">${ownerPercentage.toFixed(2)}%</td><td class="text-right ${ownerShare >= 0 ? 'positive' : 'negative'}">${formatCurrency(ownerShare)}</td></tr>
                    <tr class="total-row"><td><strong>TOTAL PROFIT</strong></td><td class="text-right">100.00%</td><td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td></tr></tbody></table>`;

            } else if (reportType === 'invoicewise') {
                html = `<table><thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th><th class="text-right">Margin %</th></tr></thead><tbody>`;
                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;
                reportData.forEach(invoice => {
                    const revenue = parseFloat(invoice.revenue);
                    const cogs = parseFloat(invoice.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;
                    totalRevenue += revenue; totalCOGS += cogs; totalProfit += profit;
                    html += `<tr><td>${esc(invoice.bill_no)}</td><td>${fmtDateShort(invoice.sale_date)}</td><td>${esc(invoice.customer_name)}</td><td class="text-right">${formatCurrency(revenue)}</td><td class="text-right">${formatCurrency(cogs)}</td><td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td><td class="text-right">${margin}%</td></tr>`;
                });
                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `<tr class="total-row"><td colspan="3"><strong>TOTAL</strong></td><td class="text-right">${formatCurrency(totalRevenue)}</td><td class="text-right">${formatCurrency(totalCOGS)}</td><td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td><td class="text-right">${totalMargin}%</td></tr></tbody></table>`;
            }

            container.innerHTML = html;
        }

        function downloadAsPdf(closeAfter) {
            const element = document.getElementById('printSheet');
            const filename = `profit-loss-detail_${new Date().toISOString().slice(0, 10)}.pdf`;
            html2pdf().set({
                margin: 0.3,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            }).from(element).save().then(() => {
                if (closeAfter) window.close();
            });
        }

        (function render() {
            let payload = null;
            try {
                const raw = sessionStorage.getItem('plDetailPrintData');
                if (raw) payload = JSON.parse(raw);
            } catch (e) {
                console.error('Could not read report data:', e);
            }

            if (!payload) {
                document.getElementById('noDataState').style.display = 'block';
                return;
            }

            document.getElementById('printSheet').style.display = 'block';
            currencySymbol = payload.currencySymbol || '';

            document.getElementById('reportTitle').textContent = REPORT_TITLES[payload.reportType] || 'Profit & Loss Detail Report';
            document.getElementById('reportPeriod').textContent = `Period: ${fmtDateShort(payload.startDate)} - ${fmtDateShort(payload.endDate)}`;

            renderReport(payload.reportType, payload.data);

            if (new URLSearchParams(window.location.search).get('pdf') === '1') {
                setTimeout(() => downloadAsPdf(true), 500);
            }
        })();
    </script>
</body>
</html>
