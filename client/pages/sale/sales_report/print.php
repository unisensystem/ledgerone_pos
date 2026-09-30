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
        .report-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; flex-wrap: wrap; gap: 15px; }
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
        .expiry-soon { color: #d64545; font-weight: 600; }
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
        <div><strong>Report Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $report_type)); ?></div>
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

        // Search matching: filter rows based on search term from main page
        function matchesSearch(fields) {
            if (!searchTerm) return true;
            return fields.some(f => String(f || '').toLowerCase().includes(searchTerm));
        }


        const ROUND_OFF_ENABLED = new URLSearchParams(window.location.search).get('round_off') === '1';

function fmtMoney(value) {
    const amount = parseFloat(value) || 0;
    return CURRENCY_SYMBOL + (ROUND_OFF_ENABLED
        ? Math.round(amount).toLocaleString()
        : amount.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 }));
}

function fmtQty(value) {
    const amount = parseFloat(value) || 0;
    return ROUND_OFF_ENABLED
        ? Math.round(amount).toLocaleString()
        : amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}
        // HTML entity escape
        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        // Format batch number cell
        function formatBatchCell(batchValue) {
            return batchValue && batchValue.length ? esc(batchValue) : '-';
        }

        // Format expiry date cell (highlight if expiring soon)
        function formatExpiryCell(expiryValue) {
            if (!expiryValue) return '-';
            const firstDate = expiryValue.split(',')[0].trim();
            const isExpiringSoon = firstDate && !isNaN(new Date(firstDate)) && (new Date(firstDate) - new Date()) / (1000 * 60 * 60 * 24) <= 30;
            const safe = esc(expiryValue);
            return isExpiringSoon
                ? `<span class="expiry-soon">${safe}</span>`
                : safe;
        }

        // Format item company cell
        function formatItemCompanyCell(itemCompanyValue) {
            return itemCompanyValue && itemCompanyValue.length ? esc(itemCompanyValue) : '-';
        }

        // ---- Column visibility (same localStorage the main report page's "Columns"
        // modal writes to - same origin, so this print tab reads the same choices) ----
        const COLUMN_STORAGE_PREFIX = 'salesReport.columns.';
        function getColumnVisibility(prefix, cols) {
            let state = {};
            try {
                const raw = localStorage.getItem(COLUMN_STORAGE_PREFIX + prefix);
                state = raw ? JSON.parse(raw) : {};
            } catch (e) {}
            // "locked" columns (e.g. Quantity, which always needs its unit visible)
            // have no toggle in the Columns modal and are never hidden.
            return cols.map(c => c.locked ? true : (state[c.key] !== false));
        }
        function filterHeader(cols, vis) {
            return cols.filter((_, i) => vis[i]).map(c => `<th>${c.label}</th>`).join('');
        }
        // cells: array of already-built inner HTML strings, same order/length as cols
        function filterRow(cells, vis) {
            return cells.filter((_, i) => vis[i]).map(c => `<td>${c}</td>`).join('');
        }

        // Fetch breakdown data (daily breakdown for officer, branch, etc.)
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

        // Fetch details data (invoices, items, products, etc.)
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

        // Build a sub-table row (always visible on print)
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

        // Build expandable section: main table rows + sub-table rows
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
            // Update Summary Cards
            const totalSales = (data.officer_sales || []).reduce((sum, item) => sum + parseFloat(item.total_sales || 0), 0);
            const totalCustomers = (data.customer_sales || []).length;
            const totalTransactions = (data.branch_sales || []).reduce((sum, item) => sum + parseFloat(item.transactions || 0), 0);
            const avgSale = totalTransactions > 0 ? totalSales / totalTransactions : 0;
            
        document.getElementById('total-sales').textContent = fmtMoney(totalSales);
