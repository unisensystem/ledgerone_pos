const API_BASE = '../../../../server/api/sale/daily_product_sale_report/daily-product-sale-report.php';

let currentPage = 1;
const invoicesPerPage = 15; // pagination is by INVOICE GROUP
let allRows = [];
let groupedInvoices = [];
let currentCurrency = '$';

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// DB date (YYYY-MM-DD) -> DD/MM/YY
function formatDateShort(dateStr) {
    if (!dateStr) return '';

    const d = new Date(dateStr);

    if (isNaN(d)) return dateStr;

    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yy = String(d.getFullYear()).slice(-2);

    return `${dd}/${mm}/${yy}`;
}

async function loadCustomers() {
    try {
        const res = await fetch(`${API_BASE}?action=customers`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('customer-list').innerHTML =
                data.data.map(c =>
                    `<option value="${c.customer_name}" data-id="${c.id}">`
                ).join('');
        }
    } catch (e) {
        console.error('Error loading customers:', e);
    }
}

async function loadBranches() {
    try {
        const res = await fetch(`${API_BASE}?action=branches`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('branch-list').innerHTML =
                data.data.map(b =>
                    `<option value="${b.branch_name}" data-id="${b.id}">`
                ).join('');
        }
    } catch (e) {
        console.error('Error loading branches:', e);
    }
}

async function loadProducts() {
    try {
        const res = await fetch(`${API_BASE}?action=products`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('product-list').innerHTML =
                data.data.map(p =>
                    `<option value="${p.name}" data-id="${p.id}">`
                ).join('');
        }
    } catch (e) {
        console.error('Error loading products:', e);
    }
}

let salesmenData = [];
let supplierMenData = [];

async function loadSalesmen() {
    try {
        const res = await fetch(`${API_BASE}?action=salesmen`);
        const data = await res.json();

        if (data.success) {
            salesmenData = data.data || [];
            setupSearchableDropdown('salesman-search', 'salesman-dropdown', 'salesman-id', salesmenData, 'full_name');
        }
    } catch (e) {
        console.error('Error loading salesmen:', e);
    }
}

async function loadSupplierMen() {
    try {
        const res = await fetch(`${API_BASE}?action=supplier_men`);
        const data = await res.json();

        if (data.success) {
            supplierMenData = data.data || [];
            setupSearchableDropdown('supplier-man-search', 'supplier-man-dropdown', 'supplier-man-id', supplierMenData, 'full_name');
        }
    } catch (e) {
        console.error('Error loading supplier men:', e);
    }
}

