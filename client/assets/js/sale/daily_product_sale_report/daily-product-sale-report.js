const API_BASE = '../../../../server/api/sale/daily_product_sale_report/daily-product-sale-report.php';

let currentPage = 1;
const invoicesPerPage = 15; // pagination is by SALE, not by item row
let allInvoices = []; // [{invoice_id, bill_no, sale_date, customer_name, ..., items:[...], sub_total, tax, round_off}]
let currentCurrency = '$';

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

// DB date (YYYY-MM-DD) -> DD/MM/YYYY like the target print format
function formatDateShort(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function money(val) {
    return `${currentCurrency}${(parseFloat(val) || 0).toFixed(2)}`;
}

// Customer/product names etc. are tenant-entered data rendered via innerHTML — escape before interpolating.
function esc(val) {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
            allInvoices = Array.isArray(data.data) ? data.data : [];
            currentPage = 1;
            currentCurrency = data.currency || '$';
            renderReport();
            updatePaginationControls();
        }
    } catch (e) {
        console.error('Error loading sale report:', e);
    }
}

function statusPill(status) {
    const s = ['Paid', 'Partial', 'Unpaid'].includes(status) ? status : 'Unknown';
    return `<span class="status-pill status-${s}">${s}</span>`;
}

function renderReport() {
    const container = document.getElementById('reportContainer');
    const startIndex = (currentPage - 1) * invoicesPerPage;
    const endIndex = startIndex + invoicesPerPage;
    const pageInvoices = allInvoices.slice(startIndex, endIndex);

    if (pageInvoices.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding: 32px; color: var(--subtext);">No sales found</div>`;
        renderGrandTotal();
        return;
    }

    container.innerHTML = pageInvoices.map(inv => `
        <div class="sale-block">
            <div class="sale-block-header">
                <div class="field"><label>Date</label><span>${esc(formatDateShort(inv.sale_date))}</span></div>
                <div class="field"><label>Bill No</label><span>${esc(inv.bill_no)}</span></div>
                <div class="field"><label>Ref No</label><span>${esc(inv.ref_no) || '-'}</span></div>
                <div class="field"><label>Party Name</label><span>${esc(inv.customer_name)}</span></div>
                <div class="field"><label>Phone No</label><span>${esc(inv.phone) || '-'}</span></div>
                <div class="field"><label>Tax No</label><span>${esc(inv.tax_no) || '-'}</span></div>
                <div class="field"><label>Txn Type</label><span>Sale</span></div>
                <div class="field"><label>Status</label><span>${statusPill(inv.status)}</span></div>
                <div class="field"><label>Total Amount</label><span>${money(inv.total_amount)}</span></div>
                <div class="field"><label>Payment Type</label><span>${esc(inv.payment_type)}</span></div>
                <div class="field"><label>Received/Paid</label><span>${money(inv.paid_amount)}</span></div>
                <div class="field"><label>Balance</label><span>${money(inv.balance_amount)}</span></div>
            </div>
            <table class="sale-items-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Batch</th>
                        <th class="num">Quantity</th>
                        <th class="num">Price/Unit</th>
                        <th class="num">Discount</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    ${inv.items.map(item => `
                        <tr>
                            <td>${esc(item.product_name)}</td>
                            <td>${esc(item.batch_no) || '-'}</td>
                            <td class="num">${parseFloat(item.quantity).toFixed(2)} ${esc(item.unit_symbol)}</td>
                            <td class="num">${money(item.sale_price)}</td>
                            <td class="num">${parseFloat(item.discount_percent).toFixed(1)}%</td>
                            <td class="num">${money(item.amount)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <div class="sale-block-footer">
                <div class="totals-line">Sub Total: <strong>${money(inv.sub_total)}</strong></div>
                <div class="totals-line">Tax: <strong>${money(inv.tax)}</strong></div>
                <div class="totals-line">Round off: <strong>${money(inv.round_off)}</strong></div>
            </div>
        </div>
    `).join('');

    renderGrandTotal();
}

function renderGrandTotal() {
    const grandTotalAmount = allInvoices.reduce((sum, inv) => sum + parseFloat(inv.total_amount || 0), 0);
    const el = document.getElementById('grand-total-info');
    if (el) {
        el.textContent = `Total Sale (${allInvoices.length} invoice${allInvoices.length === 1 ? '' : 's'}): ${money(grandTotalAmount)}`;
    }
}

function updatePaginationControls() {
    const totalInvoices = allInvoices.length;
    const totalPages = Math.ceil(totalInvoices / invoicesPerPage) || 1;
    const startItem = totalInvoices === 0 ? 0 : (currentPage - 1) * invoicesPerPage + 1;
    const endItem = Math.min(currentPage * invoicesPerPage, totalInvoices);

    document.getElementById('pagination-info').textContent = `Showing sales ${startItem} - ${endItem} of ${totalInvoices}`;
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
    if (!allInvoices.length) {
        alert('No data available to export.');
        return;
    }
    const rows = [];
    allInvoices.forEach(inv => {
        rows.push({
            'Date': formatDateShort(inv.sale_date), 'Bill No': inv.bill_no, 'Ref No': inv.ref_no || '',
            'Party Name': inv.customer_name, 'Phone No': inv.phone || '', 'Tax No': inv.tax_no || '',
            'Status': inv.status, 'Payment Type': inv.payment_type,
            'Item Name': '', 'Batch': '', 'Quantity': '', 'Price/Unit': '', 'Discount': '',
            'Amount': inv.total_amount.toFixed(2), 'Paid': inv.paid_amount.toFixed(2), 'Balance': inv.balance_amount.toFixed(2)
        });
        inv.items.forEach(item => {
            rows.push({
                'Date': '', 'Bill No': '', 'Ref No': '', 'Party Name': '', 'Phone No': '', 'Tax No': '',
                'Status': '', 'Payment Type': '',
                'Item Name': item.product_name, 'Batch': item.batch_no || '',
                'Quantity': `${parseFloat(item.quantity).toFixed(2)} ${item.unit_symbol || ''}`,
                'Price/Unit': parseFloat(item.sale_price).toFixed(2),
                'Discount': `${parseFloat(item.discount_percent).toFixed(1)}%`,
                'Amount': item.amount.toFixed(2), 'Paid': '', 'Balance': ''
            });
        });
        rows.push({
            'Date': '', 'Bill No': '', 'Ref No': '', 'Party Name': '', 'Phone No': '', 'Tax No': '',
            'Status': '', 'Payment Type': '', 'Item Name': '', 'Batch': '', 'Quantity': '',
            'Price/Unit': 'Sub Total / Tax / Round off', 'Discount': '',
            'Amount': `${inv.sub_total.toFixed(2)} / ${inv.tax.toFixed(2)} / ${inv.round_off.toFixed(2)}`, 'Paid': '', 'Balance': ''
        });
    });

    const headers = Object.keys(rows[0]);
    const lines = [headers.map(escapeCell).join(',')];
    rows.forEach(row => lines.push(headers.map(h => escapeCell(row[h])).join(',')));
    const csvContent = '﻿' + lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sale_report_${todayStr()}.csv`;
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
            renderReport();
            updatePaginationControls();
        }
    });

    document.getElementById('next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(allInvoices.length / invoicesPerPage) || 1;
        if (currentPage < totalPages) {
            currentPage++;
            renderReport();
            updatePaginationControls();
        }
    });

    loadCustomers();
    loadBranches();
    loadProducts();
    loadReport();
});
