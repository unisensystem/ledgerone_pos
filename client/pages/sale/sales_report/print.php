<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'all';
$company_id = $_GET['company_id'] ?? null;
$search = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
        .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .print-header h1 { font-size: 24px; margin-bottom: 5px; }
        .print-header .subtitle { font-size: 14px; color: #666; }
        .report-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; }
        .summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .summary-card { border: 1px solid #ddd; padding: 15px; text-align: center; }
        .summary-card .label { font-size: 11px; color: #666; margin-bottom: 5px; }
        .summary-card .value { font-size: 20px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px; }
        table caption { font-size: 14px; font-weight: bold; text-align: left; margin-bottom: 10px; padding: 8px; background: #f5f5f5; }
        th { background: #333; color: white; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .sub-table-wrap { padding: 10px 8px 16px 28px; background: #f7f9fc; }
        .sub-table-title { font-weight: 600; font-size: 11px; margin-bottom: 6px; color: #555; }
        .sub-table { width: 100%; font-size: 10px; border: 1px solid #ddd; margin-bottom: 0; }
        .sub-table th { background: #5b6472; padding: 5px 8px; }
        .sub-table td { padding: 5px 8px; }
        .sub-table-empty { font-size: 11px; color: #888; padding: 4px 0; }
        .footer { text-align: center; font-size: 10px; color: #666; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
        .print-btn { padding: 10px 20px; background: #1f7bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        .print-btn:hover { background: #1a6cdc; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Report</button>
    
    <div class="print-header">
        <h1>LedgerOne ERP - Sales Report</h1>
        <div class="subtitle">Comprehensive Sales Analysis</div>
    </div>
    
    <div class="report-info">
        <div><strong>Period:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></div>
        <div><strong>Report Type:</strong> <?php echo ucfirst($report_type); ?></div>
        <?php if ($company_id): 
            $company_stmt = $pdo->prepare("SELECT company_name FROM companies WHERE id = ? AND tenant_id = ?");
            $company_stmt->execute([$company_id, $_SESSION['tenant_id']]);
            $company_data = $company_stmt->fetch();
        ?>
        <div><strong>Company:</strong> <?php echo $company_data['company_name'] ?? 'All Companies'; ?></div>
        <?php endif; ?>
        <div><strong>Generated:</strong> <?php echo date('M d, Y h:i A'); ?></div>
    </div>
    
    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">Total Sales</div>
            <div class="value" id="total-sales"><?php echo $currency_symbol; ?>0</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Customers</div>
            <div class="value" id="total-customers">0</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Transactions</div>
            <div class="value" id="total-transactions">0</div>
        </div>
        <div class="summary-card">
            <div class="label">Average Sale</div>
            <div class="value" id="avg-sale"><?php echo $currency_symbol; ?>0</div>
        </div>
    </div>
    
    <div id="report-tables">
        <div class="sub-table-empty" id="loading-msg">Loading report data & details…</div>
    </div>
    
    <div class="footer">
        <p>LedgerOne ERP Sales Report • Confidential • Page generated on <?php echo date('F d, Y'); ?></p>
    </div>
    
    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
        const dateFrom = '<?php echo $date_from; ?>';
        const dateTo = '<?php echo $date_to; ?>';
        const reportType = '<?php echo $report_type; ?>';
        const companyId = '<?php echo $company_id; ?>';
        const searchTerm = '<?php echo htmlspecialchars($search, ENT_QUOTES); ?>'.toLowerCase();
        const API_BASE = '../../../../server/api/sale/sales_report';

        // Same field combos the main report page's search boxes match against, so
        // Print shows exactly what was searched instead of the full dataset.
        function matchesSearch(fields) {
            if (!searchTerm) return true;
            return fields.some(f => String(f || '').toLowerCase().includes(searchTerm));
        }

        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        function formatBatchCell(batchValue) {
            return batchValue && batchValue.length ? esc(batchValue) : '-';
        }

        function formatExpiryCell(expiryValue) {
            if (!expiryValue) return '-';
            const firstDate = expiryValue.split(',')[0].trim();
            const isExpiringSoon = firstDate && !isNaN(new Date(firstDate)) && (new Date(firstDate) - new Date()) / (1000 * 60 * 60 * 24) <= 30;
            const safe = esc(expiryValue);
            return isExpiringSoon
                ? `<span style="color:#d64545; font-weight:600;">${safe}</span>`
                : safe;
        }

        // ---- Detail / breakdown fetch helpers (mirror the dashboard's click handlers,
        // but run automatically for every row since print has no click interaction) ----

        async function fetchBreakdown(type, id) {
            try {
                const res = await fetch(`${API_BASE}/breakdown.php?type=${type}&id=${encodeURIComponent(id)}&date_from=${dateFrom}&date_to=${dateTo}`);
                const result = await res.json();
                return result.success ? (result.data || []) : [];
            } catch (e) {
                console.error('breakdown fetch error', e);
                return [];
            }
        }

        async function fetchDetails(type, id, extraParams = '') {
            try {
                const res = await fetch(`${API_BASE}/details.php?type=${type}&id=${encodeURIComponent(id)}&date_from=${dateFrom}&date_to=${dateTo}${extraParams}`);
                const result = await res.json();
                return result.success ? (result.data || []) : [];
            } catch (e) {
                console.error('details fetch error', e);
                return [];
            }
        }

        function subTableRow(colspan, title, headerCells, bodyRowsHtml) {
            const body = bodyRowsHtml && bodyRowsHtml.length
                ? bodyRowsHtml
                : `<tr><td colspan="${headerCells.length}" class="sub-table-empty">No data available</td></tr>`;
            return `<tr><td colspan="${colspan}" style="padding:0; border:none;">
                <div class="sub-table-wrap">
                    <div class="sub-table-title">${title}</div>
                    <table class="sub-table">
                        <thead><tr>${headerCells.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                        <tbody>${body}</tbody>
                    </table>
                </div>
            </td></tr>`;
        }

        // Builds: main row + (always-visible) detail sub-table row, for a list of items.
        // matchFn(item) -> array of fields to search against.
        // fetchFn(item) -> Promise<Array> of detail rows.
        // detailRowFn(detailItem) -> html string for one <tr> of the sub-table.
        async function buildExpandableSection(items, colspan, matchFn, mainRowFn, subTitle, subHeaders, fetchFn, detailRowFn) {
            const visibleItems = items.filter(item => matchesSearch(matchFn(item)));
            if (!visibleItems.length) return '';

            const rendered = await Promise.all(visibleItems.map(async item => {
                const mainRow = mainRowFn(item);
                const details = await fetchFn(item);
                const bodyHtml = details.map(detailRowFn).join('');
                const subRow = subTableRow(colspan, subTitle, subHeaders, bodyHtml);
                return mainRow + subRow;
            }));

            return rendered.join('');
        }

        async function loadReportData() {
            try {
                let url = `${API_BASE}/sales-report.php?date_from=${dateFrom}&date_to=${dateTo}&report_type=${reportType}`;
                if (companyId) url += `&company_id=${companyId}`;
                
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success) {
                    await populateReport(result.data);
                } else {
                    document.getElementById('report-tables').innerHTML = '<div class="sub-table-empty">Failed to load report.</div>';
                }
            } catch (error) {
                console.error('Error loading report:', error);
                document.getElementById('report-tables').innerHTML = '<div class="sub-table-empty">Error loading report.</div>';
            }
        }
        
        async function populateReport(data) {
            // Summary
            const totalSales = (data.officer_sales || []).reduce((sum, item) => sum + parseFloat(item.total_sales || 0), 0);
            const totalCustomers = (data.customer_sales || []).length;
            const totalTransactions = (data.branch_sales || []).reduce((sum, item) => sum + parseFloat(item.transactions || 0), 0);
            const avgSale = totalTransactions > 0 ? totalSales / totalTransactions : 0;
            
            document.getElementById('total-sales').textContent = CURRENCY_SYMBOL + totalSales.toLocaleString();
            document.getElementById('total-customers').textContent = totalCustomers.toLocaleString();
            document.getElementById('total-transactions').textContent = totalTransactions.toLocaleString();
            document.getElementById('avg-sale').textContent = CURRENCY_SYMBOL + avgSale.toLocaleString(undefined, {maximumFractionDigits: 2});
            
            const container = document.getElementById('report-tables');
            const sectionHtmlParts = [];

            // ---- Officer Sales (+ daily breakdown) ----
            if (reportType === 'all' || reportType === 'officer') {
                const rows = await buildExpandableSection(
                    data.officer_sales || [],
                    5,
                    item => [item.employee_id, item.officer_name],
                    item => `<tr><td>${esc(item.employee_id)}</td><td>${esc(item.officer_name)}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td><td>${esc(item.status)}</td></tr>`,
                    'Daily Breakdown',
                    ['Date', 'Invoices', 'Revenue', 'Batch No', 'Expiry Date'],
                    item => fetchBreakdown('officer', item.sale_officer_id),
                    d => `<tr><td>${esc(d.date)}</td><td>${parseFloat(d.invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.revenue || 0).toLocaleString()}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.nearest_expiry)}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Sales Officer Wise Sales</caption><thead><tr><th>Employee ID</th><th>Officer Name</th><th>Total Invoices</th><th>Total Sales</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Supplier Man Sales (+ daily breakdown) ----
            if (reportType === 'all' || reportType === 'supplier_man') {
                const rows = await buildExpandableSection(
                    data.supplier_man_sales || [],
                    5,
                    item => [item.employee_id, item.supplier_man_name],
                    item => `<tr><td>${esc(item.employee_id)}</td><td>${esc(item.supplier_man_name)}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td><td>${esc(item.status)}</td></tr>`,
                    'Daily Breakdown',
                    ['Date', 'Invoices', 'Revenue', 'Batch No', 'Expiry Date'],
                    item => fetchBreakdown('supplier_man', item.supplier_man_id),
                    d => `<tr><td>${esc(d.date)}</td><td>${parseFloat(d.invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.revenue || 0).toLocaleString()}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.nearest_expiry)}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Supplier Man Wise Sales</caption><thead><tr><th>Employee ID</th><th>Supplier Man Name</th><th>Total Invoices</th><th>Total Sales</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Product Sales (+ invoices including this product) ----
            if (reportType === 'all' || reportType === 'product') {
                const rows = await buildExpandableSection(
                    data.product_sales || [],
                    7,
                    item => [item.product_id_display, item.product_name, item.category],
                    item => {
                        const isChild = item.parent_row_id || item.parent_product_id;
                        const indent = isChild ? 'padding-left: 20px;' : '';
                        const prefix = isChild ? '→ ' : '';
                        const batchNo = item.batch_no || '-';
                        const expiryDate = item.nearest_expiry || '-';
                        return `<tr><td>${esc(item.product_id_display)}</td><td style="${indent}">${prefix}${esc(item.product_name)}</td><td>${esc(item.category)}</td><td>${esc(batchNo)}</td><td>${esc(expiryDate)}</td><td>${parseFloat(item.quantity_sold || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.revenue || 0).toLocaleString()}</td></tr>`;
                    },
                    'Invoices Including This Product',
                    ['Invoice #', 'Date', 'Customer', 'Quantity', 'Price', 'Batch No', 'Expiry Date', 'Amount'],
                    item => fetchDetails('product_invoices', item.product_id),
                    d => `<tr><td>${esc(d.bill_no)}</td><td>${esc(d.sale_date)}</td><td>${esc(d.customer_name)}</td><td>${parseFloat(d.quantity || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.sale_price || 0).toLocaleString()}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.expiry_date)}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.net_amount || 0).toLocaleString()}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Product Wise Sales</caption><thead><tr><th>Product ID</th><th>Product Name</th><th>Category</th><th>Batch No</th><th>Expiry Date</th><th>Quantity Sold</th><th>Revenue</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Branch Sales (+ daily breakdown) ----
            if (reportType === 'all' || reportType === 'branch') {
                const rows = await buildExpandableSection(
                    data.branch_sales || [],
                    5,
                    item => [item.branch_id_display, item.branch_name],
                    item => `<tr><td>${esc(item.branch_id_display)}</td><td>${esc(item.branch_name)}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td><td>${parseFloat(item.transactions || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.avg_sale || 0).toLocaleString()}</td></tr>`,
                    'Daily Breakdown',
                    ['Date', 'Quantity', 'Unit', 'Revenue', 'Batch No', 'Expiry Date'],
                    item => fetchBreakdown('branch', item.branch_id),
                    d => `<tr><td>${esc(d.date)}</td><td>${parseFloat(d.volume || 0).toLocaleString()}</td><td>${esc(d.unit || '-')}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.revenue || 0).toLocaleString()}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.nearest_expiry)}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Branch Wise Sales</caption><thead><tr><th>Branch ID</th><th>Branch Name</th><th>Total Sales</th><th>Transactions</th><th>Avg Sale</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Category Sales (no expand on the dashboard, kept flat) ----
            if (reportType === 'all' || reportType === 'category') {
                const total = (data.categories || []).reduce((sum, item) => sum + parseFloat(item.total || 0), 0);
                let html = '<table><caption>Category Wise Sales</caption><thead><tr><th>Category</th><th>Total Revenue</th><th>Percentage</th></tr></thead><tbody>';
                (data.categories || []).forEach(item => {
                    if (!matchesSearch([item.category])) return;
                    const percentage = total > 0 ? ((parseFloat(item.total || 0) / total) * 100).toFixed(1) : 0;
                    html += `<tr><td>${esc(item.category)}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total || 0).toLocaleString()}</td><td>${percentage}%</td></tr>`;
                });
                html += '</tbody></table>';
                sectionHtmlParts.push(html);
            }
            
            // ---- Territory Sales (+ territory invoices) ----
            if (reportType === 'all' || reportType === 'territory') {
                const rows = await buildExpandableSection(
                    data.territory_sales || [],
                    7,
                    item => [item.country, item.region, item.city, item.zone, item.area],
                    item => `<tr><td>${esc(item.country)}</td><td>${esc(item.region)}</td><td>${esc(item.city)}</td><td>${esc(item.zone)}</td><td>${esc(item.area)}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td></tr>`,
                    'Territory Invoices',
                    ['Invoice #', 'Date', 'Customer', 'Net Amount', 'Batch No', 'Expiry Date'],
                    item => fetchDetails('territory_invoices', 0, `&country=${encodeURIComponent(item.country)}&region=${encodeURIComponent(item.region)}&city=${encodeURIComponent(item.city)}&zone=${encodeURIComponent(item.zone)}&area=${encodeURIComponent(item.area)}`),
                    d => `<tr><td>${esc(d.bill_no)}</td><td>${esc(d.sale_date)}</td><td>${esc(d.customer_name)}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.net_amount || 0).toLocaleString()}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.nearest_expiry)}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Territory Wise Sales</caption><thead><tr><th>Country</th><th>Region</th><th>City</th><th>Zone</th><th>Area</th><th>Total Invoices</th><th>Total Sales</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Invoice Sales (+ invoice items) ----
            if (reportType === 'all' || reportType === 'invoice') {
                const rows = await buildExpandableSection(
                    data.invoice_sales || [],
                    7,
                    item => [item.bill_no, item.customer_name, item.officer_name],
                    item => `<tr><td>${esc(item.bill_no)}</td><td>${esc(item.sale_date)}</td><td>${esc(item.customer_name)}</td><td>${esc(item.officer_name)}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_bill || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_discount_amount || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.net_amount || 0).toLocaleString()}</td></tr>`,
                    'Invoice Items',
                    ['Product', 'Batch No', 'Expiry Date', 'Quantity', 'Price', 'Discount', 'Net Amount'],
                    item => fetchDetails('invoice_items', item.id),
                    d => `<tr><td>${esc(d.product_name)}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.expiry_date)}</td><td>${parseFloat(d.quantity || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.sale_price || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.discount_amount || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.net_amount || 0).toLocaleString()}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Invoice Wise Sales</caption><thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Sales Officer</th><th>Total Bill</th><th>Discount</th><th>Net Amount</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Vendor Sales (+ products sold) ----
            if (reportType === 'all' || reportType === 'vendor') {
                const rows = await buildExpandableSection(
                    data.vendor_sales || [],
                    5,
                    item => [item.vendor_id_display, item.vendor_name],
                    item => `<tr><td>${esc(item.vendor_id_display)}</td><td>${esc(item.vendor_name)}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${parseFloat(item.total_quantity || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td></tr>`,
                    'Products Sold',
                    ['Product', 'Batch No', 'Expiry Date', 'Quantity', 'Total Sales'],
                    item => fetchDetails('vendor_products', item.vendor_id),
                    d => `<tr><td>${esc(d.product_name)}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.nearest_expiry)}</td><td>${parseFloat(d.total_quantity || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.total_sales || 0).toLocaleString()}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Vendor Wise Sales</caption><thead><tr><th>Vendor ID</th><th>Vendor Name</th><th>Total Invoices</th><th>Total Quantity</th><th>Total Sales</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            // ---- Customer Sales (+ customer invoices) ----
            if (reportType === 'all' || reportType === 'customer') {
                const rows = await buildExpandableSection(
                    data.customer_sales || [],
                    4,
                    item => [item.customer_id_display, item.customer_name],
                    item => `<tr><td>${esc(item.customer_id_display)}</td><td>${esc(item.customer_name)}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td></tr>`,
                    'Customer Invoices',
                    ['Invoice #', 'Date', 'Total Bill', 'Discount', 'Net Amount', 'Batch No', 'Expiry Date'],
                    item => fetchDetails('customer_invoices', item.customer_id),
                    d => `<tr><td>${esc(d.bill_no)}</td><td>${esc(d.sale_date)}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.total_bill || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.total_discount_amount || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(d.net_amount || 0).toLocaleString()}</td><td>${formatBatchCell(d.batch_no)}</td><td>${formatExpiryCell(d.nearest_expiry)}</td></tr>`
                );
                sectionHtmlParts.push(`<table><caption>Customer Wise Sales</caption><thead><tr><th>Customer ID</th><th>Customer Name</th><th>Total Invoices</th><th>Total Sales</th></tr></thead><tbody>${rows}</tbody></table>`);
            }
            
            container.innerHTML = sectionHtmlParts.join('');
        }
        
        loadReportData();
    </script>
</body>
</html>