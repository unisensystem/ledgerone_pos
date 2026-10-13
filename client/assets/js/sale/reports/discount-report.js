// Global variables
let allReportData = [];
let currentFilters = {};

// ---- Column configuration (order = display order) ----
// footer: 'label'  -> part of the leading "TOTALS:" label group in the footer
//         '<id>'   -> id of the <td> that shows the aggregated value in the footer
const COLUMNS = [
    { key: 'sno',              label: 'S#',            width: '4%',  align: 'left',  footer: 'label' },
    { key: 'date',             label: 'Date',           width: '7%',  align: 'left',  footer: 'label' },
    { key: 'billno',           label: 'Bill No',        width: '7%',  align: 'left',  footer: 'label' },
    { key: 'customer',         label: 'Customer',       width: '10%', align: 'left',  footer: 'label' },
    { key: 'product',          label: 'Product',        width: '10%', align: 'left',  footer: 'label' },
    { key: 'salesman',         label: 'Salesman',       width: '8%',  align: 'left',  footer: 'label' },
    { key: 'supplier',         label: 'Supplier',       width: '8%',  align: 'left',  footer: 'label' },
    { key: 'qty',              label: 'Qty',            width: '5%',  align: 'right', footer: 'label' },
    { key: 'price',            label: 'Price',          width: '7%',  align: 'right', footer: 'label' },
    { key: 'gross',            label: 'Gross Amt',      width: '8%',  align: 'right', footer: 'footerGross' },
    { key: 'discPercent',      label: 'Disc %',         width: '6%',  align: 'right', footer: 'footerDiscPercent' },
    { key: 'discAmt',          label: 'Disc Amt',       width: '8%',  align: 'right', footer: 'footerDisc' },
    { key: 'extraDiscPercent', label: 'Extra Disc %',   width: '6%',  align: 'right', footer: 'footerExtraDiscPercent' },
    { key: 'extraDiscAmt',     label: 'Extra Disc Amt', width: '8%',  align: 'right', footer: 'footerExtraDisc' },
    { key: 'taxPercent',       label: 'Tax %',          width: '5%',  align: 'right', footer: 'footerTaxPercent' },
    { key: 'net',              label: 'Net Amt',        width: '8%',  align: 'right', footer: 'footerNet' }
];

const COLUMN_STORAGE_KEY = 'discountReportHiddenColumns';

function getHiddenColumns() {
    try {
        const raw = localStorage.getItem(COLUMN_STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

function setHiddenColumns(hiddenArr) {
    localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(hiddenArr));
}

function isColumnVisible(key) {
    return !getHiddenColumns().includes(key);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setDefaultDates();
    buildColumnsPanel();
    renderTableStructure();
    loadFiltersData();
    loadReport();
    attachFilterListeners();
    document.addEventListener('click', handleOutsideClickForColumnsPanel);
});

// Set default dates (current month)
function setDefaultDates() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
}

// Load filter dropdown data
async function loadFiltersData() {
    try {
        const response = await fetch('../../../../server/api/sale/reports/discount-report-api.php?type=filters');
        const data = await response.json();

        if (data.success) {
            populateSelect('branchFilter', data.filters.branches, 'id', 'branch_name');
            populateSelect('customerFilter', data.filters.customers, 'id', 'customer_name');
            populateSelect('productFilter', data.filters.products, 'id', 'name');
            populateSelect('salesmanFilter', data.filters.salesmen, 'id', 'full_name');
            populateSelect('supplierFilter', data.filters.suppliers, 'id', 'supplier_name');
        }
    } catch (error) {
        console.error('Error loading filter data:', error);
    }
}

// Populate select dropdown
function populateSelect(selectId, data, valueKey, labelKey) {
    const select = document.getElementById(selectId);
    if (!select) return;

    data.forEach(item => {
        const option = document.createElement('option');
        option.value = item[valueKey];
        option.textContent = item[labelKey];
        select.appendChild(option);
    });
}

// Attach filter event listeners
function attachFilterListeners() {
    const filterInputs = document.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('change', () => {
            // Auto-apply filters on change
            setTimeout(() => loadReport(), 300);
        });
    });
}

