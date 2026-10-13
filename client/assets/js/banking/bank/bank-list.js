// Load tenant currencies for edit modal
async function loadEditCurrencies() {
    const select = document.getElementById('editCurrency');
    try {
        const response = await fetch('../../../../server/api/system_setup/currency_setup/currency-list.php');
        const result = await response.json();
        if (result.success && result.data.length > 0) {
            select.innerHTML = '<option value="">Select currency</option>';
            result.data.forEach(currency => {
                const option = document.createElement('option');
                option.value = currency.id;
                option.textContent = `${currency.code} - ${currency.name} (${currency.symbol})`;
                select.appendChild(option);
            });
        }
    } catch (e) {
        select.innerHTML = '<option value="">Failed to load</option>';
    }
}

// Data
let bankAccounts = [];

// State
let currentSort = { column: 'bank', direction: 'asc' };
let filteredData = [];
let currentPage = 1;
const itemsPerPage = 10;
let selectedIds = new Set();

// DOM Elements
const tableBody = document.getElementById('tableBody');
const emptyState = document.getElementById('emptyState');
const bulkActions = document.getElementById('bulkActions');
const selectedCount = document.getElementById('selectedCount');
const selectAllCheckbox = document.getElementById('selectAll');
const searchInput = document.getElementById('searchInput');
const typeFilter = document.getElementById('typeFilter');
const statusFilter = document.getElementById('statusFilter');
const bankFilter = document.getElementById('bankFilter');
const applyFiltersBtn = document.getElementById('applyFilters');
const resetFiltersBtn = document.getElementById('resetFilters');
const addAccountBtn = document.getElementById('addAccountBtn');
const addFirstAccountBtn = document.getElementById('addFirstAccount');
const clearSelectionBtn = document.getElementById('clearSelection');
const bulkDeleteBtn = document.getElementById('bulkDelete');
const prevPageBtn = document.getElementById('prevPage');
const nextPageBtn = document.getElementById('nextPage');
const paginationInfo = document.getElementById('paginationInfo');

// Helper Functions
function formatCurrency(amount, currencyCode, currencySymbol) {
    try {
        const formatter = new Intl.NumberFormat('en-PK', {
            style: 'currency',
            currency: currencyCode || 'PKR',
            minimumFractionDigits: 2
        });
        return formatter.format(amount);
    } catch (e) {
        return (currencySymbol || '') + ' ' + parseFloat(amount).toFixed(2);
    }
}

function getTypeBadge(type) {
    const typeMap = {
        'current': { label: 'Current', class: 'type-current' },
        'savings': { label: 'Savings', class: 'type-savings' },
        'checking': { label: 'Checking', class: 'type-checking' },
        'mfb_account': { label: 'MFB', class: 'type-mfb' },
        'credit_card': { label: 'Credit Card', class: 'type-checking' },
        'loan': { label: 'Loan', class: 'type-mfb' },
        'branchless': { label: 'Branchless', class: 'type-savings' },
        'other': { label: 'Other', class: 'type-current' }
    };

    const typeInfo = typeMap[type] || { label: type, class: 'type-current' };
    return `<span class="type-badge ${typeInfo.class}">${typeInfo.label}</span>`;
}

function getStatusBadge(status) {
    const statusMap = {
        'active': { label: 'Active', class: 'status-active', icon: 'fa-check-circle' },
        'inactive': { label: 'Inactive', class: 'status-inactive', icon: 'fa-times-circle' },
        'pending': { label: 'Pending', class: 'status-pending', icon: 'fa-clock' }
    };

    const statusInfo = statusMap[status] || { label: status, class: 'status-inactive', icon: 'fa-question-circle' };
    return `<span class="status-badge ${statusInfo.class}">
                <i class="fas ${statusInfo.icon}"></i>${statusInfo.label}
            </span>`;
}

function getBankInitials(bankName) {
    return bankName
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .substring(0, 3);
}

