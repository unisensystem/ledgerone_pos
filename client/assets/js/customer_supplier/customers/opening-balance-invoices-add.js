// Global variables
let rowCounter = 0;
let customersData = [];
let distributionsData = [];
let employeesData = [];
let autoFillOfficerEnabled = false;
let defaultOfficerId = null;
let defaultOfficerName = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 Opening Balance Invoices form loading...');
    
    const form = document.getElementById('openingInvoicesForm');
    // NAYA: Auto-fill officer toggle listener
    const autoFillToggle = document.getElementById('autoFillOfficerToggle');
    const autoFillStatusText = document.getElementById('autoFillStatusText');
    if (autoFillToggle) {
        autoFillToggle.addEventListener('change', function () {
            autoFillOfficerEnabled = this.checked;
            if (!this.checked) {
                // switch off hote hi default reset — dobara on karne par fresh selection lega
                defaultOfficerId = null;
                defaultOfficerName = null;
                if (autoFillStatusText) autoFillStatusText.textContent = '';
            } else {
                if (autoFillStatusText) autoFillStatusText.textContent = '(select an officer in any row)';
            }
        });
    }
    // Load all data first
    Promise.all([
        loadCustomersData(),
        loadDistributionsData(),
        loadEmployeesData()
    ]).then(() => {
        console.log('✅ All data loaded successfully');
        addInvoiceRow();
    }).catch(error => {
        console.error('❌ Error loading data:', error);
        showNotification('Error', 'Failed to load form data. Please refresh the page.', 'error');
    });
    
    // Form submit handler
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveAllInvoices();
        });
    }
});

// Load customers from API
function loadCustomersData() {
    return fetch('../../../../server/api/customer_supplier/customers/get-customers-dropdown.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load customers');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                customersData = data.customers || [];
                console.log('✓ Customers loaded:', customersData.length);
            } else {
                throw new Error(data.message || 'Failed to load customers');
            }
        });
}

// Load distributions from API
function loadDistributionsData() {
    return fetch('../../../../server/api/customer_supplier/customers/get-distributions.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load distributions');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                distributionsData = data.distributions || [];
                console.log('✓ Distributions loaded:', distributionsData.length);
            } else {
                throw new Error(data.message || 'Failed to load distributions');
            }
        });
}

// Load employees from API
function loadEmployeesData() {
    return fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load employees');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                employeesData = data.employees || [];
                console.log('✓ Employees loaded:', employeesData.length);
            } else {
                throw new Error(data.message || 'Failed to load employees');
            }
        });
}

