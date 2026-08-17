// ===== CUSTOMIZE FIELDS =====
const CUSTOMIZE_KEY = 'receiveVoucherCustomizeFields';

// bulkCol: column index (0-based) in the bulk table. null = single-mode only field.
const allCustomizeFields = [
    { id: 'voucherDate',      label: 'Voucher Date',      visible: true,  bulkCol: 0  },
    { id: 'voucherNumber',    label: 'Voucher #',         visible: true,  bulkCol: null },
    { id: 'company',          label: 'Company',           visible: true,  bulkCol: null },
    { id: 'customerCode',     label: 'Customer Code',     visible: true,  bulkCol: 1  },
    { id: 'previousBalance',  label: 'Previous Balance',  visible: true,  bulkCol: 2  },
    { id: 'remainingBalance', label: 'Remaining Balance', visible: true,  bulkCol: 10 },
    { id: 'subAccount',       label: 'Sub Account',       visible: true,  bulkCol: 3  },
    { id: 'billNo',           label: 'Bill No',           visible: true,  bulkCol: 4  },
    { id: 'dsrNo',            label: 'DSR#',              visible: true,  bulkCol: 5  },
    { id: 'currency',         label: 'Currency',          visible: true,  bulkCol: 8  },
    { id: 'project',          label: 'Project',           visible: true,  bulkCol: 6  },
    { id: 'container',        label: 'Container',         visible: false, bulkCol: 7  },
    { id: 'amount',           label: 'Amount',            visible: true,  bulkCol: 9  },
    { id: 'paymentMethod',    label: 'Payment Method',    visible: true,  bulkCol: 11 },
    { id: 'againstPdc',       label: 'Against PDC',       visible: true,  bulkCol: 12 },
    { id: 'bankAccount',      label: 'Bank Account',      visible: true,  bulkCol: 13 },
    { id: 'chequeDate',       label: 'Cheque Date',       visible: true,  bulkCol: 14 },
    { id: 'chequeNo',         label: 'Cheque No',         visible: true,  bulkCol: 15 },
    { id: 'recoveryOfficer',  label: 'Recovery Officer',  visible: true,  bulkCol: null },
    { id: 'attachment',       label: 'Attachment',        visible: true,  bulkCol: null },
    { id: 'description',      label: 'Description',       visible: true,  bulkCol: 16 },
];

// Fields whose single-mode visibility is controlled by payment method logic — skip form-group toggling
const PAYMENT_CONTROLLED = ['bankAccount', 'chequeDate', 'chequeNo'];

function getCustomizeDefaults() {
    const d = {};
    allCustomizeFields.forEach(f => { d[f.id] = f.visible; });
    return d;
}

function getCustomizeVisibility() {
    const defaults = getCustomizeDefaults();
    try {
        const stored = localStorage.getItem(CUSTOMIZE_KEY);
        return stored ? Object.assign({}, defaults, JSON.parse(stored)) : defaults;
    } catch (e) { return defaults; }
}

function saveCustomizeVisibility(v) {
    localStorage.setItem(CUSTOMIZE_KEY, JSON.stringify(v));
}

function getFieldFormGroup(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return null;
    return el.closest('.form-group');
}

// Show/hide a bulk table column (th + all td at that index)
function setBulkColumnVisible(colIndex, isVisible) {
    const display = isVisible ? '' : 'none';
    const table = document.getElementById('bulkTable');
    if (!table) return;
    // header
    const th = table.querySelectorAll('thead tr th')[colIndex];
    if (th) th.style.display = display;
    // all body rows
    table.querySelectorAll('tbody tr').forEach(row => {
        const td = row.querySelectorAll('td')[colIndex];
        if (td) td.style.display = display;
    });
    // footer
    const footTds = table.querySelectorAll('tfoot tr td');
    // footer uses colspan — no per-cell hiding needed
}

function applyCustomizeVisibility() {
    const v = getCustomizeVisibility();
    allCustomizeFields.forEach(f => {
        const isVisible = v[f.id];

        // --- Single mode form-group ---
        if (!PAYMENT_CONTROLLED.includes(f.id)) {
            const fg = getFieldFormGroup(f.id);
            if (fg) {
                if (isVisible) {
                    fg.classList.remove('hidden');
                    fg.style.display = '';
                } else {
                    fg.classList.add('hidden');
                    fg.style.display = 'none';
                }
            }
        }

        // --- Bulk table column ---
        if (f.bulkCol !== null) {
            setBulkColumnVisible(f.bulkCol, isVisible);
        }
    });
}

// Called inside addBulkRow() after appending the new row
function applyBulkColumnVisibilityToRow(row) {
    const v = getCustomizeVisibility();
    allCustomizeFields.forEach(f => {
        if (f.bulkCol === null) return;
        const td = row.querySelectorAll('td')[f.bulkCol];
        if (td) td.style.display = v[f.id] ? '' : 'none';
    });
}

function populateCustomizeList() {
    const v = getCustomizeVisibility();
    const list = document.getElementById('customizeFieldsList');
    list.innerHTML = '';
    allCustomizeFields.forEach(f => {
        const item = document.createElement('div');
        item.className = 'customize-field-item';
        item.innerHTML = `
            <label class="customize-field-label">
                <input type="checkbox" class="customize-field-checkbox" data-field-id="${f.id}" ${v[f.id] ? 'checked' : ''}>
                <span>${f.label}</span>
            </label>`;
        list.appendChild(item);
    });
}

// Apply on load
applyCustomizeVisibility();

// Button open
document.getElementById('customizeFieldsBtn').addEventListener('click', function () {
    populateCustomizeList();
    document.getElementById('customizeFieldsModal').classList.add('show');
});

// Close
function closeCustomizeModal() {
    document.getElementById('customizeFieldsModal').classList.remove('show');
}
document.getElementById('customizeFieldsModalClose').addEventListener('click', closeCustomizeModal);
document.getElementById('customizeFieldsModal').addEventListener('click', function (e) {
    if (e.target === this) closeCustomizeModal();
});

