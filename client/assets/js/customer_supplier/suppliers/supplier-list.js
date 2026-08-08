let suppliers = [];
let currentPage = 1;
let totalPages = 1;
let rowsPerPage = 10;
let editEmployees = [];
let editSubAccountCounter = 0;

document.addEventListener('DOMContentLoaded', function () {
    const suppliersTable = document.getElementById('suppliersTable');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const companyFilter = document.getElementById('companyFilter');

    let userPermissions = [];

    checkPermissions().then(() => {
        loadCompanyFilter();
        loadSuppliers();
    });

    function loadSuppliers() {
        const isAll = rowsPerPage === 'all';
        const params = new URLSearchParams({
            search: searchInput.value,
            status: statusFilter.value,
            company: companyFilter.value,
            page: currentPage,
            limit: isAll ? 999999 : rowsPerPage
        });
        if (isAll) params.set('show_all', '1');

        fetch(`../../../../server/api/customer_supplier/suppliers/supplier-list.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    suppliers = data.suppliers;
                    renderSuppliers(suppliers);
                    updateStats(data.stats);
                    updatePagination(data.pagination);
                } else {
                    showNotification('Error', data.message, 'error');
                }
            })
            .catch(() => showNotification('Error', 'Failed to load suppliers', 'error'));
    }

    function renderSuppliers(list) {
        suppliersTable.innerHTML = '';
        const startSr = ((currentPage - 1) * (rowsPerPage === 'all' ? list.length : rowsPerPage)) + 1;

        list.forEach((supplier, index) => {
            const status = supplier.is_blacklisted ? 'blacklisted' : 'active';
            const editBtn = userPermissions.includes('Edit')
                ? `<button class="action-btn edit" title="Edit Supplier"><i class="fas fa-edit"></i></button>` : '';
            const deleteBtn = userPermissions.includes('Delete')
                ? `<button class="action-btn delete" title="Delete Supplier"><i class="fas fa-trash"></i></button>` : '';

            const balance = parseFloat(supplier.current_balance || 0);
            const row = document.createElement('tr');
            row.dataset.id = supplier.id;
            row.innerHTML = `
                <td>${startSr + index}</td>
                <td><span class="customer-code">${supplier.supplier_code || '-'}</span></td>
                <td><span class="customer-name">${supplier.supplier_name}</span></td>
                <td>${supplier.brand_name || '-'}</td>
                <td>${supplier.address || '-'}</td>
                <td>${supplier.city || '-'}</td>
                <td>${supplier.primary_phone || '-'}</td>
                <td>${supplier.credit_days || '-'}</td>
                <td><span class="balance-badge">${currencySymbol}${balance.toFixed(2)}</span></td>
                <td><span class="status-badge status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                <td>
                    <div class="action-buttons">
                        ${editBtn}
                        ${deleteBtn}
                        <button class="action-btn" title="View Details"><i class="fas fa-eye"></i></button>
                    </div>
                </td>
            `;
            suppliersTable.appendChild(row);
        });
    }

    function updateStats(stats) {
        document.getElementById('statTotal').textContent = stats.total_suppliers ?? '-';
        document.getElementById('statActive').textContent = stats.active_suppliers ?? '-';
        document.getElementById('statBlacklisted').textContent = stats.blacklisted_suppliers ?? '-';
        const payables = parseFloat(stats.total_payables || 0);
        document.getElementById('statPayables').textContent = currencySymbol + payables.toFixed(2);
    }

    function updatePagination(pagination) {
        currentPage = pagination.page;
        totalPages = pagination.pages;
        const tableInfo = document.getElementById('tableInfo');
        const start = ((pagination.page - 1) * pagination.limit) + 1;
        const end = Math.min(pagination.page * pagination.limit, pagination.total);
        tableInfo.textContent = `Showing ${start}-${end} of ${pagination.total} suppliers`;

        const paginationControls = document.querySelector('.pagination-controls');
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');

        paginationControls.querySelectorAll('.pagination-btn:not(#prevPage):not(#nextPage)').forEach(b => b.remove());

        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => { currentPage = i; loadSuppliers(); });
            paginationControls.insertBefore(pageBtn, nextBtn);
        }

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    }

    let searchTimeout;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadSuppliers(); }, 500);
    });

    function loadCompanyFilter() {
        fetch('../../../../server/api/customer_supplier/suppliers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.companies.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.company_name;
                        companyFilter.appendChild(option);
                    });
                }
            });
    }

    statusFilter.addEventListener('change', () => { currentPage = 1; loadSuppliers(); });
    companyFilter.addEventListener('change', () => { currentPage = 1; loadSuppliers(); });

    const rowsPerPageSelect = document.getElementById('rowsPerPage');
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function () {
            rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
            currentPage = 1;
            loadSuppliers();
        });
    }

    // ── Delete Modal ──────────────────────────────────────────────────────────
    const deleteModal = document.getElementById('deleteModal');
    const deleteModalClose = document.getElementById('deleteModalClose');
    const deleteCancelBtn = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
    const deleteSupplierName = document.getElementById('deleteSupplierName');
    let currentDeletingSupplierId = null;

    function openDeleteModal(supplierId, supplierName) {
        currentDeletingSupplierId = supplierId;
        deleteSupplierName.textContent = supplierName;
        deleteModal.classList.add('show');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('show');
        currentDeletingSupplierId = null;
    }

    function confirmDelete() {
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        fetch('../../../../server/api/customer_supplier/suppliers/supplier-delete.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentDeletingSupplierId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                closeDeleteModal();
                loadSuppliers();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(() => showNotification('Error', 'Failed to delete supplier', 'error'))
        .finally(() => {
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Supplier';
        });
    }

    deleteModalClose.addEventListener('click', closeDeleteModal);
    deleteCancelBtn.addEventListener('click', closeDeleteModal);
    deleteConfirmBtn.addEventListener('click', confirmDelete);
    deleteModal.addEventListener('click', e => { if (e.target === deleteModal) closeDeleteModal(); });

    // ── View Modal ────────────────────────────────────────────────────────────
    const viewModal = document.getElementById('viewModal');
    const viewModalClose = document.getElementById('viewModalClose');
    const viewCloseBtn = document.getElementById('viewCloseBtn');
    const viewEditBtn = document.getElementById('viewEditBtn');
    let currentViewingSupplierId = null;

    function openViewModal(supplierId) {
        currentViewingSupplierId = supplierId;
        fetch(`../../../../server/api/customer_supplier/suppliers/supplier-edit.php?id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateViewModal(data.supplier);
                    viewModal.classList.add('show');
                } else {
                    showNotification('Error', data.message, 'error');
                }
            })
            .catch(() => showNotification('Error', 'Failed to load supplier data', 'error'));
    }

    function populateViewModal(s) {
        document.getElementById('viewSupplierCode').textContent = s.supplier_code || '-';
        document.getElementById('viewSupplierName').textContent = s.supplier_name || '-';
        document.getElementById('viewBrandName').textContent = s.brand_name || '-';
        document.getElementById('viewAddress').textContent = s.address || '-';
        document.getElementById('viewCity').textContent = s.city || '-';
        document.getElementById('viewPrimaryPhone').textContent = s.primary_phone || '-';
        document.getElementById('viewSecondaryPhone').textContent = s.secondary_phone || '-';
        document.getElementById('viewEmail').textContent = s.email || '-';
        document.getElementById('viewIdentityCard').textContent = s.identity_card_no || '-';
        document.getElementById('viewAitPercent').textContent = `${parseFloat(s.ait_percent || 0).toFixed(2)}%`;
        document.getElementById('viewCreditDays').textContent = s.credit_days || '-';
        document.getElementById('viewPartyType').textContent = s.party_type || '-';
        document.getElementById('viewOpeningDebit').textContent = `${currencySymbol}${parseFloat(s.opening_debit_amount || 0).toFixed(2)}`;
        document.getElementById('viewOpeningCredit').textContent = `${currencySymbol}${parseFloat(s.opening_credit_amount || 0).toFixed(2)}`;
        document.getElementById('viewCurrentBalance').textContent = `${currencySymbol}${parseFloat(s.current_balance || 0).toFixed(2)}`;
        document.getElementById('viewStatus').innerHTML = s.is_blacklisted
            ? '<span class="status-badge status-blacklisted">Blacklisted</span>'
            : '<span class="status-badge status-active">Active</span>';
        document.getElementById('viewCreatedAt').textContent = s.created_at ? new Date(s.created_at).toLocaleDateString() : '-';

        // Company
        if (s.company_name) {
            document.getElementById('viewCompany').textContent = s.company_name;
        } else {
            document.getElementById('viewCompany').textContent = '-';
        }

        // Salesman
        if (s.salesman_id && editEmployees.length > 0) {
            const emp = editEmployees.find(e => e.id == s.salesman_id);
            document.getElementById('viewSalesman').textContent = emp ? emp.full_name : '-';
        } else {
            document.getElementById('viewSalesman').textContent = '-';
        }

        // Sub accounts
        loadViewSubAccounts(s.id);
    }

    function loadViewSubAccounts(supplierId) {
        fetch(`../../../../server/api/customer_supplier/suppliers/supplier-sub-accounts.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('viewSubAccountsTableBody');
                tbody.innerHTML = '';
                if (data.success && data.subAccounts && data.subAccounts.length > 0) {
                    data.subAccounts.forEach((sa, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${index + 1}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${sa.sub_account_name}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${currencySymbol}${parseFloat(sa.debit || 0).toFixed(2)}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${currencySymbol}${parseFloat(sa.credit || 0).toFixed(2)}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="padding: 12px; border: 1px solid var(--border-default); text-align: center; color: var(--subtext);">No sub accounts</td></tr>';
                }
            });
    }

    function closeViewModal() {
        viewModal.classList.remove('show');
        currentViewingSupplierId = null;
    }

    viewModalClose.addEventListener('click', closeViewModal);
    viewCloseBtn.addEventListener('click', closeViewModal);
    viewModal.addEventListener('click', e => { if (e.target === viewModal) closeViewModal(); });
    viewEditBtn.addEventListener('click', () => { closeViewModal(); openEditModal(currentViewingSupplierId); });

    // ── Edit Modal ────────────────────────────────────────────────────────────
    const editModal = document.getElementById('editModal');
    const editModalClose = document.getElementById('editModalClose');
    const editCancelBtn = document.getElementById('editCancelBtn');
    const editSupplierForm = document.getElementById('editSupplierForm');
    let currentEditingSupplierId = null;

    $(document).ready(function () {
        $('#editCompany').select2({ placeholder: 'Select Company', allowClear: false, dropdownParent: $('#editModal') });
        $('#editSalesman').select2({ placeholder: 'Select Salesman', allowClear: true, dropdownParent: $('#editModal') });
        $('#editPartyType').select2({ placeholder: 'Select Party Type', allowClear: true, dropdownParent: $('#editModal') });

        loadEditCompanies();
        loadEditEmployees();
    });

    function loadEditCompanies() {
        fetch('../../../../server/api/customer_supplier/suppliers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.companies.forEach(c => $('#editCompany').append(new Option(c.company_name, c.id)));
                }
            });
    }

    function loadEditEmployees() {
        fetch('../../../../server/api/customer_supplier/suppliers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editEmployees = data.employees;
                    data.employees.forEach(e => $('#editSalesman').append(new Option(e.full_name, e.id)));
                }
            });
    }

    function openEditModal(supplierId) {
        currentEditingSupplierId = supplierId;
        fetch(`../../../../server/api/customer_supplier/suppliers/supplier-edit.php?id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateEditForm(data.supplier);
                    editModal.classList.add('show');
                } else {
                    showNotification('Error', data.message, 'error');
                }
            })
            .catch(() => showNotification('Error', 'Failed to load supplier data', 'error'));
    }

    function populateEditForm(s) {
        document.getElementById('editSupplierCode').value = s.supplier_code || '';
        document.getElementById('editSupplierName').value = s.supplier_name || '';
        document.getElementById('editBrandName').value = s.brand_name || '';
        document.getElementById('editAddress').value = s.address || '';
        document.getElementById('editCity').value = s.city || '';
        document.getElementById('editPrimaryPhone').value = s.primary_phone || '';
        document.getElementById('editSecondaryPhone').value = s.secondary_phone || '';
        document.getElementById('editEmail').value = s.email || '';
        document.getElementById('editIdentityCard').value = s.identity_card_no || '';
        document.getElementById('editAitPercent').value = s.ait_percent || '';
        document.getElementById('editCreditDays').value = s.credit_days || '';
        document.getElementById('editOpeningDebit').value = s.opening_debit_amount || '';
        document.getElementById('editOpeningCredit').value = s.opening_credit_amount || '';
        document.getElementById('editBlacklist').checked = !!s.is_blacklisted;

        setTimeout(() => {
            $('#editCompany').val(s.company_id || '').trigger('change');
            $('#editSalesman').val(s.salesman_id || '').trigger('change');
            $('#editPartyType').val(s.party_type || '').trigger('change');
        }, 100);

        // Load sub accounts
        loadEditSubAccounts(s.id);

        // Mutual exclusion for opening balances
        const editOpeningDebit = document.getElementById('editOpeningDebit');
        const editOpeningCredit = document.getElementById('editOpeningCredit');
        editOpeningDebit.disabled = false;
        editOpeningCredit.disabled = false;
        if (parseFloat(s.opening_credit_amount || 0) > 0) editOpeningDebit.disabled = true;
        if (parseFloat(s.opening_debit_amount || 0) > 0) editOpeningCredit.disabled = true;
    }

    function loadEditSubAccounts(supplierId) {
        fetch(`../../../../server/api/customer_supplier/suppliers/supplier-sub-accounts.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('editSubAccountsTableBody');
                tbody.innerHTML = '';
                editSubAccountCounter = 0;
                if (data.success && data.subAccounts) {
                    data.subAccounts.forEach(sa => addEditSubAccountRow(sa));
                }
            });
    }

    function addEditSubAccountRow(subAccount = null) {
        const tbody = document.getElementById('editSubAccountsTableBody');
        editSubAccountCounter++;
        const row = document.createElement('tr');
        if (subAccount && subAccount.id) row.dataset.subAccountId = subAccount.id;
        row.innerHTML = `
            <td style="padding: 12px; border: 1px solid var(--border-default);">${editSubAccountCounter}</td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="text" class="edit-sub-account-name" value="${subAccount?.sub_account_name || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Enter sub account name" required>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="number" class="edit-sub-account-debit" value="${subAccount?.debit || ''}" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00" ${subAccount && subAccount.credit > 0 ? 'disabled' : ''}>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="number" class="edit-sub-account-credit" value="${subAccount?.credit || ''}" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00" ${subAccount && subAccount.debit > 0 ? 'disabled' : ''}>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default); text-align: center;">
                <button type="button" class="btn-remove-edit-sub-account" style="background: var(--error); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-minus"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);

        const debitInput = row.querySelector('.edit-sub-account-debit');
        const creditInput = row.querySelector('.edit-sub-account-credit');
        debitInput.addEventListener('input', function () {
            if (this.value && parseFloat(this.value) > 0) { creditInput.disabled = true; creditInput.value = ''; }
            else { creditInput.disabled = false; }
        });
        creditInput.addEventListener('input', function () {
            if (this.value && parseFloat(this.value) > 0) { debitInput.disabled = true; debitInput.value = ''; }
            else { debitInput.disabled = false; }
        });
        row.querySelector('.btn-remove-edit-sub-account').addEventListener('click', function () {
            row.remove();
            renumberEditSubAccounts();
        });
    }

    function renumberEditSubAccounts() {
        const rows = document.getElementById('editSubAccountsTableBody').querySelectorAll('tr');
        rows.forEach((row, index) => { row.querySelector('td:first-child').textContent = index + 1; });
        editSubAccountCounter = rows.length;
    }

    function collectEditSubAccountData() {
        const rows = document.getElementById('editSubAccountsTableBody').querySelectorAll('tr');
        const subAccounts = [];
        rows.forEach(row => {
            const name = row.querySelector('.edit-sub-account-name').value.trim();
            const debit = parseFloat(row.querySelector('.edit-sub-account-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.edit-sub-account-credit').value) || 0;
            if (name) {
                const sa = { name, debit, credit };
                if (row.dataset.subAccountId) sa.id = parseInt(row.dataset.subAccountId);
                subAccounts.push(sa);
            }
        });
        return subAccounts;
    }

    document.getElementById('editAddSubAccountBtn').addEventListener('click', () => addEditSubAccountRow());

    function closeEditModal() {
        editModal.classList.remove('show');
        currentEditingSupplierId = null;
        editSupplierForm.reset();
        document.getElementById('editOpeningDebit').disabled = false;
        document.getElementById('editOpeningCredit').disabled = false;
        document.getElementById('editSubAccountsTableBody').innerHTML = '';
        editSubAccountCounter = 0;
        $('#editCompany').val('').trigger('change');
        $('#editSalesman').val('').trigger('change');
        $('#editPartyType').val('').trigger('change');
    }

    editModalClose.addEventListener('click', closeEditModal);
    editCancelBtn.addEventListener('click', closeEditModal);
    editModal.addEventListener('click', e => { if (e.target === editModal) closeEditModal(); });

    editSupplierForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = {
            id: currentEditingSupplierId,
            companyId: $('#editCompany').val(),
            salesmanId: $('#editSalesman').val() || null,
            supplierName: document.getElementById('editSupplierName').value.trim(),
            brandName: document.getElementById('editBrandName').value.trim(),
            address: document.getElementById('editAddress').value.trim(),
            city: document.getElementById('editCity').value.trim(),
            primaryPhone: document.getElementById('editPrimaryPhone').value.trim(),
            secondaryPhone: document.getElementById('editSecondaryPhone').value.trim(),
            email: document.getElementById('editEmail').value.trim(),
            identityCard: document.getElementById('editIdentityCard').value.trim(),
            aitPercent: document.getElementById('editAitPercent').value || 0,
            creditDays: document.getElementById('editCreditDays').value || 0,
            partyType: $('#editPartyType').val() || null,
            openingDebit: document.getElementById('editOpeningDebit').value || 0,
            openingCredit: document.getElementById('editOpeningCredit').value || 0,
            blacklist: document.getElementById('editBlacklist').checked,
            subAccounts: collectEditSubAccountData()
        };

        const editSaveBtn = document.getElementById('editSaveBtn');
        editSaveBtn.disabled = true;
        editSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

        fetch('../../../../server/api/customer_supplier/suppliers/supplier-edit.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                closeEditModal();
                loadSuppliers();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(() => showNotification('Error', 'Failed to update supplier', 'error'))
        .finally(() => {
            editSaveBtn.disabled = false;
            editSaveBtn.innerHTML = '<i class="fas fa-save"></i> Update Supplier';
        });
    });

    // Opening balance mutual exclusion
    document.getElementById('editOpeningDebit').addEventListener('input', function () {
        const creditInput = document.getElementById('editOpeningCredit');
        if (this.value && parseFloat(this.value) > 0) { creditInput.disabled = true; creditInput.value = ''; }
        else { creditInput.disabled = false; }
    });
    document.getElementById('editOpeningCredit').addEventListener('input', function () {
        const debitInput = document.getElementById('editOpeningDebit');
        if (this.value && parseFloat(this.value) > 0) { debitInput.disabled = true; debitInput.value = ''; }
        else { debitInput.disabled = false; }
    });

    // ── Table row actions ─────────────────────────────────────────────────────
    suppliersTable.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;

        const row = target.closest('tr');
        const supplierName = row.querySelector('.customer-name').textContent;
        const supplierId = row.dataset.id;

        if (target.classList.contains('edit')) {
            if (!userPermissions.includes('Edit')) {
                showNotification('Access Denied', 'You do not have permission to edit suppliers', 'error');
                return;
            }
            openEditModal(supplierId);
        } else if (target.classList.contains('delete')) {
            if (!userPermissions.includes('Delete')) {
                showNotification('Access Denied', 'You do not have permission to delete suppliers', 'error');
                return;
            }
            openDeleteModal(supplierId, supplierName);
        } else if (target.querySelector('.fa-eye')) {
            openViewModal(supplierId);
        }
    });

    // ── Pagination ────────────────────────────────────────────────────────────
    document.getElementById('prevPage').addEventListener('click', function () {
        if (currentPage > 1) { currentPage--; loadSuppliers(); }
    });
    document.getElementById('nextPage').addEventListener('click', function () {
        if (currentPage < totalPages) { currentPage++; loadSuppliers(); }
    });

    // ── Notification ──────────────────────────────────────────────────────────
    function showNotification(title, message, type = 'success') {
        const notification = document.getElementById('notification');
        document.getElementById('notificationTitle').textContent = title;
        document.getElementById('notificationMessage').textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');
        setTimeout(() => notification.classList.remove('show'), 5000);
    }

    document.getElementById('notificationClose').addEventListener('click', () => {
        document.getElementById('notification').classList.remove('show');
    });

    // ── Permissions ───────────────────────────────────────────────────────────
    function checkPermissions() {
        return fetch('../../../../server/api/auth/check-permission.php?category=Customer / Supplier&form_name=New Supplier')
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
});
