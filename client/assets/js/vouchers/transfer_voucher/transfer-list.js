// ─── State ────────────────────────────────────────────────────────────────────
let currentPage    = 1;
let currentFilters = {};
let deleteVoucherId = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.getElementById('newVoucherBtn').addEventListener('click', () => {
    window.location.href = 'transfer-add.php';
});

loadFiltersData();
loadVouchers();

// ─── Load filter dropdowns ────────────────────────────────────────────────────
function loadFiltersData() {
    fetch('../../../../server/api/vouchers/transfer_voucher/get-companies.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('companyFilter');
                data.data.forEach(c => sel.appendChild(new Option(c.company_name, c.id)));
            }
        });

    // Load edit modal dropdowns
    fetch('../../../../server/api/vouchers/transfer_voucher/get-currencies.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('editCurrency');
                data.data.forEach(c => {
                    const o = new Option(`${c.name} (${c.symbol})`, c.currency_id);
                    sel.appendChild(o);
                });
            }
        });

    fetch('../../../../server/api/vouchers/transfer_voucher/get-payment-methods.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('editPaymentMethod');
                data.data.forEach(m => sel.appendChild(new Option(m.name, m.id)));
            }
        });

    fetch('../../../../server/api/vouchers/transfer_voucher/get-bank-accounts.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('editBankAccount');
                data.data.forEach(b => sel.appendChild(new Option(`${b.bank_name} - ${b.account_number}`, b.id)));
            }
        });

    fetch('../../../../server/api/vouchers/transfer_voucher/get-companies.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById('editCompany');
                data.data.forEach(c => sel.appendChild(new Option(c.company_name, c.id)));
            }
        });
}