// Select All / Deselect All
document.getElementById('customizeSelectAllBtn').addEventListener('click', function () {
    document.querySelectorAll('.customize-field-checkbox').forEach(cb => cb.checked = true);
});
document.getElementById('customizeDeselectAllBtn').addEventListener('click', function () {
    document.querySelectorAll('.customize-field-checkbox').forEach(cb => cb.checked = false);
});

// Save
document.getElementById('customizeSaveBtn').addEventListener('click', function () {
    const v = {};
    document.querySelectorAll('.customize-field-checkbox').forEach(cb => {
        v[cb.dataset.fieldId] = cb.checked;
    });
    saveCustomizeVisibility(v);
    applyCustomizeVisibility();
    closeCustomizeModal();
});

// Reset to default
document.getElementById('customizeResetBtn').addEventListener('click', function () {
    if (confirm('Reset field visibility to default?')) {
        saveCustomizeVisibility(getCustomizeDefaults());
        applyCustomizeVisibility();
        populateCustomizeList();
    }
});
// ===== END CUSTOMIZE FIELDS =====

// Check permissions
fetch('../../../../server/api/vouchers/receive_voucher/check-permissions.php')
    .then(r => r.json())
    .then(data => {
        if (data.success && !data.can_create) {
            document.getElementById('postBtn').disabled = true;
            document.getElementById('postBtn').title = 'You do not have permission to create vouchers';
            document.getElementById('postBulkBtn').disabled = true;
            document.getElementById('postBulkBtn').title = 'You do not have permission to create vouchers';
        }
    });

// Set default voucher date to today
document.getElementById('voucherDate').valueAsDate = new Date();

// Voucher number will be generated by server
document.getElementById('voucherNumber').value = 'Auto-generated';

// ===== Floating dropdown positioning (dropdowns are moved to <body> and =====
// ===== positioned with fixed coords so they never get clipped/cramped   =====
// ===== inside the scrollable bulk table or a narrow form column)        =====
const openFloatingDropdowns = new Set();

function makeDropdownFloating(dropdown) {
    if (dropdown.dataset.floating === '1') return;
    dropdown.dataset.floating = '1';
    document.body.appendChild(dropdown);
}

function positionFloatingDropdown(input, dropdown) {
    const rect = input.getBoundingClientRect();
    const width = Math.max(rect.width, 340);
    let left = rect.left;
    const maxLeft = window.innerWidth - width - 12;
    if (left > maxLeft) left = Math.max(12, maxLeft);
    dropdown.style.left = left + 'px';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.width = width + 'px';
}

function showFloatingDropdown(input, dropdown) {
    makeDropdownFloating(dropdown);
    positionFloatingDropdown(input, dropdown);
    dropdown.classList.add('show');
    dropdown._anchorInput = input;
    openFloatingDropdowns.add(dropdown);
}

function hideFloatingDropdown(dropdown) {
    dropdown.classList.remove('show');
    openFloatingDropdowns.delete(dropdown);
}

function repositionOpenDropdowns() {
    openFloatingDropdowns.forEach(d => {
        if (d._anchorInput) positionFloatingDropdown(d._anchorInput, d);
    });
}

window.addEventListener('resize', repositionOpenDropdowns);
window.addEventListener('scroll', repositionOpenDropdowns, true);
// ===== END floating dropdown helpers =====

// Customers data
let customers = [];
let bulkCustomers = [];
let bills = [];
let employees = [];
let companies = [];
let projects = [];
let containers = [];
let currentCustomerId = null;
let pendingPdcs = [];

// Fetch customers from API, optionally filtered by recovery officer (associated_sales_officer_id)
function fetchCustomers(recoveryOfficerId) {
    let url = '../../../../server/api/vouchers/receive_voucher/get-suppliers.php';
    if (recoveryOfficerId) url += `?recovery_officer_id=${recoveryOfficerId}`;
    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) return [];
            return data.data.map(customer => ({
                code: customer.customer_code,
                name: customer.customer_name,
                id: customer.id
            }));
        })
        .catch(error => {
            console.error('Error fetching customers:', error);
            return [];
        });
}

// Resolve the numeric employees.id for a searchable Recovery Officer input (used to join against customers.associated_sales_officer_id)
function resolveEmployeeNumericId(inputId) {
    const empCode = document.getElementById(inputId).dataset.selectedId;
    if (!empCode) return null;
    const emp = employees.find(e => e.id === empCode);
    return emp ? emp.numericId : null;
}

// Called whenever a Recovery Officer is selected/cleared — filters which customers show in the linked Customer Code dropdown
function onRecoveryOfficerChanged(mode) {
    const inputId = mode === 'bulk' ? 'bulkRecoveryOfficer' : 'recoveryOfficer';
    const numericId = resolveEmployeeNumericId(inputId);
    const targetList = mode === 'bulk' ? bulkCustomers : customers;

    fetchCustomers(numericId).then(list => {
        targetList.length = 0;
        targetList.push(...list);

        if (mode === 'single') {
            // Previously selected customer may not belong to the newly selected officer — clear it
            const customerInput = document.getElementById('customerCode');
            customerInput.value = '';
            delete customerInput.dataset.selectedId;
            document.getElementById('previousBalance').value = '';
            document.getElementById('remainingBalance').value = '';
            document.getElementById('subAccount').innerHTML = '<option value="">Select Sub Account</option>';
        }
    });
}

// Initial (unfiltered) load for both single-mode and bulk-mode customer lists
fetchCustomers(null).then(list => {
    customers.push(...list);
    bulkCustomers.push(...list);
    setupSearchableDropdown('customerCode', 'customerDropdown', customers, 'name', 'code');
});

// Fetch companies from API
fetch('../../../../server/api/vouchers/receive_voucher/get-companies.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            companies = data.data;
            const companySelect = document.getElementById('company');
            const bulkCompanySelect = document.getElementById('bulkCompany');
            data.data.forEach((company, index) => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companySelect.appendChild(option);
                if (index === 0 && data.data.length === 1) {
                    option.selected = true;
                }
                
                const bulkOption = document.createElement('option');
                bulkOption.value = company.id;
                bulkOption.textContent = company.company_name;
                bulkCompanySelect.appendChild(bulkOption);
                if (index === 0 && data.data.length === 1) {
                    bulkOption.selected = true;
                }
            });
        }
    })
    .catch(error => console.error('Error fetching companies:', error));