// Generic type-to-search dropdown: matches anywhere in the label (not just prefix),
// mirroring the same UX as the Sales Officer / Supplier Man pickers on the invoice
// Add page (initSearchableDropdown in pos-add.js).
function setupSearchableDropdown(searchInputId, dropdownId, hiddenIdInputId, items, labelKey) {
    const searchInput = document.getElementById(searchInputId);
    const dropdown = document.getElementById(dropdownId);
    const hiddenInput = document.getElementById(hiddenIdInputId);

    if (!searchInput || !dropdown || !hiddenInput) return;

    function renderOptions(filterText) {
        const term = (filterText || '').toLowerCase();
        const matches = items.filter(item => String(item[labelKey] || '').toLowerCase().includes(term));

        if (matches.length === 0) {
            dropdown.innerHTML = `<div class="dropdown-empty">No matches</div>`;
        } else {
            dropdown.innerHTML = matches.map(item =>
                `<div class="dropdown-option" data-id="${item.id}">${esc(item[labelKey])}</div>`
            ).join('');
        }

        dropdown.classList.add('open');
    }

    searchInput.addEventListener('focus', () => renderOptions(searchInput.value));
    searchInput.addEventListener('click', () => renderOptions(searchInput.value));

    searchInput.addEventListener('input', () => {
        hiddenInput.value = '';
        renderOptions(searchInput.value);
    });

    dropdown.addEventListener('click', (e) => {
        const option = e.target.closest('.dropdown-option');
        if (!option) return;

        searchInput.value = option.textContent;
        hiddenInput.value = option.getAttribute('data-id');
        dropdown.classList.remove('open');
    });

    document.addEventListener('click', (e) => {
        if (e.target !== searchInput && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });
}

async function loadCities() {
    try {
        const res = await fetch(`${API_BASE}?action=cities`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('city-list').innerHTML =
                data.data.map(c =>
                    `<option value="${c.city_name}" data-id="${c.id}">`
                ).join('');
        }
    } catch (e) {
        console.error('Error loading cities:', e);
    }
}

async function loadAreas() {
    try {
        const res = await fetch(`${API_BASE}?action=areas`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('area-list').innerHTML =
                data.data.map(a =>
                    `<option value="${a.area_name}" data-id="${a.id}">`
                ).join('');
        }
    } catch (e) {
        console.error('Error loading areas:', e);
    }
}

function findIdFromDatalist(inputVal, datalistSelector) {
    if (!inputVal) return null;

    const options = document.querySelectorAll(`${datalistSelector} option`);

    for (const opt of options) {
        if (opt.value === inputVal) {
            return opt.getAttribute('data-id');
        }
    }

    return null;
}

// Groups rows by invoice
function groupByInvoice(rows) {
    const groups = [];
    const indexByInvoice = {};

    rows.forEach(row => {
        const key = row.invoice_id;

        if (!(key in indexByInvoice)) {
            indexByInvoice[key] = groups.length;

            groups.push({
                invoice_id: row.invoice_id,
                bill_no: row.bill_no,
                sale_date: row.sale_date,
                customer_name: row.customer_name,
                city_name: row.city_name,
                area_name: row.area_name,
                zone_name: row.zone_name,
                invoice_net_amount: parseFloat(row.invoice_net_amount || 0),
                invoice_disc_percent: parseFloat(row.invoice_disc_percent || 0),
                items: [],
                total_amount: 0,
                total_tax: 0
            });
        }

        const group = groups[indexByInvoice[key]];

        group.items.push(row);
        group.total_amount += parseFloat(row.net_amount || 0);
        group.total_tax += parseFloat(row.tax_amount || 0);
    });

    return groups;
}

async function loadReport() {
    try {
        const customerInput = document.getElementById('customer').value;
        const branchInput = document.getElementById('branch').value;
        const productInput = document.getElementById('product').value;
        const cityInput = document.getElementById('city').value;
        const areaInput = document.getElementById('area').value;

        const fromDate = document.getElementById('from-date').value;
        const toDate = document.getElementById('to-date').value;

        // Batch + Expiry filters
        const batchNo = document.getElementById('batch-no').value.trim();
        const expiryDate = document.getElementById('expiry-date').value;

        const customerId = findIdFromDatalist(
            customerInput,
            '#customer-list'
        );

        const branchId = findIdFromDatalist(
            branchInput,
            '#branch-list'
        );

        const productId = findIdFromDatalist(
            productInput,
            '#product-list'
        );

        const salesmanId = document.getElementById('salesman-id').value;

        const supplierManId = document.getElementById('supplier-man-id').value;

        const cityId = findIdFromDatalist(
            cityInput,
            '#city-list'
        );

        const areaId = findIdFromDatalist(
            areaInput,
            '#area-list'
        );

        let url = `${API_BASE}?action=report`;

        if (fromDate) {
            url += `&from_date=${encodeURIComponent(fromDate)}`;
        }

        if (toDate) {
            url += `&to_date=${encodeURIComponent(toDate)}`;
        }

        if (customerId) {
            url += `&customer_id=${encodeURIComponent(customerId)}`;
        }

        if (branchId) {
            url += `&branch_id=${encodeURIComponent(branchId)}`;
        }

        if (productId) {
            url += `&product_id=${encodeURIComponent(productId)}`;
        }

        // Batch No filter
        if (batchNo) {
            url += `&batch_no=${encodeURIComponent(batchNo)}`;
        }

        // Expiry Date filter
        if (expiryDate) {
            url += `&expiry_date=${encodeURIComponent(expiryDate)}`;
        }

        // Salesman filter
        if (salesmanId) {
            url += `&sale_officer_id=${encodeURIComponent(salesmanId)}`;
        }

        // Supplier Man filter
        if (supplierManId) {
            url += `&supplier_man_id=${encodeURIComponent(supplierManId)}`;
        }

        // City filter
        if (cityId) {
            url += `&city_id=${encodeURIComponent(cityId)}`;
        }

        // Area filter
        if (areaId) {
            url += `&area_id=${encodeURIComponent(areaId)}`;
        }

        console.log('Daily Sale Report URL:', url);

        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            allRows = Array.isArray(data.data)
                ? data.data
                : [];

            groupedInvoices = groupByInvoice(allRows);

            currentPage = 1;

            currentCurrency = data.currency || '$';

            renderTable();
            updatePaginationControls();
        } else {
            console.error('Report error:', data.message);

            allRows = [];
            groupedInvoices = [];

            renderTable();
            updatePaginationControls();
        }

    } catch (e) {
        console.error(
            'Error loading daily product sale report:',
            e
        );
    }
}

