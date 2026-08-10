// Sample employee data (will be fetched from API)
let employees = [];
let paymentMethods = [];
let bankAccounts = [];

// Fetch employees
fetch('../../../../server/api/hrm/employees/employee-list.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            employees = data.employees || [];
            populateEmployeeDropdown();
        }
    })
    .catch(err => console.error('Error fetching employees:', err));

// Fetch payment methods
fetch('../../../../server/api/hrm/payroll/payment-methods.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            paymentMethods = data.payment_methods || [];
            console.log('Payment methods loaded:', paymentMethods);
        }
    })
    .catch(err => console.error('Error fetching payment methods:', err));

// Fetch bank accounts
fetch('../../../../server/api/hrm/payroll/bank-accounts.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bankAccounts = data.bank_accounts || [];
            console.log('Bank accounts loaded:', bankAccounts);
        }
    })
    .catch(err => console.error('Error fetching bank accounts:', err));

// DOM elements
const dateInput = document.getElementById('date');
const payrollIdInput = document.getElementById('payrollId');
const tableBody = document.getElementById('tableBody');
const addRowBtn = document.getElementById('addRowBtn');
const balanceNotification = document.getElementById('balanceNotification');
const balanceAmount = document.getElementById('balanceAmount');
const closeNotification = document.getElementById('closeNotification');
const employeeDropdown = document.getElementById('employeeDropdown');
const employeeSearch = document.getElementById('employeeSearch');
const employeeItems = document.getElementById('employeeItems');
const typeDropdown = document.getElementById('typeDropdown');
const cancelBtn = document.getElementById('cancelBtn');
const submitBtn = document.getElementById('submitBtn');

// Initialize form
function initializeForm() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view');
    const editId = urlParams.get('edit');
    
    if (viewId || editId) {
        loadPayrollData(viewId || editId, !!viewId);
    } else {
        // Set current date
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;

        // Generate payroll ID from backend
        fetch('../../../../server/api/hrm/payroll/generate-payroll-id.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    payrollIdInput.value = data.payroll_code;
                }
            })
            .catch(err => console.error('Error generating payroll ID:', err));

        // Wait for data to load before adding first row
        setTimeout(() => {
            addTableRow();
        }, 800);
    }

    // Populate employee dropdown
    populateEmployeeDropdown();

    // Add event listeners
    addEventListeners();
}

// Load payroll data for view/edit
let editPayrollId = null;