// Render Table
function renderTable() {
    tableBody.innerHTML = '';

    if (filteredData.length === 0) {
        emptyState.classList.remove('hidden');
        return;
    }

    emptyState.classList.add('hidden');

    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredData.length);
    const pageData = filteredData.slice(startIndex, endIndex);

    // Update pagination info
    paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${filteredData.length} accounts`;

    // Update pagination buttons
    prevPageBtn.disabled = currentPage === 1;
    nextPageBtn.disabled = endIndex >= filteredData.length;

    // Render rows
    pageData.forEach(account => {
        const isSelected = selectedIds.has(account.id);
        const balanceClass = account.balance >= 0 ? 'positive' : 'negative';

        const row = document.createElement('tr');
        row.className = isSelected ? 'selected fade-in' : 'fade-in';
        row.innerHTML = `
                    <td>
                        <input type="checkbox" class="table-checkbox" data-id="${account.id}" ${isSelected ? 'checked' : ''}>
                    </td>
                    <td>
                        <div class="bank-info">
                            <div class="bank-logo">${getBankInitials(account.bank_name)}</div>
                            <div>
                                <div class="bank-name">${account.bank_name}</div>
                                <div class="bank-account">${account.account_title}</div>
                            </div>
                        </div>
                    </td>
                    <td>${account.account_number}</td>
                    <td>${getTypeBadge(account.account_type)}</td>
                    <td>
                        <div>${account.branch_name}</div>
                        <div style="font-size: 12px; color: var(--light-text-sub);">${account.branch_city}, ${account.branch_state}</div>
                    </td>
                    <td>
                        <div class="balance ${balanceClass}">${formatCurrency(account.balance, account.currency_code, account.currency_symbol)}</div>
                        <div style="font-size: 12px; color: var(--light-text-sub);">${account.balance_type === 'debit' ? 'Debit' : 'Credit'} Balance</div>
                    </td>
                    <td>${account.currency_code ? account.currency_code + ' ' + (account.currency_symbol || '') : '-'}</td>
                    <td>${getStatusBadge(account.status)}</td>
                    <td>
                        <div class="action-buttons-cell">
                            <button class="action-btn edit" title="Edit" onclick="editAccount(${account.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete" title="Delete" onclick="deleteAccount(${account.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
    });

    // Add event listeners to checkboxes
    document.querySelectorAll('.table-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', handleCheckboxChange);
    });

    updateBulkActions();
}

// Sorting
function sortTable(column) {
    if (currentSort.column === column) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.column = column;
        currentSort.direction = 'asc';
    }

    // Update sort indicators
    document.querySelectorAll('th.sortable i').forEach(icon => {
        icon.className = 'fas fa-sort';
    });

    const currentHeader = document.querySelector(`th[data-sort="${currentSort.column}"]`);
    if (currentHeader) {
        currentHeader.classList.add('sorted');
        const icon = currentHeader.querySelector('i');
        icon.className = currentSort.direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
    }

    // Sort data
    filteredData.sort((a, b) => {
        let aValue = a[currentSort.column];
        let bValue = b[currentSort.column];

        // Special handling for different columns
        if (column === 'bank') {
            aValue = a.bank_name;
            bValue = b.bank_name;
        } else if (column === 'account') {
            aValue = a.account_number;
            bValue = b.account_number;
        } else if (column === 'balance') {
            aValue = a.balance;
            bValue = b.balance;
        }

        if (aValue < bValue) return currentSort.direction === 'asc' ? -1 : 1;
        if (aValue > bValue) return currentSort.direction === 'asc' ? 1 : -1;
        return 0;
    });

    currentPage = 1;
    renderTable();
}