// Toggle filters visibility
function toggleFilters() {
    const filtersGrid = document.getElementById('filtersGrid');
    const toggleBtn = document.getElementById('toggleFilters');

    filtersGrid.style.display = filtersGrid.style.display === 'none' ? 'grid' : 'none';
    toggleBtn.innerHTML = filtersGrid.style.display === 'none' ?
        '<i class="fas fa-chevron-down"></i>' :
        '<i class="fas fa-chevron-up"></i>';
}

// ---------------- Column show/hide ----------------

function buildColumnsPanel() {
    const panel = document.getElementById('columnsPanel');
    if (!panel) return;

    const hidden = getHiddenColumns();

    panel.innerHTML = `
        <div class="columns-panel-header">
            <span>Show / Hide Columns</span>
            <button type="button" class="columns-panel-reset" onclick="resetColumns()">Reset</button>
        </div>
        <div class="columns-panel-list">
            ${COLUMNS.map(col => `
                <label class="columns-panel-item">
                    <input type="checkbox" data-col-key="${col.key}" ${hidden.includes(col.key) ? '' : 'checked'} onchange="onColumnToggle('${col.key}', this.checked)">
                    <span>${col.label}</span>
                </label>
            `).join('')}
        </div>
    `;
}

function toggleColumnsPanel() {
    const panel = document.getElementById('columnsPanel');
    if (!panel) return;
    panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
}

function handleOutsideClickForColumnsPanel(e) {
    const panel = document.getElementById('columnsPanel');
    const btn = document.getElementById('columnsBtn');
    if (!panel || panel.style.display !== 'block') return;
    if (panel.contains(e.target) || (btn && btn.contains(e.target))) return;
    panel.style.display = 'none';
}

function onColumnToggle(key, checked) {
    let hidden = getHiddenColumns();
    if (checked) {
        hidden = hidden.filter(k => k !== key);
    } else {
        if (!hidden.includes(key)) hidden.push(key);
    }
    setHiddenColumns(hidden);
    renderTableStructure();
    displayReport(allReportData, lastTotals);
}

function resetColumns() {
    setHiddenColumns([]);
    buildColumnsPanel();
    renderTableStructure();
    displayReport(allReportData, lastTotals);
}

// Build <thead> and empty <tfoot> row based on currently visible columns
function renderTableStructure() {
    const visibleColumns = COLUMNS.filter(col => isColumnVisible(col.key));

    // Header
    const headRow = document.getElementById('tableHeadRow');
    if (headRow) {
        headRow.innerHTML = visibleColumns.map(col =>
            `<th width="${col.width}" data-col="${col.key}">${col.label}</th>`
        ).join('');
    }

    // Footer
    const footRow = document.getElementById('tableFootRow');
    if (footRow) {
        const labelCols = visibleColumns.filter(c => c.footer === 'label');
        const valueCols = visibleColumns.filter(c => c.footer !== 'label');

        let html = '';
        if (labelCols.length > 0) {
            html += `<td colspan="${labelCols.length}" style="text-align: right; font-weight: 600;">TOTALS:</td>`;
        }
        valueCols.forEach(col => {
            html += `<td id="${col.footer}" style="font-weight: 600;">${col.align === 'right' ? '' : ''}</td>`;
        });
        footRow.innerHTML = html;
    }

    // Keep loading/no-data colspans reasonably wide
    const colCount = Math.max(visibleColumns.length, 1);
    const loadingCell = document.querySelector('#tableBody td[colspan]');
    if (loadingCell) loadingCell.setAttribute('colspan', colCount);
}

