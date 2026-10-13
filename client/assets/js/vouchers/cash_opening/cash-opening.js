// DOM Elements - will be initialized in initializeForm
let branchSelect;
let counterSelect;
let asOfDateInput;
let openingCashInput;
let currencyInput;
let saveBtn;
let resetBtn;
let tableBody;
let emptyState;
let statusMessage;
let formContainer;
let lockOverlay;
let currentDateDisplay;
let editModal;
let deleteModal;
let closeEditModal;
let closeDeleteModal;
let cancelEditBtn;
let cancelDeleteBtn;
let updateBtn;
let confirmDeleteBtn;

// Data storage
let cashRecords = [];
let companies = [];
let currentEditId = null;
let currentDeleteId = null;

// Initialize the form
function initializeForm() {
    // Get DOM elements
    branchSelect = document.getElementById('branch');
    counterSelect = document.getElementById('counter');
    asOfDateInput = document.getElementById('asOfDate');
    openingCashInput = document.getElementById('openingCash');
    currencyInput = document.getElementById('currency');
    saveBtn = document.getElementById('saveBtn');
    resetBtn = document.getElementById('resetBtn');
    tableBody = document.getElementById('tableBody');
    emptyState = document.getElementById('emptyState');
    statusMessage = document.getElementById('statusMessage');
    formContainer = document.getElementById('formContainer');
    lockOverlay = document.getElementById('lockOverlay');
    currentDateDisplay = document.getElementById('currentDateDisplay');
    editModal = document.getElementById('editModal');
    deleteModal = document.getElementById('deleteModal');
    closeEditModal = document.getElementById('closeEditModal');
    closeDeleteModal = document.getElementById('closeDeleteModal');
    cancelEditBtn = document.getElementById('cancelEditBtn');
    cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    updateBtn = document.getElementById('updateBtn');
    confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    asOfDateInput.value = today;

    // Display current date in header
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentDateDisplay.textContent = new Date().toLocaleDateString('en-US', options);

    // Load branches
    loadBranches();
    
    // Load companies
    loadCompanies();

    // Load currency
    loadCurrency();

    // Load existing records
    loadRecords();

    // Check if form should be locked
    checkFormLock();

    // Set up event listeners
    saveBtn.addEventListener('click', saveRecord);
    resetBtn.addEventListener('click', resetForm);
    branchSelect.addEventListener('change', function () { validateBranch(); loadCounters(this.value); });
    asOfDateInput.addEventListener('change', validateDate);
    openingCashInput.addEventListener('input', validateAmount);
    
    // Modal event listeners
    closeEditModal.addEventListener('click', () => editModal.style.display = 'none');
    closeDeleteModal.addEventListener('click', () => deleteModal.style.display = 'none');
    cancelEditBtn.addEventListener('click', () => editModal.style.display = 'none');
    cancelDeleteBtn.addEventListener('click', () => deleteModal.style.display = 'none');
    updateBtn.addEventListener('click', updateRecord);
    confirmDeleteBtn.addEventListener('click', deleteRecord);
    
    // Close modals on outside click
    window.addEventListener('click', (e) => {
        if (e.target === editModal) editModal.style.display = 'none';
        if (e.target === deleteModal) deleteModal.style.display = 'none';
    });
}

// Load branches from API
async function loadBranches() {
    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/get-branches.php');
        const data = await response.json();
        
        if (data.success && data.branches) {
            data.branches.forEach(branch => {
                const option = document.createElement('option');
                option.value = branch.branch_id;
                option.textContent = branch.branch_name;
                branchSelect.appendChild(option);
            });
            
            // Initialize Select2
            $(branchSelect).select2({
                placeholder: 'Select Branch',
                allowClear: true,
                width: '100%'
            });
        }
    } catch (error) {
        showStatusMessage('Failed to load branches', 'error');
    }
}

// Load counters for the selected branch
async function loadCounters(branchId) {
    counterSelect.innerHTML = '<option value="">-- None (Branch Level) --</option>';
    if (!branchId) return;
    try {
        const res = await fetch(`../../../../server/api/sale/pos_invoice/get-counters.php?branch_id=${branchId}`);
        const data = await res.json();
        if (data.success && data.counters.length > 0) {
            data.counters.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.counter_name;
                counterSelect.appendChild(opt);
            });
        }
    } catch (e) { /* non-fatal */ }
}