// Filtering
function applyFilters() {
    const searchTerm = searchInput.value.toLowerCase();
    const typeValue = typeFilter.value;
    const statusValue = statusFilter.value;
    const bankValue = bankFilter.value;

    filteredData = bankAccounts.filter(account => {
        // Search filter
        const matchesSearch = !searchTerm ||
            account.bank_name.toLowerCase().includes(searchTerm) ||
            account.account_number.toLowerCase().includes(searchTerm) ||
            account.account_title.toLowerCase().includes(searchTerm) ||
            account.branch_name.toLowerCase().includes(searchTerm) ||
            account.branch_city.toLowerCase().includes(searchTerm);

        // Type filter
        const matchesType = !typeValue || account.account_type === typeValue;

        // Status filter
        const matchesStatus = !statusValue || account.status === statusValue;

        // Bank filter
        const matchesBank = !bankValue ||
            (bankValue === 'NBP' && account.bank_name.includes('National Bank')) ||
            (bankValue === 'HBL' && account.bank_name.includes('Habib Bank')) ||
            (bankValue === 'UBL' && account.bank_name.includes('United Bank')) ||
            (bankValue === 'MCB' && account.bank_name.includes('MCB Bank')) ||
            (bankValue === 'ABL' && account.bank_name.includes('Allied Bank'));

        return matchesSearch && matchesType && matchesStatus && matchesBank;
    });

    currentPage = 1;
    selectedIds.clear();
    renderTable();
}

function resetFilters() {
    searchInput.value = '';
    typeFilter.value = '';
    statusFilter.value = '';
    bankFilter.value = '';
    filteredData = [...bankAccounts];
    currentPage = 1;
    selectedIds.clear();
    renderTable();
}

// Selection Management
function handleCheckboxChange(e) {
    const checkbox = e.target;
    const accountId = parseInt(checkbox.dataset.id);

    if (checkbox.id === 'selectAll') {
        if (checkbox.checked) {
            // Select all visible accounts
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = Math.min(startIndex + itemsPerPage, filteredData.length);
            const pageData = filteredData.slice(startIndex, endIndex);

            pageData.forEach(account => {
                selectedIds.add(account.id);
            });
        } else {
            // Deselect all
            selectedIds.clear();
        }
    } else {
        if (checkbox.checked) {
            selectedIds.add(accountId);
        } else {
            selectedIds.delete(accountId);
            selectAllCheckbox.checked = false;
        }
    }

    renderTable();
}

function updateBulkActions() {
    const selectedCountValue = selectedIds.size;

    if (selectedCountValue > 0) {
        bulkActions.classList.remove('hidden');
        selectedCount.textContent = `${selectedCountValue} account${selectedCountValue > 1 ? 's' : ''} selected`;
    } else {
        bulkActions.classList.add('hidden');
    }

    // Update select all checkbox state
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredData.length);
    const pageData = filteredData.slice(startIndex, endIndex);

    const allPageSelected = pageData.length > 0 && pageData.every(account => selectedIds.has(account.id));
    selectAllCheckbox.checked = allPageSelected;
    selectAllCheckbox.indeterminate = !allPageSelected && pageData.some(account => selectedIds.has(account.id));
}

function clearSelection() {
    selectedIds.clear();
    renderTable();
}

// Modal Management
let deleteAccountId = null;
let editAccountId = null;
const deleteModal = document.getElementById('deleteModal');
const editModal = document.getElementById('editModal');
const closeModal = document.getElementById('closeModal');
const closeEditModal = document.getElementById('closeEditModal');
const cancelDelete = document.getElementById('cancelDelete');
const cancelEdit = document.getElementById('cancelEdit');
const confirmDelete = document.getElementById('confirmDelete');
const saveEdit = document.getElementById('saveEdit');
const editBankLogoInput = document.getElementById('editBankLogo');
const editLogoPreview = document.getElementById('editLogoPreview');
const editLogoPreviewImg = document.getElementById('editLogoPreviewImg');

// Edit Logo Preview
editBankLogoInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            editBankLogoInput.value = '';
            editLogoPreview.style.display = 'none';
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            editLogoPreviewImg.src = e.target.result;
            editLogoPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        editLogoPreview.style.display = 'none';
    }
});

function showDeleteModal(id) {
    deleteAccountId = id;
    deleteModal.classList.add('show');
}

