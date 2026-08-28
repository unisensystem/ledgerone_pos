document.addEventListener('DOMContentLoaded', function () {
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    let userPermissions = [];

    function checkPermissions() {
        const category = encodeURIComponent('Customer / Supplier');
        const formName = encodeURIComponent('New Customer + Supplier (Both)');
        return fetch(`../../../../server/api/auth/check-permission.php?category=${category}&form_name=${formName}`)
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = '../../../../errors/403.php';
                    throw new Error('No permissions');
                }
                if (data.success && data.permissions) {
                    userPermissions = data.permissions;
                }
            });
    }

    const tableBody = document.getElementById('bothTable');
    const searchInput = document.getElementById('searchInput');
    const rowsPerPage = document.getElementById('rowsPerPage');
    const statusFilter = document.getElementById('statusFilter');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    const paginationInfo = document.getElementById('paginationInfo');
    const tableInfo = document.getElementById('tableInfo');

    const notification = document.getElementById('notification');
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationClose = document.getElementById('notificationClose');

    function showNotification(title, message, type = 'success') {
        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');
        setTimeout(() => notification.classList.remove('show'), 5000);
    }
    notificationClose.addEventListener('click', () => notification.classList.remove('show'));

    let currentPage = 1;
    let totalPages = 1;
    let searchDebounce = null;

    function fetchAndRender() {
        const params = new URLSearchParams({
            search: searchInput.value.trim(),
            status: statusFilter.value,
            page: currentPage,
            limit: rowsPerPage.value
        });

        fetch(`../../../../server/api/customer_supplier/customer_supplier_both/both-list.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    tableBody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:24px; color:var(--error);">${esc(data.message || 'Failed to load')}</td></tr>`;
                    return;
                }
                renderTable(data.rows);
                renderStats(data.stats);
                renderPagination(data.pagination);
            })
            .catch(err => {
                tableBody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:24px; color:var(--error);">Error loading data</td></tr>`;
                console.error(err);
            });
    }

    function renderStats(stats) {
        if (!stats) return;
        document.getElementById('statTotal').textContent = stats.total_both || 0;
        document.getElementById('statActive').textContent = stats.active_both || 0;
        document.getElementById('statBlacklisted').textContent = stats.blacklisted_both || 0;
    }

    function formatBalance(val) {
        const num = parseFloat(val || 0);
        const cls = num > 0 ? 'balance-positive' : (num < 0 ? 'balance-negative' : 'balance-zero');
        return `<span class="${cls}">${num.toFixed(2)}</span>`;
    }

    function renderTable(rows) {
        if (!rows || rows.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="11" style="text-align:center; padding:24px; color:var(--subtext);">No records found</td></tr>`;
            return;
        }
        const startIndex = (currentPage - 1) * parseInt(rowsPerPage.value || 10);
        tableBody.innerHTML = rows.map((row, idx) => {
            const statusBadge = row.is_blacklisted == 1
                ? '<span class="status-badge status-blacklisted">Blacklisted</span>'
                : '<span class="status-badge status-active">Active</span>';
            const customerBal = (parseFloat(row.customer_opening_debit || 0) - parseFloat(row.customer_opening_credit || 0)).toFixed(2);
            const supplierBal = (parseFloat(row.supplier_opening_debit || 0) - parseFloat(row.supplier_opening_credit || 0)).toFixed(2);
            return `
                <tr>
                    <td>${startIndex + idx + 1}</td>
                    <td class="customer-code">${esc(row.customer_code)}</td>
                    <td class="customer-code">${esc(row.supplier_code || '-')}</td>
                    <td class="customer-name">${esc(row.customer_name)}</td>
                    <td>${esc(row.company_name || '-')}</td>
                    <td>${esc(row.primary_phone || '-')}</td>
                    <td>${esc(row.email || '-')}</td>
                    <td>${formatBalance(customerBal)}</td>
                    <td>${formatBalance(supplierBal)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" data-id="${row.customer_id}" title="View"><i class="fas fa-eye"></i></button>
                            ${userPermissions.includes('Edit') ? `<button class="action-btn edit" data-id="${row.customer_id}" title="Edit"><i class="fas fa-edit"></i></button>` : ''}
                            ${userPermissions.includes('Delete') ? `<button class="action-btn delete" data-id="${row.customer_id}" title="Delete"><i class="fas fa-trash"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(pagination) {
        if (!pagination) return;
        totalPages = pagination.pages || 1;
        currentPage = pagination.page || 1;
        const start = pagination.total === 0 ? 0 : (currentPage - 1) * pagination.limit + 1;
        const end = Math.min(currentPage * pagination.limit, pagination.total);
        const text = `Showing ${start}-${end} of ${pagination.total} records`;
        paginationInfo.textContent = text;
        tableInfo.textContent = text;
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => { currentPage = 1; fetchAndRender(); }, 400);
    });
    rowsPerPage.addEventListener('change', function () { currentPage = 1; fetchAndRender(); });
    statusFilter.addEventListener('change', function () { currentPage = 1; fetchAndRender(); });
    prevPageBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; fetchAndRender(); } });
    nextPageBtn.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; fetchAndRender(); } });

    // ===== VIEW MODAL =====
    const viewModal = document.getElementById('viewModal');
    const viewModalClose = document.getElementById('viewModalClose');
    const viewModalCloseBtn = document.getElementById('viewModalCloseBtn');
    const viewFieldsGrid = document.getElementById('viewFieldsGrid');

    function closeViewModal() { viewModal.classList.remove('show'); }
    viewModalClose.addEventListener('click', closeViewModal);
    viewModalCloseBtn.addEventListener('click', closeViewModal);
    viewModal.addEventListener('click', function (e) { if (e.target === viewModal) closeViewModal(); });

    function viewField(label, value, colClass = 'col-6') {
        return `
            <div class="form-group ${colClass}">
                <label>${esc(label)}</label>
                <div class="view-field">${esc(value || '')}</div>
            </div>
        `;
    }

    function openView(customerId) {
        fetch(`../../../../server/api/customer_supplier/customer_supplier_both/both-edit.php?id=${customerId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showNotification('Error', data.message || 'Failed to load record', 'error');
                    return;
                }
                const c = data.customer;
                const s = data.supplier || {};
                viewFieldsGrid.innerHTML =
                    viewField('Party Name', c.customer_name, 'col-12') +
                    viewField('Customer Code', c.customer_code) +
                    viewField('Supplier Code', s.supplier_code) +
                    viewField('Address', c.address, 'col-12') +
                    viewField('Primary Phone', c.primary_phone) +
                    viewField('Secondary Phone', c.secondary_phone) +
                    viewField('Email', c.email) +
                    viewField('Identity Card No', c.identity_card_no) +
                    viewField('Customer Opening Debit', parseFloat(c.opening_debit_amount || 0).toFixed(2)) +
                    viewField('Customer Opening Credit', parseFloat(c.opening_credit_amount || 0).toFixed(2)) +
                    viewField('Supplier Opening Debit', parseFloat(s.opening_debit_amount || 0).toFixed(2)) +
                    viewField('Supplier Opening Credit', parseFloat(s.opening_credit_amount || 0).toFixed(2)) +
                    viewField('STRN', c.strn) +
                    viewField('NTN', c.ntn) +
                    viewField('AIT %', s.ait_percent) +
                    viewField('Credit Days', s.credit_days) +
                    viewField('Party Type', s.party_type) +
                    viewField('Blacklisted', c.is_blacklisted == 1 ? 'Yes' : 'No');
                viewModal.classList.add('show');
            })
            .catch(() => showNotification('Error', 'Failed to load record', 'error'));
    }

    tableBody.addEventListener('click', function (e) {
        const btn = e.target.closest('button.action-btn');
        if (!btn) return;
        const id = btn.dataset.id;

        if (btn.classList.contains('view')) {
            openView(id);
        } else if (btn.classList.contains('edit')) {
            window.location.href = `customer-supplier-add.php?edit=${id}`;
        } else if (btn.classList.contains('delete')) {
            if (confirm('Are you sure you want to delete this Customer + Supplier record? This will remove BOTH the customer and supplier record permanently.')) {
                fetch('../../../../server/api/customer_supplier/customer_supplier_both/both-delete.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: id })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Success', data.message, 'success');
                        fetchAndRender();
                    } else {
                        showNotification('Error', data.message, 'error');
                    }
                })
                .catch(() => showNotification('Error', 'Failed to delete record', 'error'));
            }
        }
    });

    checkPermissions().then(() => {
        fetchAndRender();
        const addBtn = document.querySelector('.header-actions a.btn-primary');
        if (addBtn && !userPermissions.includes('Add')) {
            addBtn.style.display = 'none';
        }
    });
});
