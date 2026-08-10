const API_BASE = '../../../../server/api/sale/daily_product_sale_report/daily-product-sale-report.php';

let currentPage = 1;
const invoicesPerPage = 15; // pagination is by INVOICE GROUP, not by flat row
let allRows = [];
let groupedInvoices = []; // [{invoice_id, bill_no, sale_date, customer_name, items:[], total_amount}]
let currentCurrency = '$';

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

// DB date (YYYY-MM-DD) -> DD/MM/YY like the target print format
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
                data.data.map(c => `<option value="${c.customer_name}" data-id="${c.id}">`).join('');
        }
    } catch (e) { console.error('Error loading customers:', e); }
}

async function loadBranches() {
    try {
        const res = await fetch(`${API_BASE}?action=branches`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('branch-list').innerHTML =
                data.data.map(b => `<option value="${b.branch_name}" data-id="${b.id}">`).join('');
        }
    } catch (e) { console.error('Error loading branches:', e); }
}

async function loadProducts() {
    try {
        const res = await fetch(`${API_BASE}?action=products`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('product-list').innerHTML =
                data.data.map(p => `<option value="${p.name}" data-id="${p.id}">`).join('');
        }
    } catch (e) { console.error('Error loading products:', e); }
}

function findIdFromDatalist(inputVal, datalistSelector) {
    if (!inputVal) return null;
    const options = document.querySelectorAll(`${datalistSelector} option`);
    for (const opt of options) {
        if (opt.value === inputVal) return opt.getAttribute('data-id');
    }
    return null;
}

// Groups flat row data into per-invoice blocks, in the order the API returned them
// (API already orders by sale_date ASC, bill_no ASC, item id ASC).
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
        const fromDate = document.getElementById('from-date').value; // blank = no lower bound
        const toDate = document.getElementById('to-date').value;     // blank = no upper bound

        const customerId = findIdFromDatalist(customerInput, '#customer-list');
        const branchId = findIdFromDatalist(branchInput, '#branch-list');
        const productId = findIdFromDatalist(productInput, '#product-list');

        let url = `${API_BASE}?action=report`;
        if (fromDate) url += `&from_date=${fromDate}`;
        if (toDate) url += `&to_date=${toDate}`;
        if (customerId) url += `&customer_id=${customerId}`;
        if (branchId) url += `&branch_id=${branchId}`;
        if (productId) url += `&product_id=${productId}`;

        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            allRows = Array.isArray(data.data) ? data.data : [];
            groupedInvoices = groupByInvoice(allRows);
            currentPage = 1;
            currentCurrency = data.currency || '$';
            renderTable();
            updatePaginationControls();
        }
    } catch (e) {
        console.error('Error loading daily product sale report:', e);
    }
}