function hideDeleteModal() {
    deleteAccountId = null;
    deleteModal.classList.remove('show');
}

function showEditModal(id) {
    editAccountId = id;
    const account = bankAccounts.find(acc => acc.id === id);
    if (account) {
        document.getElementById('editBankName').value = account.bank_name;
        document.getElementById('editAccountNumber').value = account.account_number;
        document.getElementById('editAccountTitle').value = account.account_title || '';
        document.getElementById('editAccountType').value = account.account_type;
        document.getElementById('editCurrency').value = account.currency_id || '';
        document.getElementById('editBranchName').value = account.branch_name;
        document.getElementById('editBranchCode').value = account.branch_code || '';
        document.getElementById('editBranchCity').value = account.branch_city;
        document.getElementById('editBranchState').value = account.branch_state || '';
        document.getElementById('editIban').value = account.iban || '';
        document.getElementById('editSwiftCode').value = account.swift_code || '';
        document.getElementById('editContactPerson').value = account.contact_person || '';
        document.getElementById('editContactNumber').value = account.contact_number || '';
        document.getElementById('editEmail').value = account.email || '';
        document.getElementById('editBalanceType').value = account.balance_type || 'debit';
        document.getElementById('editOpeningBalance').value = account.opening_balance || 0;
        document.getElementById('editIsActive').value = account.is_active ? '1' : '0';
        
        // Show existing logo if available
        if (account.bank_logo_path) {
            // Store just the filename, construct full path from current page location
            const filename = account.bank_logo_path.split('/').pop();
            const logoUrl = '../../../assets/uploads/bank_logo/' + filename;
            editLogoPreviewImg.src = logoUrl;
            editLogoPreview.style.display = 'block';
        } else {
            editLogoPreview.style.display = 'none';
        }
        editBankLogoInput.value = '';
    }
    editModal.classList.add('show');
}

function hideEditModal() {
    editAccountId = null;
    editModal.classList.remove('show');
}

// Account Actions
function editAccount(id) {
    showEditModal(id);
}

function deleteAccount(id) {
    showDeleteModal(id);
}

function bulkDelete() {
    if (selectedIds.size === 0) return;

    if (confirm(`Are you sure you want to delete ${selectedIds.size} account(s)? This action cannot be undone.`)) {
        // TODO: Implement bulk delete API call
        alert('Bulk delete functionality will be implemented soon.');
    }
}

