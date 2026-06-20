// Sample data for dropdowns
let expenseAccounts = [];
let paymentMethods = [];
let bankAccounts = [];
let companies = [];
let projects = [];

// Fetch data from API
async function fetchDropdownData() {
    try {
        const [expenseRes, paymentRes, bankRes, companyRes, projectRes] = await Promise.all([
            fetch('../../../../server/api/vouchers/expense_voucher/get-expense-accounts.php'),
            fetch('../../../../server/api/vouchers/expense_voucher/get-payment-methods.php'),
            fetch('../../../../server/api/vouchers/expense_voucher/get-bank-accounts.php'),
            fetch('../../../../server/api/vouchers/expense_voucher/get-companies.php'),
            fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
        ]);
        
        const expenseData = await expenseRes.json();
        const paymentData = await paymentRes.json();
        const bankData = await bankRes.json();
        const companyData = await companyRes.json();
        const projectData = await projectRes.json();
        
        if (expenseData.success) {
            expenseAccounts = expenseData.data;
        }
        if (paymentData.success) {
            paymentMethods = paymentData.data;
        }
        if (bankData.success) {
            bankAccounts = bankData.data;
        }
        if (companyData.success) {
            companies = companyData.data;
            const companySelect = document.getElementById('company');
            companyData.data.forEach((company, index) => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companySelect.appendChild(option);
                if (index === 0 && companyData.data.length === 1) {
                    option.selected = true;
                }
            });
        }
        if (projectData.success && Array.isArray(projectData.projects)) {
            projects = projectData.projects;
            const projectSelect = document.getElementById('project');
            projectData.projects.forEach(p => {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
                projectSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching dropdown data:', error);
    }
}

// DOM Elements
const helpToggle = document.getElementById('helpToggle');
const helpPanel = document.getElementById('helpPanel');
const dateInput = document.getElementById('date');
const expenseEntries = document.getElementById('expenseEntries');
const addEntryBtn = document.getElementById('addEntryBtn');
const postVoucherBtn = document.getElementById('postVoucherBtn');
const validationMessage = document.getElementById('validationMessage');

// Initialize date with today's date
const today = new Date().toISOString().split('T')[0];
dateInput.value = today;

// Initialize with one empty expense entry
let entryCounter = 1;

// Fetch dropdown data and initialize
fetchDropdownData().then(() => {
    if (IS_EDIT_MODE && VOUCHER_ID) {
        loadVoucherForEdit(VOUCHER_ID);
    } else {
        fetchNextVoucherNumber();
        addExpenseEntry();
    }
});

// Load voucher data for editing
async function loadVoucherForEdit(voucherId) {
    try {
        const response = await fetch(`../../../../server/api/vouchers/expense_voucher/get-voucher-details.php?id=${voucherId}`);
        const data = await response.json();
        
        if (data.success) {
            const voucher = data.data;
            
            // Populate main fields
            document.getElementById('date').value = voucher.date;
            document.getElementById('voucher').value = voucher.voucher_no;
            document.getElementById('description').value = voucher.description;
            
            // Set company with timeout to ensure dropdown is loaded
            setTimeout(() => {
                if (voucher.company_id) {
                    document.getElementById('company').value = voucher.company_id;
                }
                if (voucher.project_id) {
                    document.getElementById('project').value = voucher.project_id;
                }
            }, 100);
            
            // Populate expense lines
            voucher.lines.forEach((line, index) => {
                addExpenseEntry();
                const entry = document.getElementById(`entry-${index + 1}`);
                
                // Set expense account
                const expenseInput = entry.querySelector('.expense-account');
                expenseInput.value = line.account_name;
                expenseInput.dataset.id = line.account_id;
                
                // Load cost centers for this account and set the value
                if (line.cost_center_id) {
                    fetchCostCenters(line.account_id).then(costCenters => {
                        const costCenterInput = entry.querySelector('.cost-center');
                        const costCenterOptionsId = `costCenterOptions-${index + 1}`;
                        initSearchableDropdown(costCenterOptionsId, costCenters, costCenterInput);
                        costCenterInput.value = line.cost_center_name;
                        costCenterInput.dataset.id = line.cost_center_id;
                    });
                }
                
                // Set amount
                entry.querySelector('.amount').value = line.amount;
                
                // Set payment method
                entry.querySelector('.payment-method').value = line.payment_method_id;
                
                // Set bank account
                if (line.bank_account_id) {
                    entry.querySelector('.bank-account').value = line.bank_account_id;
                }
                
                // Set cheque no
                if (line.cheque_no) {
                    entry.querySelector('.cheque-no').value = line.cheque_no;
                }
                
                // Set cheque date
                if (line.cheque_date) {
                    entry.querySelector('.cheque-date').value = line.cheque_date;
                }
            });
        }
    } catch (error) {
        console.error('Error loading voucher:', error);
        alert('Failed to load voucher data');
    }
}

// Fetch next voucher number
async function fetchNextVoucherNumber() {
    try {
        const response = await fetch('../../../../server/api/vouchers/expense_voucher/get-next-voucher-number.php');
        const data = await response.json();
        if (data.success) {
            document.getElementById('voucher').value = data.voucher_no;
        }
    } catch (error) {
        console.error('Error fetching voucher number:', error);
    }
}

// Toggle help panel
helpToggle.addEventListener('click', () => {
    helpPanel.classList.toggle('active');
    helpToggle.innerHTML = helpPanel.classList.contains('active')
        ? '<i class="fas fa-times"></i> Hide Help'
        : '<i class="fas fa-question-circle"></i> Show Help';
});

// Add new expense entry
addEntryBtn.addEventListener('click', addExpenseEntry);

// Post voucher button
postVoucherBtn.addEventListener('click', validateAndPost);

// Function to add a new expense entry row
function addExpenseEntry() {
    const entry = document.createElement('tr');
    entry.id = `entry-${entryCounter}`;
    entry.innerHTML = `
                <td>${entryCounter}</td>
                <td>
                    <div class="searchable-dropdown">
                        <input type="text" class="expense-account" placeholder="Search expense account..." required>
                        <div class="dropdown-options" id="expenseOptions-${entryCounter}"></div>
                    </div>
                    <div class="error-text">Expense account is required</div>
                </td>
                <td>
                    <div class="searchable-dropdown">
                        <input type="text" class="cost-center" placeholder="Search cost center...">
                        <div class="dropdown-options" id="costCenterOptions-${entryCounter}"></div>
                    </div>
                </td>
                <td>
                    <input type="number" class="amount" placeholder="0.00" min="0" step="0.01" required>
                    <div class="error-text">Amount is required</div>
                </td>
                <td>
                    <select class="payment-method" required>
                        <option value="">Select method</option>
                        ${paymentMethods.map(method => `<option value="${method.id}">${method.name}</option>`).join('')}
                    </select>
                    <div class="error-text">Payment method is required</div>
                </td>
                <td>
                    <select class="bank-account">
                        <option value="">Select account</option>
                        ${bankAccounts.map(account => `<option value="${account.id}">${account.bank_name} - ${account.account_number}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <input type="text" class="cheque-no" placeholder="Cheque number">
                </td>
                <td>
                    <input type="date" class="cheque-date">
                </td>
                <td>
                    <button class="table-action-btn delete" onclick="removeEntry(${entryCounter})">
                        <i class="fas fa-minus"></i>
                    </button>
                </td>
            `;

    expenseEntries.appendChild(entry);

    // Initialize searchable dropdowns for this entry
    initSearchableDropdown(`expenseOptions-${entryCounter}`, expenseAccounts, entry.querySelector('.expense-account'));
    initSearchableDropdown(`costCenterOptions-${entryCounter}`, [], entry.querySelector('.cost-center'));
    
    // Add event listener to expense account to load cost centers
    const expenseInput = entry.querySelector('.expense-account');
    const costCenterInput = entry.querySelector('.cost-center');
    const costCenterOptionsId = `costCenterOptions-${entryCounter}`;
    
    expenseInput.addEventListener('input', async function() {
        const accountId = this.dataset.id;
        if (accountId) {
            const costCenters = await fetchCostCenters(accountId);
            initSearchableDropdown(costCenterOptionsId, costCenters, costCenterInput);
        }
    });
    
    // Add event listener to payment method to disable bank account and cheque date
    const paymentMethodSelect = entry.querySelector('.payment-method');
    const bankAccountSelect = entry.querySelector('.bank-account');
    const chequeNoInput = entry.querySelector('.cheque-no');
    const chequeDateInput = entry.querySelector('.cheque-date');
    
    paymentMethodSelect.addEventListener('change', function() {
        if (this.value === '7') {
            bankAccountSelect.disabled = true;
            chequeNoInput.disabled = true;
            chequeDateInput.disabled = true;
            bankAccountSelect.value = '';
            chequeNoInput.value = '';
            chequeDateInput.value = '';
        } else if (this.value === '5') {
            bankAccountSelect.disabled = false;
            chequeNoInput.disabled = true;
            chequeDateInput.disabled = true;
            chequeNoInput.value = '';
            chequeDateInput.value = '';
        } else {
            bankAccountSelect.disabled = false;
            chequeNoInput.disabled = false;
            chequeDateInput.disabled = false;
        }
    });

    entryCounter++;
}

// Function to remove an expense entry
function removeEntry(id) {
    const entry = document.getElementById(`entry-${id}`);
    if (entry) {
        entry.remove();
        // Update serial numbers
        updateSerialNumbers();
    }
}

// Update serial numbers after deletion
function updateSerialNumbers() {
    const rows = expenseEntries.querySelectorAll('tr');
    rows.forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
    entryCounter = rows.length + 1;
}

// Fetch cost centers based on account
async function fetchCostCenters(accountId) {
    try {
        const response = await fetch(`../../../../server/api/vouchers/expense_voucher/get-cost-centers.php?account_id=${accountId}`);
        const data = await response.json();
        return data.success ? data.data : [];
    } catch (error) {
        console.error('Error fetching cost centers:', error);
        return [];
    }
}

// Initialize searchable dropdown
function initSearchableDropdown(optionsId, data, inputElement) {
    const dropdownOptions = document.getElementById(optionsId);
    const inputField = inputElement;

    // Populate dropdown options
    function populateOptions(filter = '') {
        dropdownOptions.innerHTML = '';
        const filteredData = data.filter(item =>
            item.name.toLowerCase().includes(filter.toLowerCase())
        );

        filteredData.forEach(item => {
            const option = document.createElement('div');
            option.className = 'dropdown-option';
            option.textContent = item.name;
            option.dataset.id = item.id;
            option.addEventListener('click', () => {
                inputField.value = item.name;
                inputField.dataset.id = item.id;
                dropdownOptions.classList.remove('active');
                // Trigger input event to update dependent dropdowns
                inputField.dispatchEvent(new Event('input'));
            });
            dropdownOptions.appendChild(option);
        });
    }

    // Show dropdown on input focus
    inputField.addEventListener('focus', () => {
        populateOptions();
        dropdownOptions.classList.add('active');
    });

    // Filter options on input
    inputField.addEventListener('input', (e) => {
        populateOptions(e.target.value);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!dropdownOptions.contains(e.target) && e.target !== inputField) {
            dropdownOptions.classList.remove('active');
        }
    });
}

// Validate and post the voucher
async function validateAndPost() {
    let isValid = true;

    // Clear previous validation messages
    validationMessage.className = 'validation-message';
    validationMessage.textContent = '';

    // Validate main section
    if (!dateInput.value) {
        showError(dateInput, 'dateError');
        isValid = false;
    } else {
        hideError(dateInput, 'dateError');
    }

    const description = document.getElementById('description');
    if (!description.value.trim()) {
        showError(description, 'descriptionError');
        isValid = false;
    } else {
        hideError(description, 'descriptionError');
    }

    // Validate expense entries
    const entries = document.querySelectorAll('#expenseEntries tr');
    if (entries.length === 0) {
        showValidationMessage('error', 'Please add at least one expense entry.');
        isValid = false;
    }

    const expenseData = [];
    entries.forEach((entry, index) => {
        const expenseAccount = entry.querySelector('.expense-account');
        const costCenter = entry.querySelector('.cost-center');
        const amount = entry.querySelector('.amount');
        const paymentMethod = entry.querySelector('.payment-method');
        const bankAccount = entry.querySelector('.bank-account');
        const chequeNo = entry.querySelector('.cheque-no');
        const chequeDate = entry.querySelector('.cheque-date');

        if (!expenseAccount.value.trim() || !expenseAccount.dataset.id) {
            showError(expenseAccount, expenseAccount.parentElement.querySelector('.error-text'));
            isValid = false;
        } else {
            hideError(expenseAccount, expenseAccount.parentElement.querySelector('.error-text'));
        }

        if (!amount.value || parseFloat(amount.value) <= 0) {
            showError(amount, amount.nextElementSibling);
            isValid = false;
        } else {
            hideError(amount, amount.nextElementSibling);
        }

        if (!paymentMethod.value) {
            showError(paymentMethod, paymentMethod.nextElementSibling);
            isValid = false;
        } else {
            hideError(paymentMethod, paymentMethod.nextElementSibling);
        }

        if (isValid) {
            expenseData.push({
                expense_account_id: expenseAccount.dataset.id,
                cost_center_id: costCenter.dataset.id || null,
                amount: parseFloat(amount.value),
                payment_method_id: paymentMethod.value,
                bank_account_id: bankAccount.value || null,
                cheque_no: chequeNo.value || null,
                cheque_date: chequeDate.value || null
            });
        }
    });

    if (isValid) {
        try {
            const url = IS_EDIT_MODE 
                ? `../../../../server/api/vouchers/expense_voucher/expense-edit.php?id=${VOUCHER_ID}`
                : '../../../../server/api/vouchers/expense_voucher/expense-add.php';
            
            const response = await fetch(url, {
                method: IS_EDIT_MODE ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: dateInput.value,
                    company_id: document.getElementById('company').value,
                    project_id: document.getElementById('project').value || null,
                    voucher_number: document.getElementById('voucher').value,
                    description: description.value,
                    entries: expenseData
                })
            });
            const data = await response.json();
            
            if (data.success) {
                showValidationMessage('success', IS_EDIT_MODE ? 'Voucher updated successfully!' : 'Voucher posted successfully! The expense entry has been saved to the system.');
                
                setTimeout(() => {
                    if (IS_EDIT_MODE) {
                        window.location.href = 'expense-list.php';
                    } else {
                        expenseEntries.innerHTML = '';
                        addExpenseEntry();
                        description.value = '';
                        fetchNextVoucherNumber();
                        validationMessage.className = 'validation-message';
                    }
                }, 3000);
            } else {
                showValidationMessage('error', 'Error: ' + data.error);
            }
        } catch (error) {
            console.error('Error posting voucher:', error);
            showValidationMessage('error', 'Failed to post voucher. Please try again.');
        }
    } else {
        showValidationMessage('error', 'Please fix the errors in the form before posting.');
    }
}

// Helper functions for validation
function showError(field, errorElement) {
    field.classList.add('error');
    if (typeof errorElement === 'string') {
        document.getElementById(errorElement).style.display = 'block';
    } else {
        errorElement.style.display = 'block';
    }
}

function hideError(field, errorElement) {
    field.classList.remove('error');
    if (errorElement) {
        if (typeof errorElement === 'string') {
            const elem = document.getElementById(errorElement);
            if (elem) elem.style.display = 'none';
        } else {
            errorElement.style.display = 'none';
        }
    }
}

function showValidationMessage(type, message) {
    validationMessage.className = `validation-message ${type}`;
    validationMessage.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                ${message}
            `;
}

// Initialize action buttons
document.getElementById('addExpenseAccountBtn').addEventListener('click', () => {
    document.getElementById('addExpenseAccountModal').classList.add('active');
    fetchSubAccounts();
});

document.getElementById('closeExpenseAccountModal').addEventListener('click', () => {
    document.getElementById('addExpenseAccountModal').classList.remove('active');
});

document.getElementById('cancelExpenseAccount').addEventListener('click', () => {
    document.getElementById('addExpenseAccountModal').classList.remove('active');
});

document.getElementById('saveExpenseAccount').addEventListener('click', () => {
    saveExpenseAccount();
});

// Fetch sub accounts for modal
async function fetchSubAccounts() {
    try {
        const response = await fetch('../../../../server/api/vouchers/expense_voucher/get-sub-accounts.php');
        const data = await response.json();
        if (data.success) {
            const subAccountSelect = document.getElementById('subAccount');
            subAccountSelect.innerHTML = '<option value="">Select sub account</option>';
            data.data.forEach(sa => {
                subAccountSelect.innerHTML += `<option value="${sa.id}">${sa.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error fetching sub accounts:', error);
    }
}

// Save expense account
async function saveExpenseAccount() {
    const subAccount = document.getElementById('subAccount');
    const accountName = document.getElementById('accountName');
    let isValid = true;

    if (!subAccount.value) {
        showError(subAccount, 'subAccountError');
        isValid = false;
    } else {
        hideError(subAccount, 'subAccountError');
    }

    if (!accountName.value.trim()) {
        showError(accountName, 'accountNameError');
        isValid = false;
    } else {
        hideError(accountName, 'accountNameError');
    }

    if (isValid) {
        try {
            const response = await fetch('../../../../server/api/vouchers/expense_voucher/add-expense-account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sub_account_id: subAccount.value,
                    name: accountName.value
                })
            });
            const data = await response.json();
            if (data.success) {
                alert('Expense account added successfully!');
                document.getElementById('addExpenseAccountModal').classList.remove('active');
                accountName.value = '';
                subAccount.value = '';
                await fetchDropdownData();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (error) {
            console.error('Error saving expense account:', error);
            alert('Failed to save expense account');
        }
    }
}

// Close modal when clicking outside
document.getElementById('addExpenseAccountModal').addEventListener('click', (e) => {
    if (e.target.id === 'addExpenseAccountModal') {
        document.getElementById('addExpenseAccountModal').classList.remove('active');
    }
});

document.getElementById('addCostCenterBtn').addEventListener('click', () => {
    document.getElementById('addCostCenterModal').classList.add('active');
    initSearchableDropdown('costCenterAccountOptions', expenseAccounts, document.getElementById('costCenterAccount'));
});

document.getElementById('closeCostCenterModal').addEventListener('click', () => {
    document.getElementById('addCostCenterModal').classList.remove('active');
});

document.getElementById('cancelCostCenter').addEventListener('click', () => {
    document.getElementById('addCostCenterModal').classList.remove('active');
});

document.getElementById('saveCostCenter').addEventListener('click', () => {
    saveCostCenter();
});

// Save cost center
async function saveCostCenter() {
    const accountInput = document.getElementById('costCenterAccount');
    const costCenterName = document.getElementById('costCenterName');
    let isValid = true;

    if (!accountInput.value.trim() || !accountInput.dataset.id) {
        showError(accountInput, 'costCenterAccountError');
        isValid = false;
    } else {
        hideError(accountInput, 'costCenterAccountError');
    }

    if (!costCenterName.value.trim()) {
        showError(costCenterName, 'costCenterNameError');
        isValid = false;
    } else {
        hideError(costCenterName, 'costCenterNameError');
    }

    if (isValid) {
        try {
            const response = await fetch('../../../../server/api/vouchers/expense_voucher/add-cost-center.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    account_id: accountInput.dataset.id,
                    name: costCenterName.value
                })
            });
            const data = await response.json();
            if (data.success) {
                alert('Cost center added successfully!');
                document.getElementById('addCostCenterModal').classList.remove('active');
                costCenterName.value = '';
                accountInput.value = '';
                accountInput.dataset.id = '';
            } else {
                alert('Error: ' + data.error);
            }
        } catch (error) {
            console.error('Error saving cost center:', error);
            alert('Failed to save cost center');
        }
    }
}

// Close modal when clicking outside
document.getElementById('addCostCenterModal').addEventListener('click', (e) => {
    if (e.target.id === 'addCostCenterModal') {
        document.getElementById('addCostCenterModal').classList.remove('active');
    }
});

document.getElementById('manageCostCenterBtn').addEventListener('click', () => {
    document.getElementById('manageCostCenterModal').classList.add('active');
    loadCostCenters();
});

document.getElementById('closeManageCostCenterModal').addEventListener('click', () => {
    document.getElementById('manageCostCenterModal').classList.remove('active');
});

// Load all cost centers
async function loadCostCenters() {
    try {
        const response = await fetch('../../../../server/api/vouchers/expense_voucher/get-all-cost-centers.php');
        const data = await response.json();
        const costCenterList = document.getElementById('costCenterList');
        
        if (data.success && data.data.length > 0) {
            costCenterList.innerHTML = data.data.map(cc => `
                <tr>
                    <td>${cc.name}</td>
                    <td>${cc.account_name}</td>
                    <td>
                        <button class="table-action-btn" onclick="editCostCenter(${cc.id}, '${cc.name}', ${cc.account_id}, '${cc.account_name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="table-action-btn delete" onclick="deleteCostCenter(${cc.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            costCenterList.innerHTML = '<tr><td colspan="3" style="text-align: center;">No cost centers found</td></tr>';
        }
    } catch (error) {
        console.error('Error loading cost centers:', error);
    }
}

// Edit cost center
function editCostCenter(id, name, accountId, accountName) {
    document.getElementById('editCostCenterId').value = id;
    document.getElementById('editCostCenterName').value = name;
    document.getElementById('editCostCenterAccount').value = accountName;
    document.getElementById('editCostCenterAccount').dataset.id = accountId;
    
    initSearchableDropdown('editCostCenterAccountOptions', expenseAccounts, document.getElementById('editCostCenterAccount'));
    document.getElementById('editCostCenterModal').classList.add('active');
}

document.getElementById('closeEditCostCenterModal').addEventListener('click', () => {
    document.getElementById('editCostCenterModal').classList.remove('active');
});

document.getElementById('cancelEditCostCenter').addEventListener('click', () => {
    document.getElementById('editCostCenterModal').classList.remove('active');
});

document.getElementById('updateCostCenter').addEventListener('click', async () => {
    const id = document.getElementById('editCostCenterId').value;
    const accountInput = document.getElementById('editCostCenterAccount');
    const nameInput = document.getElementById('editCostCenterName');
    let isValid = true;

    if (!accountInput.value.trim() || !accountInput.dataset.id) {
        showError(accountInput, 'editCostCenterAccountError');
        isValid = false;
    } else {
        hideError(accountInput, 'editCostCenterAccountError');
    }

    if (!nameInput.value.trim()) {
        showError(nameInput, 'editCostCenterNameError');
        isValid = false;
    } else {
        hideError(nameInput, 'editCostCenterNameError');
    }

    if (isValid) {
        try {
            const response = await fetch('../../../../server/api/vouchers/expense_voucher/update-cost-center.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: id,
                    account_id: accountInput.dataset.id,
                    name: nameInput.value
                })
            });
            const data = await response.json();
            if (data.success) {
                alert('Cost center updated successfully!');
                document.getElementById('editCostCenterModal').classList.remove('active');
                loadCostCenters();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (error) {
            console.error('Error updating cost center:', error);
            alert('Failed to update cost center');
        }
    }
});

// Delete cost center
let costCenterToDelete = null;

function deleteCostCenter(id) {
    costCenterToDelete = id;
    document.getElementById('deleteCostCenterModal').classList.add('active');
}

document.getElementById('closeDeleteCostCenterModal').addEventListener('click', () => {
    document.getElementById('deleteCostCenterModal').classList.remove('active');
    costCenterToDelete = null;
});

document.getElementById('cancelDeleteCostCenter').addEventListener('click', () => {
    document.getElementById('deleteCostCenterModal').classList.remove('active');
    costCenterToDelete = null;
});

document.getElementById('confirmDeleteCostCenter').addEventListener('click', async () => {
    if (costCenterToDelete) {
        try {
            const response = await fetch(`../../../../server/api/vouchers/expense_voucher/delete-cost-center.php?id=${costCenterToDelete}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            if (data.success) {
                alert('Cost center deleted successfully!');
                document.getElementById('deleteCostCenterModal').classList.remove('active');
                costCenterToDelete = null;
                loadCostCenters();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (error) {
            console.error('Error deleting cost center:', error);
            alert('Failed to delete cost center');
        }
    }
});

// Close modals when clicking outside
document.getElementById('manageCostCenterModal').addEventListener('click', (e) => {
    if (e.target.id === 'manageCostCenterModal') {
        document.getElementById('manageCostCenterModal').classList.remove('active');
    }
});

document.getElementById('editCostCenterModal').addEventListener('click', (e) => {
    if (e.target.id === 'editCostCenterModal') {
        document.getElementById('editCostCenterModal').classList.remove('active');
    }
});

document.getElementById('deleteCostCenterModal').addEventListener('click', (e) => {
    if (e.target.id === 'deleteCostCenterModal') {
        document.getElementById('deleteCostCenterModal').classList.remove('active');
    }
});