function renderTable() {
    const tbody = document.querySelector('.table tbody');
    const startIndex = (currentPage - 1) * invoicesPerPage;
    const endIndex = startIndex + invoicesPerPage;
    const pageGroups = groupedInvoices.slice(startIndex, endIndex);

    if (pageGroups.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--subtext);">No records found</td></tr>`;
        renderGrandTotal();
        return;
    }

    let html = '';
    pageGroups.forEach(group => {
        // Invoice header row: Date + Customer Name spans left, Bill No sits on the right
        html += `
            <tr style="background-color: var(--table-header);">
                <td colspan="5"><strong>${formatDateShort(group.sale_date)}&nbsp;&nbsp;${group.customer_name}</strong></td>
                <td style="text-align:right;"><strong>${group.bill_no}</strong></td>
            </tr>
        `;

        // Item rows, S.No restarts at 1 for every invoice
        group.items.forEach((row, idx) => {
            html += `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${row.product_code || '-'}</td>
                    <td>${row.product_name}</td>
                    <td>${currentCurrency}${parseFloat(row.sale_price).toFixed(2)}</td>
                    <td>${parseFloat(row.quantity).toFixed(2)} ${row.unit_symbol || ''}</td>
                    <td>${currentCurrency}${parseFloat(row.net_amount).toFixed(2)}</td>
                </tr>
            `;
        });

        // Per-invoice Total row
        html += `
            <tr style="font-weight: bold;">
                <td colspan="5" style="text-align:right;">Total</td>
                <td>${currentCurrency}${group.total_amount.toFixed(2)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    renderGrandTotal();
}

function renderGrandTotal() {
    const grandTotalQty = allRows.reduce((sum, r) => sum + parseFloat(r.quantity || 0), 0);
    const grandTotalAmount = allRows.reduce((sum, r) => sum + parseFloat(r.net_amount || 0), 0);
    const el = document.getElementById('grand-total-info');
    if (el) {
        el.textContent = `Grand Total: ${groupedInvoices.length} invoice(s), Qty ${grandTotalQty.toFixed(2)}, Amount ${currentCurrency}${grandTotalAmount.toFixed(2)}`;
    }
}

function updatePaginationControls() {
    const totalInvoices = groupedInvoices.length;
    const totalPages = Math.ceil(totalInvoices / invoicesPerPage) || 1;
    const startItem = totalInvoices === 0 ? 0 : (currentPage - 1) * invoicesPerPage + 1;
    const endItem = Math.min(currentPage * invoicesPerPage, totalInvoices);

    document.getElementById('pagination-info').textContent = `Showing invoices ${startItem} - ${endItem} of ${totalInvoices}`;
    document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages}`;
    document.getElementById('prev-btn').disabled = currentPage === 1;
    document.getElementById('next-btn').disabled = currentPage === totalPages || totalPages === 0;
}

function escapeCell(val) {
    let str = (val === undefined || val === null) ? '' : String(val);
    if (/[",\n]/.test(str)) str = '"' + str.replace(/"/g, '""') + '"';
    return str;
}

function exportToExcel() {
    if (!allRows.length) {
        alert('No data available to export.');
        return;
    }
    const rows = [];
    groupedInvoices.forEach(group => {
        rows.push({ 'S.No': '', 'Code': '', 'Detail': `${formatDateShort(group.sale_date)}  ${group.customer_name}`, 'Price': '', 'Qty': '', 'Amount': group.bill_no });
        group.items.forEach((row, idx) => {
            rows.push({
                'S.No': idx + 1,
                'Code': row.product_code || '-',
                'Detail': row.product_name,
                'Price': parseFloat(row.sale_price).toFixed(2),
                'Qty': `${parseFloat(row.quantity).toFixed(2)} ${row.unit_symbol || ''}`,
                'Amount': parseFloat(row.net_amount).toFixed(2)
            });
        });
        rows.push({ 'S.No': '', 'Code': '', 'Detail': '', 'Price': '', 'Qty': 'Total', 'Amount': group.total_amount.toFixed(2) });
    });

    const headers = Object.keys(rows[0]);
    const lines = [headers.map(escapeCell).join(',')];
    rows.forEach(row => lines.push(headers.map(h => escapeCell(row[h])).join(',')));
    const csvContent = '\uFEFF' + lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `daily_product_sale_report_${todayStr()}.csv`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function printReport() {
    const customerInput = document.getElementById('customer').value;
    const branchInput = document.getElementById('branch').value;
    const productInput = document.getElementById('product').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;

    const customerId = findIdFromDatalist(customerInput, '#customer-list');
    const branchId = findIdFromDatalist(branchInput, '#branch-list');
    const productId = findIdFromDatalist(productInput, '#product-list');

    let url = `print.php?`;
    if (fromDate) url += `from_date=${fromDate}&`;
    if (toDate) url += `to_date=${toDate}&`;
    if (customerId) url += `&customer_id=${customerId}`;
    if (branchId) url += `&branch_id=${branchId}`;
    if (productId) url += `&product_id=${productId}`;
    window.open(url, '_blank');
}

document.addEventListener('DOMContentLoaded', () => {
    // Dates left blank on purpose: no filter selected = show ALL sales.
    document.getElementById('applyFilters').addEventListener('click', loadReport);

    document.getElementById('clearFilters').addEventListener('click', () => {
        document.getElementById('customer').value = '';
        document.getElementById('branch').value = '';
        document.getElementById('product').value = '';
        document.getElementById('from-date').value = '';
        document.getElementById('to-date').value = '';
        loadReport();
    });

    document.getElementById('prev-btn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
            updatePaginationControls();
        }
    });

    document.getElementById('next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(groupedInvoices.length / invoicesPerPage) || 1;
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
            updatePaginationControls();
        }
    });

    loadCustomers();
    loadBranches();
    loadProducts();
    loadReport();
});