function renderTable() {
    const tbody = document.querySelector('.table tbody');

    if (!tbody) return;

    const startIndex =
        (currentPage - 1) * invoicesPerPage;

    const endIndex =
        startIndex + invoicesPerPage;

    const pageGroups =
        groupedInvoices.slice(startIndex, endIndex);

    if (pageGroups.length === 0) {

        tbody.innerHTML = `
            <tr>
                <td colspan="11"
                    style="text-align:center; padding:24px; color:var(--subtext);">
                    No records found
                </td>
            </tr>
        `;

        renderGrandTotal();
        return;
    }

    let html = '';

    pageGroups.forEach(group => {

        const locationParts = [group.city_name, group.area_name, group.zone_name]
            .filter(v => v && v.trim() !== '');
        const locationText = locationParts.length
            ? ` (${locationParts.map(esc).join(', ')})`
            : '';

        // Invoice header
        html += `
            <tr style="background-color:var(--table-header);">
                <td colspan="10">
                    <strong>
                        ${formatDateShort(group.sale_date)}
                        &nbsp;&nbsp;
                        ${esc(group.customer_name || '')}${locationText}
                    </strong>
                </td>
                <td style="text-align:right;" class="col-amount">
                    <strong>
                        ${currentCurrency}${group.invoice_net_amount.toFixed(2)}
                        &nbsp;&nbsp;
                        ${esc(group.bill_no || '')}
                    </strong>
                </td>
            </tr>
        `;

        // Item rows
        group.items.forEach((row, idx) => {

            const salePrice   = parseFloat(row.sale_price || 0);
            const quantity    = parseFloat(row.quantity || 0);
            const focQty      = parseFloat(row.foc_quantity || 0);
            const netAmount   = parseFloat(row.net_amount || 0);
            const batchNo     = row.batch_no || '-';
            const expiryDate  = formatDateShort(row.batch_expiry_date) || '-';
            const discPercent = parseFloat(row.discount_percent || 0); // item-level

            // Extra Disc % isn't stored directly — only the resulting amount is saved —
            // so back-derive the % (on the post-main-discount amount), same as the
            // invoice print pages do.
            const grossAmount = parseFloat(row.gross_amount || 0);
            const discAmount = parseFloat(row.discount_amount || 0);
            const extraDiscAmount = parseFloat(row.extra_discount_amount || 0);
            const afterMainDiscount = grossAmount - discAmount;
            const extraDiscPercent = afterMainDiscount > 0 ? (extraDiscAmount / afterMainDiscount * 100) : 0;

            html += `
                <tr>
                    <td class="col-sno">${idx + 1}</td>
                    <td class="col-code">${esc(row.product_code || '-')}</td>
                    <td class="col-detail">${esc(row.product_name || '-')}</td>
                    <td class="col-batch">${esc(batchNo)}</td>
                    <td class="col-expiry">${esc(expiryDate)}</td>
                    <td class="col-price">${currentCurrency}${salePrice.toFixed(2)}</td>
                    <td class="col-qty">${quantity.toFixed(2)} ${esc(row.unit_symbol || '')}</td>
                    <td class="col-disc">${discPercent.toFixed(2)}%</td>
                    <td class="col-extradisc">${extraDiscPercent.toFixed(2)}%</td>
                    <td class="col-foc">${focQty.toFixed(2)}</td>
                    <td class="col-amount">${currentCurrency}${netAmount.toFixed(2)}</td>
                </tr>
            `;
        });

        // Invoice total row
        html += `
            <tr style="font-weight:bold;">
                <td colspan="10" style="text-align:right;">Total</td>
                <td class="col-amount">${currentCurrency}${group.total_amount.toFixed(2)}</td>
            </tr>
        `;

        // Invoice tax row — right under Total
        html += `
            <tr style="font-weight:bold;">
                <td colspan="10" style="text-align:right;">Tax</td>
                <td class="col-amount">${currentCurrency}${group.total_tax.toFixed(2)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    renderGrandTotal();
}

function renderGrandTotal() {
    const grandTotalQty =
        allRows.reduce(
            (sum, r) =>
                sum + parseFloat(r.quantity || 0),
            0
        );

    const grandTotalFoc =
        allRows.reduce(
            (sum, r) =>
                sum + parseFloat(r.foc_quantity || 0),
            0
        );

    const grandTotalAmount =
        allRows.reduce(
            (sum, r) =>
                sum + parseFloat(r.net_amount || 0),
            0
        );

    const el =
        document.getElementById('grand-total-info');

    if (el) {
        el.textContent =
            `Grand Total: ${groupedInvoices.length} invoice(s), ` +
            `Qty ${grandTotalQty.toFixed(2)}, ` +
            `FOC ${grandTotalFoc.toFixed(2)}, ` +
            `Amount ${currentCurrency}${grandTotalAmount.toFixed(2)}`;
    }
}

function updatePaginationControls() {
    const totalInvoices =
        groupedInvoices.length;

    const totalPages =
        Math.ceil(totalInvoices / invoicesPerPage) || 1;

    const startItem =
        totalInvoices === 0
            ? 0
            : (currentPage - 1) * invoicesPerPage + 1;

    const endItem =
        Math.min(
            currentPage * invoicesPerPage,
            totalInvoices
        );

    const paginationInfo =
        document.getElementById('pagination-info');

    const pageInfo =
        document.getElementById('page-info');

    const prevBtn =
        document.getElementById('prev-btn');

    const nextBtn =
        document.getElementById('next-btn');

    if (paginationInfo) {
        paginationInfo.textContent =
            `Showing invoices ${startItem} - ${endItem} of ${totalInvoices}`;
    }

    if (pageInfo) {
        pageInfo.textContent =
            `Page ${currentPage} of ${totalPages}`;
    }

    if (prevBtn) {
        prevBtn.disabled =
            currentPage === 1;
    }

    if (nextBtn) {
        nextBtn.disabled =
            currentPage === totalPages ||
            totalPages === 0;
    }
}

function escapeCell(val) {
    let str =
        (val === undefined || val === null)
            ? ''
            : String(val);

    if (/[",\n]/.test(str)) {
        str =
            '"' +
            str.replace(/"/g, '""') +
            '"';
    }

    return str;
}

function exportToExcel() {
    if (!allRows.length) {
        alert('No data available to export.');
        return;
    }

    const rows = [];

    groupedInvoices.forEach(group => {

        // Invoice header
        rows.push({
            'S.No': '',
            'Code': '',
            'Detail':
                `${formatDateShort(group.sale_date)}  ${group.customer_name}`,
            'Batch No': '',
            'Expiry Date': '',
            'Price': '',
            'Qty': '',
            'Disc %': '',
            'Extra Disc %': '',
            'FOC': '',
            'Amount': group.bill_no
        });

        // Items
        group.items.forEach((row, idx) => {

            const grossAmount = parseFloat(row.gross_amount || 0);
            const discAmount = parseFloat(row.discount_amount || 0);
            const extraDiscAmount = parseFloat(row.extra_discount_amount || 0);
            const afterMainDiscount = grossAmount - discAmount;
            const extraDiscPercent = afterMainDiscount > 0 ? (extraDiscAmount / afterMainDiscount * 100) : 0;

            rows.push({
                'S.No': idx + 1,

                'Code':
                    row.product_code || '-',

                'Detail':
                    row.product_name || '-',

                'Batch No':
                    row.batch_no || '-',

                'Expiry Date':
                    formatDateShort(row.batch_expiry_date) || '-',

                'Price':
                    parseFloat(row.sale_price || 0)
                        .toFixed(2),

                'Qty':
                    `${parseFloat(row.quantity || 0).toFixed(2)} ${row.unit_symbol || ''}`,

                'Disc %':
                    parseFloat(row.discount_percent || 0).toFixed(2),

                'Extra Disc %':
                    extraDiscPercent.toFixed(2),

                'FOC':
                    parseFloat(row.foc_quantity || 0)
                        .toFixed(2),

                'Amount':
                    parseFloat(row.net_amount || 0)
                        .toFixed(2)
            });
        });

        // Total
        rows.push({
            'S.No': '',
            'Code': '',
            'Detail': '',
            'Batch No': '',
            'Expiry Date': '',
            'Price': '',
            'Qty': '',
            'Disc %': '',
            'Extra Disc %': '',
            'FOC': 'Total',
            'Amount':
                group.total_amount.toFixed(2)
        });

        // Tax — right under Total
        rows.push({
            'S.No': '',
            'Code': '',
            'Detail': '',
            'Batch No': '',
            'Expiry Date': '',
            'Price': '',
            'Qty': '',
            'Disc %': '',
            'Extra Disc %': '',
            'FOC': 'Tax',
            'Amount':
                group.total_tax.toFixed(2)
        });
    });

    if (!rows.length) {
        alert('No data available to export.');
        return;
    }

    const headers =
        Object.keys(rows[0]);

    const lines = [
        headers
            .map(escapeCell)
            .join(',')
    ];

    rows.forEach(row => {

        lines.push(
            headers
                .map(h => escapeCell(row[h]))
                .join(',')
        );

    });

    const csvContent =
        '\uFEFF' +
        lines.join('\r\n');

    const blob =
        new Blob(
            [csvContent],
            {
                type:
                    'text/csv;charset=utf-8;'
            }
        );

    const url =
        URL.createObjectURL(blob);

    const link =
        document.createElement('a');

    link.href = url;

    link.download =
        `daily_product_sale_report_${todayStr()}.csv`;

    link.style.display = 'none';

    document.body.appendChild(link);

    link.click();

    document.body.removeChild(link);

    URL.revokeObjectURL(url);
}

function printReport() {

    const customerInput =
        document.getElementById('customer').value;

    const branchInput =
        document.getElementById('branch').value;

    const productInput =
        document.getElementById('product').value;

    const salesmanId =
        document.getElementById('salesman-id').value;

    const supplierManId =
        document.getElementById('supplier-man-id').value;

    const cityInput =
        document.getElementById('city').value;

    const areaInput =
        document.getElementById('area').value;

    const fromDate =
        document.getElementById('from-date').value;

    const toDate =
        document.getElementById('to-date').value;

    const batchNo =
        document.getElementById('batch-no').value.trim();

    const expiryDate =
        document.getElementById('expiry-date').value;

    const customerId =
        findIdFromDatalist(
            customerInput,
            '#customer-list'
        );

    const branchId =
        findIdFromDatalist(
            branchInput,
            '#branch-list'
        );

    const productId =
        findIdFromDatalist(
            productInput,
            '#product-list'
        );

    const cityId =
        findIdFromDatalist(
            cityInput,
            '#city-list'
        );

    const areaId =
        findIdFromDatalist(
            areaInput,
            '#area-list'
        );

    let params = [];

    if (fromDate) {
        params.push(
            `from_date=${encodeURIComponent(fromDate)}`
        );
    }

    if (toDate) {
        params.push(
            `to_date=${encodeURIComponent(toDate)}`
        );
    }

    if (customerId) {
        params.push(
            `customer_id=${encodeURIComponent(customerId)}`
        );
    }

    if (branchId) {
        params.push(
            `branch_id=${encodeURIComponent(branchId)}`
        );
    }

    if (productId) {
        params.push(
            `product_id=${encodeURIComponent(productId)}`
        );
    }

    if (batchNo) {
        params.push(
            `batch_no=${encodeURIComponent(batchNo)}`
        );
    }

    if (expiryDate) {
        params.push(
            `expiry_date=${encodeURIComponent(expiryDate)}`
        );
    }

    if (salesmanId) {
        params.push(
            `sale_officer_id=${encodeURIComponent(salesmanId)}`
        );
    }

    if (supplierManId) {
        params.push(
            `supplier_man_id=${encodeURIComponent(supplierManId)}`
        );
    }

    if (cityId) {
        params.push(
            `city_id=${encodeURIComponent(cityId)}`
        );
    }

    if (areaId) {
        params.push(
            `area_id=${encodeURIComponent(areaId)}`
        );
    }

    const url =
        `print.php?${params.join('&')}`;

    window.open(url, '_blank');
}

/*
|--------------------------------------------------------------------------
| COLUMN SHOW / HIDE
|
| Preferences are stored in localStorage under a key shared with
| print.php (same origin), so toggling columns here also affects
| the print page, and vice versa.
|--------------------------------------------------------------------------
*/

const COLUMN_STORAGE_KEY = 'dpsr_column_visibility';

const COLUMN_CONFIG = [
    { key: 'sno',    label: 'Sr#' },
    { key: 'code',   label: 'Product Code' },
    { key: 'detail', label: 'Details' },
    { key: 'batch',  label: 'Batch No' },
    { key: 'expiry', label: 'Expiry Date' },
    { key: 'price',  label: 'Price' },
    { key: 'qty',    label: 'Qty' },
    { key: 'disc',   label: 'Disc %' },
    { key: 'extradisc', label: 'Extra Disc %' },
    { key: 'foc',    label: 'FOC' },
    { key: 'amount', label: 'Amount' }
];

function getColumnPrefs() {
    try {
        const raw = localStorage.getItem(COLUMN_STORAGE_KEY);
        if (!raw) return {};
        return JSON.parse(raw);
    } catch (e) {
        return {};
    }
}

function saveColumnPrefs(prefs) {
    try {
        localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(prefs));
    } catch (e) {
        console.error('Could not save column preferences:', e);
    }
}

function applyColumnVisibility() {
    const prefs = getColumnPrefs();
    const body = document.body;

    COLUMN_CONFIG.forEach(col => {
        const isHidden = prefs[col.key] === false;
        body.classList.toggle(`hide-col-${col.key}`, isHidden);
    });
}

function buildColumnsModalBody() {
    const prefs = getColumnPrefs();
    const container = document.getElementById('columns-modal-body');
    if (!container) return;

    container.innerHTML = COLUMN_CONFIG.map(col => {
        const checked = prefs[col.key] === false ? '' : 'checked';
        return `
            <label>
                <input type="checkbox" data-col-key="${col.key}" ${checked}>
                ${col.label}
            </label>
        `;
    }).join('');
}

function openColumnsModal() {
    buildColumnsModalBody();
    const overlay = document.getElementById('columns-modal-overlay');
    if (overlay) overlay.classList.add('open');
}

function closeColumnsModal() {
    const overlay = document.getElementById('columns-modal-overlay');
    if (overlay) overlay.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {

    // Apply saved column preferences on load
    applyColumnVisibility();

    const applyFilters =
        document.getElementById('applyFilters');

    const clearFilters =
        document.getElementById('clearFilters');

    const prevBtn =
        document.getElementById('prev-btn');

    const nextBtn =
        document.getElementById('next-btn');

    const columnsBtn =
        document.getElementById('columns-btn');

    const columnsModalClose =
        document.getElementById('columns-modal-close');

    const columnsApplyBtn =
        document.getElementById('columns-apply-btn');

    const columnsResetBtn =
        document.getElementById('columns-reset-btn');

    const columnsOverlay =
        document.getElementById('columns-modal-overlay');

    // Apply filters
    if (applyFilters) {
        applyFilters.addEventListener(
            'click',
            loadReport
        );
    }

    // Clear filters
    if (clearFilters) {

        clearFilters.addEventListener(
            'click',
            () => {

                document.getElementById('customer').value = '';

                document.getElementById('branch').value = '';

                document.getElementById('product').value = '';

                document.getElementById('salesman-search').value = '';
                document.getElementById('salesman-id').value = '';

                document.getElementById('supplier-man-search').value = '';
                document.getElementById('supplier-man-id').value = '';

                document.getElementById('city').value = '';

                document.getElementById('area').value = '';

                document.getElementById('batch-no').value = '';

                document.getElementById('expiry-date').value = '';

                document.getElementById('from-date').value = '';

                document.getElementById('to-date').value = '';

                loadReport();
            }
        );
    }

    // Previous page
    if (prevBtn) {

        prevBtn.addEventListener(
            'click',
            () => {

                if (currentPage > 1) {

                    currentPage--;

                    renderTable();

                    updatePaginationControls();
                }
            }
        );
    }

    // Next page
    if (nextBtn) {

        nextBtn.addEventListener(
            'click',
            () => {

                const totalPages =
                    Math.ceil(
                        groupedInvoices.length /
                        invoicesPerPage
                    ) || 1;

                if (currentPage < totalPages) {

                    currentPage++;

                    renderTable();

                    updatePaginationControls();
                }
            }
        );
    }

    // Columns modal open
    if (columnsBtn) {
        columnsBtn.addEventListener('click', openColumnsModal);
    }

    // Columns modal close (X button)
    if (columnsModalClose) {
        columnsModalClose.addEventListener('click', closeColumnsModal);
    }

    // Columns modal close (click outside box)
    if (columnsOverlay) {
        columnsOverlay.addEventListener('click', (e) => {
            if (e.target === columnsOverlay) closeColumnsModal();
        });
    }

    // Columns modal apply
    if (columnsApplyBtn) {
        columnsApplyBtn.addEventListener('click', () => {
            const prefs = {};
            document.querySelectorAll('#columns-modal-body input[type="checkbox"]').forEach(cb => {
                prefs[cb.getAttribute('data-col-key')] = cb.checked;
            });
            saveColumnPrefs(prefs);
            applyColumnVisibility();
            closeColumnsModal();
        });
    }

    // Columns modal reset (show all columns)
    if (columnsResetBtn) {
        columnsResetBtn.addEventListener('click', () => {
            saveColumnPrefs({});
            applyColumnVisibility();
            buildColumnsModalBody();
        });
    }

    // Initial loading
    loadCustomers();
    loadBranches();
    loadProducts();
    loadSalesmen();
    loadSupplierMen();
    loadCities();
    loadAreas();
    loadReport();
});
