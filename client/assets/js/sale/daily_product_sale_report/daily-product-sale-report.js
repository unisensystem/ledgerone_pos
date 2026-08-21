const API_BASE = '../../../../server/api/sale/daily_product_sale_report/daily-product-sale-report.php';

let currentPage = 1;
const invoicesPerPage = 15; // pagination is by INVOICE GROUP
let allRows = [];
let groupedInvoices = [];
let currentCurrency = '$';

function todayStr() {
    return new Date().toISOString().slice(0, 10);
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

async function loadSalesmen() {
    try {
        const res = await fetch(`${API_BASE}?action=salesmen`);
        const data = await res.json();

        if (data.success) {
            document.getElementById('salesman-list').innerHTML =
                data.data.map(s =>
                    `<option value="${s.full_name}" data-id="${s.id}">`
                ).join('');
        }
    } catch (e) {
        console.error('Error loading salesmen:', e);
    }
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
                items: [],
                total_amount: 0
            });
        }

        const group = groups[indexByInvoice[key]];

        group.items.push(row);
        group.total_amount += parseFloat(row.net_amount || 0);
    });

    return groups;
}

async function loadReport() {
    try {
        const customerInput = document.getElementById('customer').value;
        const branchInput = document.getElementById('branch').value;
        const productInput = document.getElementById('product').value;
        const salesmanInput = document.getElementById('salesman').value;
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

        const salesmanId = findIdFromDatalist(
            salesmanInput,
            '#salesman-list'
        );

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
                <td colspan="8"
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

        // Invoice header
        html += `
            <tr style="background-color:var(--table-header);">

                <td colspan="7">
                    <strong>
                        ${formatDateShort(group.sale_date)}
                        &nbsp;&nbsp;
                        ${group.customer_name || ''}
                    </strong>
                </td>

                <td style="text-align:right;">
                    <strong>
                        ${group.bill_no || ''}
                    </strong>
                </td>

            </tr>
        `;

        // Item rows
        group.items.forEach((row, idx) => {

            const salePrice =
                parseFloat(row.sale_price || 0);

            const quantity =
                parseFloat(row.quantity || 0);

            const netAmount =
                parseFloat(row.net_amount || 0);

            const batchNo =
                row.batch_no || '-';

            const expiryDate =
                formatDateShort(row.batch_expiry_date) || '-';

            html += `
                <tr>

                    <td>
                        ${idx + 1}
                    </td>

                    <td>
                        ${row.product_code || '-'}
                    </td>

                    <td>
                        ${row.product_name || '-'}
                    </td>

                    <td>
                        ${batchNo}
                    </td>

                    <td>
                        ${expiryDate}
                    </td>

                    <td>
                        ${currentCurrency}${salePrice.toFixed(2)}
                    </td>

                    <td>
                        ${quantity.toFixed(2)}
                        ${row.unit_symbol || ''}
                    </td>

                    <td>
                        ${currentCurrency}${netAmount.toFixed(2)}
                    </td>

                </tr>
            `;
        });

        // Invoice total
        html += `
            <tr style="font-weight:bold;">

                <td colspan="7"
                    style="text-align:right;">
                    Total
                </td>

                <td>
                    ${currentCurrency}${group.total_amount.toFixed(2)}
                </td>

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
            'Amount': group.bill_no
        });

        // Items
        group.items.forEach((row, idx) => {

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
            'Qty': 'Total',
            'Amount':
                group.total_amount.toFixed(2)
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

    const salesmanInput =
        document.getElementById('salesman').value;

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

    const salesmanId =
        findIdFromDatalist(
            salesmanInput,
            '#salesman-list'
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

document.addEventListener('DOMContentLoaded', () => {

    const applyFilters =
        document.getElementById('applyFilters');

    const clearFilters =
        document.getElementById('clearFilters');

    const prevBtn =
        document.getElementById('prev-btn');

    const nextBtn =
        document.getElementById('next-btn');

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

                document.getElementById('salesman').value = '';

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

    // Initial loading
    loadCustomers();
    loadBranches();
    loadProducts();
    loadSalesmen();
    loadCities();
    loadAreas();
    loadReport();
});