document.getElementById('total-customers').textContent = fmtQty(totalCustomers);
document.getElementById('total-transactions').textContent = fmtQty(totalTransactions);
document.getElementById('avg-sale').textContent = fmtMoney(avgSale);
            
            const container = document.getElementById('report-tables');
            const sectionHtmlParts = [];

            // ========== OFFICER SALES ==========
            if (reportType === 'all' || reportType === 'officer') {
                const officerCols = [
                    {key:'employee_id', label:'Employee ID'}, {key:'officer_name', label:'Officer Name'},
                    {key:'total_invoices', label:'Total Invoices'}, {key:'total_sales', label:'Total Sales'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'},
                    {key:'item_company', label:'Item Company'}, {key:'status', label:'Status'}
                ];
                const officerVis = getColumnVisibility('officer', officerCols);
                const officerColspan = officerVis.filter(Boolean).length;
                const officerSubCols = [
                    {key:'bd_date', label:'Date'}, {key:'bd_invoices', label:'Invoices'}, {key:'bd_revenue', label:'Revenue'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const officerSubVis = getColumnVisibility('officer', officerSubCols);
                const rows = await buildExpandableSection(
                    data.officer_sales || [],
                    officerColspan,
                    item => [item.employee_id, item.officer_name],
                    item => `<tr>${filterRow([
                        esc(item.employee_id), esc(item.officer_name),
                        parseFloat(item.total_invoices || 0).toLocaleString(), CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry),
                        formatItemCompanyCell(item.item_company_name), esc(item.status)
                    ], officerVis)}</tr>`,
                    'Daily Breakdown',
                    officerSubCols.filter((_, i) => officerSubVis[i]).map(c => c.label),
                    item => fetchBreakdown('officer', item.sale_officer_id),
                    d => `<tr>${filterRow([
                        esc(d.date), parseFloat(d.invoices || 0).toLocaleString(), CURRENCY_SYMBOL + parseFloat(d.revenue || 0).toLocaleString(),
                        formatBatchCell(d.batch_no), formatExpiryCell(d.nearest_expiry), formatItemCompanyCell(d.item_company_name)
                    ], officerSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Sales Officer Wise Sales</caption>
                    <thead><tr>${filterHeader(officerCols, officerVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== SUPPLIER MAN SALES ==========
            if (reportType === 'all' || reportType === 'supplier_man') {
                const supManCols = [
                    {key:'employee_id', label:'Employee ID'}, {key:'supplier_man_name', label:'Supplier Man Name'},
                    {key:'total_invoices', label:'Total Invoices'}, {key:'total_sales', label:'Total Sales'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'},
                    {key:'item_company', label:'Item Company'}, {key:'status', label:'Status'}
                ];
                const supManVis = getColumnVisibility('supplier_man', supManCols);
                const supManColspan = supManVis.filter(Boolean).length;
                const supManSubCols = [
                    {key:'bd_date', label:'Date'}, {key:'bd_invoices', label:'Invoices'}, {key:'bd_revenue', label:'Revenue'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const supManSubVis = getColumnVisibility('supplier_man', supManSubCols);
                const rows = await buildExpandableSection(
                    data.supplier_man_sales || [],
                    supManColspan,
                    item => [item.employee_id, item.supplier_man_name],
                    item => `<tr>${filterRow([
                        esc(item.employee_id), esc(item.supplier_man_name),
                        parseFloat(item.total_invoices || 0).toLocaleString(), CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry),
                        formatItemCompanyCell(item.item_company_name), esc(item.status)
                    ], supManVis)}</tr>`,
                    'Daily Breakdown',
                    supManSubCols.filter((_, i) => supManSubVis[i]).map(c => c.label),
                    item => fetchBreakdown('supplier_man', item.supplier_man_id),
                    d => `<tr>${filterRow([
                        esc(d.date), parseFloat(d.invoices || 0).toLocaleString(), CURRENCY_SYMBOL + parseFloat(d.revenue || 0).toLocaleString(),
                        formatBatchCell(d.batch_no), formatExpiryCell(d.nearest_expiry), formatItemCompanyCell(d.item_company_name)
                    ], supManSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Supplier Man Wise Sales</caption>
                    <thead><tr>${filterHeader(supManCols, supManVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== PRODUCT SALES ==========
            if (reportType === 'all' || reportType === 'product') {
                const productCols = [
                    {key:'product_id', label:'Product ID'}, {key:'product_name', label:'Product Name'},
                    {key:'category', label:'Category'}, {key:'batch_no', label:'Batch No'},
                    {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'},
                    {key:'quantity_sold', label:'Quantity Sold'}, {key:'revenue', label:'Revenue'}, {key:'growth', label:'Growth'}
                ];
                const productVis = getColumnVisibility('product', productCols);
                const productColspan = productVis.filter(Boolean).length;
                const productSubCols = [
                    {key:'pi_bill_no', label:'Invoice #'}, {key:'pi_date', label:'Date'}, {key:'pi_customer', label:'Customer'},
                    {label:'Quantity', locked:true}, {key:'pi_price', label:'Price'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'},
                    {key:'pi_amount', label:'Amount'}
                ];
                const productSubVis = getColumnVisibility('product', productSubCols);
                const rows = await buildExpandableSection(
                    data.product_sales || [],
                    productColspan,
                    item => [item.product_id_display, item.product_name, item.category],
                    item => {
                        const isChild = item.parent_row_id || item.parent_product_id;
                        const indent = isChild ? 'padding-left: 20px;' : '';
                        const prefix = isChild ? '→ ' : '';
                        const growth = item.growth !== null && item.growth !== undefined ? `${item.growth >= 0 ? '+' : ''}${item.growth}%` : '-';
                        return `<tr>${filterRow([
                            esc(item.product_id_display),
                            `<span style="${indent}">${prefix}${esc(item.product_name)}</span>`,
                            esc(item.category), formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry),
                            formatItemCompanyCell(item.item_company_name),
                            parseFloat(item.quantity_sold || 0).toLocaleString() + (item.unit_symbol ? ' ' + esc(item.unit_symbol) : ''),
                            CURRENCY_SYMBOL + parseFloat(item.revenue || 0).toLocaleString(),
                            growth
                        ], productVis)}</tr>`;
                    },
                    'Invoices Including This Product',
                    productSubCols.filter((_, i) => productSubVis[i]).map(c => c.label),
                    item => fetchDetails('product_invoices', item.product_id),
                    d => `<tr>${filterRow([
                        esc(d.bill_no), esc(d.sale_date), esc(d.customer_name),
                        parseFloat(d.quantity || 0).toLocaleString() + (d.unit_symbol ? ' ' + esc(d.unit_symbol) : ''),
                        CURRENCY_SYMBOL + parseFloat(d.sale_price || 0).toLocaleString(),
                        formatBatchCell(d.batch_no), formatExpiryCell(d.expiry_date), formatItemCompanyCell(d.item_company_name),
                        CURRENCY_SYMBOL + parseFloat(d.net_amount || 0).toLocaleString()
                    ], productSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Product Wise Sales</caption>
                    <thead><tr>${filterHeader(productCols, productVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== BRANCH SALES ==========
            if (reportType === 'all' || reportType === 'branch') {
                const branchCols = [
                    {key:'branch_id', label:'Branch ID'}, {key:'branch_name', label:'Branch Name'},
                    {key:'total_sales', label:'Total Sales'}, {key:'transactions', label:'Transactions'},
                    {key:'avg_sale', label:'Avg Sale'}, {key:'batch_no', label:'Batch No'},
                    {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const branchVis = getColumnVisibility('branch', branchCols);
                const branchColspan = branchVis.filter(Boolean).length;
                const branchSubCols = [
                    {key:'bd_date', label:'Date'}, {label:'Quantity', locked:true}, {label:'Unit', locked:true}, {key:'bd_revenue', label:'Revenue'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const branchSubVis = getColumnVisibility('branch', branchSubCols);
                const rows = await buildExpandableSection(
                    data.branch_sales || [],
                    branchColspan,
                    item => [item.branch_id_display, item.branch_name],
                    item => `<tr>${filterRow([
                        esc(item.branch_id_display), esc(item.branch_name),
                        CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        parseFloat(item.transactions || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(item.avg_sale || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry),
                        formatItemCompanyCell(item.item_company_name)
                    ], branchVis)}</tr>`,
                    'Daily Breakdown',
                    branchSubCols.filter((_, i) => branchSubVis[i]).map(c => c.label),
                    item => fetchBreakdown('branch', item.branch_id),
                    d => `<tr>${filterRow([
                        esc(d.date), parseFloat(d.volume || 0).toLocaleString(), esc(d.unit || '-'),
                        CURRENCY_SYMBOL + parseFloat(d.revenue || 0).toLocaleString(),
                        formatBatchCell(d.batch_no), formatExpiryCell(d.nearest_expiry), formatItemCompanyCell(d.item_company_name)
                    ], branchSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Branch Wise Sales</caption>
                    <thead><tr>${filterHeader(branchCols, branchVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== CATEGORY SALES ==========
            if (reportType === 'all' || reportType === 'category') {
                const categoryCols = [
                    {key:'category', label:'Category'}, {key:'total_revenue', label:'Total Revenue'},
                    {key:'percentage', label:'Percentage'}, {key:'batch_no', label:'Batch No'},
                    {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const categoryVis = getColumnVisibility('category', categoryCols);
                const total = (data.categories || []).reduce((sum, item) => sum + parseFloat(item.total || 0), 0);
                let html = `<table><caption>Category Wise Sales</caption><thead><tr>${filterHeader(categoryCols, categoryVis)}</tr></thead><tbody>`;
                (data.categories || []).forEach(item => {
                    if (!matchesSearch([item.category])) return;
                    const percentage = total > 0 ? ((parseFloat(item.total || 0) / total) * 100).toFixed(1) : 0;
                    html += `<tr>${filterRow([
                        esc(item.category), CURRENCY_SYMBOL + parseFloat(item.total || 0).toLocaleString(), percentage + '%',
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry), formatItemCompanyCell(item.item_company_name)
                    ], categoryVis)}</tr>`;
                });
                html += '</tbody></table>';
                sectionHtmlParts.push(html);
            }
            
            // ========== TERRITORY SALES ==========
            if (reportType === 'all' || reportType === 'territory') {
                const territoryCols = [
                    {key:'country', label:'Country'}, {key:'region', label:'Region'}, {key:'city', label:'City'},
                    {key:'zone', label:'Zone'}, {key:'area', label:'Area'}, {key:'total_invoices', label:'Total Invoices'},
                    {key:'total_sales', label:'Total Sales'}, {key:'batch_no', label:'Batch No'},
                    {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const territoryVis = getColumnVisibility('territory', territoryCols);
                const territoryColspan = territoryVis.filter(Boolean).length;
                const territorySubCols = [
                    {key:'ti_bill_no', label:'Invoice #'}, {key:'ti_date', label:'Date'}, {key:'ti_customer', label:'Customer'}, {key:'ti_net_amount', label:'Net Amount'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const territorySubVis = getColumnVisibility('territory', territorySubCols);
                const rows = await buildExpandableSection(
                    data.territory_sales || [],
                    territoryColspan,
                    item => [item.country, item.region, item.city, item.zone, item.area],
                    item => `<tr>${filterRow([
                        esc(item.country), esc(item.region), esc(item.city), esc(item.zone), esc(item.area),
                        parseFloat(item.total_invoices || 0).toLocaleString(), CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry), formatItemCompanyCell(item.item_company_name)
                    ], territoryVis)}</tr>`,
                    'Territory Invoices',
                    territorySubCols.filter((_, i) => territorySubVis[i]).map(c => c.label),
                    item => fetchDetails('territory_invoices', 0, `&country=${encodeURIComponent(item.country)}&region=${encodeURIComponent(item.region)}&city=${encodeURIComponent(item.city)}&zone=${encodeURIComponent(item.zone)}&area=${encodeURIComponent(item.area)}`),
                    d => `<tr>${filterRow([
                        esc(d.bill_no), esc(d.sale_date), esc(d.customer_name),
                        CURRENCY_SYMBOL + parseFloat(d.net_amount || 0).toLocaleString(),
                        formatBatchCell(d.batch_no), formatExpiryCell(d.nearest_expiry), formatItemCompanyCell(d.item_company_name)
                    ], territorySubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Territory Wise Sales</caption>
                    <thead><tr>${filterHeader(territoryCols, territoryVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== INVOICE SALES ==========
            if (reportType === 'all' || reportType === 'invoice') {
                const invoiceCols = [
                    {key:'bill_no', label:'Invoice #'}, {key:'sale_date', label:'Date'}, {key:'customer_name', label:'Customer'},
                    {key:'officer_name', label:'Sales Officer'}, {key:'total_bill', label:'Total Bill'}, {key:'discount', label:'Discount'},
                    {key:'net_amount', label:'Net Amount'}, {key:'batch_no', label:'Batch No'},
                    {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const invoiceVis = getColumnVisibility('invoice', invoiceCols);
                const invoiceColspan = invoiceVis.filter(Boolean).length;
                const invoiceSubCols = [
                    {key:'ii_product', label:'Product'}, {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'},
                    {label:'Quantity', locked:true}, {key:'ii_price', label:'Price'}, {key:'ii_discount', label:'Discount'}, {key:'ii_net_amount', label:'Net Amount'}
                ];
                const invoiceSubVis = getColumnVisibility('invoice', invoiceSubCols);
                const rows = await buildExpandableSection(
                    data.invoice_sales || [],
                    invoiceColspan,
                    item => [item.bill_no, item.customer_name, item.officer_name],
                    item => `<tr>${filterRow([
                        esc(item.bill_no), esc(item.sale_date), esc(item.customer_name), esc(item.officer_name),
                        CURRENCY_SYMBOL + parseFloat(item.total_bill || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(item.total_discount_amount || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(item.net_amount || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry), formatItemCompanyCell(item.item_company_name)
                    ], invoiceVis)}</tr>`,
                    'Invoice Items',
                    invoiceSubCols.filter((_, i) => invoiceSubVis[i]).map(c => c.label),
                    item => fetchDetails('invoice_items', item.id),
                    d => `<tr>${filterRow([
                        esc(d.product_name), formatBatchCell(d.batch_no), formatExpiryCell(d.expiry_date), formatItemCompanyCell(d.item_company_name),
                        parseFloat(d.quantity || 0).toLocaleString() + (d.unit_symbol ? ' ' + esc(d.unit_symbol) : ''),
                        CURRENCY_SYMBOL + parseFloat(d.sale_price || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(d.discount_amount || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(d.net_amount || 0).toLocaleString()
                    ], invoiceSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Invoice Wise Sales</caption>
                    <thead><tr>${filterHeader(invoiceCols, invoiceVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== VENDOR SALES ==========
            if (reportType === 'all' || reportType === 'vendor') {
                const vendorCols = [
                    {key:'vendor_id', label:'Vendor ID'}, {key:'vendor_name', label:'Vendor Name'},
                    {key:'total_invoices', label:'Total Invoices'}, {key:'total_quantity', label:'Total Quantity'},
                    {key:'total_sales', label:'Total Sales'}, {key:'batch_no', label:'Batch No'},
                    {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const vendorVis = getColumnVisibility('vendor', vendorCols);
                const vendorColspan = vendorVis.filter(Boolean).length;
                const vendorSubCols = [
                    {key:'vp_product', label:'Product'}, {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'},
                    {label:'Quantity', locked:true}, {key:'vp_total_sales', label:'Total Sales'}
                ];
                const vendorSubVis = getColumnVisibility('vendor', vendorSubCols);
                const rows = await buildExpandableSection(
                    data.vendor_sales || [],
                    vendorColspan,
                    item => [item.vendor_id_display, item.vendor_name],
                    item => `<tr>${filterRow([
                        esc(item.vendor_id_display), esc(item.vendor_name),
                        parseFloat(item.total_invoices || 0).toLocaleString(),
                        parseFloat(item.total_quantity || 0).toLocaleString() + (item.unit_symbol ? ' ' + esc(item.unit_symbol) : ''),
                        CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry), formatItemCompanyCell(item.item_company_name)
                    ], vendorVis)}</tr>`,
                    'Products Sold',
                    vendorSubCols.filter((_, i) => vendorSubVis[i]).map(c => c.label),
                    item => fetchDetails('vendor_products', item.vendor_id),
                    d => `<tr>${filterRow([
                        esc(d.product_name), formatBatchCell(d.batch_no), formatExpiryCell(d.nearest_expiry), formatItemCompanyCell(d.item_company_name),
                        parseFloat(d.total_quantity || 0).toLocaleString() + (d.unit_symbol ? ' ' + esc(d.unit_symbol) : ''),
                        CURRENCY_SYMBOL + parseFloat(d.total_sales || 0).toLocaleString()
                    ], vendorSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Vendor Wise Sales</caption>
                    <thead><tr>${filterHeader(vendorCols, vendorVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }
            
            // ========== CUSTOMER SALES ==========
            if (reportType === 'all' || reportType === 'customer') {
                const customerCols = [
                    {key:'customer_id', label:'Customer ID'}, {key:'customer_name', label:'Customer Name'},
                    {key:'total_invoices', label:'Total Invoices'}, {key:'total_sales', label:'Total Sales'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const customerVis = getColumnVisibility('customer', customerCols);
                const customerColspan = customerVis.filter(Boolean).length;
                const customerSubCols = [
                    {key:'ci_bill_no', label:'Invoice #'}, {key:'ci_date', label:'Date'}, {key:'ci_total_bill', label:'Total Bill'},
                    {key:'ci_discount', label:'Discount'}, {key:'ci_net_amount', label:'Net Amount'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}, {key:'item_company', label:'Item Company'}
                ];
                const customerSubVis = getColumnVisibility('customer', customerSubCols);
                const rows = await buildExpandableSection(
                    data.customer_sales || [],
                    customerColspan,
                    item => [item.customer_id_display, item.customer_name],
                    item => `<tr>${filterRow([
                        esc(item.customer_id_display), esc(item.customer_name),
                        parseFloat(item.total_invoices || 0).toLocaleString(), CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry), formatItemCompanyCell(item.item_company_name)
                    ], customerVis)}</tr>`,
                    'Customer Invoices',
                    customerSubCols.filter((_, i) => customerSubVis[i]).map(c => c.label),
                    item => fetchDetails('customer_invoices', item.customer_id),
                    d => `<tr>${filterRow([
                        esc(d.bill_no), esc(d.sale_date),
                        CURRENCY_SYMBOL + parseFloat(d.total_bill || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(d.total_discount_amount || 0).toLocaleString(),
                        CURRENCY_SYMBOL + parseFloat(d.net_amount || 0).toLocaleString(),
                        formatBatchCell(d.batch_no), formatExpiryCell(d.nearest_expiry), formatItemCompanyCell(d.item_company_name)
                    ], customerSubVis)}</tr>`
                );
                sectionHtmlParts.push(`<table>
                    <caption>Customer Wise Sales</caption>
                    <thead><tr>${filterHeader(customerCols, customerVis)}</tr></thead>
                    <tbody>${rows}</tbody>
                </table>`);
            }

            // ========== ITEM COMPANY SALES ==========
            if (reportType === 'all' || reportType === 'item_company') {
                const itemCompanyCols = [
                    {key:'item_company', label:'Item Company'}, {key:'total_invoices', label:'Total Invoices'},
                    {key:'total_quantity', label:'Total Quantity'}, {key:'total_sales', label:'Total Sales'},
                    {key:'batch_no', label:'Batch No'}, {key:'expiry_date', label:'Expiry Date'}
                ];
                const itemCompanyVis = getColumnVisibility('item_company', itemCompanyCols);
                let html = `<table><caption>Item Company Wise Sales</caption><thead><tr>${filterHeader(itemCompanyCols, itemCompanyVis)}</tr></thead><tbody>`;
                (data.item_company_sales || []).forEach(item => {
                    if (!matchesSearch([item.item_company_name])) return;
                    html += `<tr>${filterRow([
                        formatItemCompanyCell(item.item_company_name),
                        parseFloat(item.total_invoices || 0).toLocaleString(),
                        parseFloat(item.total_quantity || 0).toLocaleString() + (item.unit_symbol ? ' ' + esc(item.unit_symbol) : ''),
                        CURRENCY_SYMBOL + parseFloat(item.total_sales || 0).toLocaleString(),
                        formatBatchCell(item.batch_no), formatExpiryCell(item.nearest_expiry)
                    ], itemCompanyVis)}</tr>`;
                });
                html += '</tbody></table>';
                sectionHtmlParts.push(html);
            }
            
            container.innerHTML = sectionHtmlParts.join('');
        }
        
        loadReportData();
    </script>
</body>
</html>