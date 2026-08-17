// Global variables for products and UOM data
let productsData = [];
let uomData = [];
let customersData = [];
let branchesData = [];
let employeesData = [];
let currenciesData = [];
let bankAccountsData = [];

// Cache for quick lookups
const dataCache = {
    products: new Map(),
    customers: new Map(),
    branches: new Map()
};

// Default shortcuts
const defaultShortcuts = {
    addRow: 'F2',
    save: 'F9',
    draft: 'F8',
    reset: 'F5',
    customer: 'F3',
    product: 'F4',
    viewDrafts: 'F6',
    closeModal: 'Escape',
    deleteRow: 'Delete',
    moveForward: 'ArrowRight',
    moveBackward: 'ArrowLeft',
    addCustomer: 'Ctrl+N',
    salesReturn: 'F7',
    nextField: 'Tab',
    prevField: 'Shift+Tab'
};

let shortcuts = { ...defaultShortcuts };

document.addEventListener('DOMContentLoaded', function () {
    // Check permissions
    fetch('../../../../server/api/auth/check-permission.php?category=Sale&form_name=Sale Order')
        .then(response => {
            if (response.status === 403) {
                window.location.href = '../../../../../errors/403.php';
                return;
            }
            return response.json();
        })
        .then(data => {
            if (!data) return;
            if (data.success) {
                // Check if user has at least Add or Edit permission
                if (!data.permissions.includes('Add') && !data.permissions.includes('Edit')) {
                    window.location.href = '../../../../../errors/403.php';
                    return;
                }
                initializePage(data.permissions);
            }
        });
});