// Load companies from API
async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/get-companies.php');
        const data = await response.json();
        
        if (data.success && data.data) {
            companies = data.data;
            const companySelect = document.getElementById('company');
            data.data.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companySelect.appendChild(option);
            });
            
            // Auto-select if only one company
            if (data.data.length === 1) {
                companySelect.value = data.data[0].id;
            }
        }
    } catch (error) {
        showStatusMessage('Failed to load companies', 'error');
    }
}

// Load currency from API
async function loadCurrency() {
    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/get-currency.php');
        const data = await response.json();
        
        if (data.success) {
            currencyInput.value = `${data.code} - ${data.name} (${data.symbol})`;
            currencyInput.dataset.currencyId = data.currency_id;
            currencyInput.dataset.symbol = data.symbol;
            currencyInput.dataset.code = data.code;
        }
    } catch (error) {
        console.error('Failed to load currency');
    }
}

// Validate branch selection
function validateBranch() {
    const branchError = document.getElementById('branchError');
    
    if (!branchSelect.value) {
        branchError.textContent = 'Please select a branch';
        branchError.style.display = 'block';
        branchSelect.classList.add('error');
        return false;
    }
    
    branchError.style.display = 'none';
    branchSelect.classList.remove('error');
    return true;
}

// Validate company selection
function validateCompany() {
    const companySelect = document.getElementById('company');
    const companyError = document.getElementById('companyError');
    
    if (!companySelect.value) {
        companyError.textContent = 'Please select a company';
        companyError.style.display = 'block';
        companySelect.classList.add('error');
        return false;
    }
    
    companyError.style.display = 'none';
    companySelect.classList.remove('error');
    return true;
}

// Form is no longer globally locked — the API rejects duplicate branch+counter+date combos.
function checkFormLock() {
    lockOverlay.style.display = 'none';
}

// Validate date input
function validateDate() {
    const dateError = document.getElementById('asOfDateError');

    if (!asOfDateInput.value) {
        dateError.textContent = 'Please select a date';
        dateError.style.display = 'block';
        asOfDateInput.classList.add('error');
        return false;
    }

    dateError.style.display = 'none';
    asOfDateInput.classList.remove('error');
    return true;
}

// Validate amount input
function validateAmount() {
    const amountError = document.getElementById('openingCashError');

    if (!openingCashInput.value || openingCashInput.value <= 0) {
        amountError.textContent = 'Please enter a valid amount greater than 0';
        amountError.style.display = 'block';
        openingCashInput.classList.add('error');
        return false;
    }

    amountError.style.display = 'none';
    openingCashInput.classList.remove('error');
    return true;
}

// Save the record
async function saveRecord() {
    if (!validateBranch() || !validateCompany() || !validateDate() || !validateAmount()) {
        showStatusMessage('Please fix the errors in the form before saving.', 'error');
        return;
    }

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/cash-opening.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                branch_id: branchSelect.value,
                counter_id: counterSelect.value || null,
                company_id: document.getElementById('company').value,
                as_of_date: asOfDateInput.value,
                opening_amount: openingCashInput.value,
                currency: currencyInput.dataset.currencyId
            })
        });

        const data = await response.json();

        if (data.success) {
            showStatusMessage(`Opening cash saved successfully.`, 'success');
            resetForm();
            await loadRecords();
            checkFormLock();
        } else {
            showStatusMessage(data.error || 'Failed to save record', 'error');
            console.error('Error details:', data);
        }
    } catch (error) {
        showStatusMessage('Failed to save record. Please try again.', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Opening Cash';
    }
}

