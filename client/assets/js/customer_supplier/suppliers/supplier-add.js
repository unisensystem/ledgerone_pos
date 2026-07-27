document.addEventListener('DOMContentLoaded', function () {
    // ===== CUSTOMIZE FIELDS =====
    const CUSTOMIZE_FIELDS_KEY = 'supplierFormCustomizeFields';

    const allFields = [
        { id: 'company',          label: 'Company',                visible: true },
        { id: 'assocCompanySearch', label: 'Associated Companies', visible: true },
        { id: 'supplierCode',     label: 'Supplier Code',          visible: true },
        { id: 'salesmanSearch',   label: 'Salesman',               visible: true },
        { id: 'supplierName',     label: 'Brand Name - Company',   visible: true },
        { id: 'brandName',        label: 'Supplier Name',          visible: true },
        { id: 'address',          label: 'Address',                visible: true },
        { id: 'primaryPhone',     label: 'Primary Phone',          visible: true },
        { id: 'secondaryPhone',   label: 'Secondary Phone',        visible: false },
        { id: 'email',            label: 'Email Address',          visible: false },
        { id: 'identityCard',     label: 'Identity Card No',       visible: false },
        { id: 'project',          label: 'Project',                visible: false },
        { id: 'openingDebit',     label: 'Opening Debit Amount',   visible: true },
        { id: 'openingCredit',    label: 'Opening Credit Amount',  visible: true },
        { id: 'aitPercent',       label: 'AIT %',                  visible: false },
        { id: 'partyType',        label: 'Party Type',             visible: true },
        { id: 'subAccountsTable', label: '📋 Sub Accounts',        visible: true },
        { id: 'blacklist',        label: 'Blacklist Supplier',     visible: true },
    ];

    function getDefaultVisibility() {
        const d = {};
        allFields.forEach(f => { d[f.id] = f.visible; });
        return d;
    }

    function getFieldVisibility() {
        const stored = localStorage.getItem(CUSTOMIZE_FIELDS_KEY);
        if (stored) {
            try { return Object.assign({}, getDefaultVisibility(), JSON.parse(stored)); }
            catch (e) {}
        }
        return getDefaultVisibility();
    }

    function saveFieldVisibility(v) {
        localStorage.setItem(CUSTOMIZE_FIELDS_KEY, JSON.stringify(v));
    }

    function getFieldFormGroup(fieldId) {
        const el = document.getElementById(fieldId);
        if (!el) return null;
        if (fieldId === 'subAccountsTable') {
            return el.closest('.form-section') || el.closest('.form-group');
        }
        // chips containers: find the form-group wrapper
        if (fieldId === 'assocCompanySearch' || fieldId === 'salesmanSearch') {
            return el.closest('.form-group');
        }
        const fg = el.closest('.form-group');
        if (fg) return fg;
        const cg = el.closest('.checkbox-group');
        return cg ? cg.closest('.form-group') : null;
    }

    function refreshSectionHeadings() {
        document.querySelectorAll('.form-section').forEach(section => {
            const groups = section.querySelectorAll('.form-group');
            let hasVisible = false;
            groups.forEach(fg => {
                if (fg.querySelector('input, select, textarea') && fg.style.display !== 'none') {
                    hasVisible = true;
                }
            });
            const heading = section.querySelector('.section-title');
            if (heading) heading.style.display = hasVisible ? '' : 'none';
        });
    }

    function applyFieldVisibility() {
        const vis = getFieldVisibility();
        allFields.forEach(f => {
            const fg = getFieldFormGroup(f.id);
            if (fg) fg.style.display = vis[f.id] ? '' : 'none';
        });
        refreshSectionHeadings();
    }

    // Modal elements
    const customizeFieldsBtn        = document.getElementById('customizeFieldsBtn');
    const customizeFieldsModal      = document.getElementById('customizeFieldsModal');
    const customizeFieldsModalClose = document.getElementById('customizeFieldsModalClose');
    const customizeFieldsList       = document.getElementById('customizeFieldsList');
    const customizeFieldsSaveBtn    = document.getElementById('customizeFieldsSaveBtn');
    const customizeFieldsResetBtn   = document.getElementById('customizeFieldsResetBtn');
    const customizeFieldsSelectAll  = document.getElementById('customizeFieldsSelectAllBtn');
    const customizeFieldsDeselectAll= document.getElementById('customizeFieldsDeselectAllBtn');

    function populateCustomizeFieldsList() {
        const vis = getFieldVisibility();
        customizeFieldsList.innerHTML = '';
        allFields.forEach(f => {
            const item = document.createElement('div');
            item.className = 'customize-field-item';
            item.innerHTML = `<label class="customize-field-label">
                <input type="checkbox" class="customize-field-checkbox" data-field-id="${f.id}" ${vis[f.id] ? 'checked' : ''}>
                <span>${f.label}</span>
            </label>`;
            customizeFieldsList.appendChild(item);
        });
    }

    function closeCustomizeFieldsModal() {
        customizeFieldsModal.classList.remove('show');
    }

    customizeFieldsBtn.addEventListener('click', function () {
        populateCustomizeFieldsList();
        customizeFieldsModal.classList.add('show');
    });

    customizeFieldsModalClose.addEventListener('click', closeCustomizeFieldsModal);
    customizeFieldsModal.addEventListener('click', function (e) {
        if (e.target === customizeFieldsModal) closeCustomizeFieldsModal();
    });

    customizeFieldsSaveBtn.addEventListener('click', function () {
        const vis = {};
        customizeFieldsList.querySelectorAll('.customize-field-checkbox').forEach(cb => {
            vis[cb.dataset.fieldId] = cb.checked;
        });
        saveFieldVisibility(vis);
        applyFieldVisibility();
        closeCustomizeFieldsModal();
        showNotification('Success', 'Field preferences saved successfully!', 'success');
    });

    customizeFieldsResetBtn.addEventListener('click', function () {
        if (confirm('Reset to default field visibility?')) {
            saveFieldVisibility(getDefaultVisibility());
            applyFieldVisibility();
            populateCustomizeFieldsList();
            showNotification('Success', 'Field visibility reset to default', 'success');
        }
    });

    customizeFieldsSelectAll.addEventListener('click', function () {
        customizeFieldsList.querySelectorAll('.customize-field-checkbox').forEach(cb => cb.checked = true);
    });

    customizeFieldsDeselectAll.addEventListener('click', function () {
        customizeFieldsList.querySelectorAll('.customize-field-checkbox').forEach(cb => cb.checked = false);
    });

    // Apply on page load
    applyFieldVisibility();
    // ===== END CUSTOMIZE FIELDS =====

    const form = document.getElementById('supplierForm');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    const notification = document.getElementById('notification');
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationClose = document.getElementById('notificationClose');
    const companySelect = document.getElementById('company');

    // Salesman multi-select elements
    const salesmanSearch = document.getElementById('salesmanSearch');
    const salesmanChipsList = document.getElementById('salesmanChipsList');
    const salesmanDropdown = document.getElementById('salesmanDropdown');
    const salesmanIdsInput = document.getElementById('salesmanIds');
    const salesmanChipsContainer = document.getElementById('salesmanChipsContainer');

    // Associated Companies multi-select elements
    const assocCompanySearch = document.getElementById('assocCompanySearch');
    const assocCompanyChipsList = document.getElementById('assocCompanyChipsList');
    const assocCompanyDropdown = document.getElementById('assocCompanyDropdown');
    const assocCompanyIdsInput = document.getElementById('assocCompanyIds');
    const assocCompanyChipsContainer = document.getElementById('assocCompanyChipsContainer');

    let employees = [];
    let selectedSalesmen = [];
    let allCompanies = [];
    let selectedAssocCompanies = [];
    let isSubmitting = false;

    // Load companies and employees
    loadCompanies();
    loadEmployees();
    loadProjects();

    function loadCompanies() {
        fetch('../../../../server/api/customer_supplier/suppliers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allCompanies = data.companies;
                    data.companies.forEach(company => {
                        const option = new Option(company.company_name, company.id);
                        companySelect.appendChild(option);
                    });
                    // Auto-select if only one company
                    if (data.companies.length === 1) {
                        companySelect.value = data.companies[0].id;
                    }
                }
            })
            .catch(err => console.error('loadCompanies error:', err));
    }

    function loadEmployees() {
        fetch('../../../../server/api/customer_supplier/suppliers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    employees = data.employees;
                }
            });
    }

    function loadProjects() {
        fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    const sel = document.getElementById('project');
                    data.data.forEach(p => {
                        const label = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
                        sel.appendChild(new Option(label, p.id));
                    });
                }
            })
            .catch(err => console.error('loadProjects error:', err));
    }

    // Salesman search functionality
    salesmanSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        if (searchTerm.length === 0) {
            salesmanDropdown.style.display = 'none';
            return;
        }

        const filtered = employees.filter(emp =>
            emp.full_name.toLowerCase().includes(searchTerm) &&
            !selectedSalesmen.find(s => s.id === emp.id)
        );

        if (filtered.length > 0) {
            salesmanDropdown.innerHTML = filtered.map(emp => `
                <div class="salesman-dropdown-item" data-id="${emp.id}" data-name="${emp.full_name}">
                    ${emp.full_name}
                </div>
            `).join('');
            salesmanDropdown.style.display = 'block';
        } else {
            salesmanDropdown.style.display = 'none';
        }
    });

    // Handle dropdown item click
    salesmanDropdown.addEventListener('click', function(e) {
        const item = e.target.closest('.salesman-dropdown-item');
        if (item) {
            const id = item.dataset.id;
            const name = item.dataset.name;
            addSalesmanChip(id, name);
            salesmanSearch.value = '';
            salesmanDropdown.style.display = 'none';
        }
    });

    // Add salesman chip
    function addSalesmanChip(id, name) {
        if (selectedSalesmen.find(s => s.id === id)) return;

        selectedSalesmen.push({ id, name });
        updateSalesmanChips();
        updateSalesmanIdsInput();
    }

    // Remove salesman chip
    function removeSalesmanChip(id) {
        selectedSalesmen = selectedSalesmen.filter(s => s.id !== id);
        updateSalesmanChips();
        updateSalesmanIdsInput();
    }

    // Update chips display
    function updateSalesmanChips() {
        salesmanChipsList.innerHTML = selectedSalesmen.map(s => `
            <div class="salesman-chip">
                <span>${s.name}</span>
                <span class="chip-remove" data-id="${s.id}">×</span>
            </div>
        `).join('');

        // Add remove listeners
        salesmanChipsList.querySelectorAll('.chip-remove').forEach(btn => {
            btn.addEventListener('click', function() {
                removeSalesmanChip(this.dataset.id);
            });
        });
    }

    // Update hidden input with comma-separated IDs
    function updateSalesmanIdsInput() {
        salesmanIdsInput.value = selectedSalesmen.map(s => s.id).join(',');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!salesmanChipsContainer.contains(e.target) && !salesmanDropdown.contains(e.target)) {
            salesmanDropdown.style.display = 'none';
        }
        if (!assocCompanyChipsContainer.contains(e.target) && !assocCompanyDropdown.contains(e.target)) {
            assocCompanyDropdown.style.display = 'none';
        }
    });

    // Focus search input when clicking container
    salesmanChipsContainer.addEventListener('click', function() {
        salesmanSearch.focus();
    });

    // Associated Companies chip logic
    assocCompanyChipsContainer.addEventListener('click', function() {
        assocCompanySearch.focus();
    });

    assocCompanySearch.addEventListener('focus', function() {
        const term = this.value.toLowerCase();
        const list = term
            ? allCompanies.filter(c => c.company_name.toLowerCase().includes(term) && !selectedAssocCompanies.find(s => s.id == c.id))
            : allCompanies.filter(c => !selectedAssocCompanies.find(s => s.id == c.id));
        if (list.length > 0) {
            assocCompanyDropdown.innerHTML = list.map(c =>
                `<div class="salesman-dropdown-item" data-id="${c.id}" data-name="${c.company_name}">${c.company_name}</div>`
            ).join('');
            assocCompanyDropdown.style.display = 'block';
        }
    });

    assocCompanySearch.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        if (!term) { assocCompanyDropdown.style.display = 'none'; return; }
        const filtered = allCompanies.filter(c =>
            c.company_name.toLowerCase().includes(term) &&
            !selectedAssocCompanies.find(s => s.id == c.id)
        );
        if (filtered.length > 0) {
            assocCompanyDropdown.innerHTML = filtered.map(c =>
                `<div class="salesman-dropdown-item" data-id="${c.id}" data-name="${c.company_name}">${c.company_name}</div>`
            ).join('');
            assocCompanyDropdown.style.display = 'block';
        } else {
            assocCompanyDropdown.style.display = 'none';
        }
    });

    assocCompanyDropdown.addEventListener('click', function(e) {
        const item = e.target.closest('.salesman-dropdown-item');
        if (item) {
            addAssocCompanyChip(item.dataset.id, item.dataset.name);
            assocCompanySearch.value = '';
            assocCompanyDropdown.style.display = 'none';
        }
    });

    function addAssocCompanyChip(id, name) {
        if (selectedAssocCompanies.find(s => s.id == id)) return;
        selectedAssocCompanies.push({ id, name });
        renderAssocCompanyChips();
    }

    function renderAssocCompanyChips() {
        assocCompanyChipsList.innerHTML = selectedAssocCompanies.map(s =>
            `<div class="assoc-company-chip">
                <span>${s.name}</span>
                <span class="chip-remove" data-id="${s.id}">×</span>
            </div>`
        ).join('');
        assocCompanyChipsList.querySelectorAll('.chip-remove').forEach(btn => {
            btn.addEventListener('click', function() {
                selectedAssocCompanies = selectedAssocCompanies.filter(s => s.id != this.dataset.id);
                renderAssocCompanyChips();
                assocCompanyIdsInput.value = selectedAssocCompanies.map(s => s.id).join(',');
            });
        });
        assocCompanyIdsInput.value = selectedAssocCompanies.map(s => s.id).join(',');
    }

    // Sub-accounts table management
    const subAccountsBody = document.getElementById('subAccountsBody');

    function updateRowNumbers() {
        const rows = subAccountsBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
        });
    }

    function addSubAccountRow() {
        const rowCount = subAccountsBody.querySelectorAll('tr').length + 1;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>${rowCount}</td>
            <td><input type="text" class="sub-account-input" placeholder="Enter sub account name"></td>
            <td><input type="number" class="sub-account-debit" step="0.01" min="0" placeholder="0.00"></td>
            <td><input type="number" class="sub-account-credit" step="0.01" min="0" placeholder="0.00"></td>
            <td>
                <button type="button" class="btn-icon btn-add" title="Add Row"><i class="fas fa-plus"></i></button>
                <button type="button" class="btn-icon btn-remove" title="Remove Row"><i class="fas fa-minus"></i></button>
            </td>
        `;
        subAccountsBody.appendChild(newRow);
        attachSubAccountRowListeners(newRow);
    }

    function removeSubAccountRow(button) {
        const row = button.closest('tr');
        if (subAccountsBody.querySelectorAll('tr').length > 1) {
            row.remove();
            updateRowNumbers();
        } else {
            alert('At least one sub-account row must remain');
        }
    }

    function attachSubAccountRowListeners(row) {
        const addBtn = row.querySelector('.btn-add');
        const removeBtn = row.querySelector('.btn-remove');

        if (addBtn) {
            addBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addSubAccountRow();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                removeSubAccountRow(this);
            });
        }
    }

    // Attach listeners to initial row
    subAccountsBody.querySelectorAll('tr').forEach(row => {
        attachSubAccountRowListeners(row);
    });

    function showNotification(title, message, type = 'success') {
        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');
        setTimeout(() => notification.classList.remove('show'), 5000);
    }

    notificationClose.addEventListener('click', () => notification.classList.remove('show'));

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isSubmitting) return;

        if (!companySelect.value) {
            showNotification('Validation Error', 'Company is required', 'error');
            return;
        }

        if (!document.getElementById('supplierName').value.trim()) {
            showNotification('Validation Error', 'Supplier Name is required', 'error');
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        // Collect sub accounts from table
        const subAccounts = [];
        const subAccountRows = document.querySelectorAll('#subAccountsBody tr');
        subAccountRows.forEach(row => {
            const nameInput = row.querySelector('.sub-account-input');
            const debitInput = row.querySelector('.sub-account-debit');
            const creditInput = row.querySelector('.sub-account-credit');

            if (nameInput && nameInput.value.trim()) {
                subAccounts.push({
                    name: nameInput.value.trim(),
                    debit: debitInput ? parseFloat(debitInput.value) || 0 : 0,
                    credit: creditInput ? parseFloat(creditInput.value) || 0 : 0
                });
            }
        });

        const formData = {
            projectId: document.getElementById('project').value || null,
            companyId: companySelect.value,
            salesmanId: salesmanIdsInput.value || null,
            associatedCompanyIds: assocCompanyIdsInput.value || null,
            supplierName: document.getElementById('supplierName').value.trim(),
            brandName: document.getElementById('brandName').value.trim(),
            address: document.getElementById('address').value.trim(),
            primaryPhone: document.getElementById('primaryPhone').value.trim(),
            secondaryPhone: document.getElementById('secondaryPhone').value.trim(),
            identityCard: document.getElementById('identityCard').value.trim(),
            email: document.getElementById('email').value.trim(),
            openingDebit: document.getElementById('openingDebit').value || 0,
            openingCredit: document.getElementById('openingCredit').value || 0,
            aitPercent: document.getElementById('aitPercent').value || 0,
            partyType: document.getElementById('partyType').value || null,
            blacklist: document.getElementById('blacklist').checked,
            subAccounts: subAccounts
        };

        fetch('../../../../server/api/customer_supplier/suppliers/supplier-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                setTimeout(() => {
                    form.reset();
                    document.getElementById('supplierCode').value = 'Auto Generated';
                    document.getElementById('project').value = '';
                    selectedSalesmen = [];
                    updateSalesmanChips();
                    updateSalesmanIdsInput();
                    selectedAssocCompanies = [];
                    renderAssocCompanyChips();
                }, 2000);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            showNotification('Error', error.message || 'Failed to save supplier', 'error');
        })
        .finally(() => {
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Supplier';
        });
    });

    resetBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to reset the form?')) {
            form.reset();
            document.getElementById('supplierCode').value = 'Auto Generated';
            selectedSalesmen = [];
            updateSalesmanChips();
            updateSalesmanIdsInput();
            selectedAssocCompanies = [];
            renderAssocCompanyChips();
        }
    });
});