function initializePage(permissions) {
    // Load shortcuts
    loadShortcuts();
    
    // Load invoice settings on page load
    loadInvoiceSettings();
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('saleDate').value = today;
    
    // Check if in edit mode
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const isEditMode = editId !== null;

    // Items table functionality
    const itemsTable = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const addRowBtn = document.getElementById('addRowBtn');
    const saveBtn = document.getElementById('saveBtn');
    const resetBtn = document.getElementById('resetBtn');
    const form = document.getElementById('invoiceForm');

    // Load all data, then initialize dropdowns and add first row
    Promise.all([loadCustomers(), loadBranches(), loadProducts(), loadUOM(), loadCurrencies(), loadBankAccounts(), loadEmployees(), loadCompanies()]).then(() => {
        initSearchableDropdown('customerCodeSearch', 'customerCodeOptions', 'customerCode');
        initSearchableDropdown('branchSearch', 'branchOptions', 'branch');
        
        // Apply invoice settings
        applyInvoiceSettings();
        
        if (isEditMode) {
            loadInvoiceData(editId);
        } else {
            // Add first row after data is loaded
            addRow();
        }
    });

    // Add row button event
    addRowBtn.addEventListener('click', addRow);

    // Reset button event
    resetBtn.addEventListener('click', resetForm);

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!permissions.includes('Add') && !isEditMode) {
            alert('You do not have permission to add orders.');
            return;
        }
        if (!permissions.includes('Edit') && isEditMode) {
            alert('You do not have permission to edit orders.');
            return;
        }
        if (validateForm()) {
            saveInvoice('Posted');
        }
    });
    
    // Save as Draft button event
    document.getElementById('saveDraftBtn').addEventListener('click', function() {
        if (!permissions.includes('Add') && !isEditMode) {
            alert('You do not have permission to add orders.');
            return;
        }
        if (!permissions.includes('Edit') && isEditMode) {
            alert('You do not have permission to edit orders.');
            return;
        }
        saveInvoice('Draft');
    });
    
    // Sales Return button event
    document.getElementById('salesReturnBtn').addEventListener('click', function() {
        openOverlay('../sale_return/return-add.php');
    });
    
    // Tab navigation from Save Invoice button to Customer Code
    saveBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('customerCodeSearch').focus();
        }
    });
    
    // Shortcut Settings Modal
    const shortcutSettingsBtn = document.getElementById('shortcutSettingsBtn');
    if (shortcutSettingsBtn) {
        shortcutSettingsBtn.addEventListener('click', function() {
            loadShortcutInputs();
            document.getElementById('shortcutSettingsModal').style.display = 'flex';
        });
    }
    
    const closeShortcutSettingsBtn = document.getElementById('closeShortcutSettingsBtn');
    if (closeShortcutSettingsBtn) {
        closeShortcutSettingsBtn.addEventListener('click', function() {
            document.getElementById('shortcutSettingsModal').style.display = 'none';
        });
    }
    
    const saveShortcutSettingsBtn = document.getElementById('saveShortcutSettingsBtn');
    if (saveShortcutSettingsBtn) {
        saveShortcutSettingsBtn.addEventListener('click', saveShortcutSettings);
    }
    
    const resetShortcutsBtn = document.getElementById('resetShortcutsBtn');
    if (resetShortcutsBtn) {
        resetShortcutsBtn.addEventListener('click', resetShortcuts);
    }
    
    // Shortcut input listeners
    const shortcutIds = ['shortcutAddRow', 'shortcutSave', 'shortcutDraft', 'shortcutReset', 'shortcutCustomer', 'shortcutProduct', 'shortcutViewDrafts', 'shortcutCloseModal', 'shortcutDeleteRow', 'shortcutMoveForward', 'shortcutMoveBackward', 'shortcutAddCustomer', 'shortcutSalesReturn', 'shortcutNextField', 'shortcutPrevField'];
    shortcutIds.forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('keydown', function(e) {
            e.preventDefault();
            let key = e.key;
            
            // Handle special keys - preserve original case for special characters
            if (key === ' ') key = 'Space';
            else if (key.length === 1 && !e.ctrlKey && !e.altKey) {
                // Keep special characters as-is, uppercase letters only
                if (key.match(/[a-z]/i)) {
                    key = key.toUpperCase();
                }
            } else if (key.length > 1) {
                // Keep special keys as-is (ArrowRight, Delete, etc.)
            }
            
            // Build combo
            const parts = [];
            if (e.ctrlKey) parts.push('Ctrl');
            if (e.altKey) parts.push('Alt');
            if (e.shiftKey && key.length > 1) parts.push('Shift');
            
            // Skip if only modifier key pressed
            if (key !== 'Control' && key !== 'Alt' && key !== 'Shift' && key !== 'Meta') {
                parts.push(key);
            }
            
            if (parts.length > 0) {
                this.value = parts.join('+');
            }
        });
    });
    
    // Global shortcut listener
    document.addEventListener('keydown', function(e) {
        // Don't trigger shortcuts if shortcut settings modal is open
        const shortcutModal = document.getElementById('shortcutSettingsModal');
        if (shortcutModal && shortcutModal.style.display === 'flex') {
            return;
        }
        
        let key = e.key;
        // Preserve original case for special characters, uppercase only letters
        if (key.length === 1 && key.match(/[a-z]/i)) {
            key = key.toUpperCase();
        }
        
        let combo = '';
        if (e.ctrlKey) combo += 'Ctrl+';
        if (e.altKey) combo += 'Alt+';
        if (e.shiftKey && key.length > 1) combo += 'Shift+';
        combo += key;
        
        // Check if this combo matches any shortcut
        const isShortcut = Object.values(shortcuts).includes(combo);
        
        // Don't trigger shortcuts if user is typing in an input field (except for configured shortcuts)
        const activeElement = document.activeElement;
        if (!isShortcut && activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'SELECT')) {
            return;
        }
        
        if (combo === shortcuts.addRow) {
            e.preventDefault();
            addRow();
        } else if (combo === shortcuts.save) {
            e.preventDefault();
            if (validateForm()) saveInvoice('Posted');
        } else if (combo === shortcuts.draft) {
            e.preventDefault();
            saveInvoice('Draft');
        } else if (combo === shortcuts.reset) {
            e.preventDefault();
            resetForm();
        } else if (combo === shortcuts.customer) {
            e.preventDefault();
            document.getElementById('customerCodeSearch').focus();
        } else if (combo === shortcuts.product) {
            e.preventDefault();
            const firstRow = itemsTable.rows[0];
            if (firstRow) firstRow.cells[1].querySelector('.search-input').focus();
        } else if (combo === shortcuts.viewDrafts) {
            e.preventDefault();
            document.getElementById('viewDraftsBtn').click();
        } else if (combo === shortcuts.closeModal) {
            e.preventDefault();
            const modals = ['draftsModal', 'overlayModal', 'invoiceSettingsModal', 'printSettingsModal', 'fieldSettingsModal', 'shortcutSettingsModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && modal.style.display === 'flex') {
                    modal.style.display = 'none';
                    if (modalId === 'overlayModal') closeOverlay();
                }
            });
        } else if (combo === shortcuts.deleteRow) {
            e.preventDefault();
            const activeElement = document.activeElement;
            const activeRow = activeElement.closest('tr');
            if (activeRow && activeRow.parentElement.tagName === 'TBODY') {
                const deleteBtn = activeRow.querySelector('.btn-danger');
                if (deleteBtn) deleteBtn.click();
            }
        } else if (combo === shortcuts.moveForward) {
            e.preventDefault();
            const activeElement = document.activeElement;
            if (activeElement && (activeElement.classList.contains('table-input') || activeElement.classList.contains('search-input'))) {
                const row = activeElement.closest('tr');
                if (row && row.nextElementSibling) {
                    const cellIndex = activeElement.closest('td').cellIndex;
                    const nextInput = row.nextElementSibling.cells[cellIndex]?.querySelector('input, select');
                    if (nextInput) nextInput.focus();
                }
            }
        } else if (combo === shortcuts.moveBackward) {
            e.preventDefault();
            const activeElement = document.activeElement;
            if (activeElement && (activeElement.classList.contains('table-input') || activeElement.classList.contains('search-input'))) {
                const row = activeElement.closest('tr');
                if (row && row.previousElementSibling) {
                    const cellIndex = activeElement.closest('td').cellIndex;
                    const prevInput = row.previousElementSibling.cells[cellIndex]?.querySelector('input, select');
                    if (prevInput) {
                        prevInput.focus();
                        if (prevInput.select) prevInput.select();
                    }
                }
            }
        } else if (combo === shortcuts.addCustomer) {
            e.preventDefault();
            openOverlay('../../customer_supplier/customers/customer-add.php');
        } else if (combo === shortcuts.salesReturn) {
            e.preventDefault();
            document.getElementById('salesReturnBtn').click();
        } else if (combo === shortcuts.nextField) {
            e.preventDefault();
            const focusableElements = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'));
            const currentIndex = focusableElements.indexOf(document.activeElement);
            if (currentIndex > -1 && currentIndex < focusableElements.length - 1) {
                focusableElements[currentIndex + 1].focus();
                if (focusableElements[currentIndex + 1].select) focusableElements[currentIndex + 1].select();
            }
        } else if (combo === shortcuts.prevField) {
            e.preventDefault();
            const focusableElements = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'));
            const currentIndex = focusableElements.indexOf(document.activeElement);
            if (currentIndex > 0) {
                focusableElements[currentIndex - 1].focus();
                if (focusableElements[currentIndex - 1].select) focusableElements[currentIndex - 1].select();
            }
        }
    });
    
    function loadShortcuts() {
        const saved = localStorage.getItem('pos_shortcuts');
        if (saved) {
            shortcuts = JSON.parse(saved);
        }
    }
    
    function loadShortcutInputs() {
        const ids = ['shortcutAddRow', 'shortcutSave', 'shortcutDraft', 'shortcutReset', 'shortcutCustomer', 'shortcutProduct', 'shortcutViewDrafts', 'shortcutCloseModal', 'shortcutDeleteRow', 'shortcutMoveForward', 'shortcutMoveBackward', 'shortcutAddCustomer', 'shortcutSalesReturn', 'shortcutNextField', 'shortcutPrevField'];
        const keys = ['addRow', 'save', 'draft', 'reset', 'customer', 'product', 'viewDrafts', 'closeModal', 'deleteRow', 'moveForward', 'moveBackward', 'addCustomer', 'salesReturn', 'nextField', 'prevField'];
        
        ids.forEach((id, index) => {
            const el = document.getElementById(id);
            if (el) el.value = shortcuts[keys[index]];
        });
    }
    
    function saveShortcutSettings() {
        shortcuts = {
            addRow: document.getElementById('shortcutAddRow').value || defaultShortcuts.addRow,
            save: document.getElementById('shortcutSave').value || defaultShortcuts.save,
            draft: document.getElementById('shortcutDraft').value || defaultShortcuts.draft,
            reset: document.getElementById('shortcutReset').value || defaultShortcuts.reset,
            customer: document.getElementById('shortcutCustomer').value || defaultShortcuts.customer,
            product: document.getElementById('shortcutProduct').value || defaultShortcuts.product,
            viewDrafts: document.getElementById('shortcutViewDrafts').value || defaultShortcuts.viewDrafts,
            closeModal: document.getElementById('shortcutCloseModal').value || defaultShortcuts.closeModal,
            deleteRow: document.getElementById('shortcutDeleteRow').value || defaultShortcuts.deleteRow,
            moveForward: document.getElementById('shortcutMoveForward').value || defaultShortcuts.moveForward,
            moveBackward: document.getElementById('shortcutMoveBackward').value || defaultShortcuts.moveBackward,
            addCustomer: document.getElementById('shortcutAddCustomer').value || defaultShortcuts.addCustomer,
            salesReturn: document.getElementById('shortcutSalesReturn').value || defaultShortcuts.salesReturn,
            nextField: document.getElementById('shortcutNextField').value || defaultShortcuts.nextField,
            prevField: document.getElementById('shortcutPrevField').value || defaultShortcuts.prevField
        };
        localStorage.setItem('pos_shortcuts', JSON.stringify(shortcuts));
        document.getElementById('shortcutSettingsModal').style.display = 'none';
        alert('Shortcut settings saved successfully!');
    }
    
    function resetShortcuts() {
        shortcuts = { ...defaultShortcuts };
        loadShortcutInputs();
    }

    // Load customers from API
    async function loadCustomers() {
        try {
            const response = await fetch('../../../../server/api/sale/pos_invoice/get-customers.php');
            const data = await response.json();
            
            if (data.success) {
                customersData = data.customers;
                const codeOptions = document.getElementById('customerCodeOptions');
                
                codeOptions.innerHTML = '';
                
                // Build cache and DOM fragment for performance
                const fragment = document.createDocumentFragment();
                data.customers.forEach(customer => {
                    dataCache.customers.set(customer.id, customer);
                    
                    const codeOption = document.createElement('div');
                    codeOption.className = 'dropdown-option';
                    codeOption.setAttribute('data-value', customer.id);
                    codeOption.setAttribute('data-balance', customer.current_balance);
                    codeOption.setAttribute('data-address', customer.address || '');
                    codeOption.setAttribute('data-discount', customer.default_discount_percentage || 0);
                    codeOption.textContent = `${customer.customer_code} - ${customer.customer_name}`;
                    fragment.appendChild(codeOption);
                });
                codeOptions.appendChild(fragment);
            }
        } catch (error) {
            console.error('Error loading customers:', error);
        }
    }

    // Load branches from API
    async function loadBranches() {
        try {
            const response = await fetch('../../../../server/api/sale/pos_invoice/get-branches.php');
            const data = await response.json();
            
            if (data.success) {
                branchesData = data.branches;
                const branchOptions = document.getElementById('branchOptions');
                branchOptions.innerHTML = '';
                
                const fragment = document.createDocumentFragment();
                data.branches.forEach(branch => {
                    dataCache.branches.set(branch.id, branch);
                    
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', branch.id);
                    const branchText = branch.parent_branch_name 
                        ? `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type}) - Parent: ${branch.parent_branch_name}`
                        : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                    option.textContent = branchText;
                    fragment.appendChild(option);
                });
                branchOptions.appendChild(fragment);
                
                // Set last selected branch
                const lastBranchId = localStorage.getItem('lastSelectedBranch');
                if (lastBranchId) {
                    const lastBranch = dataCache.branches.get(parseInt(lastBranchId));
                    if (lastBranch) {
                        document.getElementById('branchSearch').value = `${lastBranch.branch_code} - ${lastBranch.branch_name} (${lastBranch.branch_type})`;
                        document.getElementById('branch').value = lastBranchId;
                    }
                }
            }
        } catch (error) {
            console.error('Error loading branches:', error);
        }
    }

    // Load products from API
    async function loadProducts() {
        try {
            const priceSource = localStorage.getItem('priceSource') || 'trade_price';
            console.log('Loading products with priceSource:', priceSource);
            const apiUrl = `../../../../server/api/sale/sale_order/get-products.php?priceSource=${priceSource}`;
            console.log('API URL:', apiUrl);
            const response = await fetch(apiUrl);
            const data = await response.json();
            console.log('Products loaded:', data);
            
            if (data.success) {
                productsData = data.products;
                console.log('First product sale_price:', data.products[0]?.sale_price);
                // Build product cache for instant lookup
                data.products.forEach(product => {
                    dataCache.products.set(product.id, product);
                });
            }
        } catch (error) {
            console.error('Error loading products:', error);
        }
    }

    // Load UOM from API
    async function loadUOM() {
        try {
            const response = await fetch('../../../../server/api/sale/pos_invoice/get-uom.php');
            const data = await response.json();
            
            if (data.success) {
                uomData = data.uoms;
            }
        } catch (error) {
            console.error('Error loading UOM:', error);
        }
    }

    // Load bank accounts from API
    async function loadBankAccounts() {
        try {
            const response = await fetch('../../../../server/api/sale/pos_invoice/get-bank-accounts.php');
            const data = await response.json();
            
            if (data.success) {
                const bankAccountSelect = document.getElementById('bankAccount');
                if (bankAccountSelect) {
                    bankAccountSelect.innerHTML = '<option value="">Select Bank Account</option>';
                    
                    data.accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = `${account.account_title} - ${account.account_number}`;
                        bankAccountSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading bank accounts:', error);
        }
    }
    
    // Load employees from API
    async function loadEmployees() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_order/get-employees.php');
            const data = await response.json();
            
            if (data.success) {
                const salesOfficerSelect = document.getElementById('salesOfficer');
                salesOfficerSelect.innerHTML = '<option value="">Select Sales Officer</option>';
                
                const supplierManSelect = document.getElementById('supplierMan');
                supplierManSelect.innerHTML = '<option value="">Select Supplier Man</option>';
                
                data.employees.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = `${employee.employee_id} - ${employee.full_name}`;
                    salesOfficerSelect.appendChild(option);
                    
                    const supplierOption = document.createElement('option');
                    supplierOption.value = employee.id;
                    supplierOption.textContent = `${employee.employee_id} - ${employee.full_name}`;
                    supplierManSelect.appendChild(supplierOption);
                });
                
                // Set last selected sales officer (only if not in edit mode)
                const urlParams = new URLSearchParams(window.location.search);
                const editId = urlParams.get('edit');
                if (!editId) {
                    const lastSalesOfficer = localStorage.getItem('lastSelectedSalesOfficer');
                    if (lastSalesOfficer) {
                        salesOfficerSelect.value = lastSalesOfficer;
                    }
                    
                    const lastSupplierMan = localStorage.getItem('lastSelectedSupplierMan');
                    if (lastSupplierMan) {
                        supplierManSelect.value = lastSupplierMan;
                    }
                }
                
                // Save sales officer selection
                salesOfficerSelect.addEventListener('change', function() {
                    if (this.value) {
                        localStorage.setItem('lastSelectedSalesOfficer', this.value);
                    }
                });
                
                // Save supplier man selection
                supplierManSelect.addEventListener('change', function() {
                    if (this.value) {
                        localStorage.setItem('lastSelectedSupplierMan', this.value);
                    }
                });
            }
        } catch (error) {
            console.error('Error loading employees:', error);
        }
    }
    
    // Load currencies from API
    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/sale/pos_invoice/get-currencies.php');
            const data = await response.json();
            
            if (data.success) {
                const currencySelect = document.getElementById('currency');
                currencySelect.innerHTML = '<option value="">Select Currency</option>';
                
                data.currencies.forEach(currency => {
                    const option = document.createElement('option');
                    option.value = currency.currency_id;
                    option.textContent = `${currency.code} - ${currency.name} (${currency.symbol})`;
                    option.setAttribute('data-symbol', currency.symbol);
                    option.setAttribute('data-code', currency.code);
                    
                    // Select base currency by default
                    if (currency.is_base_currency == 1) {
                        option.selected = true;
                    }
                    
                    currencySelect.appendChild(option);
                });
                
                // Update currency symbols on initial load
                updateCurrencySymbols();
            }
        } catch (error) {
            console.error('Error loading currencies:', error);
        }
    }
    
    // Load companies from API
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_order/get-companies.php');
            const data = await response.json();
            
            if (data.success) {
                const companySelect = document.getElementById('company');
                companySelect.innerHTML = '<option value="">Select Company</option>';
                
                data.companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companySelect.appendChild(option);
                });
                
                // Auto-select if only one company
                if (data.companies.length === 1) {
                    companySelect.value = data.companies[0].id;
                }
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }
    
    // Update currency symbols in labels and values
    function updateCurrencySymbols() {
        const currencySelect = document.getElementById('currency');
        if (!currencySelect) return;
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        const symbol = selectedOption ? selectedOption.getAttribute('data-symbol') : '';
        
        if (symbol) {
            // Update table headers
            const salePriceLabel = document.getElementById('salePriceLabel');
            const grossAmountLabel = document.getElementById('grossAmountLabel');
            const discountAmountLabel = document.getElementById('discountAmountLabel');
            const netAmountLabel = document.getElementById('netAmountLabel');
            
            if (salePriceLabel) salePriceLabel.textContent = `Sale Price (${symbol})`;
            if (grossAmountLabel) grossAmountLabel.textContent = `Gross Amount (${symbol})`;
            if (discountAmountLabel) discountAmountLabel.textContent = `Discount Amount (${symbol})`;
            if (netAmountLabel) netAmountLabel.textContent = `Net Amount (${symbol})`;
            
            // Update summary labels
            const totalBillLabel = document.getElementById('totalBillLabel');
            const totalDiscountAmountLabel = document.getElementById('totalDiscountAmountLabel');
            const netAmountSummaryLabel = document.getElementById('netAmountSummaryLabel');
            
            if (totalBillLabel) totalBillLabel.textContent = `Total Bill (${symbol})`;
            if (totalDiscountAmountLabel) totalDiscountAmountLabel.textContent = `Discount Amount (${symbol})`;
            if (netAmountSummaryLabel) netAmountSummaryLabel.textContent = `Net Amount (${symbol})`;
        }
    }
    
    // Add currency change event listener
    document.getElementById('currency').addEventListener('change', updateCurrencySymbols);
    
    // Load invoice data for editing
    async function loadInvoiceData(invoiceId) {
        try {
            const response = await fetch(`../../../../server/api/sale/sale_order/order-edit.php?id=${invoiceId}`);
            const data = await response.json();
            
            if (data.success) {
                const invoice = data.invoice;
                
                // Populate form fields
                document.getElementById('company').value = invoice.company_id || '';
                document.getElementById('saleDate').value = invoice.sale_date;
                document.getElementById('customerCodeSearch').value = invoice.customer_name;
                document.getElementById('customerCode').value = invoice.customer_id;
                document.getElementById('branchSearch').value = invoice.branch_name;
                document.getElementById('branch').value = invoice.branch_id;
                document.getElementById('currency').value = invoice.currency_id;
                document.getElementById('previousBalance').value = invoice.previous_balance;
                document.getElementById('remarks').value = invoice.remarks || '';
                document.getElementById('salesOfficer').value = invoice.sales_officer_id || '';
                document.getElementById('supplierMan').value = invoice.supplier_man_id || '';
                document.getElementById('biltyNo').value = invoice.bilty_no || '';
                document.getElementById('transportName').value = invoice.transport_name || '';
                
                // Populate customer address
                let addressText = invoice.customer_address || '';
                document.getElementById('customerAddress').textContent = addressText || 'No address available';
                
                // Update currency symbols
                updateCurrencySymbols();
                
                // Load invoice items - separate parents and children
                const parentItems = data.items.filter(item => !item.parent_row_id);
                const childItems = data.items.filter(item => item.parent_row_id);
                const itemIdToRowMap = {};
                
                // Load parent items first
                parentItems.forEach(item => {
                    addRow();
                    const lastRow = itemsTable.rows[itemsTable.rows.length - 1];
                    itemIdToRowMap[item.id] = lastRow;
                    
                    lastRow.cells[1].querySelector('.search-input').value = item.product_name;
                    lastRow.cells[1].querySelector('.item-code').value = item.product_id;
                    lastRow.cells[2].querySelector('select').value = item.uom_id;
                    lastRow.cells[3].querySelector('input').value = item.quantity;
                    lastRow.cells[7].querySelector('input').value = item.sale_price;
                    lastRow.cells[8].querySelector('input').value = item.gross_amount;
                    lastRow.cells[9].querySelector('input').value = item.discount_percent;
                    lastRow.cells[10].querySelector('input').value = item.discount_amount;
                    lastRow.cells[11].querySelector('input').value = item.trade_offer_percent || 0;
                    lastRow.cells[12].querySelector('input').value = item.trade_offer_amount || 0;
                    lastRow.cells[13].querySelector('input').value = item.gst_percent || 0;
                    lastRow.cells[14].querySelector('input').value = item.gst_amount || 0;
                    lastRow.cells[15].querySelector('input').value = item.foc_quantity || 0;
                    lastRow.cells[16].querySelector('input').value = item.net_amount;
                    lastRow.dataset.productId = item.product_id;
                });
                
                // Load child items
                childItems.forEach(child => {
                    const parentRow = itemIdToRowMap[child.parent_row_id];
                    if (!parentRow) return;
                    
                    const parentRowIndex = parentRow.rowIndex - 1;
                    let insertIndex = parentRowIndex + 1;
                    while (insertIndex < itemsTable.rows.length && 
                           itemsTable.rows[insertIndex].classList.contains('child-row') && 
                           itemsTable.rows[insertIndex].dataset.parentRowIndex == parentRowIndex) {
                        insertIndex++;
                    }
                    
                    const row = itemsTable.insertRow(insertIndex);
                    row.className = 'child-row';
                    row.dataset.parentRowIndex = parentRowIndex;
                    row.dataset.productId = child.product_id;
                    
                    
                    const cell0 = row.insertCell(0);
                    cell0.innerHTML = '<span style="margin-left: 20px;">↳</span>';
                    
                    
                    const cell1 = row.insertCell(1);
                    cell1.innerHTML = `<span style="font-size: 13px; color: var(--subtext);">${child.product_name}</span>`;
                    
                    const cell2 = row.insertCell(2);
                    const unitSelect = document.createElement('select');
                    unitSelect.className = 'table-input';
                    unitSelect.innerHTML = uomData.map(uom => `<option value="${uom.id}" ${uom.id == child.uom_id ? 'selected' : ''}>${uom.uom_name}</option>`).join('');
                    unitSelect.tabIndex = -1;
                    cell2.appendChild(unitSelect);
                    
                    const cell3 = row.insertCell(3);
                    const qtyInput = document.createElement('input');
                    qtyInput.type = 'number';
                    qtyInput.className = 'table-input';
                    qtyInput.value = child.quantity;
                    qtyInput.min = '0';
                    qtyInput.step = '0.01';
                    qtyInput.addEventListener('input', () => updateParentQtyFromChildren(parentRow));
                    cell3.appendChild(qtyInput);
                    
                    const enablePcs = localStorage.getItem('enablePcs') === 'true';
                    const enableCtn = localStorage.getItem('enableCtn') === 'true';
                    const enableDz = localStorage.getItem('enableDz') === 'true';
                    const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
                    const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
                    const enableTradeOfferDiscount = localStorage.getItem('enableTradeOfferDiscount') === 'true';
                    const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
                    const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
                    const enableFOC = localStorage.getItem('enableFOC') === 'true';
                    
                    for (let i = 4; i <= 16; i++) {
                        const cell = row.insertCell(i);
                        cell.innerHTML = '<span style="color: var(--subtext);">-</span>';
                        cell.style.textAlign = 'center';
                        
                        if (i === 4 && !enablePcs) cell.style.display = 'none';
                        if (i === 5 && !enableCtn) cell.style.display = 'none';
                        if (i === 6 && !enableDz) cell.style.display = 'none';
                        if (i === 9 && !enableCashDiscountPercent) cell.style.display = 'none';
                        if (i === 10 && !enableCashDiscountAmount) cell.style.display = 'none';
                        if (i === 11 && !enableTradeOfferDiscount) cell.style.display = 'none';
                        if (i === 12 && !enableTradeOfferAmount) cell.style.display = 'none';
                        if (i === 13 && !enableTaxation) cell.style.display = 'none';
                        if (i === 14 && !enableTaxation) cell.style.display = 'none';
                        if (i === 15 && !enableFOC) cell.style.display = 'none';
                    }
                    
                    const cell17 = row.insertCell(17);
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'btn btn-danger btn-sm';
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    deleteBtn.tabIndex = -1;
                    deleteBtn.addEventListener('click', () => {
                        row.remove();
                        updateSerialNumbers();
                        updateParentQtyFromChildren(parentRow);
                    });
                    cell17.appendChild(deleteBtn);
                });
                
                updateSerialNumbers();
                
                // Update summary
                document.getElementById('totalDiscountPercent').value = invoice.total_discount_percent;
                document.getElementById('totalDiscountAmount').value = invoice.total_discount_amount;
                const paymentMethodEl = document.getElementById('paymentMethod');
                if (paymentMethodEl) {
                    paymentMethodEl.value = invoice.payment_method || '';
                    
                    // Show bank account if payment method is bank_transfer
                    if (invoice.payment_method === 'bank_transfer') {
                        const bankAccountContainer = document.getElementById('bankAccountContainer');
                        if (bankAccountContainer) {
                            bankAccountContainer.style.display = 'flex';
                            const bankAccount = document.getElementById('bankAccount');
                            if (bankAccount) bankAccount.value = invoice.bank_account_id || '';
                        }
                    }
                }
                
                const amountPaidEl = document.getElementById('amountPaid');
                if (amountPaidEl) amountPaidEl.value = invoice.amount_paid || 0;
                updateInvoiceSummary();
                
                // Update page title
                document.querySelector('.page-title').textContent = `Edit Sale Order - ${invoice.bill_no}`;
                
            } else {
                alert('Error loading order: ' + data.message);
                window.location.href = 'order-add.php';
            }
        } catch (error) {
            console.error('Error loading order:', error);
            alert('Error loading order data');
            window.location.href = 'order-add.php';
        }
    }

    // Initialize searchable dropdown
    function initSearchableDropdown(searchInputId, optionsContainerId, hiddenInputId) {
        const searchInput = document.getElementById(searchInputId);
        const optionsContainer = document.getElementById(optionsContainerId);
        const hiddenInput = document.getElementById(hiddenInputId);
        let selectedIndex = -1;

        // Show options when clicking on search input
        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
            optionsContainer.style.display = 'block';
            filterOptions();
        });
        
        // Show options when input receives focus (tab navigation)
        searchInput.addEventListener('focus', function (e) {
            optionsContainer.style.display = 'block';
            filterOptions();
        });
        
        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
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
                } else if (visibleOptions.length > 0) {
                    visibleOptions[0].click();
                }
            }
        });
        
        function updateSelection(visibleOptions) {
            visibleOptions.forEach((option, index) => {
                option.style.backgroundColor = index === selectedIndex ? 'var(--surface-1)' : '';
            });
        }

        // Filter options based on search input
        searchInput.addEventListener('input', function() {
            selectedIndex = -1;
            filterOptions();
        });

        // Select option
        optionsContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('dropdown-option')) {
                const value = e.target.getAttribute('data-value');
                const displayText = e.target.textContent;

                // Set the search input to display the selected option
                searchInput.value = displayText;
                hiddenInput.value = value;

                // Update related fields for customer selection
                if (hiddenInputId === 'customerCode') {
                    const balance = parseFloat(e.target.getAttribute('data-balance'));
                    document.getElementById('previousBalance').value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
                    
                    // Update customer address
                    const address = e.target.getAttribute('data-address');
                    document.getElementById('customerAddress').textContent = address || 'No address available';
                    
                    // Auto-populate discount if enabled
                    const enableInvoiceDiscount = localStorage.getItem('enableInvoiceCashDiscountPercent') === 'true';
                    if (enableInvoiceDiscount) {
                        const discount = parseFloat(e.target.getAttribute('data-discount')) || 0;
                        document.getElementById('totalDiscountPercent').value = discount;
                        updateInvoiceSummary();
                    }
                    
                    // Auto-populate Supplier Man from customer data
                    const customerId = value;
                    const customer = dataCache.customers.get(parseInt(customerId));
                    if (customer) {
                        // Auto-populate Sales Officer
                        if (customer.associated_sales_officer_id) {
                            document.getElementById('salesOfficer').value = customer.associated_sales_officer_id;
                        } else {
                            document.getElementById('salesOfficer').value = '';
                        }
                        
                        // Auto-populate Supplier Man
                        if (customer.supplier_man_id) {
                            document.getElementById('supplierMan').value = customer.supplier_man_id;
                        } else {
                            document.getElementById('supplierMan').value = '';
                        }
                    }
                    
                    // Load price history for all rows
                    loadPriceHistoryForAllRows();
                }
                
                // Save branch selection
                if (hiddenInputId === 'branch') {
                    localStorage.setItem('lastSelectedBranch', value);
                }

                // Hide options
                optionsContainer.style.display = 'none';

                // Remove error state if any
                hiddenInput.classList.remove('error');
                document.getElementById(hiddenInputId + 'Error').style.display = 'none';
            }
        });

        // Hide options when clicking outside
        document.addEventListener('click', function () {
            optionsContainer.style.display = 'none';
        });

        // Filter options function
        function filterOptions() {
            const searchTerm = searchInput.value.toLowerCase();
            const options = optionsContainer.getElementsByClassName('dropdown-option');

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();

                if (text.includes(searchTerm)) {
                    option.style.display = 'block';
                    option.style.backgroundColor = '';
                } else {
                    option.style.display = 'none';
                }
            }
        }
    }

    // Add a new row to the items table
    function addRow() {
        const rowCount = itemsTable.rows.length;
        const row = itemsTable.insertRow();

        // Serial number
        const cell0 = row.insertCell(0);
        cell0.textContent = rowCount + 1;

        // Code (searchable dropdown)
        const cell1 = row.insertCell(1);
        const codeContainer = document.createElement('div');
        codeContainer.className = 'searchable-dropdown';
        console.log('Creating dropdown, productsData length:', productsData.length);
        const codeOptionsHtml = productsData.map(product => {
            const codes = [];
            if (product.qr_code) codes.push(product.qr_code);
            if (product.barcode) codes.push(product.barcode);
            const codeDisplay = codes.length > 0 ? ` (${codes.join(' - ')})` : '';
            const photoHtml = product.photo ? `<img src="../../../assets/uploads/products/${product.photo}" alt="${product.name}" style="width: 30px; height: 30px; object-fit: cover; margin-right: 8px; border-radius: 4px;">` : '';
            const salePrice = product.sale_price || '0';
            return `<div class="dropdown-option" data-value="${product.id}" data-price="${salePrice}" data-unit="${product.default_unit_id}" style="display: flex; align-items: center;">${photoHtml}${product.code} - ${product.name}${codeDisplay}</div>`;
        }).join('');
        codeContainer.innerHTML = `
            <input type="text" class="search-input table-input" placeholder="Search product...">
            <input type="hidden" class="item-code" required>
        `;
        
        // Create dropdown options and append to body
        const dropdownOptions = document.createElement('div');
        dropdownOptions.className = 'dropdown-options';
        dropdownOptions.innerHTML = codeOptionsHtml;
        dropdownOptions.style.position = 'absolute';
        dropdownOptions.style.display = 'none';
        dropdownOptions.style.zIndex = '9999';
        document.body.appendChild(dropdownOptions);
        
        // Store reference to dropdown
        codeContainer.querySelector('.search-input').dropdownOptions = dropdownOptions;
        cell1.appendChild(codeContainer);
        initTableDropdown(codeContainer);

        // Unit (dropdown)
        const cell2 = row.insertCell(2);
        const unitSelect = document.createElement('select');
        unitSelect.className = 'table-input';
        const uomOptionsHtml = uomData.map(uom => 
            `<option value="${uom.id}">${uom.uom_name}</option>`
        ).join('');
        unitSelect.innerHTML = `<option value="">Select Unit</option>${uomOptionsHtml}`;
        unitSelect.required = true;
        unitSelect.tabIndex = -1;
        
        // Add UOM change event for price conversion
        unitSelect.addEventListener('change', function() {
            handleUOMChange(row, this);
        });
        
        cell2.appendChild(unitSelect);

        // Qty (input)
        const cell3 = row.insertCell(3);
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'table-input';
        qtyInput.min = '0';
        qtyInput.step = '0.01';
        qtyInput.required = true;
        qtyInput.value = '0';
        cell3.appendChild(qtyInput);

        // Pcs (input)
        const cell4 = row.insertCell(4);
        const pcsInput = document.createElement('input');
        pcsInput.type = 'number';
        pcsInput.className = 'table-input';
        pcsInput.min = '0';
        pcsInput.step = '1';
        pcsInput.value = '0';
        cell4.appendChild(pcsInput);

        // Ctn (input)
        const cell5 = row.insertCell(5);
        const ctnInput = document.createElement('input');
        ctnInput.type = 'number';
        ctnInput.className = 'table-input';
        ctnInput.min = '0';
        ctnInput.step = '1';
        ctnInput.value = '0';
        cell5.appendChild(ctnInput);

        // Dz (input)
        const cell6 = row.insertCell(6);
        const dzInput = document.createElement('input');
        dzInput.type = 'number';
        dzInput.className = 'table-input';
        dzInput.min = '0';
        dzInput.step = '1';
        dzInput.value = '0';
        cell6.appendChild(dzInput);

        // Calculate Qty from Ctn, Dz, Pcs
        function calculateQty() {
            const ctn = parseFloat(ctnInput.value) || 0;
            const dz = parseFloat(dzInput.value) || 0;
            const pcs = parseFloat(pcsInput.value) || 0;
            const cartonConv = parseFloat(row.dataset.cartonConversion) || 0;
            const qty = pcs + (ctn * cartonConv) + (dz * 12);
            qtyInput.value = qty.toFixed(2);
            calculateRow();
        }

        ctnInput.addEventListener('input', calculateQty);
        dzInput.addEventListener('input', calculateQty);
        pcsInput.addEventListener('input', calculateQty);

        // Sale Price (input)
        const cell7 = row.insertCell(7);
        const priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.className = 'table-input';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.required = true;
        cell7.appendChild(priceInput);

        // Gross Amount (readonly)
        const cell8 = row.insertCell(8);
        const grossAmount = document.createElement('input');
        grossAmount.type = 'text';
        grossAmount.className = 'table-input';
        grossAmount.readOnly = true;
        grossAmount.value = '0.00';
        grossAmount.tabIndex = -1;
        cell8.appendChild(grossAmount);

        // Discount % (input)
        const cell9 = row.insertCell(9);
        const discountPercent = document.createElement('input');
        discountPercent.type = 'number';
        discountPercent.className = 'table-input';
        discountPercent.min = '0';
        discountPercent.max = '100';
        discountPercent.step = '0.01';
        discountPercent.value = '0';
        cell9.appendChild(discountPercent);

        // Discount Amount (input)
        const cell10 = row.insertCell(10);
        const discountAmount = document.createElement('input');
        discountAmount.type = 'number';
        discountAmount.className = 'table-input';
        discountAmount.min = '0';
        discountAmount.step = '0.01';
        discountAmount.value = '0.00';
        cell10.appendChild(discountAmount);

        // Trade Offer Discount % (input)
        const cell11 = row.insertCell(11);
        const tradeOfferDiscount = document.createElement('input');
        tradeOfferDiscount.type = 'number';
        tradeOfferDiscount.className = 'table-input';
        tradeOfferDiscount.min = '0';
        tradeOfferDiscount.max = '100';
        tradeOfferDiscount.step = '0.01';
        tradeOfferDiscount.value = '0';
        cell11.appendChild(tradeOfferDiscount);
        
        // Trade Offer Amount (input)
        const cell12 = row.insertCell(12);
        const tradeOfferAmount = document.createElement('input');
        tradeOfferAmount.type = 'number';
        tradeOfferAmount.className = 'table-input';
        tradeOfferAmount.min = '0';
        tradeOfferAmount.step = '0.01';
        tradeOfferAmount.value = '0.00';
        cell12.appendChild(tradeOfferAmount);
        
        // GST % (input)
        const cell13 = row.insertCell(13);
        const gstPercent = document.createElement('input');
        gstPercent.type = 'number';
        gstPercent.className = 'table-input';
        gstPercent.min = '0';
        gstPercent.max = '100';
        gstPercent.step = '0.01';
        gstPercent.value = '0';
        cell13.appendChild(gstPercent);
        
        // GST Amount (input)
        const cell14 = row.insertCell(14);
        const gstAmount = document.createElement('input');
        gstAmount.type = 'number';
        gstAmount.className = 'table-input';
        gstAmount.min = '0';
        gstAmount.step = '0.01';
        gstAmount.value = '0.00';
        cell14.appendChild(gstAmount);
        
        // FOC Qty (input)
        const cell15 = row.insertCell(15);
        const focQty = document.createElement('input');
        focQty.type = 'number';
        focQty.className = 'table-input';
        focQty.min = '0';
        focQty.step = '0.01';
        focQty.value = '0';
        cell15.appendChild(focQty);
        
        // Apply settings to new row
        const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
        const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
        const enableTradeOfferDiscount = localStorage.getItem('enableTradeOfferDiscount') === 'true';
        const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC = localStorage.getItem('enableFOC') === 'true';
        const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
        const enableCtn = localStorage.getItem('enableCtn') === 'true';
        const enableDz = localStorage.getItem('enableDz') === 'true';
        const enablePcs = localStorage.getItem('enablePcs') === 'true';
        
        cell4.style.display = enablePcs ? '' : 'none';
        cell5.style.display = enableCtn ? '' : 'none';
        cell6.style.display = enableDz ? '' : 'none';
        cell9.style.display = enableCashDiscountPercent ? '' : 'none';
        cell10.style.display = enableCashDiscountAmount ? '' : 'none';
        cell11.style.display = enableTradeOfferDiscount ? '' : 'none';
        cell12.style.display = enableTradeOfferAmount ? '' : 'none';
        cell13.style.display = enableTaxation ? '' : 'none';
        cell14.style.display = enableTaxation ? '' : 'none';
        cell15.style.display = enableFOC ? '' : 'none';

        // Net Amount (readonly)
        const cell16 = row.insertCell(16);
        const netAmount = document.createElement('input');
        netAmount.type = 'text';
        netAmount.className = 'table-input';
        netAmount.readOnly = true;
        netAmount.value = '0.00';
        netAmount.tabIndex = 0;
        
        // Add tab navigation logic for Net Amount
        netAmount.addEventListener('keydown', function(e) {
            if (e.key === 'Tab' && !e.shiftKey) {
                const codeInput = row.cells[1].querySelector('.item-code');
                if (codeInput.value) {
                    e.preventDefault();
                    const currentRowIndex = row.rowIndex - 1; // Subtract 1 for header row
                    const nextRowIndex = currentRowIndex + 1;
                    
                    if (nextRowIndex < itemsTable.rows.length) {
                        // Focus on existing next row
                        const nextRow = itemsTable.rows[nextRowIndex];
                        nextRow.cells[1].querySelector('.search-input').focus();
                    } else {
                        // Create new row and focus on it
                        addRow();
                        setTimeout(() => {
                            const newRow = itemsTable.rows[itemsTable.rows.length - 1];
                            newRow.cells[1].querySelector('.search-input').focus();
                        }, 10);
                    }
                } else {
                    e.preventDefault();
                    // Delete current row if there are multiple rows
                    if (itemsTable.rows.length > 1) {
                        // Clean up dropdown options
                        const searchInput = row.cells[1].querySelector('.search-input');
                        if (searchInput.dropdownOptions) {
                            searchInput.dropdownOptions.remove();
                        }
                        row.remove();
                        updateSerialNumbers();
                        updateInvoiceSummary();
                    }
                    document.getElementById('totalDiscountPercent').focus();
                }
            }
        });
        
        cell16.appendChild(netAmount);

        // Actions (variants + delete button)
        const cell17 = row.insertCell(17);
        const actionsDiv = document.createElement('div');
        actionsDiv.style.display = 'flex';
        actionsDiv.style.gap = '4px';
        
        const addVariantsBtn = document.createElement('button');
        addVariantsBtn.type = 'button';
        addVariantsBtn.className = 'btn btn-secondary btn-sm';
        addVariantsBtn.innerHTML = '<i class="fas fa-boxes"></i>';
        addVariantsBtn.title = 'Add Variants';
        addVariantsBtn.tabIndex = -1;
        addVariantsBtn.style.display = 'none';
        
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-sm';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.title = 'Delete row';
        deleteBtn.tabIndex = -1;
        
        actionsDiv.appendChild(addVariantsBtn);
        actionsDiv.appendChild(deleteBtn);
        cell17.appendChild(actionsDiv);

        // Add event listeners for calculations
        qtyInput.addEventListener('input', calculateRow);
        priceInput.addEventListener('input', calculateRow);
        discountPercent.addEventListener('input', calculateRow);
        discountAmount.addEventListener('input', calculateRowFromAmount);
        tradeOfferDiscount.addEventListener('input', calculateRow);
        tradeOfferAmount.addEventListener('input', calculateRowFromTradeOfferAmount);
        gstPercent.addEventListener('input', calculateRow);
        gstAmount.addEventListener('input', calculateRowFromGstAmount);
        
        // Enter key navigation for faster workflow
        qtyInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                priceInput.focus();
            }
        });
        
        pcsInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                priceInput.focus();
            }
        });
        
        priceInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Skip to discount if enabled, otherwise go to next row
                const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
                if (enableCashDiscountPercent) {
                    discountPercent.focus();
                } else {
                    netAmount.focus();
                }
            }
        });
        
        discountPercent.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                netAmount.focus();
            }
        });

        // Add event listener for delete button
        deleteBtn.addEventListener('click', function () {
            if (itemsTable.rows.length > 1) {
                const rowIndex = row.rowIndex - 1;
                const childRows = [];
                for (let i = rowIndex + 1; i < itemsTable.rows.length; i++) {
                    if (itemsTable.rows[i].classList.contains('child-row') && itemsTable.rows[i].dataset.parentRowIndex == rowIndex) {
                        childRows.push(itemsTable.rows[i]);
                    } else {
                        break;
                    }
                }
                childRows.forEach(childRow => childRow.remove());
                row.remove();
                updateSerialNumbers();
                updateInvoiceSummary();
            } else {
                alert('Cannot delete the only row. Add another row first.');
            }
        });
        
        addVariantsBtn.addEventListener('click', function() {
            openVariantsModal(row);
        });

        function calculateRow() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountPct = parseFloat(discountPercent.value) || 0;
            const tradeOfferPct = parseFloat(tradeOfferDiscount.value) || 0;
            const gstPct = parseFloat(gstPercent.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate discount amount
            const discountAmt = gross * (discountPct / 100);
            discountAmount.value = discountAmt.toFixed(2);
            
            // Calculate trade offer amount from percentage
            const afterDiscount = gross - discountAmt;
            const tradeOfferAmt = afterDiscount * (tradeOfferPct / 100);
            tradeOfferAmount.value = tradeOfferAmt.toFixed(2);
            
            // Calculate GST amount from percentage
            const afterTradeOffer = afterDiscount - tradeOfferAmt;
            const gstAmt = afterTradeOffer * (gstPct / 100);
            gstAmount.value = gstAmt.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);
            
            // Auto-calculate FOC if enabled
            const enableFOC = localStorage.getItem('enableFOC') === 'true';
            if (enableFOC && qty > 0) {
                const customerId = document.getElementById('customerCode').value;
                const productId = row.cells[1].querySelector('.item-code').value;
                if (customerId && productId) {
                    fetch(`../../../../server/api/sale/sale_order/get-foc.php?customer_id=${customerId}&product_id=${productId}&quantity=${qty}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                focQty.value = data.foc_qty || 0;
                                if (data.rate && data.rate > 0) {
                                    priceInput.value = parseFloat(data.rate).toFixed(2);
                                    priceInput.dispatchEvent(new Event('input'));
                                }
                                if (data.trade_offer && data.trade_offer > 0) {
                                    tradeOfferAmount.value = parseFloat(data.trade_offer).toFixed(2);
                                    tradeOfferAmount.dispatchEvent(new Event('input'));
                                } else {
                                    tradeOfferAmount.value = '0.00';
                                }
                            }
                        });
                }
            }

            // Update invoice summary
            updateInvoiceSummary();
        }
        
        function calculateRowFromAmount() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountAmt = parseFloat(discountAmount.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate discount percentage from amount
            const discountPct = gross > 0 ? (discountAmt / gross) * 100 : 0;
            discountPercent.value = discountPct.toFixed(2);
            
            // Calculate trade offer from percentage
            const tradeOfferPct = parseFloat(tradeOfferDiscount.value) || 0;
            const afterDiscount = gross - discountAmt;
            const tradeOfferAmt = afterDiscount * (tradeOfferPct / 100);
            tradeOfferAmount.value = tradeOfferAmt.toFixed(2);
            
            // Calculate GST from percentage
            const gstPct = parseFloat(gstPercent.value) || 0;
            const afterTradeOffer = afterDiscount - tradeOfferAmt;
            const gstAmt = afterTradeOffer * (gstPct / 100);
            gstAmount.value = gstAmt.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update invoice summary
            updateInvoiceSummary();
        }
        
        function calculateRowFromTradeOfferAmount() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountPct = parseFloat(discountPercent.value) || 0;
            const tradeOfferAmt = parseFloat(tradeOfferAmount.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate discount amount
            const discountAmt = gross * (discountPct / 100);
            discountAmount.value = discountAmt.toFixed(2);
            
            // Calculate trade offer percentage from amount
            const afterDiscount = gross - discountAmt;
            const tradeOfferPct = afterDiscount > 0 ? (tradeOfferAmt / afterDiscount) * 100 : 0;
            tradeOfferDiscount.value = tradeOfferPct.toFixed(2);
            
            // Calculate GST from percentage
            const gstPct = parseFloat(gstPercent.value) || 0;
            const afterTradeOffer = afterDiscount - tradeOfferAmt;
            const gstAmt = afterTradeOffer * (gstPct / 100);
            gstAmount.value = gstAmt.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update invoice summary
            updateInvoiceSummary();
        }
        
        function calculateRowFromGstAmount() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountPct = parseFloat(discountPercent.value) || 0;
            const tradeOfferPct = parseFloat(tradeOfferDiscount.value) || 0;
            const gstAmt = parseFloat(gstAmount.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate discount amount
            const discountAmt = gross * (discountPct / 100);
            discountAmount.value = discountAmt.toFixed(2);
            
            // Calculate trade offer amount
            const afterDiscount = gross - discountAmt;
            const tradeOfferAmt = afterDiscount * (tradeOfferPct / 100);
            tradeOfferAmount.value = tradeOfferAmt.toFixed(2);
            
            // Calculate GST percentage from amount
            const afterTradeOffer = afterDiscount - tradeOfferAmt;
            const gstPct = afterTradeOffer > 0 ? (gstAmt / afterTradeOffer) * 100 : 0;
            gstPercent.value = gstPct.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update invoice summary
            updateInvoiceSummary();
        }
    }

    // Initialize dropdown for table rows
    function initTableDropdown(container) {
        const searchInput = container.querySelector('.search-input');
        const optionsContainer = searchInput.dropdownOptions;
        const hiddenInput = container.querySelector('input[type="hidden"]');
        const row = container.closest('tr');
        let selectedIndex = -1;

        // Show options when clicking on search input
        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
            
            // Position dropdown relative to input
            const rect = searchInput.getBoundingClientRect();
            optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
            optionsContainer.style.left = rect.left + 'px';
            optionsContainer.style.width = rect.width + 'px';
            optionsContainer.style.display = 'block';
            
            filterOptions();
        });
        
        // Show options when input receives focus (tab navigation)
        searchInput.addEventListener('focus', function (e) {
            // Position dropdown relative to input
            const rect = searchInput.getBoundingClientRect();
            optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
            optionsContainer.style.left = rect.left + 'px';
            optionsContainer.style.width = rect.width + 'px';
            optionsContainer.style.display = 'block';
            
            filterOptions();
        });
        
        // Auto-search on typing for speed
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterOptions();
                // Auto-select first match if only one result
                const visibleOptions = Array.from(optionsContainer.getElementsByClassName('dropdown-option'))
                    .filter(option => option.style.display !== 'none');
                if (visibleOptions.length === 1) {
                    selectedIndex = 0;
                    updateSelection(visibleOptions);
                }
            }, 100);
        });
        
        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
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
                } else if (visibleOptions.length > 0) {
                    visibleOptions[0].click();
                }
            }
        });
        
        function updateSelection(visibleOptions) {
            visibleOptions.forEach((option, index) => {
                option.style.backgroundColor = index === selectedIndex ? 'var(--surface-1)' : '';
            });
        }

        // Filter options based on search input
        searchInput.addEventListener('input', function() {
            selectedIndex = -1;
            filterOptions();
        });

        // Select option
        optionsContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('dropdown-option')) {
                const value = e.target.getAttribute('data-value');
                const displayText = e.target.textContent;
                const price = e.target.getAttribute('data-price');
                const unitId = e.target.getAttribute('data-unit');
                
                console.log('Product selected - ID:', value, 'Price:', price, 'Unit:', unitId);

                // Set the search input to display the selected option
                searchInput.value = displayText;
                hiddenInput.value = value;
                
                // Store carton conversion in row data
                const product = dataCache.products.get(parseInt(value));
                if (product) {
                    row.dataset.cartonConversion = product.carton_conversion || 0;
                    row.dataset.productId = value;
                    checkAndShowVariantsButton(row, value);
                }

                // Update related fields
                if (price) {
                    row.cells[7].querySelector('input').value = price;
                }
                if (unitId) {
                    row.cells[2].querySelector('select').value = unitId;
                    row.cells[2].querySelector('select').dataset.previousValue = unitId;
                }
                
                // Auto-focus Qty for faster entry
                setTimeout(() => {
                    row.cells[3].querySelector('input').select();
                }, 10);
                
                // Auto-populate based on settings
                const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
                const enableTradeOfferDiscount = localStorage.getItem('enableTradeOfferDiscount') === 'true';
                const enableFOC = localStorage.getItem('enableFOC') === 'true';
                const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
                
                if (product) {
                    if (enableCashDiscountPercent && product.default_discount) {
                        row.cells[9].querySelector('input').value = product.default_discount;
                    }
                    if (enableTradeOfferDiscount && product.trade_offer_discount) {
                        row.cells[11].querySelector('input').value = product.trade_offer_discount;
                    }
                    if (enableFOC && product.default_foc) {
                        row.cells[15].querySelector('input').value = product.default_foc;
                    }
                    if (enableTaxation && product.sales_tax) {
                        row.cells[13].querySelector('input').value = product.sales_tax;
                    }
                }

                // Hide options
                optionsContainer.style.display = 'none';
                
                // Load price history and stock
                loadPriceHistory(value);
                loadProductStock(value);
            }
        });

        // Hide options when clicking outside
        document.addEventListener('click', function () {
            optionsContainer.style.display = 'none';
        });

        // Filter options function
        function filterOptions() {
            const searchTerm = searchInput.value.toLowerCase();
            const options = optionsContainer.getElementsByClassName('dropdown-option');

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();

                if (text.includes(searchTerm)) {
                    option.style.display = 'block';
                    option.style.backgroundColor = '';
                } else {
                    option.style.display = 'none';
                }
            }
        }
    }

    // Update serial numbers after row deletion
    function updateSerialNumbers() {
        const rows = itemsTable.rows;
        for (let i = 0; i < rows.length; i++) {
            rows[i].cells[0].textContent = i + 1;
        }
    }

    // Update invoice summary
    function updateInvoiceSummary() {
        requestAnimationFrame(() => {
            let totalBill = 0;
            let totalPcs = 0;
            let totalCtn = 0;
            let totalDz = 0;
            let totalQty = 0;
            let totalSalePrice = 0;
            let totalGrossAmount = 0;
            let totalDiscountAmountItems = 0;
            let totalTradeOfferAmount = 0;
            let totalGstAmount = 0;
            let totalFocQty = 0;
            let totalNetAmountItems = 0;
            const rows = itemsTable.rows;

            for (let i = 0; i < rows.length; i++) {
                const qtyInput = rows[i].cells[3].querySelector('input');
                const pcsInput = rows[i].cells[4].querySelector('input');
                const ctnInput = rows[i].cells[5].querySelector('input');
                const dzInput = rows[i].cells[6].querySelector('input');
                const salePriceInput = rows[i].cells[7].querySelector('input');
                const grossAmountInput = rows[i].cells[8].querySelector('input');
                const discountAmountInput = rows[i].cells[10].querySelector('input');
                const tradeOfferAmountInput = rows[i].cells[12].querySelector('input');
                const gstAmountInput = rows[i].cells[14].querySelector('input');
                const focQtyInput = rows[i].cells[15].querySelector('input');
                const netAmountInput = rows[i].cells[16].querySelector('input');
                
                if (netAmountInput) {
                    totalQty += parseFloat(qtyInput.value) || 0;
                    totalPcs += parseFloat(pcsInput.value) || 0;
                    totalCtn += parseFloat(ctnInput.value) || 0;
                    totalDz += parseFloat(dzInput.value) || 0;
                    totalSalePrice += parseFloat(salePriceInput.value) || 0;
                    totalGrossAmount += parseFloat(grossAmountInput.value) || 0;
                    totalDiscountAmountItems += parseFloat(discountAmountInput.value) || 0;
                    totalTradeOfferAmount += parseFloat(tradeOfferAmountInput.value) || 0;
                    totalGstAmount += parseFloat(gstAmountInput.value) || 0;
                    totalFocQty += parseFloat(focQtyInput.value) || 0;
                    totalNetAmountItems += parseFloat(netAmountInput.value) || 0;
                    totalBill += parseFloat(netAmountInput.value) || 0;
                }
            }

            document.getElementById('totalQty').textContent = totalQty.toFixed(2);
            document.getElementById('totalPcs').textContent = totalPcs.toFixed(2);
            document.getElementById('totalCtn').textContent = totalCtn.toFixed(2);
            document.getElementById('totalDz').textContent = totalDz.toFixed(2);
            document.getElementById('totalSalePrice').textContent = totalSalePrice.toFixed(2);
            document.getElementById('totalGrossAmount').textContent = totalGrossAmount.toFixed(2);
            document.getElementById('totalDiscountAmountItems').textContent = totalDiscountAmountItems.toFixed(2);
            document.getElementById('totalTradeOfferAmount').textContent = totalTradeOfferAmount.toFixed(2);
            document.getElementById('totalGstAmount').textContent = totalGstAmount.toFixed(2);
            document.getElementById('totalFocQty').textContent = totalFocQty.toFixed(2);
            document.getElementById('totalNetAmountItems').textContent = totalNetAmountItems.toFixed(2);
            document.getElementById('totalBill').textContent = totalBill.toFixed(2);

            const discountPercent = parseFloat(document.getElementById('totalDiscountPercent').value) || 0;
            const discountAmount = totalBill * (discountPercent / 100);
            const afterDiscount = totalBill - discountAmount;
            
            const gstPercent = parseFloat(document.getElementById('totalGstPercent').value) || 0;
            const gstAmount = afterDiscount * (gstPercent / 100);
            const shippingFees = parseFloat(document.getElementById('shippingFees').value) || 0;
            const netAmount = afterDiscount + gstAmount + shippingFees;

            document.getElementById('totalDiscountAmount').value = discountAmount.toFixed(2);
            document.getElementById('totalGstAmountSummary').value = gstAmount.toFixed(2);
            document.getElementById('netAmount').textContent = netAmount.toFixed(2);
            const amountPaidEl = document.getElementById('amountPaid');
            if (amountPaidEl) amountPaidEl.value = netAmount.toFixed(2);
            
            updateRemainingBalance();
            checkCreditLimit();
        });
    }
    
    function checkCreditLimit() {
        const customerCode = document.getElementById('customerCode');
        const creditLimit = parseFloat(customerCode.getAttribute('data-credit-limit')) || 0;
        if (creditLimit === 0) return;
        
        const previousBalance = parseFloat(document.getElementById('previousBalance').value.replace(/[^0-9.-]/g, '')) || 0;
        const netAmount = parseFloat(document.getElementById('netAmount').textContent) || 0;
        const total = previousBalance + netAmount;
        
        if (total >= creditLimit) {
            alert(`Warning: Total amount (${total.toFixed(2)}) exceeds or equals customer credit limit (${creditLimit.toFixed(2)})`);
        }
    }
    
    // Update remaining balance
    function updateRemainingBalance() {
        const netAmount = parseFloat(document.getElementById('netAmount').textContent) || 0;
        const amountPaid = parseFloat(document.getElementById('amountPaid')?.value) || 0;
        const remainingBalance = netAmount - amountPaid;
        const remainingBalanceEl = document.getElementById('remainingBalance');
        if (remainingBalanceEl) {
            remainingBalanceEl.value = remainingBalance.toFixed(2);
        }
    }

    // Add event listener for total discount percent
    document.getElementById('totalDiscountPercent').addEventListener('input', updateInvoiceSummary);
    
    // Add event listener for total discount amount
    document.getElementById('totalDiscountAmount').addEventListener('input', updateInvoiceSummaryFromAmount);
    
    // Add event listener for total GST percent
    document.getElementById('totalGstPercent').addEventListener('input', updateInvoiceSummary);
    
    // Add event listener for total GST amount
    document.getElementById('totalGstAmountSummary').addEventListener('input', updateInvoiceSummaryFromGstAmount);
    
    // Add event listener for shipping fees
    document.getElementById('shippingFees').addEventListener('input', updateInvoiceSummary);
    
    // Add event listener for amount paid
    const amountPaidEl = document.getElementById('amountPaid');
    if (amountPaidEl) {
        amountPaidEl.addEventListener('input', updateRemainingBalance);
    }
    
    // Add event listener for payment method to show/hide bank account
    const paymentMethodEl = document.getElementById('paymentMethod');
    if (paymentMethodEl) {
        paymentMethodEl.addEventListener('change', function() {
            const bankAccountContainer = document.getElementById('bankAccountContainer');
            if (bankAccountContainer) {
                if (this.value === 'bank_transfer') {
                    bankAccountContainer.style.display = 'flex';
                } else {
                    bankAccountContainer.style.display = 'none';
                    const bankAccount = document.getElementById('bankAccount');
                    if (bankAccount) bankAccount.value = '';
                }
            }
        });
    }
    
    // Update invoice summary from discount amount
    function updateInvoiceSummaryFromAmount() {
        let totalBill = 0;
        const rows = itemsTable.rows;

        for (let i = 0; i < rows.length; i++) {
            const netAmountInput = rows[i].cells[8].querySelector('input');
            if (netAmountInput) {
                totalBill += parseFloat(netAmountInput.value) || 0;
            }
        }

        document.getElementById('totalBill').textContent = totalBill.toFixed(2);

        const discountAmount = parseFloat(document.getElementById('totalDiscountAmount').value) || 0;
        const discountPercent = totalBill > 0 ? (discountAmount / totalBill) * 100 : 0;
        const netAmount = totalBill - discountAmount;

        document.getElementById('totalDiscountPercent').value = discountPercent.toFixed(2);
        document.getElementById('netAmount').textContent = netAmount.toFixed(2);
        
        // Auto-populate Amount Paid with Net Amount
        const amountPaidEl = document.getElementById('amountPaid');
        if (amountPaidEl) amountPaidEl.value = netAmount.toFixed(2);
        
        updateRemainingBalance();
    }

    // Validate form before submission
    function validateForm() {
        let isValid = true;

        // Reset error states
        document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
        document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');

        // Check required fields
        const requiredFields = [
            { id: 'customerCode', errorId: 'customerCodeError' },
            { id: 'branch', errorId: 'branchError' },
            { id: 'currency', errorId: 'currencyError' }
        ];

        requiredFields.forEach(field => {
            const element = document.getElementById(field.id);
            if (element && !element.value) {
                element.classList.add('error');
                const errorElement = document.getElementById(field.errorId);
                if (errorElement) errorElement.style.display = 'block';
                isValid = false;
            }
        });

        // Check if at least one item is added
        if (itemsTable.rows.length === 0) {
            alert('Please add at least one item to the invoice.');
            isValid = false;
        }

        // Check each item row for required fields
        const rows = itemsTable.rows;
        for (let i = 0; i < rows.length; i++) {
            const code = rows[i].cells[1].querySelector('.item-code');
            const unit = rows[i].cells[2].querySelector('select');
            const qty = rows[i].cells[3].querySelector('input');
            const price = rows[i].cells[7].querySelector('input');

            if (!code.value || !unit.value || !qty.value || !price.value) {
                alert('Please fill all required fields in the items table.');
                isValid = false;
                break;
            }
        }

        return isValid;
    }



    // Reset form with confirmation
    function resetForm() {
        if (confirm('Are you sure you want to reset the form? All data will be lost.')) {
            performReset();
        }
    }
    
    // Silent reset without confirmation
    function performReset() {
        form.reset();

        // Clear items table
        while (itemsTable.rows.length > 0) {
            itemsTable.deleteRow(0);
        }

        // Add one empty row
        addRow();

        // Reset summary
        document.getElementById('totalBill').textContent = '0.00';
        document.getElementById('totalDiscountAmount').value = '0.00';
        document.getElementById('netAmount').textContent = '0.00';

        // Set default date to today
        document.getElementById('saleDate').value = today;

        // Reset searchable dropdowns
        document.getElementById('customerCodeSearch').value = '';
        document.getElementById('customerCode').value = '';
        document.getElementById('branchSearch').value = '';
        document.getElementById('branch').value = '';
        document.getElementById('fuelingStationSearch').value = '';
        document.getElementById('fuelingStation').value = '';
        
        // Reset currency to base currency
        loadCurrencies();
        
        // Reset currency symbols
        setTimeout(updateCurrencySymbols, 100);
    }

    // Modal button events
    document.getElementById('printLaterBtn').addEventListener('click', function () {
        document.getElementById('successModal').style.display = 'none';
        performReset();
    });

    document.getElementById('printInvoiceBtn').addEventListener('click', function () {
        document.getElementById('successModal').style.display = 'none';
        const invoiceId = isEditMode ? editId : window.lastInvoiceId;
        showPrintOptions(invoiceId);
    });
    
    // Print options modal
    function showPrintOptions(invoiceId) {
        const savedChoice = localStorage.getItem('pos_print_preference');
        if (savedChoice) {
            printInvoice(invoiceId, savedChoice);
            performReset();
            return;
        }
        
        document.getElementById('printOptionsModal').style.display = 'flex';
        
        document.getElementById('printFullBtn').onclick = function() {
            if (document.getElementById('rememberPrintChoice').checked) {
                localStorage.setItem('pos_print_preference', 'full');
            }
            document.getElementById('printOptionsModal').style.display = 'none';
            printInvoice(invoiceId, 'full');
            performReset();
        };
        
        document.getElementById('printThermalBtn').onclick = function() {
            if (document.getElementById('rememberPrintChoice').checked) {
                localStorage.setItem('pos_print_preference', 'thermal');
            }
            document.getElementById('printOptionsModal').style.display = 'none';
            printInvoice(invoiceId, 'thermal');
            performReset();
        };
    }
    
    function printInvoice(invoiceId, type) {
        if (invoiceId) {
            const url = type === 'full' ? `invoice-print.php?id=${invoiceId}` : `thermal-print.php?id=${invoiceId}`;
            window.open(url, '_blank');
        }
    }
    
    // View Drafts button
    document.getElementById('viewDraftsBtn').addEventListener('click', async function() {
        document.getElementById('draftsModal').style.display = 'flex';
        await loadDraftInvoices();
    });
    
    // Close Drafts modal
    document.getElementById('closeDraftsBtn').addEventListener('click', function() {
        document.getElementById('draftsModal').style.display = 'none';
    });
    
    // Load draft invoices
    async function loadDraftInvoices() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_order/get-drafts.php');
            const data = await response.json();
            
            const tbody = document.getElementById('draftsTableBody');
            tbody.innerHTML = '';
            
            if (data.success && data.drafts.length > 0) {
                data.drafts.forEach(draft => {
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${draft.bill_no}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${new Date(draft.sale_date).toLocaleDateString()}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${draft.customer_name}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${draft.currency_symbol} ${parseFloat(draft.net_amount).toFixed(2)}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">
                            <button class="btn btn-primary btn-sm" onclick="window.location.href='order-add.php?edit=${draft.id}'" style="margin-right: 5px;">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteDraft(${draft.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No draft invoices found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading drafts:', error);
            document.getElementById('draftsTableBody').innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--error);">Error loading drafts</td></tr>';
        }
    }
    
    // Hide modals when clicking outside
    document.getElementById('draftsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    
    // Delete draft function
    window.deleteDraft = async function(draftId) {
        if (!confirm('Are you sure you want to delete this draft invoice?')) {
            return;
        }
        
        try {
            const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-delete.php?id=${draftId}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            
            if (data.success) {
                alert('Draft invoice deleted successfully!');
                loadDraftInvoices();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error deleting draft: ' + error.message);
        }
    };
    
    // Print Settings button
    document.getElementById('printSettingsBtn').addEventListener('click', function() {
        const savedPref = localStorage.getItem('pos_print_preference');
        const enableCheckbox = document.getElementById('enableRememberPrint');
        const prefSection = document.getElementById('printPreferenceSection');
        const prefSelect = document.getElementById('printPreference');
        
        if (savedPref) {
            enableCheckbox.checked = true;
            prefSection.style.display = 'block';
            prefSelect.value = savedPref;
        } else {
            enableCheckbox.checked = false;
            prefSection.style.display = 'none';
        }
        
        document.getElementById('printSettingsModal').style.display = 'flex';
    });
    
    // Enable/disable preference section
    document.getElementById('enableRememberPrint').addEventListener('change', function() {
        const prefSection = document.getElementById('printPreferenceSection');
        prefSection.style.display = this.checked ? 'block' : 'none';
    });
    
    // Close print settings
    document.getElementById('closePrintSettingsBtn').addEventListener('click', function() {
        document.getElementById('printSettingsModal').style.display = 'none';
    });
    
    // Save print settings
    document.getElementById('savePrintSettingsBtn').addEventListener('click', function() {
        const enableCheckbox = document.getElementById('enableRememberPrint');
        const prefSelect = document.getElementById('printPreference');
        
        if (enableCheckbox.checked) {
            localStorage.setItem('pos_print_preference', prefSelect.value);
            alert('Print settings saved successfully!');
        } else {
            localStorage.removeItem('pos_print_preference');
            alert('Remember print choice disabled!');
        }
        
        document.getElementById('printSettingsModal').style.display = 'none';
    });
    
    // Hide print settings modal when clicking outside
    document.getElementById('printSettingsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
};


// Save as Draft button event
document.getElementById('saveDraftBtn').addEventListener('click', function() {
    if (validateForm()) {
        saveInvoice('Draft');
    }
});

// Modified saveInvoice to accept status parameter
function saveInvoice(status = 'Posted') {
    const saveBtn = document.getElementById('saveBtn');
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    
    saveBtn.disabled = true;
    saveDraftBtn.disabled = true;
    
    if (status === 'Draft') {
        saveDraftBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    } else {
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    const formData = {
        companyId: document.getElementById('company').value,
        saleDate: document.getElementById('saleDate').value,
        customerId: document.getElementById('customerCode').value,
        branchId: document.getElementById('branch').value,
        currencyId: document.getElementById('currency').value,
        previousBalance: parseFloat(document.getElementById('previousBalance')?.value) || 0,
        salesOfficerId: document.getElementById('salesOfficer')?.value || null,
        supplierManId: document.getElementById('supplierMan')?.value || null,
        biltyNo: document.getElementById('biltyNo')?.value || null,
        transportName: document.getElementById('transportName')?.value || null,
        totalBill: parseFloat(document.getElementById('totalBill').textContent),
        totalDiscountPercent: parseFloat(document.getElementById('totalDiscountPercent')?.value) || 0,
        totalDiscountAmount: parseFloat(document.getElementById('totalDiscountAmount')?.value),
        netAmount: parseFloat(document.getElementById('netAmount').textContent),
        paymentMethod: document.getElementById('paymentMethod')?.value,
        bankAccountId: document.getElementById('bankAccount')?.value || null,
        amountPaid: parseFloat(document.getElementById('amountPaid')?.value) || 0,
        remainingBalance: parseFloat(document.getElementById('remainingBalance')?.value) || 0,
        remarks: document.getElementById('remarks')?.value,
        status: status,
        items: []
    };
    
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const isEditMode = editId !== null;
    
    if (isEditMode) {
        formData.invoice_id = editId;
    }

    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        
        const item = {
            productId: row.cells[1].querySelector('.item-code').value,
            uomId: row.cells[2].querySelector('select').value,
            quantity: parseFloat(row.cells[3].querySelector('input').value),
            pcs: parseFloat(row.cells[4].querySelector('input').value) || 0,
            ctn: parseFloat(row.cells[5].querySelector('input').value) || 0,
            dz: parseFloat(row.cells[6].querySelector('input').value) || 0,
            salePrice: parseFloat(row.cells[7].querySelector('input').value),
            grossAmount: parseFloat(row.cells[8].querySelector('input').value),
            discountPercent: parseFloat(row.cells[9].querySelector('input').value) || 0,
            discountAmount: parseFloat(row.cells[10].querySelector('input').value),
            tradeOfferPercent: parseFloat(row.cells[11].querySelector('input').value) || 0,
            tradeOfferAmount: parseFloat(row.cells[12].querySelector('input').value) || 0,
            gstPercent: parseFloat(row.cells[13].querySelector('input').value) || 0,
            gstAmount: parseFloat(row.cells[14].querySelector('input').value) || 0,
            focQty: parseFloat(row.cells[15].querySelector('input').value) || 0,
            netAmount: parseFloat(row.cells[16].querySelector('input').value),
            parentRowId: null
        };
        formData.items.push(item);
        
        if (row.dataset.childProducts) {
            const children = JSON.parse(row.dataset.childProducts);
            const parentIndex = formData.items.length;
            children.forEach(child => {
                formData.items.push({
                    productId: child.id,
                    uomId: child.unitId,
                    quantity: child.qty,
                    salePrice: 0,
                    grossAmount: 0,
                    discountPercent: 0,
                    discountAmount: 0,
                    tradeOfferPercent: 0,
                    tradeOfferAmount: 0,
                    gstPercent: 0,
                    gstAmount: 0,
                    focQty: 0,
                    netAmount: 0,
                    parentRowId: parentIndex
                });
            });
        }
    }

    const apiUrl = isEditMode ? 
        '../../../../server/api/sale/sale_order/order-edit.php' : 
        '../../../../server/api/sale/sale_order/order-add.php';
    const method = isEditMode ? 'PUT' : 'POST';
    
    fetch(apiUrl, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (status === 'Draft') {
                alert('Invoice saved as draft successfully!');
                window.location.href = 'order-add.php';
            } else {
                window.lastInvoiceId = data.invoice_id;
                document.getElementById('successModal').style.display = 'flex';
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error saving invoice: ' + error.message);
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveDraftBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Invoice';
        saveDraftBtn.innerHTML = '<i class="fas fa-file"></i> Save as Draft';
    });
}



// Invoice Settings button
document.getElementById('invoiceSettingsBtn').addEventListener('click', function() {
    loadInvoiceSettings();
    document.getElementById('invoiceSettingsModal').style.display = 'flex';
});

// Close Invoice Settings
document.getElementById('closeInvoiceSettingsBtn').addEventListener('click', function() {
    document.getElementById('invoiceSettingsModal').style.display = 'none';
});

// Save Invoice Settings
document.getElementById('saveInvoiceSettingsBtn').addEventListener('click', function() {
    saveInvoiceSettings();
    document.getElementById('invoiceSettingsModal').style.display = 'none';
    
    // Apply settings immediately
    applyInvoiceSettings();
});

// Hide invoice settings modal when clicking outside
document.getElementById('invoiceSettingsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

// Load invoice settings from localStorage
function loadInvoiceSettings() {
    document.getElementById('enableTradeOfferDiscount').checked = localStorage.getItem('enableTradeOfferDiscount') === 'true';
    document.getElementById('enableTradeOfferAmount').checked = localStorage.getItem('enableTradeOfferAmount') === 'true';
    document.getElementById('enableFOC').checked = localStorage.getItem('enableFOC') === 'true';
    document.getElementById('enableTaxation').checked = localStorage.getItem('enableTaxation') === 'true';
    document.getElementById('enableCashDiscountPercent').checked = localStorage.getItem('enableCashDiscountPercent') === 'true';
    document.getElementById('enableCashDiscountAmount').checked = localStorage.getItem('enableCashDiscountAmount') === 'true';
    document.getElementById('enableInvoiceCashDiscountPercent').checked = localStorage.getItem('enableInvoiceCashDiscountPercent') === 'true';
    document.getElementById('enableInvoiceCashDiscountAmount').checked = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';
    document.getElementById('enableShippingFees').checked = localStorage.getItem('enableShippingFees') === 'true';
    document.getElementById('enableCtn').checked = localStorage.getItem('enableCtn') === 'true';
    document.getElementById('enableDz').checked = localStorage.getItem('enableDz') === 'true';
    document.getElementById('enablePcs').checked = localStorage.getItem('enablePcs') === 'true';
    
    const priceSource = localStorage.getItem('priceSource') || 'trade_price';
    if (priceSource === 'mrp') {
        document.getElementById('useMRP').checked = true;
    } else {
        document.getElementById('useTP').checked = true;
    }
    
    const childDisplay = localStorage.getItem('childDisplay') || 'separate';
    if (childDisplay === 'inline') {
        document.getElementById('inlineChild').checked = true;
    } else {
        document.getElementById('separateChild').checked = true;
    }
}

// Save invoice settings to localStorage
function saveInvoiceSettings() {
    localStorage.setItem('enableTradeOfferDiscount', document.getElementById('enableTradeOfferDiscount').checked);
    localStorage.setItem('enableTradeOfferAmount', document.getElementById('enableTradeOfferAmount').checked);
    localStorage.setItem('enableFOC', document.getElementById('enableFOC').checked);
    localStorage.setItem('enableTaxation', document.getElementById('enableTaxation').checked);
    localStorage.setItem('enableCashDiscountPercent', document.getElementById('enableCashDiscountPercent').checked);
    localStorage.setItem('enableCashDiscountAmount', document.getElementById('enableCashDiscountAmount').checked);
    localStorage.setItem('enableInvoiceCashDiscountPercent', document.getElementById('enableInvoiceCashDiscountPercent').checked);
    localStorage.setItem('enableInvoiceCashDiscountAmount', document.getElementById('enableInvoiceCashDiscountAmount').checked);
    localStorage.setItem('enableShippingFees', document.getElementById('enableShippingFees').checked);
    localStorage.setItem('enableCtn', document.getElementById('enableCtn').checked);
    localStorage.setItem('enableDz', document.getElementById('enableDz').checked);
    localStorage.setItem('enablePcs', document.getElementById('enablePcs').checked);
    
    const priceSource = document.querySelector('input[name="priceSource"]:checked')?.value || 'trade_price';
    localStorage.setItem('priceSource', priceSource);
    
    const childDisplay = document.querySelector('input[name="childDisplay"]:checked')?.value || 'separate';
    localStorage.setItem('childDisplay', childDisplay);
    
    alert('Invoice settings saved successfully!');
    loadProducts();
}

// Apply invoice settings to show/hide columns
function applyInvoiceSettings() {
    const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
    const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
    const enableTradeOfferDiscount = localStorage.getItem('enableTradeOfferDiscount') === 'true';
    const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
    const enableFOC = localStorage.getItem('enableFOC') === 'true';
    const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
    const enableInvoiceCashDiscountPercent = localStorage.getItem('enableInvoiceCashDiscountPercent') === 'true';
    const enableInvoiceCashDiscountAmount = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';
    const enableShippingFees = localStorage.getItem('enableShippingFees') === 'true';
    const enableCtn = localStorage.getItem('enableCtn') === 'true';
    const enableDz = localStorage.getItem('enableDz') === 'true';
    const enablePcs = localStorage.getItem('enablePcs') === 'true';
    
    // Get table
    const table = document.getElementById('itemsTable');
    const headerRow = table.querySelector('thead tr');
    const footerRow = table.querySelector('tfoot tr');
    
    // Column indices (0-based)
    const PCS_COL = 4;
    const CTN_COL = 5;
    const DZ_COL = 6;
    const DISC_PERCENT_COL = 9;
    const DISC_AMOUNT_COL = 10;
    const TO_PERCENT_COL = 11;
    const TO_AMOUNT_COL = 12;
    const GST_PERCENT_COL = 13;
    const GST_AMOUNT_COL = 14;
    const FOC_COL = 15;
    
    // Hide/show header columns
    headerRow.cells[PCS_COL].style.display = enablePcs ? '' : 'none';
    headerRow.cells[CTN_COL].style.display = enableCtn ? '' : 'none';
    headerRow.cells[DZ_COL].style.display = enableDz ? '' : 'none';
    headerRow.cells[DISC_PERCENT_COL].style.display = enableCashDiscountPercent ? '' : 'none';
    headerRow.cells[DISC_AMOUNT_COL].style.display = enableCashDiscountAmount ? '' : 'none';
    headerRow.cells[TO_PERCENT_COL].style.display = enableTradeOfferDiscount ? '' : 'none';
    headerRow.cells[TO_AMOUNT_COL].style.display = enableTradeOfferAmount ? '' : 'none';
    headerRow.cells[GST_PERCENT_COL].style.display = enableTaxation ? '' : 'none';
    headerRow.cells[GST_AMOUNT_COL].style.display = enableTaxation ? '' : 'none';
    headerRow.cells[FOC_COL].style.display = enableFOC ? '' : 'none';
    
    // Hide/show footer columns
    footerRow.cells[PCS_COL].style.display = enablePcs ? '' : 'none';
    footerRow.cells[CTN_COL].style.display = enableCtn ? '' : 'none';
    footerRow.cells[DZ_COL].style.display = enableDz ? '' : 'none';
    footerRow.cells[DISC_PERCENT_COL].style.display = enableCashDiscountPercent ? '' : 'none';
    footerRow.cells[DISC_AMOUNT_COL].style.display = enableCashDiscountAmount ? '' : 'none';
    footerRow.cells[TO_PERCENT_COL].style.display = enableTradeOfferDiscount ? '' : 'none';
    footerRow.cells[TO_AMOUNT_COL].style.display = enableTradeOfferAmount ? '' : 'none';
    footerRow.cells[GST_PERCENT_COL].style.display = enableTaxation ? '' : 'none';
    footerRow.cells[GST_AMOUNT_COL].style.display = enableTaxation ? '' : 'none';
    footerRow.cells[FOC_COL].style.display = enableFOC ? '' : 'none';
    
    // Hide/show body columns for existing rows
    const itemsTable = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const rows = itemsTable.rows;
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].cells[PCS_COL]) rows[i].cells[PCS_COL].style.display = enablePcs ? '' : 'none';
        if (rows[i].cells[CTN_COL]) rows[i].cells[CTN_COL].style.display = enableCtn ? '' : 'none';
        if (rows[i].cells[DZ_COL]) rows[i].cells[DZ_COL].style.display = enableDz ? '' : 'none';
        if (rows[i].cells[DISC_PERCENT_COL]) rows[i].cells[DISC_PERCENT_COL].style.display = enableCashDiscountPercent ? '' : 'none';
        if (rows[i].cells[DISC_AMOUNT_COL]) rows[i].cells[DISC_AMOUNT_COL].style.display = enableCashDiscountAmount ? '' : 'none';
        if (rows[i].cells[TO_PERCENT_COL]) rows[i].cells[TO_PERCENT_COL].style.display = enableTradeOfferDiscount ? '' : 'none';
        if (rows[i].cells[TO_AMOUNT_COL]) rows[i].cells[TO_AMOUNT_COL].style.display = enableTradeOfferAmount ? '' : 'none';
        if (rows[i].cells[GST_PERCENT_COL]) rows[i].cells[GST_PERCENT_COL].style.display = enableTaxation ? '' : 'none';
        if (rows[i].cells[GST_AMOUNT_COL]) rows[i].cells[GST_AMOUNT_COL].style.display = enableTaxation ? '' : 'none';
        if (rows[i].cells[FOC_COL]) rows[i].cells[FOC_COL].style.display = enableFOC ? '' : 'none';
    }
    
    // Hide/show invoice summary fields
    const totalDiscountPercentItem = document.getElementById('totalDiscountPercent')?.closest('.summary-item');
    const totalDiscountAmountItem = document.getElementById('totalDiscountAmount')?.closest('.summary-item');
    const totalGstPercentItem = document.getElementById('totalGstPercent')?.closest('.summary-item');
    const totalGstAmountItem = document.getElementById('totalGstAmountSummary')?.closest('.summary-item');
    const shippingFeesItem = document.getElementById('shippingFees')?.closest('.summary-item');
    
    if (totalDiscountPercentItem) totalDiscountPercentItem.style.display = enableInvoiceCashDiscountPercent ? '' : 'none';
    if (totalDiscountAmountItem) totalDiscountAmountItem.style.display = enableInvoiceCashDiscountAmount ? '' : 'none';
    if (totalGstPercentItem) totalGstPercentItem.style.display = enableTaxation ? '' : 'none';
    if (totalGstAmountItem) totalGstAmountItem.style.display = enableTaxation ? '' : 'none';
    if (shippingFeesItem) shippingFeesItem.style.display = enableShippingFees ? '' : 'none';
}

function applyColumnVisibility(settings) {
    document.getElementById('tradeOfferDiscountHeader').style.display = settings.enableTradeOfferDiscount ? '' : 'none';
    document.getElementById('focQtyHeader').style.display = settings.enableFOC ? '' : 'none';
    document.getElementById('totalTradeOfferDiscount').style.display = settings.enableTradeOfferDiscount ? '' : 'none';
    document.getElementById('totalFocQty').style.display = settings.enableFOC ? '' : 'none';
    
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].cells[8]) rows[i].cells[8].style.display = settings.enableTradeOfferDiscount ? '' : 'none';
        if (rows[i].cells[9]) rows[i].cells[9].style.display = settings.enableFOC ? '' : 'none';
    }
}

function updateInvoiceSummaryFromGstAmount() {
    let totalBill = 0;
    const rows = itemsTable.rows;

    for (let i = 0; i < rows.length; i++) {
        const netAmountInput = rows[i].cells[13].querySelector('input');
        if (netAmountInput) {
            totalBill += parseFloat(netAmountInput.value) || 0;
        }
    }

    document.getElementById('totalBill').textContent = totalBill.toFixed(2);

    const discountAmount = parseFloat(document.getElementById('totalDiscountAmount').value) || 0;
    const afterDiscount = totalBill - discountAmount;
    const gstAmount = parseFloat(document.getElementById('totalGstAmountSummary').value) || 0;
    const gstPercent = afterDiscount > 0 ? (gstAmount / afterDiscount) * 100 : 0;
    const shippingFees = parseFloat(document.getElementById('shippingFees').value) || 0;
    const netAmount = afterDiscount + gstAmount + shippingFees;

    document.getElementById('totalGstPercent').value = gstPercent.toFixed(2);
    document.getElementById('netAmount').textContent = netAmount.toFixed(2);
    document.getElementById('amountPaid').value = netAmount.toFixed(2);
    
    updateRemainingBalance();
}

// Handle UOM change for price conversion
function handleUOMChange(row, unitSelect) {
    const oldUnitId = unitSelect.dataset.previousValue || unitSelect.value;
    const newUnitId = unitSelect.value;
    
    // Store current value for next change
    unitSelect.dataset.previousValue = newUnitId;
    
    // Only convert if both units are set and different
    if (!oldUnitId || !newUnitId || oldUnitId === newUnitId) return;
    
    const priceInput = row.cells[4].querySelector('input');
    const currentPrice = parseFloat(priceInput.value) || 0;
    
    if (currentPrice === 0) return;
    
    // Get conversion factors
    const oldConversion = getConversionFactor(oldUnitId, row);
    const newConversion = getConversionFactor(newUnitId, row);
    
    if (oldConversion && newConversion) {
        // Convert price: new_price = old_price * (old_conversion / new_conversion)
        const newPrice = currentPrice * (oldConversion / newConversion);
        priceInput.value = newPrice.toFixed(4);
        
        // Recalculate row
        priceInput.dispatchEvent(new Event('input'));
    }
}

// Get conversion factor for UOM
function getConversionFactor(unitId, row) {
    const id = parseInt(unitId);
    
    // Piece (id: 9) - base unit
    if (id === 9) return 1;
    
    // Dozen (id: 10) - 12 pieces
    if (id === 10) return 12;
    
    // Carton (id: 16) - get from product's carton_conversion
    if (id === 16) {
        const cartonConversion = parseInt(row.dataset.cartonConversion) || 0;
        return cartonConversion > 0 ? cartonConversion : null;
    }
    
    // Other units - no conversion
    return null;
}


// Overlay functions
function openOverlay(url) {
    const modal = document.getElementById('overlayModal');
    const iframe = document.getElementById('overlayIframe');
    iframe.src = url;
    modal.style.display = 'flex';
}

function closeOverlay() {
    const modal = document.getElementById('overlayModal');
    const iframe = document.getElementById('overlayIframe');
    modal.style.display = 'none';
    iframe.src = '';
    
    // Reload data after closing overlay
    loadCustomers();
    loadProducts();
}

// Listen for messages from iframe
window.addEventListener('message', function(event) {
    if (event.data === 'closeOverlay') {
        closeOverlay();
    }
});

// Load price history for all rows
function loadPriceHistoryForAllRows() {
    const itemsTable = document.getElementById('itemsTable')?.getElementsByTagName('tbody')[0];
    if (!itemsTable) return;
    
    const rows = itemsTable.rows;
    for (let i = 0; i < rows.length; i++) {
        const codeInput = rows[i].cells[1]?.querySelector('.item-code');
        if (codeInput && codeInput.value) {
            loadPriceHistory(codeInput.value);
            break;
        }
    }
}

// Load price history
async function loadPriceHistory(productId) {
    const customerId = document.getElementById('customerCode').value;
    if (!customerId || !productId) {
        document.getElementById('priceHistoryContainer').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-price-history.php?customer_id=${customerId}&product_id=${productId}`);
        const data = await response.json();
        
        if (data.success && data.history.length > 0) {
            const content = data.history.map((item, index) => {
                const date = new Date(item.sale_date).toLocaleDateString();
                return `<div>${index + 1}. ${date} - ${item.currency_symbol}${parseFloat(item.sale_price).toFixed(2)} (Qty: ${item.quantity})</div>`;
            }).join('');
            
            document.getElementById('priceHistoryContent').innerHTML = content;
            document.getElementById('priceHistoryContainer').style.display = 'block';
        } else {
            document.getElementById('priceHistoryContainer').style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading price history:', error);
    }
}

// Load product stock
async function loadProductStock(productId) {
    const branchId = document.getElementById('branch').value;
    const stockContainer = document.getElementById('stockContainer');
    if (!productId || !stockContainer) {
        if (stockContainer) stockContainer.style.display = 'none';
        return;
    }
    
    try {
        const url = branchId 
            ? `../../../../server/api/sale/pos_invoice/get-product-stock.php?product_id=${productId}&branch_id=${branchId}`
            : `../../../../server/api/sale/pos_invoice/get-product-stock.php?product_id=${productId}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            const stock = parseFloat(data.stock);
            const stockColor = stock > 0 ? 'var(--success)' : 'var(--error)';
            document.getElementById('stockContent').innerHTML = `<div style="color: ${stockColor}; font-weight: 600;">${stock.toFixed(2)} units</div>`;
            document.getElementById('stockContainer').style.display = 'block';
        } else {
            document.getElementById('stockContainer').style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading stock:', error);
    }
}



// Field Settings
const fieldSettingsBtn = document.getElementById('fieldSettingsBtn');
if (fieldSettingsBtn) {
    fieldSettingsBtn.addEventListener('click', function() {
        loadFieldSettings();
        document.getElementById('fieldSettingsModal').style.display = 'flex';
    });
}

const closeFieldSettingsBtn = document.getElementById('closeFieldSettingsBtn');
if (closeFieldSettingsBtn) {
    closeFieldSettingsBtn.addEventListener('click', function() {
        document.getElementById('fieldSettingsModal').style.display = 'none';
    });
}

const saveFieldSettingsBtn = document.getElementById('saveFieldSettingsBtn');
if (saveFieldSettingsBtn) {
    saveFieldSettingsBtn.addEventListener('click', function() {
        saveFieldSettings();
        document.getElementById('fieldSettingsModal').style.display = 'none';
        applyFieldVisibility();
    });
}

const fieldSettingsModal = document.getElementById('fieldSettingsModal');
if (fieldSettingsModal) {
    fieldSettingsModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
}

function loadFieldSettings() {
    document.getElementById('hideBillNo').checked = localStorage.getItem('hideBillNo') === 'true';
    document.getElementById('hidePreviousBalance').checked = localStorage.getItem('hidePreviousBalance') === 'true';
    document.getElementById('hideCustomerAddress').checked = localStorage.getItem('hideCustomerAddress') === 'true';
    document.getElementById('hideBranch').checked = localStorage.getItem('hideBranch') === 'true';
    document.getElementById('hideCurrency').checked = localStorage.getItem('hideCurrency') === 'true';
    document.getElementById('hideSalesOfficer').checked = localStorage.getItem('hideSalesOfficer') === 'true';
    document.getElementById('hideBiltyNo').checked = localStorage.getItem('hideBiltyNo') === 'true';
    document.getElementById('hideTransportName').checked = localStorage.getItem('hideTransportName') === 'true';
    document.getElementById('hideRemarks').checked = localStorage.getItem('hideRemarks') === 'true';
}

function saveFieldSettings() {
    localStorage.setItem('hideBillNo', document.getElementById('hideBillNo').checked);
    localStorage.setItem('hidePreviousBalance', document.getElementById('hidePreviousBalance').checked);
    localStorage.setItem('hideCustomerAddress', document.getElementById('hideCustomerAddress').checked);
    localStorage.setItem('hideBranch', document.getElementById('hideBranch').checked);
    localStorage.setItem('hideCurrency', document.getElementById('hideCurrency').checked);
    localStorage.setItem('hideSalesOfficer', document.getElementById('hideSalesOfficer').checked);
    localStorage.setItem('hideBiltyNo', document.getElementById('hideBiltyNo').checked);
    localStorage.setItem('hideTransportName', document.getElementById('hideTransportName').checked);
    localStorage.setItem('hideRemarks', document.getElementById('hideRemarks').checked);
    alert('Field settings saved successfully!');
}

function applyFieldVisibility() {
    const mode = document.querySelector('input[name="invoiceMode"]:checked').value;
    
    if (mode === 'standard') {
        // Show all fields in standard mode
        document.getElementById('billNoGroup').style.display = '';
        document.getElementById('previousBalanceGroup').style.display = '';
        document.getElementById('customerAddressGroup').style.display = '';
        document.getElementById('branchGroup').style.display = '';
        document.getElementById('currencyGroup').style.display = '';
        document.getElementById('salesOfficerGroup').style.display = '';
        document.getElementById('biltyNoGroup').style.display = '';
        document.getElementById('transportNameGroup').style.display = '';
        document.getElementById('remarksGroup').style.display = '';
    } else {
        // Apply POS mode settings
        document.getElementById('billNoGroup').style.display = localStorage.getItem('hideBillNo') === 'true' ? 'none' : '';
        document.getElementById('previousBalanceGroup').style.display = localStorage.getItem('hidePreviousBalance') === 'true' ? 'none' : '';
        document.getElementById('customerAddressGroup').style.display = localStorage.getItem('hideCustomerAddress') === 'true' ? 'none' : '';
        document.getElementById('branchGroup').style.display = localStorage.getItem('hideBranch') === 'true' ? 'none' : '';
        document.getElementById('currencyGroup').style.display = localStorage.getItem('hideCurrency') === 'true' ? 'none' : '';
        document.getElementById('salesOfficerGroup').style.display = localStorage.getItem('hideSalesOfficer') === 'true' ? 'none' : '';
        document.getElementById('biltyNoGroup').style.display = localStorage.getItem('hideBiltyNo') === 'true' ? 'none' : '';
        document.getElementById('transportNameGroup').style.display = localStorage.getItem('hideTransportName') === 'true' ? 'none' : '';
        document.getElementById('remarksGroup').style.display = localStorage.getItem('hideRemarks') === 'true' ? 'none' : '';
    }
}

// Mode change listeners
const standardMode = document.getElementById('standardMode');
if (standardMode) {
    standardMode.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('invoiceMode', 'standard');
            applyFieldVisibility();
        }
    });
}

const posMode = document.getElementById('posMode');
if (posMode) {
    posMode.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('invoiceMode', 'pos');
            applyFieldVisibility();
        }
    });
}

// Load saved mode on page load
const savedMode = localStorage.getItem('invoiceMode') || 'standard';
const posModeRadio = document.getElementById('posMode');
const standardModeRadio = document.getElementById('standardMode');

if (posModeRadio && standardModeRadio) {
    if (savedMode === 'pos') {
        posModeRadio.checked = true;
    } else {
        standardModeRadio.checked = true;
    }
    applyFieldVisibility();
}


// Debounce utility for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Keyboard shortcuts for speed
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S to save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('saveBtn').click();
    }
    
    // Ctrl/Cmd + D to save as draft
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        document.getElementById('saveDraftBtn').click();
    }
    
    // Ctrl/Cmd + N to add new row
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        document.getElementById('addRowBtn').click();
    }
    
    // F2 to focus customer search
    if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('customerCodeSearch').focus();
    }
});

// Preload next invoice number for instant display
async function preloadNextInvoiceNumber() {
    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/get-next-invoice-number.php');
        const data = await response.json();
        if (data.success && data.nextNumber) {
            document.getElementById('billNo').value = data.nextNumber;
        }
    } catch (error) {
        console.error('Error preloading invoice number:', error);
    }
}

// Call on page load if not in edit mode
if (!new URLSearchParams(window.location.search).get('edit')) {
    preloadNextInvoiceNumber();
}


// Compact mode toggle for faster visual scanning
let compactMode = localStorage.getItem('compactMode') === 'true';

function toggleCompactMode() {
    compactMode = !compactMode;
    localStorage.setItem('compactMode', compactMode);
    applyCompactMode();
}

function applyCompactMode() {
    const table = document.getElementById('itemsTable');
    if (compactMode) {
        table.style.fontSize = '11px';
        document.querySelectorAll('.table-input').forEach(input => {
            input.style.padding = '4px 6px';
            input.style.height = '28px';
        });
        document.querySelector('.card').style.padding = '16px';
    } else {
        table.style.fontSize = '';
        document.querySelectorAll('.table-input').forEach(input => {
            input.style.padding = '';
            input.style.height = '';
        });
        document.querySelector('.card').style.padding = '';
    }
}

// Apply on load
applyCompactMode();

// Barcode scanner support - auto-submit on Enter
document.addEventListener('keypress', function(e) {
    const activeElement = document.activeElement;
    const isProductSearch = activeElement && activeElement.classList.contains('search-input') && 
                           activeElement.closest('.searchable-dropdown');
    
    if (isProductSearch && e.key === 'Enter') {
        // Barcode scanned, auto-select first match
        const dropdown = activeElement.dropdownOptions || activeElement.closest('.searchable-dropdown').querySelector('.dropdown-options');
        if (dropdown) {
            const firstVisible = Array.from(dropdown.getElementsByClassName('dropdown-option'))
                .find(opt => opt.style.display !== 'none');
            if (firstVisible) {
                firstVisible.click();
            }
        }
    }
});

// Quick customer selection with number keys (1-9 for recent customers)
let recentCustomers = JSON.parse(localStorage.getItem('recentCustomers') || '[]');

function addRecentCustomer(customerId, customerName) {
    recentCustomers = recentCustomers.filter(c => c.id !== customerId);
    recentCustomers.unshift({ id: customerId, name: customerName });
    recentCustomers = recentCustomers.slice(0, 9);
    localStorage.setItem('recentCustomers', JSON.stringify(recentCustomers));
}

// Alt + Number for quick customer selection
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key >= '1' && e.key <= '9') {
        e.preventDefault();
        const index = parseInt(e.key) - 1;
        if (recentCustomers[index]) {
            const customer = recentCustomers[index];
            document.getElementById('customerCodeSearch').value = customer.name;
            document.getElementById('customerCode').value = customer.id;
            // Trigger customer selection logic
            const customerOption = document.querySelector(`#customerCodeOptions [data-value="${customer.id}"]`);
            if (customerOption) {
                customerOption.click();
            }
        }
    }
    
    // Ctrl + P for compact mode toggle
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        toggleCompactMode();
    }
});