// ─── Load Vouchers ────────────────────────────────────────────────────────────
function loadVouchers(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ page, limit: 10, ...currentFilters });

    fetch(`../../../../server/api/vouchers/transfer_voucher/transfer-list.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTable(data.data);
                renderPagination(data.pagination);
                renderSummary(data.summary);
            }
        })
        .catch(() => {
            document.getElementById('vouchersTableBody').innerHTML =
                '<tr><td colspan="7" style="text-align:center; color:var(--error);">Failed to load data</td></tr>';
        });
}

// ─── Render Table ─────────────────────────────────────────────────────────────
function renderTable(vouchers) {
    const tbody = document.getElementById('vouchersTableBody');
    if (!vouchers.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--subtext);">No vouchers found</td></tr>';
        return;
    }

    tbody.innerHTML = vouchers.map(v => {
        const fromName = v.from_type === 'customer'
            ? `<span class="badge badge-customer">C</span> ${v.from_customer_code || ''} - ${v.from_customer_name || ''}`
            : `<span class="badge badge-supplier">S</span> ${v.from_supplier_code || ''} - ${v.from_supplier_name || ''}`;
        const toName = v.to_type === 'customer'
            ? `<span class="badge badge-customer">C</span> ${v.to_customer_code || ''} - ${v.to_customer_name || ''}`
            : `<span class="badge badge-supplier">S</span> ${v.to_supplier_code || ''} - ${v.to_supplier_name || ''}`;

        const date = new Date(v.voucher_date).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
        const amount = `${v.currency_symbol || ''}${parseFloat(v.amount).toFixed(2)}`;

        return `<tr>
            <td><strong>${v.voucher_number}</strong></td>
            <td>${date}</td>
            <td>${fromName}</td>
            <td>${toName}</td>
            <td class="amount">${amount}</td>
            <td>${v.slip_no || '-'}</td>
            <td>
                <div class="actions">
                    <button class="action-btn edit" title="Edit" onclick="openEditModal(${v.id})"><i class="fas fa-edit"></i></button>
                    <button class="action-btn print" title="Print" onclick="printVoucher(${v.id})"><i class="fas fa-print"></i></button>
                    <button class="action-btn delete" title="Delete" onclick="openDeleteModal(${v.id}, '${v.voucher_number}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ─── Render Summary ───────────────────────────────────────────────────────────
function renderSummary(summary) {
    document.getElementById('summaryTotalValue').textContent = parseFloat(summary.grand_total).toFixed(2);
    const methodsEl = document.getElementById('summaryMethods');
    const icons = { 'Cash': 'fa-money-bill-wave', 'Cheque': 'fa-money-check', 'Bank Transfer': 'fa-university' };
    methodsEl.innerHTML = summary.by_payment_method.map(m => `
        <div class="summary-card summary-method-card">
            <div class="summary-icon"><i class="fas ${icons[m.payment_method] || 'fa-credit-card'}"></i></div>
            <div class="summary-info">
                <div class="summary-label">${m.payment_method}</div>
                <div class="summary-value">${parseFloat(m.total).toFixed(2)}</div>
            </div>
        </div>`).join('');
}

// ─── Render Pagination ────────────────────────────────────────────────────────
function renderPagination(p) {
    document.getElementById('paginationInfo').textContent =
        `Showing ${((p.current_page - 1) * p.limit) + 1} to ${Math.min(p.current_page * p.limit, p.total_records)} of ${p.total_records} entries`;

    const ctrl = document.getElementById('paginationControls');
    let html = `<button class="pagination-btn" ${!p.has_prev ? 'disabled' : ''} onclick="loadVouchers(${p.current_page - 1})"><i class="fas fa-chevron-left"></i></button>`;

    const start = Math.max(1, p.current_page - 2);
    const end   = Math.min(p.total_pages, p.current_page + 2);
    for (let i = start; i <= end; i++) {
        html += `<button class="pagination-btn ${i === p.current_page ? 'active' : ''}" onclick="loadVouchers(${i})">${i}</button>`;
    }
    html += `<button class="pagination-btn" ${!p.has_next ? 'disabled' : ''} onclick="loadVouchers(${p.current_page + 1})"><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML = html;
}

// ─── Filters ──────────────────────────────────────────────────────────────────
document.getElementById('applyFiltersBtn').addEventListener('click', () => {
    currentFilters = {
        date_from:  document.getElementById('dateFrom').value,
        date_to:    document.getElementById('dateTo').value,
        company_id: document.getElementById('companyFilter').value,
        from_type:  document.getElementById('fromTypeFilter').value,
        to_type:    document.getElementById('toTypeFilter').value,
        search:     document.getElementById('searchInput').value,
    };
    Object.keys(currentFilters).forEach(k => { if (!currentFilters[k]) delete currentFilters[k]; });
    loadVouchers(1);
});

document.getElementById('resetFiltersBtn').addEventListener('click', () => {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value   = '';
    document.getElementById('companyFilter').value   = '';
    document.getElementById('fromTypeFilter').value  = '';
    document.getElementById('toTypeFilter').value    = '';
    document.getElementById('searchInput').value     = '';
    currentFilters = {};
    loadVouchers(1);
});

document.getElementById('searchInput').addEventListener('input', function () {
    currentFilters.search = this.value;
    if (!this.value) delete currentFilters.search;
    loadVouchers(1);
});

document.getElementById('refreshBtn').addEventListener('click', () => loadVouchers(currentPage));

// ─── Export CSV ───────────────────────────────────────────────────────────────
document.getElementById('exportBtn').addEventListener('click', () => {
    const params = new URLSearchParams({ limit: 9999, ...currentFilters });
    fetch(`../../../../server/api/vouchers/transfer_voucher/transfer-list.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data.length) { alert('No data to export'); return; }
            const headers = ['Voucher #', 'Date', 'From Type', 'From Party', 'To Type', 'To Party', 'Amount', 'Payment Method'];
            const rows = data.data.map(v => [
                v.voucher_number,
                v.voucher_date,
                v.from_type,
                v.from_type === 'customer' ? `${v.from_customer_code} - ${v.from_customer_name}` : `${v.from_supplier_code} - ${v.from_supplier_name}`,
                v.to_type,
                v.to_type === 'customer' ? `${v.to_customer_code} - ${v.to_customer_name}` : `${v.to_supplier_code} - ${v.to_supplier_name}`,
                parseFloat(v.amount).toFixed(2),
                v.payment_method || ''
            ]);
            const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `transfer_vouchers_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
        });
});

// ─── Print ────────────────────────────────────────────────────────────────────
function printVoucher(id) {
    window.open(`print.php?id=${id}`, '_blank');
}

// ─── Edit Modal ───────────────────────────────────────────────────────────────
function openEditModal(id) {
    fetch(`../../../../server/api/vouchers/transfer_voucher/transfer-get.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Failed to load voucher'); return; }
            const v = data.data;
            document.getElementById('editVoucherId').value      = v.id;
            document.getElementById('editVoucherNumber').value  = v.voucher_number;
            document.getElementById('editVoucherDate').value    = v.voucher_date;
            document.getElementById('editAmount').value         = v.amount;
            document.getElementById('editDescription').value    = v.description || '';
            document.getElementById('editFromParty').value      = v.from_display || '';
            document.getElementById('editToParty').value        = v.to_display   || '';
            document.getElementById('editChequeNo').value       = v.cheque_no    || '';
            document.getElementById('editChequeDate').value     = v.cheque_date  || '';
            document.getElementById('editSlipNo').value         = v.slip_no      || '';
            document.getElementById('editSlipAttachment').value = '';

            // Show existing attachment info
            const curAttEl = document.getElementById('editCurrentAttachment');
            if (v.attachment) {
                curAttEl.innerHTML = `Current: <a href="../../../../client/assets/uploads/transfer_voucher/${v.attachment}" target="_blank">${v.attachment}</a> <small>(upload new to replace)</small>`;
            } else {
                curAttEl.innerHTML = '';
            }

            setSelectValue('editCompany',       v.company_id);
            setSelectValue('editCurrency',      v.currency_id);
            setSelectValue('editPaymentMethod', v.payment_method_id);
            setSelectValue('editBankAccount',   v.bank_account_id);

            toggleEditPaymentFields(parseInt(v.payment_method_id));
            document.getElementById('editModal').classList.add('show');
        });
}