// Pagination
function goToPage(page) {
    if (page < 1 || page > Math.ceil(filteredData.length / itemsPerPage)) return;

    currentPage = page;
    renderTable();

    // Update pagination buttons
    document.querySelectorAll('.page-btn').forEach(btn => {
        if (btn.textContent === page.toString()) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

function nextPage() {
    goToPage(currentPage + 1);
}

function prevPage() {
    goToPage(currentPage - 1);
}

// Add Account
function addAccount() {
    window.location.href = 'bank-add.php';
}

// Render Stats
function renderStats(stats) {
    document.querySelector('.stat-card.total-accounts .stat-value').textContent = stats.total_accounts;
    document.querySelector('.stat-card.active-accounts .stat-value').textContent = stats.active_accounts;
    document.querySelector('.stat-card.balance .stat-value').textContent = formatCurrency(stats.total_balance, stats.base_currency_code, stats.base_currency_symbol);
    document.querySelector('.stat-card.mfb-accounts .stat-value').textContent = stats.mfb_accounts;
    
    // Update percentages
    const activePercentage = stats.total_accounts > 0 ? Math.round((stats.active_accounts / stats.total_accounts) * 100) : 0;
    document.querySelector('.stat-card.active-accounts .stat-change').textContent = `${activePercentage}% active`;
}

// Load data from API
function loadBankAccounts() {
    fetch('../../../../server/api/banking/bank/bank-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bankAccounts = data.data;
                filteredData = [...bankAccounts];
                renderStats(data.stats);
                renderTable();
            } else {
                console.error('Error loading accounts:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Load data
    loadBankAccounts();
    loadEditCurrencies();

    // Sorting
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => sortTable(th.dataset.sort));
    });

    // Filtering
    applyFiltersBtn.addEventListener('click', applyFilters);
    resetFiltersBtn.addEventListener('click', resetFilters);
    searchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') applyFilters();
    });

    // Selection
    selectAllCheckbox.addEventListener('change', handleCheckboxChange);
    clearSelectionBtn.addEventListener('click', clearSelection);
    bulkDeleteBtn.addEventListener('click', bulkDelete);

    // Pagination
    prevPageBtn.addEventListener('click', prevPage);
    nextPageBtn.addEventListener('click', nextPage);

    // Add account buttons
    addAccountBtn.addEventListener('click', addAccount);
    addFirstAccountBtn.addEventListener('click', addAccount);
    
    // Modal events
    closeModal.addEventListener('click', hideDeleteModal);
    cancelDelete.addEventListener('click', hideDeleteModal);
    closeEditModal.addEventListener('click', hideEditModal);
    cancelEdit.addEventListener('click', hideEditModal);
    
    saveEdit.addEventListener('click', () => {
        if (editAccountId) {
            const formData = new FormData();
            formData.append('id', editAccountId);
            formData.append('bankName', document.getElementById('editBankName').value);
            formData.append('accountNumber', document.getElementById('editAccountNumber').value);
            formData.append('accountTitle', document.getElementById('editAccountTitle').value);
            formData.append('accountType', document.getElementById('editAccountType').value);
            formData.append('currency', document.getElementById('editCurrency').value);
            formData.append('branchName', document.getElementById('editBranchName').value);
            formData.append('branchCode', document.getElementById('editBranchCode').value);
            formData.append('branchCity', document.getElementById('editBranchCity').value);
            formData.append('branchState', document.getElementById('editBranchState').value);
            formData.append('iban', document.getElementById('editIban').value);
            formData.append('swiftCode', document.getElementById('editSwiftCode').value);
            formData.append('contactPerson', document.getElementById('editContactPerson').value);
            formData.append('contactNumber', document.getElementById('editContactNumber').value);
            formData.append('email', document.getElementById('editEmail').value);
            formData.append('balanceType', document.getElementById('editBalanceType').value);
            formData.append('openingBalance', document.getElementById('editOpeningBalance').value);
            formData.append('isActive', document.getElementById('editIsActive').value === '1');
            
            // Append bank logo if selected
            if (editBankLogoInput.files[0]) {
                formData.append('bankLogo', editBankLogoInput.files[0]);
            }
            
            saveEdit.disabled = true;
            saveEdit.textContent = 'Saving...';
            
            fetch('../../../../server/api/banking/bank/bank-edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bank account updated successfully!');
                    loadBankAccounts(); // Reload data
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the account.');
            })
            .finally(() => {
                saveEdit.disabled = false;
                saveEdit.textContent = 'Save Changes';
                hideEditModal();
            });
        }
    });
    confirmDelete.addEventListener('click', () => {
        if (deleteAccountId) {
            confirmDelete.disabled = true;
            confirmDelete.textContent = 'Deleting...';
            
            fetch(`../../../../server/api/banking/bank/bank-delete.php?id=${deleteAccountId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bank account deleted successfully!');
                    loadBankAccounts(); // Reload data
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the account.');
            })
            .finally(() => {
                confirmDelete.disabled = false;
                confirmDelete.textContent = 'Delete';
                hideDeleteModal();
            });
        }
    });
    
    // Close modal on overlay click
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            hideDeleteModal();
        }
    });
    
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            hideEditModal();
        }
    });

    // Page buttons
    document.querySelectorAll('.page-btn').forEach(btn => {
        if (!isNaN(btn.textContent)) {
            btn.addEventListener('click', () => goToPage(parseInt(btn.textContent)));
        }
    });
});

// Expose functions to global scope for onclick attributes
window.editAccount = editAccount;
window.deleteAccount = deleteAccount;