function loadPayrollData(id, isViewMode) {
    if (!isViewMode) {
        editPayrollId = id;
    }
    fetch(`../../../../server/api/hrm/payroll/payroll-get.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const payroll = data.payroll;
                dateInput.value = payroll.payroll_date;
                payrollIdInput.value = payroll.payroll_code;
                
                // Wait for data to load
                setTimeout(() => {
                    payroll.items.forEach((item, index) => {
                        addTableRow();
                    });
                    
                    setTimeout(() => {
                        const rows = tableBody.querySelectorAll('tr');
                        payroll.items.forEach((item, index) => {
                            if (rows[index]) {
                                const row = rows[index];
                                row.querySelector('.employee-code').value = `${item.employee_id} - ${item.employee_name}`;
                                row.querySelector('.employee-id').value = item.employee_id;
                                row.querySelector('.type').value = item.type;
                                row.querySelector('.payment-method').value = item.payment_method_id;
                                row.querySelector('.bank-account').value = item.bank_account_id || '';
                                row.querySelector('.cheque-date').value = item.cheque_date || '';
                                row.querySelector('.description').value = item.description || '';
                                row.querySelector('.amount').value = item.amount;
                                
                                // Trigger payment method change to show/hide fields
                                row.querySelector('.payment-method').dispatchEvent(new Event('change'));
                            }
                        });
                    }, 200);
                    
                    if (isViewMode) {
                        // Disable all inputs in view mode
                        document.querySelectorAll('input, select, button').forEach(el => {
                            if (!el.id || el.id !== 'cancelBtn') el.disabled = true;
                        });
                        submitBtn.style.display = 'none';
                    }
                }, 1200);
            } else {
                alert('Error: ' + data.message);
                window.location.href = 'payroll-list.php';
            }
        })
        .catch(err => {
            alert('Error loading payroll: ' + err.message);
            window.location.href = 'payroll-list.php';
        });
}

// Add a new row to the payroll table
function addTableRow() {
    const rowIndex = tableBody.children.length;
    const row = document.createElement('tr');
    
    let paymentMethodOptions = '';
    if (paymentMethods && paymentMethods.length > 0) {
        paymentMethodOptions = paymentMethods.map(method => 
            `<option value="${method.id}">${method.name}</option>`
        ).join('');
    }
    
    let bankAccountOptions = '';
    if (bankAccounts && bankAccounts.length > 0) {
        bankAccountOptions = bankAccounts.map(account => 
            `<option value="${account.id}">${account.name}</option>`
        ).join('');
    }
    
    row.innerHTML = `
                <td>
                    <div class="dropdown">
                        <input type="text" class="employee-code" placeholder="Select employee" readonly data-row="${rowIndex}">
                        <input type="hidden" class="employee-id" data-row="${rowIndex}">
                    </div>
                </td>
                <td>
                    <div class="dropdown">
                        <input type="text" class="type" placeholder="Select type" readonly data-row="${rowIndex}">
                    </div>
                </td>
                <td>
                    <select class="payment-method">
                        <option value="">Select method</option>
                        ${paymentMethodOptions}
                    </select>
                </td>
                <td>
                    <select class="bank-account">
                        <option value="">Select account</option>
                        ${bankAccountOptions}
                    </select>
                </td>
                <td>
                    <input type="date" class="cheque-date">
                </td>
                <td>
                    <input type="text" class="description" placeholder="Enter description">
                </td>
                <td>
                    <input type="number" class="amount" placeholder="0.00" min="0" step="0.01">
                </td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-icon btn-add" data-action="add" title="Add row">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-icon btn-remove" data-action="remove" title="Remove row" ${rowIndex === 0 ? 'disabled' : ''}>
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </td>
            `;
    tableBody.appendChild(row);

    // Add event listeners to the new row
    addRowEventListeners(row, rowIndex);
}

// Populate employee dropdown
function populateEmployeeDropdown() {
    if (!employeeItems) return;
    employeeItems.innerHTML = '';
    employees.forEach(employee => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = `${employee.employee_id} - ${employee.full_name}`;
        item.dataset.code = employee.employee_id;
        item.dataset.name = employee.full_name;
        item.dataset.balance = employee.opening_balance || 0;
        employeeItems.appendChild(item);
    });
}

// Add event listeners
function addEventListeners() {
    // Add row button
    addRowBtn.addEventListener('click', () => addTableRow());

    // Close notification button
    closeNotification.addEventListener('click', () => {
        balanceNotification.classList.remove('show');
    });

    // Employee search
    employeeSearch.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const items = employeeItems.querySelectorAll('.dropdown-item');

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.matches('.employee-code, .employee-name, .type') &&
            !e.target.closest('.dropdown-content')) {
            employeeDropdown.classList.remove('show');
            typeDropdown.classList.remove('show');
        }
    });

    // Form action buttons
    cancelBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
            window.location.href = 'payroll-list.php';
        }
    });

    submitBtn.addEventListener('click', () => {
        if (validateForm()) {
            submitPayroll();
        }
    });
}

// Add event listeners to a table row
function addRowEventListeners(row, rowIndex) {
    const employeeCodeInput = row.querySelector('.employee-code');

    employeeCodeInput.addEventListener('click', (e) => {
        e.stopPropagation();
        const rect = e.target.getBoundingClientRect();
        employeeDropdown.style.top = `${rect.bottom}px`;
        employeeDropdown.style.left = `${rect.left}px`;
        employeeDropdown.style.minWidth = `${rect.width}px`;
        employeeDropdown.dataset.row = rowIndex;
        employeeDropdown.classList.add('show');
        setTimeout(() => employeeSearch.focus(), 100);
    });

    const typeInput = row.querySelector('.type');
    typeInput.addEventListener('click', (e) => {
        e.stopPropagation();
        const rect = e.target.getBoundingClientRect();
        typeDropdown.style.top = `${rect.bottom}px`;
        typeDropdown.style.left = `${rect.left}px`;
        typeDropdown.style.minWidth = `${rect.width}px`;
        typeDropdown.dataset.row = rowIndex;
        typeDropdown.classList.add('show');
    });

    // Payment method change (show/hide bank account and cheque date)
    const paymentMethodSelect = row.querySelector('.payment-method');
    const bankAccountSelect = row.querySelector('.bank-account');
    const chequeDateInput = row.querySelector('.cheque-date');

    paymentMethodSelect.addEventListener('change', () => {
        const methodId = paymentMethodSelect.value;
        
        // Show bank account if payment method is not Cash (7)
        if (methodId && methodId != '7') {
            bankAccountSelect.style.display = 'block';
        } else {
            bankAccountSelect.style.display = 'none';
            bankAccountSelect.value = '';
        }
        
        // Show cheque date if payment method is Cheque (6)
        if (methodId == '6') {
            chequeDateInput.style.display = 'block';
        } else {
            chequeDateInput.style.display = 'none';
            chequeDateInput.value = '';
        }
    });

    // Initially hide bank account and cheque date
    bankAccountSelect.style.display = 'none';
    chequeDateInput.style.display = 'none';

    // Action buttons
    const addBtn = row.querySelector('[data-action="add"]');
    const removeBtn = row.querySelector('[data-action="remove"]');

    addBtn.addEventListener('click', () => addTableRow());
    removeBtn.addEventListener('click', () => {
        if (tableBody.children.length > 1) {
            row.remove();
            updateRowIndices();
        }
    });
}

// Update row indices after removal
function updateRowIndices() {
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        const inputs = row.querySelectorAll('input[data-row]');
        inputs.forEach(input => {
            input.dataset.row = index;
        });

        // Enable/disable remove button for first row
        const removeBtn = row.querySelector('[data-action="remove"]');
        removeBtn.disabled = index === 0;
    });
}

// Employee dropdown item selection
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('dropdown-item') && e.target.closest('#employeeDropdown')) {
        const rowIndex = employeeDropdown.dataset.row;
        const rows = tableBody.querySelectorAll('tr');

        if (rows[rowIndex]) {
            const row = rows[rowIndex];
            const employeeCodeInput = row.querySelector('.employee-code');
            const employeeIdInput = row.querySelector('.employee-id');

            employeeCodeInput.value = e.target.textContent;
            employeeIdInput.value = e.target.dataset.code;

            balanceAmount.textContent = `${currencySymbol}${parseFloat(e.target.dataset.balance).toFixed(2)}`;
            balanceNotification.classList.add('show');

            setTimeout(() => {
                balanceNotification.classList.remove('show');
            }, 8000);

            employeeItems.querySelectorAll('.dropdown-item').forEach(item => {
                item.classList.remove('selected');
            });
            e.target.classList.add('selected');
        }

        employeeDropdown.classList.remove('show');
    }
});

// Type dropdown item selection
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('dropdown-item') && e.target.closest('#typeDropdown')) {
        const rowIndex = typeDropdown.dataset.row;
        const rows = tableBody.querySelectorAll('tr');

        if (rows[rowIndex]) {
            const row = rows[rowIndex];
            const typeInput = row.querySelector('.type');

            // Set value
            typeInput.value = e.target.dataset.value;
        }

        // Close dropdown
        typeDropdown.classList.remove('show');
    }
});

// Form validation
function validateForm() {
    const rows = tableBody.querySelectorAll('tr');

    if (!dateInput.value) {
        alert('Please select a date.');
        dateInput.focus();
        return false;
    }

    if (rows.length === 0) {
        alert('Please add at least one payroll entry.');
        return false;
    }

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const employeeId = row.querySelector('.employee-id').value;
        const type = row.querySelector('.type').value;
        const paymentMethod = row.querySelector('.payment-method').value;
        const amount = row.querySelector('.amount').value;

        if (!employeeId) {
            alert(`Row ${i + 1}: Please select an employee.`);
            return false;
        }
        if (!type) {
            alert(`Row ${i + 1}: Please select a payroll type.`);
            return false;
        }
        if (!paymentMethod) {
            alert(`Row ${i + 1}: Please select a payment method.`);
            return false;
        }
        if (!amount || parseFloat(amount) <= 0) {
            alert(`Row ${i + 1}: Please enter a valid amount.`);
            return false;
        }
    }

    return true;
}

// Submit payroll
function submitPayroll() {
    const rows = tableBody.querySelectorAll('tr');
    const items = [];

    rows.forEach(row => {
        const employeeId = row.querySelector('.employee-id').value;
        const type = row.querySelector('.type').value;
        const paymentMethodId = row.querySelector('.payment-method').value;
        const bankAccountId = row.querySelector('.bank-account').value || null;
        const chequeDate = row.querySelector('.cheque-date').value || null;
        const description = row.querySelector('.description').value || null;
        const amount = parseFloat(row.querySelector('.amount').value);

        items.push({
            employee_code: employeeId,
            type: type,
            payment_method_id: paymentMethodId,
            bank_account_id: bankAccountId,
            cheque_date: chequeDate,
            description: description,
            amount: amount
        });
    });

    const payload = {
        payroll_date: dateInput.value,
        payroll_code: payrollIdInput.value,
        items: items
    };

    if (editPayrollId) {
        payload.payroll_id = editPayrollId;
    }

    submitBtn.disabled = true;

    const apiUrl = editPayrollId 
        ? '../../../../server/api/hrm/payroll/payroll-edit.php'
        : '../../../../server/api/hrm/payroll/payroll-add.php';

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Payroll submitted successfully!');
            window.location.href = 'payroll-list.php';
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Error submitting payroll: ' + err.message);
        submitBtn.disabled = false;
    });
}

// Initialize the form when page loads
document.addEventListener('DOMContentLoaded', initializeForm);