function setSelectValue(id, val) {
    const sel = document.getElementById(id);
    if (sel && val) sel.value = val;
}

document.getElementById('editPaymentMethod').addEventListener('change', function () {
    toggleEditPaymentFields(parseInt(this.value));
});

function toggleEditPaymentFields(id) {
    const bankGrp    = document.getElementById('editBankAccountGroup');
    const chequeNo   = document.getElementById('editChequeNoGroup');
    const chequeDt   = document.getElementById('editChequeDateGroup');
    const slipNoGrp  = document.getElementById('editSlipNoGroup');
    const attachGrp  = document.getElementById('editAttachmentGroup');

    // Hide all first
    [bankGrp, chequeNo, chequeDt, slipNoGrp, attachGrp].forEach(g => g.style.display = 'none');

    if (id === 6) {
        // PDC/Cheque
        bankGrp.style.display  = 'flex';
        chequeNo.style.display = 'flex';
        chequeDt.style.display = 'flex';
    } else if (id && id !== 7) {
        // Bank Transfer
        slipNoGrp.style.display = 'flex';
        attachGrp.style.display = 'flex';
    }
    // id=7 Cash — nothing extra
}

document.getElementById('editModalClose').addEventListener('click', closeEditModal);
document.getElementById('cancelEditBtn').addEventListener('click', closeEditModal);
document.getElementById('editModal').addEventListener('click', e => { if (e.target === document.getElementById('editModal')) closeEditModal(); });

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

document.getElementById('saveEditBtn').addEventListener('click', () => {
    const id = document.getElementById('editVoucherId').value;
    const btn = document.getElementById('saveEditBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const fd = new FormData();
    fd.append('voucher_date',      document.getElementById('editVoucherDate').value);
    fd.append('company_id',        document.getElementById('editCompany').value);
    fd.append('currency_id',       document.getElementById('editCurrency').value);
    fd.append('amount',            document.getElementById('editAmount').value);
    fd.append('payment_method_id', document.getElementById('editPaymentMethod').value);
    fd.append('bank_account_id',   document.getElementById('editBankAccount').value || '');
    fd.append('cheque_no',         document.getElementById('editChequeNo').value    || '');
    fd.append('cheque_date',       document.getElementById('editChequeDate').value  || '');
    fd.append('slip_no',           document.getElementById('editSlipNo').value      || '');
    fd.append('description',       document.getElementById('editDescription').value || '');

    const curAttEl = document.getElementById('editCurrentAttachment');
    const keepAtt  = curAttEl.querySelector('a')?.textContent || '';
    fd.append('keep_attachment', keepAtt);

    const file = document.getElementById('editSlipAttachment').files[0];
    if (file) fd.append('attachment', file);

    fetch(`../../../../server/api/vouchers/transfer_voucher/transfer-edit.php?id=${id}`, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            loadVouchers(currentPage);
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(() => alert('Network error'))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    });
});

// ─── Delete Modal ─────────────────────────────────────────────────────────────
function openDeleteModal(id, voucherNumber) {
    deleteVoucherId = id;
    document.getElementById('deleteVoucherNumber').textContent = voucherNumber;
    document.getElementById('deleteModal').classList.add('show');
}

document.getElementById('deleteModalClose').addEventListener('click', closeDeleteModal);
document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteModal);
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === document.getElementById('deleteModal')) closeDeleteModal(); });

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    deleteVoucherId = null;
}

document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
    if (!deleteVoucherId) return;
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    fetch(`../../../../server/api/vouchers/transfer_voucher/transfer-delete.php?id=${deleteVoucherId}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeDeleteModal();
                loadVouchers(currentPage);
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error'))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Delete';
        });
});