// Save recent customer on invoice save
const originalSaveInvoice = window.saveInvoice;
window.saveInvoice = function(status) {
    const customerId = document.getElementById('customerCode').value;
    const customerName = document.getElementById('customerCodeSearch').value;
    if (customerId && customerName) {
        addRecentCustomer(customerId, customerName);
    }
    if (originalSaveInvoice) {
        originalSaveInvoice(status);
    }
};


// Auto-select customer with id 1 when POS mode is selected
(function() {
    const posModeRadio = document.getElementById('posMode');
    if (!posModeRadio) return;
    
    posModeRadio.addEventListener('change', function() {
        if (this.checked) {
            setTimeout(() => {
                const customerOption = document.querySelector('#customerCodeOptions [data-value="1"]');
                if (customerOption) {
                    customerOption.click();
                }
            }, 300);
        }
    });
    
    // Auto-select on page load if POS mode is already selected
    if (posModeRadio.checked) {
        setTimeout(() => {
            const customerOption = document.querySelector('#customerCodeOptions [data-value="1"]');
            if (customerOption) {
                customerOption.click();
            }
        }, 800);
    }
})();


// Parent-Child Product System
async function checkAndShowVariantsButton(row, productId) {
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-child-products.php?parent_id=${productId}`);
        const data = await response.json();
        if (data.success && data.children.length > 0) {
            const variantsBtn = row.cells[17].querySelector('.btn-secondary');
            if (variantsBtn) variantsBtn.style.display = '';
        }
    } catch (error) {
        console.error('Error checking variants:', error);
    }
}

function openVariantsModal(parentRow) {
    const productId = parentRow.dataset.productId;
    if (!productId) return;
    
    fetch(`../../../../server/api/sale/pos_invoice/get-child-products.php?parent_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.children.length > 0) {
                showVariantsModal(parentRow, data.children);
            } else {
                alert('No variants found for this product.');
            }
        })
        .catch(error => {
            console.error('Error loading variants:', error);
            alert('Error loading variants.');
        });
}