// Fetch projects from API
fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && Array.isArray(data.projects)) {
            projects = data.projects;
            const projectSelect = document.getElementById('project');
            data.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                const cityName = project.city_name || 'N/A';
                option.textContent = `${project.project_code} - ${project.project_name} - ${cityName}`;
                projectSelect.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching projects:', error));

// Fetch containers from API
fetch('../../../../server/api/vouchers/receive_voucher/get-containers.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            containers = data.data;
            const containerSelect = document.getElementById('container');
            data.data.forEach(container => {
                const option = document.createElement('option');
                option.value = container.id;
                option.textContent = `${container.container_code} - ${container.container_name}`;
                containerSelect.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching containers:', error));

// Fetch pending PDCs for selected customer
function fetchPendingPdcsForCustomer(customerId) {
    const select = document.getElementById('againstPdc');
    select.innerHTML = '<option value="">Select Pending PDC...</option>';
    document.getElementById('pdcRemainingInfo').style.display = 'none';
    pendingPdcs = [];
    if (!customerId) return;

    fetch(`../../../../server/api/vouchers/receive_voucher/get-pending-pdcs.php?customer_id=${customerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                pendingPdcs = data.data;
                data.data.forEach(pdc => {
                    const opt = document.createElement('option');
                    opt.value = pdc.id;
                    const date = new Date(pdc.cheque_date).toLocaleDateString();
                    const paid = parseFloat(pdc.paid_amount) > 0
                        ? ` | Paid: ${parseFloat(pdc.paid_amount).toFixed(2)}`
                        : '';
                    opt.textContent = `${pdc.cheque_no} — ${pdc.bank_name || 'N/A'} — ${date} — Remaining: ${parseFloat(pdc.remaining).toFixed(2)}${paid}`;
                    opt.dataset.remaining = pdc.remaining;
                    opt.dataset.paid = pdc.paid_amount;
                    select.appendChild(opt);
                });
            }
        })
        .catch(err => console.error('Error fetching pending PDCs:', err));
}

// PDC select handler
document.getElementById('againstPdc').addEventListener('change', function() {
    const infoEl = document.getElementById('pdcRemainingInfo');
    const selected = this.options[this.selectedIndex];
    if (!this.value) { infoEl.style.display = 'none'; return; }
    const remaining = parseFloat(selected.dataset.remaining) || 0;
    const paid = parseFloat(selected.dataset.paid) || 0;
    infoEl.style.display = 'block';
    if (paid > 0) {
        infoEl.innerHTML = `<i class="fas fa-info-circle"></i> Already paid: ${paid.toFixed(2)} | Remaining: ${remaining.toFixed(2)}`;
    } else {
        infoEl.innerHTML = `<i class="fas fa-info-circle"></i> Full cheque amount remaining: ${remaining.toFixed(2)}`;
    }
});

// Fetch bills for selected customer
function fetchBillsForCustomer(customerCode) {
    fetch(`../../../../server/api/vouchers/receive_voucher/get-bills.php?customer_code=${customerCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bills = data.data.map(bill => ({
                    number: bill.bill_no,
                    amount: bill.net_amount,
                    date: bill.sale_date,
                    currency: bill.currency_symbol || '$'
                }));
                renderBillDropdown('');
            }
        })
        .catch(error => console.error('Error fetching bills:', error));
}

function renderBillDropdown(searchTerm) {
    const billInput = document.getElementById('billNo');
    const dropdown = document.getElementById('billDropdown');
    dropdown.innerHTML = '';
    const filtered = bills.filter(b =>
        b.number.toLowerCase().includes(searchTerm) ||
        (b.date && b.date.toLowerCase().includes(searchTerm)) ||
        b.amount.toString().includes(searchTerm)
    );
    if (filtered.length === 0) {
        hideFloatingDropdown(dropdown);
        return;
    }
    filtered.slice(0, 10).forEach(bill => {
        const option = document.createElement('div');
        option.className = 'dropdown-item';
        const date = new Date(bill.date).toLocaleDateString();
        option.innerHTML = `<strong>${bill.number}</strong> - ${date} - ${bill.currency}${parseFloat(bill.amount).toFixed(2)}`;
        option.addEventListener('click', function () {
            billInput.value = `${bill.number} - ${date} - ${bill.currency}${parseFloat(bill.amount).toFixed(2)}`;
            billInput.dataset.selectedBillNo = bill.number;
            hideFloatingDropdown(dropdown);
            // previousBalance is NOT changed — only remainingBalance recalculates
            updateRemainingBalance();
        });
        dropdown.appendChild(option);
    });
    showFloatingDropdown(billInput, dropdown);
}

// Fetch sub-accounts for selected customer
function fetchSubAccountsForCustomer(customerId) {
    const subAccountSelect = document.getElementById('subAccount');
    subAccountSelect.innerHTML = '<option value="">Select Sub Account</option>';
    
    if (!customerId) return;
    
    fetch(`../../../../server/api/vouchers/receive_voucher/get-sub-accounts.php?customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                data.data.forEach(subAccount => {
                    const option = document.createElement('option');
                    option.value = subAccount.id;
                    option.textContent = subAccount.sub_account_name;
                    subAccountSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error fetching sub-accounts:', error));
}

// Fetch employees from API
fetch('../../../../server/api/vouchers/receive_voucher/get-employees.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            employees = data.data.map(emp => ({
                id: emp.employee_id,
                numericId: emp.id || emp.employee_id,
                name: emp.full_name,
                display: `${emp.employee_id} - ${emp.full_name}`
            }));
            setupSearchableDropdown('recoveryOfficer', 'recoveryOfficerDropdown', employees, 'display', 'id');
            setupSearchableDropdown('bulkRecoveryOfficer', 'bulkRecoveryOfficerDropdown', employees, 'display', 'id');
        }
    })
    .catch(error => console.error('Error fetching employees:', error));



// Fetch bank accounts from API
fetch('../../../../server/api/vouchers/receive_voucher/get-bank-accounts.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const bankAccountSelect = document.getElementById('bankAccount');
            data.data.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = `${account.bank_name} - ${account.account_number}`;
                bankAccountSelect.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching bank accounts:', error));

// Fetch currencies from API
fetch('../../../../server/api/vouchers/receive_voucher/get-currencies.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const currencySelect = document.getElementById('currency');
            data.data.forEach((currency, index) => {
                const option = document.createElement('option');
                option.value = currency.currency_id;
                option.textContent = `${currency.name} (${currency.symbol}) - ${currency.code}`;
                option.setAttribute('data-symbol', currency.symbol);
                option.setAttribute('data-code', currency.code);
                currencySelect.appendChild(option);
                
                // Auto-select first option
                if (index === 0) {
                    option.selected = true;
                    document.getElementById('currencySymbol').textContent = currency.symbol;
                }
            });
        }
    })
    .catch(error => console.error('Error fetching currencies:', error));

// Currency change handler to update amount field symbol
document.getElementById('currency').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const symbol = selectedOption.getAttribute('data-symbol') || '$';
    document.getElementById('currencySymbol').textContent = symbol;
});

// Fetch payment methods from API
fetch('../../../../server/api/vouchers/receive_voucher/get-payment-methods.php')
    .then(response => response.json())
    .then(data => {
        console.log('Payment methods data:', data);
        if (data.success) {
            const paymentMethodSelect = document.getElementById('paymentMethod');
            data.data.forEach(method => {
                const option = document.createElement('option');
                option.value = method.id; // Using actual database ID
                option.textContent = method.name;
                console.log(`Method: ${method.name}, ID: ${method.id}`);
                paymentMethodSelect.appendChild(option);
            });

            // Default to Cash
            const cashMethod = data.data.find(m => (m.name || '').toLowerCase() === 'cash');
            if (cashMethod) {
                paymentMethodSelect.value = cashMethod.id;
                paymentMethodSelect.dispatchEvent(new Event('change'));
            }
        }
    })
    .catch(error => console.error('Error fetching payment methods:', error));

// Payment method change handler
document.getElementById('paymentMethod').addEventListener('change', function() {
    const selectedId = parseInt(this.value);
    const bankAccountGroup = document.getElementById('bankAccountGroup');
    const chequeDateGroup = document.getElementById('chequeDateGroup');
    const chequeNoGroup = document.getElementById('chequeNoGroup');
    const bankAccountField = document.getElementById('bankAccount');
    const chequeDateField = document.getElementById('chequeDate');
    const chequeNoField = document.getElementById('chequeNo');
    
    console.log('Selected payment method ID:', selectedId);
    
    // Hide all fields by default
    bankAccountGroup.classList.add('hidden');
    chequeDateGroup.classList.add('hidden');
    chequeNoGroup.classList.add('hidden');
    bankAccountField.removeAttribute('required');
    chequeDateField.removeAttribute('required');
    chequeNoField.removeAttribute('required');
    
    // Clear values when hiding fields
    if (selectedId === 7) {
        bankAccountField.value = '';
        chequeDateField.value = '';
        chequeNoField.value = '';
    }
    
    // Show bank account for all methods except ID 7 (and when something is selected)
    if (selectedId && selectedId !== 7) {
        console.log('Showing bank account for method ID:', selectedId);
        bankAccountGroup.classList.remove('hidden');
        bankAccountField.setAttribute('required', 'required');
    }
    
    // Show cheque date and cheque no for method ID 6
    if (selectedId === 6) {
        console.log('Showing cheque date and cheque no for method ID 6');
        chequeDateGroup.classList.remove('hidden');
        chequeNoGroup.classList.remove('hidden');
        chequeDateField.setAttribute('required', 'required');
        chequeNoField.setAttribute('required', 'required');
    }
});

// DSR# validation
const dsrInput = document.getElementById('dsrNo');
if (dsrInput) {
    dsrInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\s+/g, '').toUpperCase();
    });
    
    dsrInput.addEventListener('blur', function() {
        const pattern = /^DSR-[A-Z]-\d{2}-\d{2}-\d{8}-\d{8}$/;
        if (this.value && !pattern.test(this.value)) {
            this.setCustomValidity('Invalid DSR format. Expected: DSR-B-24-26-20251231-20260112');
            this.reportValidity();
        } else {
            this.setCustomValidity('');
        }
    });
}

// Searchable dropdown functionality
function setupSearchableDropdown(inputId, dropdownId, data, displayKey, valueKey) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    let highlightedIndex = -1;

    input.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        dropdown.innerHTML = '';
        highlightedIndex = -1;

        if ((inputId === 'recoveryOfficer' || inputId === 'bulkRecoveryOfficer') && searchTerm === '' && this.dataset.selectedId) {
            // Officer search box was cleared out — reset the linked customer list back to unfiltered
            delete this.dataset.selectedId;
            onRecoveryOfficerChanged(inputId === 'bulkRecoveryOfficer' ? 'bulk' : 'single');
        }

        const filteredData = data.filter(item => {
            const displayValue = item[displayKey] || '';
            const valueValue = item[valueKey] || '';
            return displayValue.toString().toLowerCase().includes(searchTerm) ||
                   valueValue.toString().toLowerCase().includes(searchTerm) ||
                   (item.date && item.date.toLowerCase().includes(searchTerm)) ||
                   (item.amount && item.amount.toString().includes(searchTerm));
        });

        if (filteredData.length > 0) {
            filteredData.slice(0, 10).forEach(item => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                
                if (item.date && item.amount) {
                    // For bills: show bill_no - date - amount
                    const date = new Date(item.date).toLocaleDateString();
                    const currency = item.currency || '$';
                    option.innerHTML = `<strong>${item[valueKey]}</strong> - ${date} - ${currency}${parseFloat(item.amount).toFixed(2)}`;
                    option.dataset.display = `${date} - ${currency}${parseFloat(item.amount).toFixed(2)}`;
                    option.dataset.billno = item[valueKey];
                } else {
                    // For suppliers: show code - name
                    option.innerHTML = `<strong>${item[valueKey]}</strong> - ${item[displayKey]}`;
                    option.dataset.display = item[displayKey];
                }
                
                option.dataset.value = item[valueKey];
                if (item.id) {
                    option.dataset.id = item.id;
                }
                if (item.display) {
                    option.dataset.displaytext = item.display;
                }

                option.addEventListener('click', function () {
                    input.value = this.dataset.displaytext || `${this.dataset.value} - ${this.dataset.display}`;
                    input.dataset.selectedId = this.dataset.value;
                    hideFloatingDropdown(dropdown);

                    // Fetch bills and sub-accounts if this is customer dropdown
                    if (inputId === 'customerCode') {
                        currentCustomerId = this.dataset.id;
                        fetchBillsForCustomer(this.dataset.value);
                        fetchSubAccountsForCustomer(this.dataset.id);
                        fetchPendingPdcsForCustomer(this.dataset.id);

                        // Fetch balance from API
                        fetch(`../../../../server/api/vouchers/receive_voucher/get-customer-balance.php?customer_code=${this.dataset.value}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('previousBalance').value = parseFloat(data.balance).toFixed(2);
                                    updateRemainingBalance();
                                }
                            });
                    } else if (inputId === 'recoveryOfficer' || inputId === 'bulkRecoveryOfficer') {
                        onRecoveryOfficerChanged(inputId === 'bulkRecoveryOfficer' ? 'bulk' : 'single');
                    }
                });

                dropdown.appendChild(option);
            });

            showFloatingDropdown(input, dropdown);
        } else if (searchTerm.length > 0) {
            const noResult = document.createElement('div');
            noResult.className = 'dropdown-item';
            noResult.textContent = 'No suppliers found';
            noResult.style.color = 'var(--subtext)';
            noResult.style.cursor = 'default';
            dropdown.appendChild(noResult);
            showFloatingDropdown(input, dropdown);
        } else {
            hideFloatingDropdown(dropdown);
        }
    });

    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.dropdown-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            updateHighlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            updateHighlight(items);
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            const selectedItem = items[highlightedIndex];
            if (selectedItem.dataset.value) {
                input.value = selectedItem.dataset.displaytext || `${selectedItem.dataset.value} - ${selectedItem.dataset.display}`;
                input.dataset.selectedId = selectedItem.dataset.value;
                hideFloatingDropdown(dropdown);

                // Fetch bills and sub-accounts if this is customer dropdown
                if (inputId === 'customerCode') {
                    currentCustomerId = selectedItem.dataset.id;
                    fetchBillsForCustomer(selectedItem.dataset.value);
                    fetchSubAccountsForCustomer(selectedItem.dataset.id);
                    fetchPendingPdcsForCustomer(selectedItem.dataset.id);

                    // Fetch balance from API
                    fetch(`../../../../server/api/vouchers/receive_voucher/get-customer-balance.php?customer_code=${selectedItem.dataset.value}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('previousBalance').value = parseFloat(data.balance).toFixed(2);
                                updateRemainingBalance();
                            }
                        });
                } else if (inputId === 'recoveryOfficer' || inputId === 'bulkRecoveryOfficer') {
                    onRecoveryOfficerChanged(inputId === 'bulkRecoveryOfficer' ? 'bulk' : 'single');
                }
            }
        } else if (e.key === 'Escape') {
            hideFloatingDropdown(dropdown);
        }
    });

    function updateHighlight(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === highlightedIndex);
        });
    }

    input.addEventListener('focus', function() {
        if (data.length > 0) {
            this.dispatchEvent(new Event('input'));
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            hideFloatingDropdown(dropdown);
        }
    });
}

// Initialize bill dropdown input listener (once)
(function setupBillInput() {
    const billInput = document.getElementById('billNo');
    const billDropdown = document.getElementById('billDropdown');

    billInput.addEventListener('input', function () {
        renderBillDropdown(this.value.toLowerCase());
    });

    billInput.addEventListener('focus', function () {
        if (bills.length > 0) renderBillDropdown(this.value.toLowerCase());
    });

    document.addEventListener('click', function (e) {
        if (!billInput.contains(e.target) && !billDropdown.contains(e.target)) {
            hideFloatingDropdown(billDropdown);
        }
    });
})();

// Update remaining balance when amount changes
document.getElementById('amount').addEventListener('input', updateRemainingBalance);

function updateRemainingBalance() {
    const previousBalance = parseFloat(document.getElementById('previousBalance').value) || 0;
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const remainingBalance = previousBalance - amount;
    document.getElementById('remainingBalance').value = remainingBalance.toFixed(2);
}

// Attachment preview functionality
const attachmentInput = document.getElementById('attachment');
const attachmentPreview = document.getElementById('attachmentPreview');
const attachmentThumbnail = document.getElementById('attachmentThumbnail');
const attachmentName = document.getElementById('attachmentName');
const attachmentSize = document.getElementById('attachmentSize');
const removeAttachment = document.getElementById('removeAttachment');
const modal = document.getElementById('attachmentModal');
const modalTitle = document.getElementById('modalTitle');
const modalBody = document.getElementById('modalBody');
const modalClose = document.getElementById('modalClose');
let currentFile = null;

attachmentInput.addEventListener('change', function () {
    if (this.files && this.files[0]) {
        const file = this.files[0];
        currentFile = file;
        attachmentName.textContent = file.name;

        // Format file size
        const fileSize = file.size;
        let sizeText = '';
        if (fileSize < 1024) {
            sizeText = fileSize + ' bytes';
        } else if (fileSize < 1048576) {
            sizeText = (fileSize / 1024).toFixed(2) + ' KB';
        } else {
            sizeText = (fileSize / 1048576).toFixed(2) + ' MB';
        }
        attachmentSize.textContent = sizeText;

        // Generate thumbnail
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                attachmentThumbnail.innerHTML = `<img src="${e.target.result}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius);">`;
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            attachmentThumbnail.innerHTML = '<i class="fas fa-file-pdf" style="color: #e34f4f;"></i>';
        } else if (file.type.includes('word')) {
            attachmentThumbnail.innerHTML = '<i class="fas fa-file-word" style="color: #1f7bff;"></i>';
        } else {
            attachmentThumbnail.innerHTML = '<i class="fas fa-file"></i>';
        }

        attachmentPreview.classList.add('show');
    }
});

// Thumbnail click handler
attachmentThumbnail.addEventListener('click', function() {
    if (currentFile) {
        modalTitle.textContent = currentFile.name;
        
        if (currentFile.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                modalBody.innerHTML = `<img src="${e.target.result}" alt="${currentFile.name}">`;
            };
            reader.readAsDataURL(currentFile);
        } else if (currentFile.type === 'application/pdf') {
            const fileURL = URL.createObjectURL(currentFile);
            modalBody.innerHTML = `<iframe src="${fileURL}" type="application/pdf"></iframe>`;
        } else {
            modalBody.innerHTML = `<p>Preview not available for this file type.</p><p>File: ${currentFile.name}</p>`;
        }
        
        modal.classList.add('show');
    }
});

// Modal close handlers
modalClose.addEventListener('click', function() {
    modal.classList.remove('show');
});

modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        modal.classList.remove('show');
    }
});

removeAttachment.addEventListener('click', function () {
    attachmentInput.value = '';
    currentFile = null;
    attachmentThumbnail.innerHTML = '<i class="fas fa-file"></i>';
    attachmentPreview.classList.remove('show');
});

// Mode Toggle
const bulkModeToggle = document.getElementById('bulkModeToggle');
const singleModeForm = document.getElementById('singleModeForm');
const bulkModeForm = document.getElementById('bulkModeForm');

bulkModeToggle.addEventListener('change', function() {
    if (this.checked) {
        singleModeForm.style.display = 'none';
        bulkModeForm.style.display = 'block';
        addBulkRow();
    } else {
        singleModeForm.style.display = 'block';
        bulkModeForm.style.display = 'none';
    }
});

// Bulk Mode Functions
let bulkRowCounter = 0;
let currenciesData = [];
let paymentMethodsData = [];
let bankAccountsData = [];

function addBulkRow() {
    const tbody = document.getElementById('bulkTableBody');
    const row = document.createElement('tr');
    const rowId = ++bulkRowCounter;
    const bulkJournalDate = document.getElementById('bulkJournalDate').value;
    const rowDate = bulkJournalDate || new Date().toISOString().split('T')[0];

    row.innerHTML = `
        <td><input type="date" class="bulk-date" value="${rowDate}" required></td>
        <td><input type="text" class="bulk-customer" placeholder="Customer code" required autocomplete="off"></td>
        <td><input type="text" class="bulk-prev-balance" readonly></td>
        <td><select class="bulk-sub-account"><option value="">Select</option></select></td>
        <td><input type="text" class="bulk-bill" placeholder="Bill no" autocomplete="off"></td>
        <td><input type="text" class="bulk-dsr" placeholder="DSR-B-24-26-20251231-20260112"></td>
        <td><select class="bulk-project"><option value="">Select</option></select></td>
        <td><select class="bulk-container"><option value="">Select</option></select></td>
        <td><select class="bulk-currency" required></select></td>
        <td><input type="number" class="bulk-amount" step="0.01" min="0" placeholder="0.00" required></td>
        <td><input type="text" class="bulk-rem-balance" readonly></td>
        <td><select class="bulk-payment" required></select></td>
        <td><select class="bulk-against-pdc"><option value="">— No PDC —</option></select></td>
        <td><select class="bulk-bank"></select></td>
        <td><input type="date" class="bulk-cheque-date"></td>
        <td><input type="text" class="bulk-cheque-no"></td>
        <td><textarea class="bulk-description" placeholder="Description"></textarea></td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
    `;
    
    tbody.appendChild(row);
    populateRowSelects(row);
    setupBulkCustomerDropdown(row);
    applyBulkColumnVisibilityToRow(row);
    
    // Add tab key handler for description field
    row.querySelector('.bulk-description').addEventListener('keydown', function(e) {
        if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            addBulkRow();
            // Focus on first input of new row
            setTimeout(() => {
                const newRow = document.querySelector('#bulkTableBody tr:last-child');
                if (newRow) {
                    newRow.querySelector('.bulk-date').focus();
                }
            }, 0);
        }
    });
    
    row.querySelector('.bulk-amount').addEventListener('input', function() {
        updateBulkTotal();
        updateBulkRowRemainingBalance(row);
    });
    
    row.querySelector('.bulk-payment').addEventListener('change', function() {
        const bankSelect = row.querySelector('.bulk-bank');
        const chequeDateInput = row.querySelector('.bulk-cheque-date');
        const chequeNoInput = row.querySelector('.bulk-cheque-no');
        const selectedId = parseInt(this.value);
        
        if (selectedId === 7) {
            bankSelect.disabled = true;
            bankSelect.value = '';
            chequeDateInput.disabled = true;
            chequeDateInput.value = '';
            chequeNoInput.disabled = true;
            chequeNoInput.value = '';
        } else {
            bankSelect.disabled = false;
            chequeDateInput.disabled = selectedId !== 6;
            chequeNoInput.disabled = selectedId !== 6;
        }
    });
    row.querySelector('.bulk-payment').dispatchEvent(new Event('change'));

    updateBulkTotal();
}

function populateRowSelects(row) {
    const currencySelect = row.querySelector('.bulk-currency');
    const paymentSelect = row.querySelector('.bulk-payment');
    const bankSelect = row.querySelector('.bulk-bank');
    const projectSelect = row.querySelector('.bulk-project');
    const containerSelect = row.querySelector('.bulk-container');
    
    currencySelect.innerHTML = '';
    currenciesData.forEach((c, index) => {
        const option = document.createElement('option');
        option.value = c.currency_id;
        option.textContent = `${c.name} (${c.symbol})`;
        if (index === 0) option.selected = true;
        currencySelect.appendChild(option);
    });
    currencySelect.tabIndex = -1;
    
    paymentSelect.innerHTML = '<option value="">Select</option>';
    paymentMethodsData.forEach(p => {
        paymentSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
    });
    // Default to Cash
    const cashMethod = paymentMethodsData.find(p => (p.name || '').toLowerCase() === 'cash');
    if (cashMethod) {
        paymentSelect.value = cashMethod.id;
    }
    
    bankSelect.innerHTML = '<option value="">Select</option>';
    bankAccountsData.forEach(b => {
        bankSelect.innerHTML += `<option value="${b.id}">${b.bank_name} - ${b.account_number}</option>`;
    });
    
    projectSelect.innerHTML = '<option value="">Select</option>';
    projects.forEach(p => {
        const cityName = p.city_name || 'N/A';
        projectSelect.innerHTML += `<option value="${p.id}">${p.project_code} - ${p.project_name} - ${cityName}</option>`;
    });
    
    containerSelect.innerHTML = '<option value="">Select</option>';
    containers.forEach(c => {
        containerSelect.innerHTML += `<option value="${c.id}">${c.container_code} - ${c.container_name}</option>`;
    });
}

function setupBulkCustomerDropdown(row) {
    const input = row.querySelector('.bulk-customer');

    const dropdown = document.createElement('div');
    dropdown.className = 'dropdown-list';

    let highlightedIndex = -1;

    input.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        dropdown.innerHTML = '';
        highlightedIndex = -1;

        const filtered = bulkCustomers.filter(c =>
            c.code.toLowerCase().includes(searchTerm) ||
            c.name.toLowerCase().includes(searchTerm)
        );

        if (filtered.length > 0) {
            filtered.slice(0, 10).forEach(customer => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                option.innerHTML = `<strong>${customer.code}</strong> - ${customer.name}`;
                option.addEventListener('click', function() {
                    input.value = customer.code;
                    hideFloatingDropdown(dropdown);
                    // Fetch bills for this customer
                    fetchBillsForCustomer(customer.code);

                    // Populate Against PDC dropdown for this row
                    const pdcSelect = row.querySelector('.bulk-against-pdc');
                    pdcSelect.innerHTML = '<option value="">— No PDC —</option>';
                    fetch(`../../../../server/api/vouchers/receive_voucher/get-pending-pdcs.php?customer_id=${customer.id}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.data.length > 0) {
                                data.data.forEach(pdc => {
                                    const opt = document.createElement('option');
                                    opt.value = pdc.id;
                                    const date = new Date(pdc.cheque_date).toLocaleDateString();
                                    opt.textContent = `${pdc.cheque_no} (${date}) Rem:${parseFloat(pdc.remaining).toFixed(0)}`;
                                    pdcSelect.appendChild(opt);
                                });
                            }
                        });

                    // Fetch balance from API
                    fetch(`../../../../server/api/vouchers/receive_voucher/get-customer-balance.php?customer_code=${customer.code}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                row.querySelector('.bulk-prev-balance').value = parseFloat(data.balance).toFixed(2);
                                updateBulkRowRemainingBalance(row);
                            }
                        });
                });
                dropdown.appendChild(option);
            });
            showFloatingDropdown(input, dropdown);
        } else {
            hideFloatingDropdown(dropdown);
        }
    });

    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.dropdown-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            updateHighlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            updateHighlight(items);
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            items[highlightedIndex].click();
        } else if (e.key === 'Escape') {
            hideFloatingDropdown(dropdown);
        }
    });

    function updateHighlight(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === highlightedIndex);
        });
    }

    input.addEventListener('focus', function() {
        if (bulkCustomers.length > 0) {
            this.dispatchEvent(new Event('input'));
        }
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            hideFloatingDropdown(dropdown);
        }
    });

    // Setup bill dropdown
    const billInput = row.querySelector('.bulk-bill');

    const billDropdown = document.createElement('div');
    billDropdown.className = 'dropdown-list';

    billInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        billDropdown.innerHTML = '';

        const filtered = bills.filter(b =>
            b.number.toLowerCase().includes(searchTerm)
        );

        if (filtered.length > 0) {
            filtered.slice(0, 10).forEach(bill => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                const date = new Date(bill.date).toLocaleDateString();
                option.innerHTML = `<strong>${bill.number}</strong> - ${date} - ${bill.currency}${parseFloat(bill.amount).toFixed(2)}`;
                option.addEventListener('click', function() {
                    billInput.value = bill.number;
                    hideFloatingDropdown(billDropdown);
                });
                billDropdown.appendChild(option);
            });
            showFloatingDropdown(billInput, billDropdown);
        } else {
            hideFloatingDropdown(billDropdown);
        }
    });

    billInput.addEventListener('focus', function() {
        if (bills.length > 0) {
            this.dispatchEvent(new Event('input'));
        }
    });

    document.addEventListener('click', function(e) {
        if (!billInput.contains(e.target) && !billDropdown.contains(e.target)) {
            hideFloatingDropdown(billDropdown);
        }
    });
}

function updateBulkRowRemainingBalance(row) {
    const prevBalance = parseFloat(row.querySelector('.bulk-prev-balance').value) || 0;
    const amount = parseFloat(row.querySelector('.bulk-amount').value) || 0;
    const remBalance = prevBalance - amount;
    row.querySelector('.bulk-rem-balance').value = remBalance.toFixed(2);
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateBulkTotal();
}

function updateBulkTotal() {
    const amounts = document.querySelectorAll('.bulk-amount');
    let total = 0;
    amounts.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });
    document.getElementById('bulkTotalAmount').textContent = total.toFixed(2);
}

// Bulk Journal Date — applies the selected date to every row already in the table
document.getElementById('bulkJournalDate').addEventListener('change', function() {
    if (!this.value) return;
    document.querySelectorAll('#bulkTableBody .bulk-date').forEach(input => {
        input.value = this.value;
    });
});

document.getElementById('addRowBtn').addEventListener('click', addBulkRow);

document.getElementById('postBulkBtn').addEventListener('click', async function() {
    const rows = document.querySelectorAll('#bulkTableBody tr');
    if (rows.length === 0) {
        alert('Please add at least one voucher');
        return;
    }
    
    const bulkCompanyId = document.getElementById('bulkCompany').value;
    if (!bulkCompanyId) {
        alert('Please select a company');
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<div class="spinner"></div> Processing...';
    
    let successCount = 0;
    let failCount = 0;
    
    for (const row of rows) {
        const formData = new FormData();
        formData.append('voucher_date', row.querySelector('.bulk-date').value);
        formData.append('customer_code', row.querySelector('.bulk-customer').value);
        formData.append('company_id', bulkCompanyId);
        formData.append('previous_balance', row.querySelector('.bulk-prev-balance').value);
        formData.append('sub_account_id', row.querySelector('.bulk-sub-account').value || null);
        formData.append('bill_no', row.querySelector('.bulk-bill').value || null);
        formData.append('dsr_no', row.querySelector('.bulk-dsr').value || null);
        formData.append('project_id', row.querySelector('.bulk-project').value || null);
        formData.append('container_id', row.querySelector('.bulk-container').value || null);
        formData.append('amount', row.querySelector('.bulk-amount').value);
        formData.append('currency_id', row.querySelector('.bulk-currency').value);
        formData.append('payment_method_id', row.querySelector('.bulk-payment').value);
        formData.append('against_pdc_id', row.querySelector('.bulk-against-pdc').value || null);
        formData.append('bank_account', row.querySelector('.bulk-bank').value || null);
        formData.append('cheque_date', row.querySelector('.bulk-cheque-date').value || null);
        formData.append('cheque_no', row.querySelector('.bulk-cheque-no').value || null);
        formData.append('description', row.querySelector('.bulk-description').value || null);
        
        const recoveryOfficerValue = document.getElementById('bulkRecoveryOfficer').dataset.selectedId;
        if (recoveryOfficerValue) {
            const selectedEmployee = employees.find(emp => emp.id === recoveryOfficerValue);
            if (selectedEmployee && selectedEmployee.numericId) {
                formData.append('recovery_officer', selectedEmployee.numericId);
            }
        }
        
        try {
            const response = await fetch('../../../../server/api/vouchers/receive_voucher/receive-add.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                successCount++;
                row.style.backgroundColor = 'rgba(47, 191, 113, 0.1)';
            } else {
                failCount++;
                row.style.backgroundColor = 'rgba(227, 79, 79, 0.1)';
            }
        } catch (error) {
            failCount++;
            row.style.backgroundColor = 'rgba(227, 79, 79, 0.1)';
        }
    }
    
    alert(`Completed: ${successCount} successful, ${failCount} failed`);
    this.disabled = false;
    this.innerHTML = '<i class="fas fa-paper-plane"></i> Post All Vouchers';
    
    if (successCount > 0) {
        setTimeout(() => location.reload(), 2000);
    }
});

// Load data for bulk mode
Promise.all([
    fetch('../../../../server/api/vouchers/receive_voucher/get-currencies.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/receive_voucher/get-payment-methods.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/receive_voucher/get-bank-accounts.php').then(r => r.json())
]).then(([currData, payData, bankData]) => {
    if (currData.success) currenciesData = currData.data;
    if (payData.success) paymentMethodsData = payData.data;
    if (bankData.success) bankAccountsData = bankData.data;
});

// Form submission with lock state
const form = document.getElementById('paymentVoucherForm');
const postBtn = document.getElementById('postBtn');

form.addEventListener('submit', function (e) {
    e.preventDefault();
    e.stopPropagation();

    // Validate required fields
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = 'var(--error)';
        } else {
            field.style.borderColor = '';
        }
    });

    if (!isValid) {
        alert('Please fill in all required fields.');
        return;
    }

    // Lock the button and show spinner
    postBtn.disabled = true;
    postBtn.innerHTML = '<div class="spinner"></div> Processing...';

    // Prepare form data
    const formData = new FormData();
    formData.append('voucher_date', document.getElementById('voucherDate').value);
    formData.append('customer_code', document.getElementById('customerCode').value.split(' - ')[0]);
    formData.append('company_id', document.getElementById('company').value);
    formData.append('previous_balance', document.getElementById('previousBalance').value);
    formData.append('sub_account_id', document.getElementById('subAccount').value || null);
    formData.append('bill_no', document.getElementById('billNo').dataset.selectedBillNo || document.getElementById('billNo').value.split(' - ')[0] || null);
    formData.append('dsr_no', document.getElementById('dsrNo').value || null);
    formData.append('project_id', document.getElementById('project').value || null);
    formData.append('container_id', document.getElementById('container').value || null);
    formData.append('amount', document.getElementById('amount').value);
    formData.append('currency_id', document.getElementById('currency').value);
    formData.append('payment_method_id', document.getElementById('paymentMethod').value);
    formData.append('bank_account', document.getElementById('bankAccount').value || null);
    formData.append('cheque_date', document.getElementById('chequeDate').value || '');
    formData.append('cheque_no', document.getElementById('chequeNo').value || '');
    formData.append('description', document.getElementById('description').value || null);
    formData.append('against_pdc_id', document.getElementById('againstPdc').value || null);
    
    const recoveryOfficerInput = document.getElementById('recoveryOfficer');
    const recoveryOfficerId = recoveryOfficerInput.dataset.selectedId;
    if (recoveryOfficerId) {
        const selectedEmployee = employees.find(emp => emp.id === recoveryOfficerId);
        if (selectedEmployee && selectedEmployee.numericId) {
            formData.append('recovery_officer', selectedEmployee.numericId);
        }
    }
    
    // Add attachment if selected
    if (currentFile) {
        formData.append('attachment', currentFile);
    }

    // Submit to API
    console.log('Submitting form data to API...');
    fetch('../../../../server/api/vouchers/receive_voucher/receive-add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('API Response:', data);
        if (data.success) {
            alert('Voucher posted successfully!');
            resetForm();
        } else {
            console.error('API Error:', data);
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    })
    .finally(() => {
        // Unlock the button
        postBtn.disabled = false;
        postBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post Voucher';
    });
});

// Reset form functionality
const resetBtn = document.getElementById('resetBtn');

resetBtn.addEventListener('click', resetForm);

function resetForm() {
    form.reset();
    document.getElementById('voucherDate').valueAsDate = new Date();
    document.getElementById('voucherNumber').value = 'Auto-generated';
    currentFile = null;
    attachmentThumbnail.innerHTML = '<i class="fas fa-file"></i>';
    attachmentPreview.classList.remove('show');
    document.getElementById('againstPdc').innerHTML = '<option value="">Select Pending PDC...</option>';
    document.getElementById('pdcRemainingInfo').style.display = 'none';
    pendingPdcs = [];

    // Reset border colors
    const fields = form.querySelectorAll('input, select, textarea');
    fields.forEach(field => {
        field.style.borderColor = '';
    });
}