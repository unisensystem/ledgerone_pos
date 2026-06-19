document.addEventListener('DOMContentLoaded', function () {
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

    let employees = [];
    let selectedSalesmen = [];
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
                    data.companies.forEach(company => {
                        const option = new Option(company.company_name, company.id);
                        companySelect.appendChild(option);
                    });
                }
            });
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
                if (data.success) {
                    const sel = document.getElementById('project');
                    data.data.forEach(p => {
                        const label = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
                        sel.appendChild(new Option(label, p.id));
                    });
                }
            });
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
    });

    // Focus search input when clicking container
    salesmanChipsContainer.addEventListener('click', function() {
        salesmanSearch.focus();
    });

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
        }
    });
});