// Build a single cell's inner HTML for a given column key + row
function getCellContent(row, key, index) {
    switch (key) {
        case 'sno': return index + 1;
        case 'date': return formatDate(row.sale_date);
        case 'billno': return `<strong>${row.bill_no}</strong>`;
        case 'customer': return `${row.customer_code}<br><small style="color: var(--subtext);">${row.customer_name}</small>`;
        case 'product': return `${row.product_code}<br><small style="color: var(--subtext);">${row.product_name}</small>`;
        case 'salesman': return row.salesman_name || 'Unassigned';
        case 'supplier': return row.supplier_name || '-';
        case 'qty': return parseFloat(row.quantity).toFixed(3);
        case 'price': return `${row.currency_symbol} ${parseFloat(row.sale_price).toFixed(3)}`;
        case 'gross': return `${row.currency_symbol} ${parseFloat(row.gross_amount).toFixed(3)}`;
        case 'discPercent': return `<span class="badge">${parseFloat(row.calculated_disc_percent).toFixed(2)}%</span>`;
        case 'discAmt': return `${row.currency_symbol} ${parseFloat(row.discount_amount).toFixed(3)}`;
        case 'extraDiscPercent': return `<span class="badge" style="background: var(--warning);">${parseFloat(row.calculated_extra_disc_percent).toFixed(2)}%</span>`;
        case 'extraDiscAmt': return `${row.currency_symbol} ${parseFloat(row.extra_discount_amount).toFixed(3)}`;
        case 'taxPercent': return `${parseFloat(row.tax_percent).toFixed(2)}%`;
        case 'net': return `<strong>${row.currency_symbol} ${parseFloat(row.net_amount).toFixed(3)}</strong>`;
        default: return '';
    }
}

// ---------------- Report loading / rendering ----------------

// Load report data
async function loadReport() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const customerId = document.getElementById('customerFilter').value;
    const productId = document.getElementById('productFilter').value;
    const branchId = document.getElementById('branchFilter').value;
    const salesmanId = document.getElementById('salesmanFilter').value;
    const supplierId = document.getElementById('supplierFilter').value;
    const minDiscount = document.getElementById('minDiscount').value;
    const maxDiscount = document.getElementById('maxDiscount').value;

    // Store current filters
    currentFilters = {
        startDate, endDate, customerId, productId, branchId, salesmanId, supplierId, minDiscount, maxDiscount
    };

    // Build query string
    let queryString = '../../../../server/api/sale/reports/discount-report-api.php?';
    if (startDate) queryString += `startDate=${startDate}&`;
    if (endDate) queryString += `endDate=${endDate}&`;
    if (customerId) queryString += `customerId=${customerId}&`;
    if (productId) queryString += `productId=${productId}&`;
    if (branchId) queryString += `branchId=${branchId}&`;
    if (salesmanId) queryString += `salesmanId=${salesmanId}&`;
    if (supplierId) queryString += `supplierId=${supplierId}&`;
    if (minDiscount) queryString += `minDiscount=${minDiscount}&`;
    if (maxDiscount) queryString += `maxDiscount=${maxDiscount}&`;

    try {
        const response = await fetch(queryString);
        const result = await response.json();

        if (result.success) {
            allReportData = result.data;
            displayReport(result.data, result.totals);
        } else {
            alert('Error loading report: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error loading report data');
    }
}

let lastTotals = { gross_amount: 0, discount_amount: 0, extra_discount_amount: 0, tax_amount: 0, net_amount: 0, count: 0 };

// Display report data
function displayReport(data, totals) {
    lastTotals = totals || lastTotals;

    const tbody = document.getElementById('tableBody');
    const noData = document.getElementById('noDataMessage');
    const reportTable = document.querySelector('.report-table-container');

    if (!data || data.length === 0) {
        tbody.innerHTML = '';
        noData.style.display = 'block';
        reportTable.style.display = 'none';
        return;
    }

    noData.style.display = 'none';
    reportTable.style.display = 'block';

    const visibleColumns = COLUMNS.filter(col => isColumnVisible(col.key));

    tbody.innerHTML = data.map((row, index) => `
        <tr>
            ${visibleColumns.map(col =>
                `<td data-col="${col.key}" class="${col.align === 'right' ? 'text-right' : ''}">${getCellContent(row, col.key, index)}</td>`
            ).join('')}
        </tr>
    `).join('');

    // Update summary
    updateSummary(lastTotals);

    // Update footer
    updateFooter(lastTotals);
}