// Add new invoice row
function addInvoiceRow() {
    rowCounter++;
    const tbody = document.getElementById('invoicesTableBody');
    
    if (!tbody) {
        console.error('❌ Table body not found');
        return;
    }
    
    const rowNum = tbody.children.length + 1;
    const row = document.createElement('tr');
    row.setAttribute('data-row-id', rowCounter);
    row.className = 'invoice-row';
    
    // Create unique IDs for this row's searchable dropdowns
    const customerSearchId = `customer-search-${rowCounter}`;
    const customerOptionsId = `customer-options-${rowCounter}`;
    const customerInputId = `customer-input-${rowCounter}`;
    
    const distributionSearchId = `distribution-search-${rowCounter}`;
    const distributionOptionsId = `distribution-options-${rowCounter}`;
    const distributionInputId = `distribution-input-${rowCounter}`;
    
    const officerSearchId = `officer-search-${rowCounter}`;
    const officerOptionsId = `officer-options-${rowCounter}`;
    const officerInputId = `officer-input-${rowCounter}`;
    
    // Build row HTML
    row.innerHTML = `
        <td class="row-number">${rowNum}</td>
        <td>
            <div class="searchable-dropdown">
                <input type="text" class="search-input customer-search" autocomplete="off" placeholder="Search customer..." id="${customerSearchId}" data-row-id="${rowCounter}">
                <input type="hidden" id="${customerInputId}" class="customer-input" data-row-id="${rowCounter}">
            </div>
        </td>
        <td>
            <div class="searchable-dropdown">
                <input type="text" class="search-input distribution-search" autocomplete="off" placeholder="Search distribution..." id="${distributionSearchId}" data-row-id="${rowCounter}">
                <input type="hidden" id="${distributionInputId}" class="distribution-input" data-row-id="${rowCounter}">
            </div>
        </td>
        <td>
            <div class="searchable-dropdown">
                <input type="text" class="search-input officer-search" autocomplete="off" placeholder="Search officer..." id="${officerSearchId}" data-row-id="${rowCounter}">
                <input type="hidden" id="${officerInputId}" class="officer-input" data-row-id="${rowCounter}">
            </div>
        </td>
        <td>
            <input type="text" class="invoice-number form-control required" placeholder="INV001" data-row-id="${rowCounter}">
        </td>
        <td>
            <input type="number" class="debit-amount form-control" placeholder="0.00" step="0.01" min="0" data-row-id="${rowCounter}">
        </td>
        <td>
            <input type="number" class="credit-amount form-control" placeholder="0.00" step="0.01" min="0" data-row-id="${rowCounter}">
        </td>
        <td>
            <input type="date" class="invoice-date form-control required" data-row-id="${rowCounter}">
        </td>
        <td class="actions-cell">
            <button type="button" class="btn btn-success btn-sm add-row-btn" data-row-id="${rowCounter}" title="Add new row">
                <i class="fas fa-plus"></i>
            </button>
            <button type="button" class="btn btn-danger btn-sm delete-row-btn" data-row-id="${rowCounter}" title="Delete this row">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
    
    // Create dropdown options containers and append to body
    const customerOptionsDiv = document.createElement('div');
    customerOptionsDiv.id = customerOptionsId;
    customerOptionsDiv.className = 'dropdown-options';
    customerOptionsDiv.style.position = 'absolute';
    customerOptionsDiv.style.display = 'none';
    customerOptionsDiv.style.zIndex = '9999';
    customersData.forEach(cust => {
        const option = document.createElement('div');
        option.className = 'dropdown-option';
        option.setAttribute('data-value', cust.id);
        option.textContent = `${cust.customer_code} - ${cust.customer_name}`;
        customerOptionsDiv.appendChild(option);
    });
    document.body.appendChild(customerOptionsDiv);
    
    const distributionOptionsDiv = document.createElement('div');
    distributionOptionsDiv.id = distributionOptionsId;
    distributionOptionsDiv.className = 'dropdown-options';
    distributionOptionsDiv.style.position = 'absolute';
    distributionOptionsDiv.style.display = 'none';
    distributionOptionsDiv.style.zIndex = '9999';
    distributionsData.forEach(dist => {
        const option = document.createElement('div');
        option.className = 'dropdown-option';
        option.setAttribute('data-value', dist.id);
        option.textContent = `${dist.supplier_code} - ${dist.supplier_name}`;
        distributionOptionsDiv.appendChild(option);
    });
    document.body.appendChild(distributionOptionsDiv);
    
    const officerOptionsDiv = document.createElement('div');
    officerOptionsDiv.id = officerOptionsId;
    officerOptionsDiv.className = 'dropdown-options';
    officerOptionsDiv.style.position = 'absolute';
    officerOptionsDiv.style.display = 'none';
    officerOptionsDiv.style.zIndex = '9999';
    employeesData.forEach(emp => {
        const option = document.createElement('div');
        option.className = 'dropdown-option';
        option.setAttribute('data-value', emp.id);
        option.textContent = emp.full_name;
        officerOptionsDiv.appendChild(option);
    });
    document.body.appendChild(officerOptionsDiv);
    
    // Initialize searchable dropdowns for this row
        // Initialize searchable dropdowns for this row
    initSearchableDropdown(customerSearchId, customerOptionsId, customerInputId);
    initSearchableDropdown(distributionSearchId, distributionOptionsId, distributionInputId);
    
    // Officer dropdown — callback ke sath jo "first selection" ko capture karega
    initSearchableDropdown(officerSearchId, officerOptionsId, officerInputId, function (value, displayText) {
        if (autoFillOfficerEnabled && !defaultOfficerId) {
            defaultOfficerId = value;
            defaultOfficerName = displayText;
            const statusText = document.getElementById('autoFillStatusText');
            if (statusText) statusText.textContent = `(default: ${displayText})`;
        }
    });

    // NAYA: agar auto-fill ON hai aur default officer set ho chuka hai,
    // to naye row mein by default wahi officer fill kar dein (changeable rahega)
    if (autoFillOfficerEnabled && defaultOfficerId) {
        const officerSearchInput = document.getElementById(officerSearchId);
        const officerHiddenInput = document.getElementById(officerInputId);
        if (officerSearchInput && officerHiddenInput) {
            officerSearchInput.value = defaultOfficerName;
            officerHiddenInput.value = defaultOfficerId;
        }
    }
    
    // Add event listeners for action buttons
    const addBtn = row.querySelector('.add-row-btn');
    const deleteBtn = row.querySelector('.delete-row-btn');
    const debitInput = row.querySelector('.debit-amount');
    const creditInput = row.querySelector('.credit-amount');

    if (addBtn) {
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            addInvoiceRow();
        });
    }
    
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            deleteRow(rowCounter);
        });
    }
    
    if (debitInput && creditInput) {
        const onDebitInput = function () {
            if (debitInput.value && parseFloat(debitInput.value) > 0) {
                creditInput.disabled = true;
                creditInput.value = '';
            } else {
                creditInput.disabled = false;
            }
            updateTotals();
        };
        const onCreditInput = function () {
            if (creditInput.value && parseFloat(creditInput.value) > 0) {
                debitInput.disabled = true;
                debitInput.value = '';
            } else {
                debitInput.disabled = false;
            }
            updateTotals();
        };
        debitInput.addEventListener('change', onDebitInput);
        debitInput.addEventListener('input', onDebitInput);
        creditInput.addEventListener('change', onCreditInput);
        creditInput.addEventListener('input', onCreditInput);
    }

    updateRowNumbers();
    updateDeleteButtons();
    updateTotals();
}

// Update button visibility
function updateDeleteButtons() {
    const rows = document.querySelectorAll('.invoice-row');
    rows.forEach((row, index) => {
        const addBtn = row.querySelector('.add-row-btn');
        const deleteBtn = row.querySelector('.delete-row-btn');
        
        if (index === rows.length - 1) {
            // Last row - show both add and delete buttons
            if (addBtn) addBtn.style.display = 'inline-block';
            if (deleteBtn) deleteBtn.style.display = 'inline-block';
        } else {
            // Not last row - hide add button, show delete
            if (addBtn) addBtn.style.display = 'none';
            if (deleteBtn) deleteBtn.style.display = 'inline-block';
        }
    });
}

// Delete row
function deleteRow(rowId) {
    const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
    
    if (!row) {
        console.error('❌ Row not found:', rowId);
        return;
    }
    
    const tbody = document.getElementById('invoicesTableBody');
    
    if (tbody.children.length > 1) {
        row.remove();
        updateRowNumbers();
        updateDeleteButtons();
        updateTotals();
    } else {
        showNotification('Warning', 'At least one row must remain', 'warning');
    }
}

// Update row numbers
function updateRowNumbers() {
    const rows = document.querySelectorAll('.invoice-row');
    rows.forEach((row, index) => {
        const rowNumCell = row.querySelector('.row-number');
        if (rowNumCell) {
            rowNumCell.textContent = index + 1;
        }
    });
}

// Update total debit and total credit
function updateTotals() {
    let totalDebit = 0;
    let totalCredit = 0;

    document.querySelectorAll('.debit-amount').forEach(input => {
        totalDebit += parseFloat(input.value) || 0;
    });
    document.querySelectorAll('.credit-amount').forEach(input => {
        totalCredit += parseFloat(input.value) || 0;
    });

    const totalDebitElement = document.getElementById('totalDebit');
    if (totalDebitElement) {
        totalDebitElement.textContent = totalDebit.toFixed(2);
    }
    const totalCreditElement = document.getElementById('totalCredit');
    if (totalCreditElement) {
        totalCreditElement.textContent = totalCredit.toFixed(2);
    }
}

// Initialize searchable dropdown
function initSearchableDropdown(searchInputId, optionsContainerId, hiddenInputId, onSelectCallback) {
        const searchInput = document.getElementById(searchInputId);
    const optionsContainer = document.getElementById(optionsContainerId);
    const hiddenInput = document.getElementById(hiddenInputId);
    let selectedIndex = -1;

    if (!searchInput || !optionsContainer || !hiddenInput) {
        console.error('❌ Dropdown elements not found:', { searchInputId, optionsContainerId, hiddenInputId });
        return;
    }

    // Show options on click
    searchInput.addEventListener('click', function (e) {
        e.stopPropagation();
        const rect = searchInput.getBoundingClientRect();
        optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
        optionsContainer.style.left = rect.left + 'px';
        optionsContainer.style.width = rect.width + 'px';
        optionsContainer.style.display = 'block';
        searchInput.focus();
        filterOptions();
    });

    // Show options on focus
    searchInput.addEventListener('focus', function (e) {
        const rect = searchInput.getBoundingClientRect();
        optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
        optionsContainer.style.left = rect.left + 'px';
        optionsContainer.style.width = rect.width + 'px';
        optionsContainer.style.display = 'block';
        filterOptions();
    });

    // Keyboard navigation
    searchInput.addEventListener('keydown', function (e) {
        if (optionsContainer.style.display === 'none') return;
        
        const visibleOptions = Array.from(optionsContainer.getElementsByClassName('dropdown-option'))
            .filter(option => option.style.display !== 'none');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, visibleOptions.length - 1);
            updateSelection(visibleOptions);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(visibleOptions);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && visibleOptions[selectedIndex]) {
                visibleOptions[selectedIndex].click();
            }
        }
    });

    function updateSelection(visibleOptions) {
        visibleOptions.forEach((option, index) => {
            if (index === selectedIndex) {
                option.style.backgroundColor = 'var(--surface-1)';
                option.scrollIntoView({ block: 'nearest' });
            } else {
                option.style.backgroundColor = '';
            }
        });
    }

    // Filter on input
    searchInput.addEventListener('input', function () {
        selectedIndex = -1;
        filterOptions();
    });

    // Select option
      // Select option
    optionsContainer.addEventListener('click', function (e) {
        if (e.target.classList.contains('dropdown-option')) {
            const value = e.target.getAttribute('data-value');
            const displayText = e.target.textContent;

            searchInput.value = displayText;
            hiddenInput.value = value;
            optionsContainer.style.display = 'none';
            selectedIndex = -1;

            // NAYA: callback fire karein (sirf officer dropdown ke liye pass hoga)
            if (typeof onSelectCallback === 'function') {
                onSelectCallback(value, displayText);
            }
        }
    });
    // Hide on click outside
    document.addEventListener('click', function (e) {
        if (e.target !== searchInput && !optionsContainer.contains(e.target)) {
            optionsContainer.style.display = 'none';
        }
    });

    // Reposition on scroll
    window.addEventListener('scroll', function () {
        if (optionsContainer.style.display === 'block') {
            const rect = searchInput.getBoundingClientRect();
            optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
            optionsContainer.style.left = rect.left + 'px';
        }
    });

    function filterOptions() {
        const searchTerm = searchInput.value.toLowerCase();
        const options = Array.from(optionsContainer.getElementsByClassName('dropdown-option'));
        
        let visibleCount = 0;
        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                option.style.display = 'block';
                visibleCount++;
            } else {
                option.style.display = 'none';
            }
        });
        
        console.log(`🔽 Showing ${visibleCount}/${options.length} options for "${searchTerm}"`);
    }
}

// Validate form data
function validateFormData() {
    const rows = document.querySelectorAll('.invoice-row');
    const errors = [];
    const invoices = [];
    
    if (rows.length === 0) {
        return { valid: false, invoices: [], errors: ['No invoices to save'] };
    }
    
    let validRowCount = 0;
    
    rows.forEach((row, index) => {
        const customerId = row.querySelector('.customer-input').value.trim();
        const distributionId = row.querySelector('.distribution-input').value.trim();
        const officerId = row.querySelector('.officer-input').value.trim();
        const invoiceNumber = row.querySelector('.invoice-number').value.trim();
        const debit = row.querySelector('.debit-amount').value.trim();
        const credit = row.querySelector('.credit-amount').value.trim();
        const invoiceDate = row.querySelector('.invoice-date').value.trim();

        const debitVal = parseFloat(debit) || 0;
        const creditVal = parseFloat(credit) || 0;

        const rowErrors = [];

        if (!customerId) rowErrors.push('Customer is required');
        if (!invoiceDate) rowErrors.push('Invoice date is required');
        if (debitVal <= 0 && creditVal <= 0) {
            rowErrors.push('Either Debit or Credit amount must be greater than 0');
        }

        if (rowErrors.length > 0) {
            rowErrors.forEach(err => {
                errors.push(`Row ${index + 1}: ${err}`);
            });
        } else {
            validRowCount++;
            invoices.push({
                customer_id: parseInt(customerId),
                distribution_id: distributionId ? parseInt(distributionId) : null,
                employee_id: officerId ? parseInt(officerId) : null,
                invoice_number: invoiceNumber,
                invoice_date: invoiceDate,
                debit: debitVal,
                credit: creditVal
            });
        }
    });
    
    return { 
        valid: validRowCount > 0 && errors.length === 0, 
        invoices: invoices, 
        errors: errors, 
        validRowCount: validRowCount 
    };
}

// Save all invoices
function saveAllInvoices() {
    const validation = validateFormData();
    
    if (!validation.valid) {
        let errorMsg = 'Please fix the following errors:\n\n';
        validation.errors.forEach(error => {
            errorMsg += '• ' + error + '\n';
        });
        showNotification('Validation Error', errorMsg, 'error');
        return;
    }
    
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    console.log('💾 Saving', validation.invoices.length, 'invoices');
    
    fetch('../../../../server/api/customer_supplier/customers/opening-balance-invoices-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoices: validation.invoices })
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        if (data.success) {
            showNotification('✅ Success', `${data.saved_count} invoices saved successfully!`, 'success');
            
            setTimeout(() => {
                document.getElementById('invoicesTableBody').innerHTML = '';
                rowCounter = 0;
                addInvoiceRow();
                updateTotals();
            }, 1500);
        } else {
            showNotification('❌ Error', data.message || 'Failed to save', 'error');
        }
    })
    .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        console.error('❌ Error:', error);
        showNotification('❌ Error', 'Failed to save: ' + error.message, 'error');
    });
}

// Show notification
function showNotification(title, message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const bgColor = {
        'success': '#4CAF50',
        'error': '#f44336',
        'warning': '#ff9800',
        'info': '#2196F3'
    }[type] || '#2196F3';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 20px;
        background-color: ${bgColor};
        color: white;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        z-index: 10000;
        max-width: 500px;
        max-height: 400px;
        overflow-y: auto;
        word-wrap: break-word;
        white-space: pre-wrap;
        font-family: Arial, sans-serif;
    `;
    
    const titleEl = document.createElement('div');
    titleEl.style.cssText = 'font-weight: 600; margin-bottom: 8px; font-size: 14px;';
    titleEl.textContent = title;
    
    const messageEl = document.createElement('div');
    messageEl.style.cssText = 'font-size: 13px; line-height: 1.4;';
    messageEl.textContent = message;
    
    notification.appendChild(titleEl);
    notification.appendChild(messageEl);
    document.body.appendChild(notification);
    
    setTimeout(() => notification.remove(), 6000);
}
