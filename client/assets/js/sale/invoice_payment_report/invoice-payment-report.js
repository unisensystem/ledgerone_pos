const API_BASE = '../../../../server/api/sale/invoice_payment_report/invoice-payment-report.php';

let currentPage = 1;
const rowsPerPage = 25;
let allRows = [];
let currentCurrency = '$';

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yy = String(d.getFullYear()).slice(-2);
    return `${dd}/${mm}/${yy}`;
}

function findIdFromDatalist(inputVal, datalistSelector) {
    if (!inputVal) return null;
    const options = document.querySelectorAll(`${datalistSelector} option`);
    for (const opt of options) {
        if (opt.value === inputVal) return opt.getAttribute('data-id');
    }
    return null;
}

async function loadCustomers() {
    try {
        const res = await fetch(`${API_BASE}?action=customers`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('customer-list').innerHTML =
                data.data.map(c => `<option value="${esc(c.customer_name)}" data-id="${c.id}">`).join('');
        }
    } catch (e) {
        console.error('Error loading customers:', e);
    }
}

async function loadCompanies() {
    try {
        const res = await fetch(`${API_BASE}?action=companies`);
        const data = await res.json();
        if (data.success) {
            const sel = document.getElementById('company');
            data.data.forEach(c => {
                sel.innerHTML += `<option value="${c.id}">${esc(c.company_name)}</option>`;
            });
        }
    } catch (e) {
        console.error('Error loading companies:', e);
    }
}

async function loadBranches() {
    try {
        const res = await fetch(`${API_BASE}?action=branches`);
        const data = await res.json();
        if (data.success) {
            const sel = document.getElementById('branch');
            data.data.forEach(b => {
                sel.innerHTML += `<option value="${b.id}">${esc(b.branch_name)}</option>`;
            });
        }
    } catch (e) {
        console.error('Error loading branches:', e);
    }
}

async function loadReport() {
    try {
        const fromDate = document.getElementById('from-date').value;
        const toDate = document.getElementById('to-date').value;
        const customerInput = document.getElementById('customer').value;
        const customerId = findIdFromDatalist(customerInput, '#customer-list');
        const companyId = document.getElementById('company').value;
        const branchId = document.getElementById('branch').value;
        const paymentMethod = document.getElementById('payment-method').value;
        const paymentSource = document.getElementById('payment-source').value;
        const onlyPaid = document.getElementById('only-paid').checked;

        let url = `${API_BASE}?action=report`;
        if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
        if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;
        if (customerId) url += `&customer_id=${encodeURIComponent(customerId)}`;
        if (companyId) url += `&company_id=${encodeURIComponent(companyId)}`;
        if (branchId) url += `&branch_id=${encodeURIComponent(branchId)}`;
        if (paymentMethod) url += `&payment_method=${encodeURIComponent(paymentMethod)}`;
        if (paymentSource) url += `&payment_source=${encodeURIComponent(paymentSource)}`;
        if (onlyPaid) url += `&only_paid=1`;

        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            allRows = data.data || [];
            currentCurrency = data.currency || '$';
            currentPage = 1;
            renderSummary(data.summary);
            renderTable();
            updatePaginationControls();
        } else {
            console.error('Report error:', data.message);
            allRows = [];
            renderTable();
            updatePaginationControls();
        }
    } catch (e) {
        console.error('Error loading invoice payment report:', e);
    }
}

function renderSummary(summary) {
    const bar = document.getElementById('summaryBar');
    if (!bar || !summary) return;
    bar.innerHTML = `
        <span><strong>${summary.total_invoices}</strong> invoice(s)</span>
        <span>Invoice Total: <strong>${currentCurrency}${summary.total_invoice_amount.toFixed(2)}</strong></span>
        <span>Total Paid: <strong style="color:#2fbf71;">${currentCurrency}${summary.total_paid.toFixed(2)}</strong></span>
        <span>Balance Due: <strong style="color:${summary.total_balance > 0.005 ? '#dc3545' : '#2fbf71'};">${currentCurrency}${summary.total_balance.toFixed(2)}</strong></span>
        <span style="border-left:1px solid var(--border); padding-left:24px;">Paid via Invoice: <strong style="color:#9a6300;">${currentCurrency}${summary.total_paid_via_invoice.toFixed(2)}</strong></span>
        <span>Paid via Receive Voucher: <strong style="color:#0b6e99;">${currentCurrency}${summary.total_paid_via_voucher.toFixed(2)}</strong></span>
    `;
}

function methodBadge(methodId, methodName) {
    if (!methodId) return `<span class="method-badge method-none">-</span>`;
    const cls = methodId == 1 ? 'method-cash' : (methodId == 5 ? 'method-bank' : 'method-none');
    return `<span class="method-badge ${cls}">${esc(methodName || '-')}</span>`;
}

function sourceBadge(source) {
    if (source === 'invoice') return `<span class="source-badge source-invoice" title="Recorded together with the invoice (within 5 min of save)">Invoice</span>`;
    if (source === 'receive_voucher') return `<span class="source-badge source-voucher" title="Entered separately, later, via the Receive Voucher module">Receive Voucher</span>`;
    return `<span class="source-badge source-none">-</span>`;
}

function fillBadge(autoFill) {
    if (autoFill === 'yes') return `<span class="fill-badge fill-auto">Auto</span>`;
    if (autoFill === 'no') return `<span class="fill-badge">Manual</span>`;
    return `<span class="fill-badge">-</span>`;
}