// Update summary cards
function updateSummary(totals) {
    document.getElementById('totalRecords').textContent = totals.count;
    document.getElementById('totalGross').textContent = '₨ ' + parseFloat(totals.gross_amount).toFixed(3);
    document.getElementById('totalDiscount').textContent = '₨ ' + parseFloat(totals.discount_amount).toFixed(3);
    document.getElementById('totalExtraDiscount').textContent = '₨ ' + parseFloat(totals.extra_discount_amount).toFixed(3);
    document.getElementById('totalNet').textContent = '₨ ' + parseFloat(totals.net_amount).toFixed(3);
}

// Update footer totals (only touches footer cells that currently exist, based on visible columns)
function updateFooter(totals) {
    const avgDisc = totals.gross_amount > 0 ?
        ((totals.discount_amount / totals.gross_amount) * 100).toFixed(2) : '0.00';
    const avgExtraDisc = (totals.gross_amount - totals.discount_amount) > 0 ?
        ((totals.extra_discount_amount / (totals.gross_amount - totals.discount_amount)) * 100).toFixed(2) : '0.00';
    const avgTax = totals.net_amount > 0 ?
        ((totals.tax_amount / totals.net_amount) * 100).toFixed(2) : '0.00';

    setTextIfExists('footerGross', '₨ ' + parseFloat(totals.gross_amount).toFixed(3));
    setTextIfExists('footerDiscPercent', avgDisc + '%');
    setTextIfExists('footerDisc', '₨ ' + parseFloat(totals.discount_amount).toFixed(3));
    setTextIfExists('footerExtraDiscPercent', avgExtraDisc + '%');
    setTextIfExists('footerExtraDisc', '₨ ' + parseFloat(totals.extra_discount_amount).toFixed(3));
    setTextIfExists('footerTaxPercent', avgTax + '%');
    setTextIfExists('footerNet', '₨ ' + parseFloat(totals.net_amount).toFixed(3));
}

function setTextIfExists(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PK', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Reset filters
function resetFilters() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('customerFilter').value = '';
    document.getElementById('productFilter').value = '';
    document.getElementById('branchFilter').value = '';
    document.getElementById('salesmanFilter').value = '';
    document.getElementById('supplierFilter').value = '';
    document.getElementById('minDiscount').value = '';
    document.getElementById('maxDiscount').value = '';

    setDefaultDates();
    loadReport();
}

// Print report
function printReport() {
    document.getElementById('printModal').classList.add('show');
}

function confirmPrint() {
    const format = document.querySelector('input[name="printFormat"]:checked').value;
    document.getElementById('printModal').classList.remove('show');

    if (format === 'current') {
        window.print();
    } else {
        // Open detailed print page
        const params = new URLSearchParams(currentFilters);
        window.open(`discount-report-print.php?${params.toString()}`, '_blank');
    }
}

// Export to CSV (respects currently visible columns)
function exportToCSV() {
    if (allReportData.length === 0) {
        alert('No data to export');
        return;
    }

    const visibleColumns = COLUMNS.filter(col => isColumnVisible(col.key));

    const csvGetters = {
        sno: (row, i) => i + 1,
        date: row => row.sale_date,
        billno: row => row.bill_no,
        customer: row => `${row.customer_code} - ${row.customer_name}`,
        product: row => `${row.product_code} - ${row.product_name}`,
        salesman: row => row.salesman_name || 'Unassigned',
        supplier: row => row.supplier_name || '-',
        qty: row => row.quantity,
        price: row => row.sale_price,
        gross: row => row.gross_amount,
        discPercent: row => row.calculated_disc_percent,
        discAmt: row => row.discount_amount,
        extraDiscPercent: row => row.calculated_extra_disc_percent,
        extraDiscAmt: row => row.extra_discount_amount,
        taxPercent: row => row.tax_percent,
        net: row => row.net_amount
    };

    let csv = visibleColumns.map(c => c.label).join(',') + '\n';

    allReportData.forEach((row, index) => {
        csv += visibleColumns.map(col => {
            const val = csvGetters[col.key](row, index);
            return `"${String(val).replace(/"/g, '""')}"`;
        }).join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `discount-report-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Style for badges and text-right
const style = document.createElement('style');
style.textContent = `
    .badge {
        display: inline-block;
        padding: 2px 8px;
        background: var(--success);
        color: white;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .text-right {
        text-align: right;
    }
`;
document.head.appendChild(style);