// Load records from API
async function loadRecords() {
    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/cash-opening.php');
        const data = await response.json();

        if (data.success) {
            cashRecords = data.records;
            tableBody.innerHTML = '';

            if (cashRecords.length === 0) {
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';

            cashRecords.forEach(record => {
                const row = document.createElement('tr');
                const currencySymbol = record.currency_symbol || '$';
                const currencyCode = record.currency || '-';
                row.innerHTML = `
                    <td>${record.branch_name || '-'}</td>
                    <td>${record.counter_name || '<span style="color:var(--subtext);font-size:12px;">Branch Level</span>'}</td>
                    <td>${record.company_name || '-'}</td>
                    <td>${formatDate(record.as_of_date)}</td>
                    <td><strong>${currencySymbol}${parseFloat(record.opening_amount).toFixed(2)}</strong></td>
                    <td>${currencyCode}</td>
                    <td>${record.entered_by}</td>
                    <td>${formatDateTime(record.updated_at)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon btn-edit" title="Edit" onclick="openEditModal(${record.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-delete" title="Delete" onclick="openDeleteModal(${record.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }
    } catch (error) {
        showStatusMessage('Failed to load records', 'error');
    }
}

// Reset the form
function resetForm() {
    // Reset to default values
    branchSelect.value = '';
    counterSelect.innerHTML = '<option value="">-- None (Branch Level) --</option>';
    document.getElementById('company').value = '';
    const today = new Date().toISOString().split('T')[0];
    asOfDateInput.value = today;
    openingCashInput.value = '';

    // Clear errors
    document.getElementById('branchError').style.display = 'none';
    document.getElementById('companyError').style.display = 'none';
    document.getElementById('asOfDateError').style.display = 'none';
    document.getElementById('openingCashError').style.display = 'none';
    branchSelect.classList.remove('error');
    document.getElementById('company').classList.remove('error');
    asOfDateInput.classList.remove('error');
    openingCashInput.classList.remove('error');

    // Clear status message
    statusMessage.style.display = 'none';

    // Focus on first field
    if (!branchSelect.disabled) {
        branchSelect.focus();
    }
}

// Show status message
function showStatusMessage(message, type = 'info') {
    // Set message content
    statusMessage.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;

    // Set styling based on type
    statusMessage.className = 'status-message';
    statusMessage.classList.add(`status-${type}`);

    // Show the message
    statusMessage.style.display = 'flex';

    // Auto-hide after 5 seconds (except for warnings when form is locked)
    if (type !== 'warning' || cashRecords.length === 0) {
        setTimeout(() => {
            statusMessage.style.display = 'none';
        }, 5000);
    }
}

// Format date for display
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Format date and time for display
function formatDateTime(dateTimeString) {
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateTimeString).toLocaleDateString('en-US', options);
}

// Open edit modal
function openEditModal(recordId) {
    const record = cashRecords.find(r => r.id === recordId);
    if (!record) return;
    
    currentEditId = recordId;
    document.getElementById('editBranch').value = record.branch_name || '-';
    document.getElementById('editCounter').value = record.counter_name || 'Branch Level';
    document.getElementById('editCompany').value = record.company_name || '-';
    document.getElementById('editAsOfDate').value = record.as_of_date;
    document.getElementById('editOpeningCash').value = record.opening_amount;
    document.getElementById('editCurrency').value = `${record.currency} - ${record.currency_symbol}`;
    document.getElementById('editOpeningCashError').style.display = 'none';
    
    editModal.style.display = 'block';
}

// Update record
async function updateRecord() {
    const editOpeningCash = document.getElementById('editOpeningCash');
    const editOpeningCashError = document.getElementById('editOpeningCashError');
    
    if (!editOpeningCash.value || editOpeningCash.value <= 0) {
        editOpeningCashError.textContent = 'Please enter a valid amount greater than 0';
        editOpeningCashError.style.display = 'block';
        return;
    }
    
    updateBtn.disabled = true;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    
    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentEditId,
                opening_amount: editOpeningCash.value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showStatusMessage('Opening cash updated successfully.', 'success');
            editModal.style.display = 'none';
            await loadRecords();
        } else {
            showStatusMessage(data.error || 'Failed to update record', 'error');
        }
    } catch (error) {
        showStatusMessage('Failed to update record. Please try again.', 'error');
    } finally {
        updateBtn.disabled = false;
        updateBtn.innerHTML = '<i class="fas fa-save"></i> Update';
    }
}

// Open delete modal
function openDeleteModal(recordId) {
    currentDeleteId = recordId;
    deleteModal.style.display = 'block';
}

// Delete record
async function deleteRecord() {
    confirmDeleteBtn.disabled = true;
    confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    
    try {
        const response = await fetch('../../../../server/api/vouchers/cash_opening/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentDeleteId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showStatusMessage('Opening cash record deleted successfully.', 'success');
            deleteModal.style.display = 'none';
            await loadRecords();
            checkFormLock();
        } else {
            showStatusMessage(data.error || 'Failed to delete record', 'error');
        }
    } catch (error) {
        showStatusMessage('Failed to delete record. Please try again.', 'error');
    } finally {
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', initializeForm);