function renderTable() {
    const tbody = document.getElementById('reportBody');
    if (!tbody) return;

    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const pageRows = allRows.slice(startIndex, endIndex);

    if (pageRows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="13" style="text-align:center; padding:24px; color:var(--subtext);">No records found</td></tr>`;
        return;
    }

    tbody.innerHTML = pageRows.map((row, idx) => {
        const netAmount = parseFloat(row.invoice_net_amount || 0);
        const paidAmount = parseFloat(row.paid_amount || 0);
        const balance = netAmount - paidAmount;
        const balanceClass = balance > 0.005 ? 'balance-due' : 'balance-positive';

        return `
            <tr>
                <td>${startIndex + idx + 1}</td>
                <td>${esc(row.bill_no)}</td>
                <td>${formatDateShort(row.sale_date)}</td>
                <td>${esc(row.customer_name)}</td>
                <td>${currentCurrency}${netAmount.toFixed(2)}</td>
                <td>${currentCurrency}${paidAmount.toFixed(2)}</td>
                <td class="${balanceClass}">${currentCurrency}${balance.toFixed(2)}</td>
                <td>${sourceBadge(row.payment_source)}</td>
                <td>${fillBadge(row.amount_paid_auto_fill)}</td>
                <td>${methodBadge(row.payment_method_id, row.payment_method_name)}</td>
                <td>${esc(row.bank_name || '-')}</td>
                <td>${esc(row.voucher_number || '-')}</td>
                <td>${row.voucher_date ? formatDateShort(row.voucher_date) : '-'}</td>
            </tr>
        `;
    }).join('');
}

function updatePaginationControls() {
    const totalRows = allRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
    const startItem = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
    const endItem = Math.min(currentPage * rowsPerPage, totalRows);

    const paginationInfo = document.getElementById('pagination-info');
    const pageInfo = document.getElementById('page-info');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');

    if (paginationInfo) paginationInfo.textContent = `Showing ${startItem} - ${endItem} of ${totalRows} entries`;
    if (pageInfo) pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    if (prevBtn) prevBtn.disabled = currentPage === 1;
    if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalPages === 0;
}

function escapeCsvCell(val) {
    let str = (val === undefined || val === null) ? '' : String(val);
    if (/[",\n]/.test(str)) str = '"' + str.replace(/"/g, '""') + '"';
    return str;
}

function exportToCSV() {
    if (!allRows.length) {
        alert('No data available to export.');
        return;
    }

    const headers = ['Bill No', 'Date', 'Party (Customer)', 'Net Amount', 'Paid Amount', 'Balance', 'Source', 'Fill', 'Method', 'Bank', 'Voucher No', 'Paid On'];
    const lines = [headers.map(escapeCsvCell).join(',')];

    const sourceLabel = s => s === 'invoice' ? 'Invoice' : (s === 'receive_voucher' ? 'Receive Voucher' : '-');
    const fillLabel = f => f === 'yes' ? 'Auto' : (f === 'no' ? 'Manual' : '-');

    allRows.forEach(row => {
        const netAmount = parseFloat(row.invoice_net_amount || 0);
        const paidAmount = parseFloat(row.paid_amount || 0);
        const balance = netAmount - paidAmount;
        lines.push([
            row.bill_no,
            formatDateShort(row.sale_date),
            row.customer_name,
            netAmount.toFixed(2),
            paidAmount.toFixed(2),
            balance.toFixed(2),
            sourceLabel(row.payment_source),
            fillLabel(row.amount_paid_auto_fill),
            row.payment_method_name || '-',
            row.bank_name || '-',
            row.voucher_number || '-',
            row.voucher_date ? formatDateShort(row.voucher_date) : '-'
        ].map(escapeCsvCell).join(','));
    });

    const csvContent = '﻿' + lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `invoice-payment-report_${todayStr()}.csv`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', () => {
    const applyFilters = document.getElementById('applyFilters');
    const clearFilters = document.getElementById('clearFilters');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const printBtn = document.getElementById('printBtn');
    const exportCSVBtn = document.getElementById('exportCSVBtn');

    // Default to current month, matching the other Sale reports
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from-date').value = firstDay.toISOString().slice(0, 10);
    document.getElementById('to-date').value = today.toISOString().slice(0, 10);

    if (applyFilters) applyFilters.addEventListener('click', loadReport);

    if (clearFilters) {
        clearFilters.addEventListener('click', () => {
            document.getElementById('from-date').value = firstDay.toISOString().slice(0, 10);
            document.getElementById('to-date').value = today.toISOString().slice(0, 10);
            document.getElementById('customer').value = '';
            document.getElementById('company').value = '';
            document.getElementById('branch').value = '';
            document.getElementById('payment-method').value = '';
            document.getElementById('payment-source').value = '';
            document.getElementById('only-paid').checked = false;
            loadReport();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
                updatePaginationControls();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(allRows.length / rowsPerPage) || 1;
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
                updatePaginationControls();
            }
        });
    }

    if (printBtn) printBtn.addEventListener('click', () => window.print());
    if (exportCSVBtn) exportCSVBtn.addEventListener('click', exportToCSV);

    loadCustomers();
    loadCompanies();
    loadBranches();
    loadReport();
});