function showVariantsModal(parentRow, children) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    
    const childrenHtml = children.map(child => `
        <div style="display: flex; align-items: center; gap: 12px; padding: 8px; border-bottom: 1px solid var(--border-default);">
            <input type="checkbox" class="variant-checkbox" data-id="${child.id}" data-name="${child.name}" data-code="${child.code}" data-unit="${child.default_unit_id}" style="width: auto;">
            <span style="flex: 1;">${child.code} - ${child.name}</span>
            <input type="number" class="variant-qty table-input" data-id="${child.id}" min="0" step="0.01" value="0" placeholder="Qty" style="width: 80px;" disabled>
        </div>
    `).join('');
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <h3 class="modal-title">Select Product Variants</h3>
            <div style="margin: 16px 0;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="selectAllVariants" style="width: auto;">
                    <strong>Select All</strong>
                </label>
                <div id="variantsContainer">
                    ${childrenHtml}
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelVariantsBtn">Cancel</button>
                <button class="btn btn-primary" id="addVariantsBtn">Add Selected</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    modal.querySelectorAll('.variant-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const qtyInput = modal.querySelector(`.variant-qty[data-id="${this.dataset.id}"]`);
            qtyInput.disabled = !this.checked;
            if (this.checked && qtyInput.value == 0) qtyInput.value = 1;
        });
    });
    
    document.getElementById('selectAllVariants').addEventListener('change', function() {
        modal.querySelectorAll('.variant-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
            checkbox.dispatchEvent(new Event('change'));
        });
    });
    
    document.getElementById('cancelVariantsBtn').addEventListener('click', () => modal.remove());
    
    document.getElementById('addVariantsBtn').addEventListener('click', () => {
        const selected = [];
        modal.querySelectorAll('.variant-checkbox:checked').forEach(checkbox => {
            const qtyInput = modal.querySelector(`.variant-qty[data-id="${checkbox.dataset.id}"]`);
            const qty = parseFloat(qtyInput.value) || 0;
            if (qty > 0) {
                selected.push({
                    id: checkbox.dataset.id,
                    name: checkbox.dataset.name,
                    code: checkbox.dataset.code,
                    unitId: checkbox.dataset.unit,
                    qty: qty
                });
            }
        });
        
        if (selected.length > 0) {
            addChildRows(parentRow, selected);
            modal.remove();
        } else {
            alert('Please select at least one variant with quantity.');
        }
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

function addChildRows(parentRow, children) { const totalChildQty = children.reduce((sum, child) => sum + child.qty, 0); const parentQtyInput = parentRow.cells[3].querySelector('input'); parentQtyInput.value = totalChildQty.toFixed(2); parentQtyInput.dispatchEvent(new Event('input')); if (!parentRow.dataset.childProducts) parentRow.dataset.childProducts = JSON.stringify([]); const existingChildren = JSON.parse(parentRow.dataset.childProducts); children.forEach(child => existingChildren.push({id: child.id, code: child.code, name: child.name, unitId: child.unitId, qty: child.qty})); parentRow.dataset.childProducts = JSON.stringify(existingChildren); }

function updateParentQtyFromChildren(parentRow) {
    const parentRowIndex = parentRow.rowIndex - 1;
    const itemsTable = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const rows = itemsTable.rows;
    
    let totalChildQty = 0;
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.classList.contains('child-row') && row.dataset.parentRowIndex == parentRowIndex) {
            const qtyInput = row.cells[3].querySelector('input');
            totalChildQty += parseFloat(qtyInput.value) || 0;
        }
    }
    
    const parentQtyInput = parentRow.cells[3].querySelector('input');
    parentQtyInput.value = totalChildQty.toFixed(2);
    parentQtyInput.dispatchEvent(new Event('input'));
}

function updateSerialNumbers() {
    const itemsTable = document.getElementById('itemsTable')?.getElementsByTagName('tbody')[0];
    if (!itemsTable) return;
    
    const rows = itemsTable.rows;
    let parentCounter = 1;
    let childCounters = {};
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.classList.contains('child-row')) {
            const parentIdx = row.dataset.parentRowIndex;
            if (!childCounters[parentIdx]) childCounters[parentIdx] = 0;
            childCounters[parentIdx]++;
            const letter = String.fromCharCode(96 + childCounters[parentIdx]);
            row.cells[0].innerHTML = `<span style="margin-left: 20px;">↳ ${parentCounter - 1}${letter}</span>`;
        } else {
            row.cells[0].textContent = parentCounter;
            parentCounter++;
        }
    }
}




