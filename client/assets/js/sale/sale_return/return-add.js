// Global variables for products and UOM data
let productsData = [];
let uomData = [];
let customersData = [];
let branchesData = [];
let employeesData = [];
let currenciesData = [];
let bankAccountsData = [];
let subAccountsData = [];

// Cache for quick lookups
const dataCache = {
    products: new Map(),
    customers: new Map(),
    branches: new Map()
};

// Store carton conversion for products
const productCartonConversion = new Map();

document.addEventListener('DOMContentLoaded', function () {
    // Check permissions
    fetch('../../../../server/api/auth/check-permission.php?category=Sale&form_name=Sale Return')
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
    Promise.all([loadCustomers(), loadBranches(), loadProducts(), loadUOM(), loadCurrencies(), loadBankAccounts(), loadSaleInvoices(), loadEmployees(), loadCompanies()]).then(() => {
        initSearchableDropdown('customerCodeSearch', 'customerCodeOptions', 'customerCode');
        initSearchableDropdown('branchSearch', 'branchOptions', 'branch');
        initSearchableDropdown('saleInvoiceSearch', 'saleInvoiceOptions', 'saleInvoice');
        
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

    // Price type change event
    document.querySelectorAll('input[name="priceType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Clear all existing rows and add fresh one
            while (itemsTable.rows.length > 0) {
                itemsTable.deleteRow(0);
            }
            addRow();
        });
    });

    // Bulk status button event
    document.getElementById('bulkStatusBtn').addEventListener('click', function() {
        const status = confirm('Set all items to Damaged?\n\nOK = Damaged\nCancel = Sellable');
        const statusValue = status ? 'damaged' : 'sellable';
        const rows = itemsTable.rows;
        for (let i = 0; i < rows.length; i++) {
            const statusSelect = rows[i].cells[13]?.querySelector('select');
            if (statusSelect) statusSelect.value = statusValue;
        }
    });

    // Reset button event
    resetBtn.addEventListener('click', resetForm);

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!permissions.includes('Add') && !isEditMode) {
            alert('You do not have permission to add returns.');
            return;
        }
        if (!permissions.includes('Edit') && isEditMode) {
            alert('You do not have permission to edit returns.');
            return;
        }
        if (validateForm()) {
            saveInvoice('Posted');
        }
    });
    
    // Tab navigation from Save Invoice button to Customer Code
    saveBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('customerCodeSearch').focus();
        }
    });

    // Load customers from API
    async function loadCustomers() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-customers.php');
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
                    codeOption.setAttribute('data-supplier-man', customer.supplier_man_id || '');
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
            const response = await fetch('../../../../server/api/sale/sale_return/get-branches.php');
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
                    option.textContent = `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
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
            const response = await fetch('../../../../server/api/sale/sale_return/get-products.php');
            const data = await response.json();
            
            if (data.success) {
                productsData = data.products;
                // Build product cache for instant lookup
                data.products.forEach(product => {
                    dataCache.products.set(product.id, product);
                    productCartonConversion.set(product.id, product.carton_conversion || 1);
                });
            }
        } catch (error) {
            console.error('Error loading products:', error);
        }
    }

    // Load UOM from API
    async function loadUOM() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-uom.php');
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
            const response = await fetch('../../../../server/api/sale/sale_return/get-bank-accounts.php');
            const data = await response.json();
            
            if (data.success) {
                const bankAccountSelect = document.getElementById('bankAccount');
                bankAccountSelect.innerHTML = '<option value="">Select Bank Account</option>';
                
                data.accounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.account_title} - ${account.account_number}`;
                    bankAccountSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading bank accounts:', error);
        }
    }
    
    // Load employees from API
    async function loadEmployees() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-employees.php');
            const data = await response.json();
            
            if (data.success) {
                const salesOfficerSelect = document.getElementById('salesOfficer');
                const supplierManSelect = document.getElementById('supplierMan');
                salesOfficerSelect.innerHTML = '<option value="">Select Sales Officer</option>';
                supplierManSelect.innerHTML = '<option value="">Select Supplier Man</option>';
                
                data.employees.forEach(employee => {
                    const option1 = document.createElement('option');
                    option1.value = employee.id;
                    option1.textContent = `${employee.employee_id} - ${employee.full_name}`;
                    salesOfficerSelect.appendChild(option1);
                    
                    const option2 = document.createElement('option');
                    option2.value = employee.id;
                    option2.textContent = `${employee.employee_id} - ${employee.full_name}`;
                    supplierManSelect.appendChild(option2);
                });
            }
        } catch (error) {
            console.error('Error loading employees:', error);
        }
    }
    
    // Load companies from API
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-companies.php');
            const data = await response.json();
            
            if (data.success) {
                const companySelect = document.getElementById('company');
                companySelect.innerHTML = '<option value="">Select Company</option>';
                
                data.companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = `${company.company_code} - ${company.company_name}`;
                    companySelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }
    
    // Load sub-accounts for selected customer
    async function loadSubAccounts(customerId) {
        try {
            const response = await fetch(`../../../../server/api/sale/sale_return/get-sub-accounts.php?customer_id=${customerId}`);
            const data = await response.json();
            
            if (data.success) {
                const subAccountSelect = document.getElementById('subAccount');
                subAccountSelect.innerHTML = '<option value="">Select Sub Account</option>';
                
                data.sub_accounts.forEach(subAccount => {
                    const option = document.createElement('option');
                    option.value = subAccount.id;
                    option.textContent = subAccount.sub_account_name;
                    subAccountSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading sub-accounts:', error);
        }
    }
    
    // Load sale invoices from API
    async function loadSaleInvoices() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-sale-invoices.php');
            const data = await response.json();
            
            if (data.success) {
                const invoiceOptions = document.getElementById('saleInvoiceOptions');
                invoiceOptions.innerHTML = '';
                
                const fragment = document.createDocumentFragment();
                data.invoices.forEach(invoice => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', invoice.id);
                    const amount = `${invoice.currency_symbol}${parseFloat(invoice.net_amount).toFixed(2)}`;
                    option.textContent = `${invoice.bill_no} - ${invoice.customer_name} (${invoice.sale_date}) - ${amount}`;
                    fragment.appendChild(option);
                });
                invoiceOptions.appendChild(fragment);
            }
        } catch (error) {
            console.error('Error loading sale invoices:', error);
        }
    }
    
    // Load sale invoice data and populate form
    async function loadSaleInvoiceData(invoiceId) {
        try {
            const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${invoiceId}`);
            const data = await response.json();
            
            if (data.success) {
                const invoice = data.invoice;
                
                // Populate form fields
                document.getElementById('customerCodeSearch').value = `${invoice.customer_code} - ${invoice.customer_name}`;
                document.getElementById('customerCode').value = invoice.customer_id;
                const branchText = invoice.parent_branch_name ? 
                    `${invoice.branch_code || ''} - ${invoice.branch_name} (${invoice.branch_type}) - Parent: ${invoice.parent_branch_name}` : 
                    `${invoice.branch_code || ''} - ${invoice.branch_name} (${invoice.branch_type})`;
                document.getElementById('branchSearch').value = branchText;
                document.getElementById('branch').value = invoice.branch_id;
                document.getElementById('currency').value = invoice.currency_id;
                document.getElementById('previousBalance').value = invoice.previous_balance;
                
                // Set company if exists
                if (invoice.company_id) {
                    document.getElementById('company').value = invoice.company_id;
                }
                
                // Update customer address
                document.getElementById('customerAddress').textContent = invoice.customer_address || 'No address available';
                
                // Update currency symbols
                updateCurrencySymbols();
                
                // Clear existing items
                while (itemsTable.rows.length > 0) {
                    itemsTable.deleteRow(0);
                }
                
                // Load invoice items
                data.items.forEach(item => {
                    addRow();
                    const lastRow = itemsTable.rows[itemsTable.rows.length - 1];
                    
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
                    lastRow.cells[16].querySelector('select').value = item.stock_status || 'sellable';
                    lastRow.cells[17].querySelector('input').value = item.net_amount;
                });
                
                // Update summary
                document.getElementById('totalDiscountPercent').value = invoice.total_discount_percent;
                document.getElementById('totalDiscountAmount').value = invoice.total_discount_amount;
                updateInvoiceSummary();
            }
        } catch (error) {
            console.error('Error loading sale invoice:', error);
            alert('Error loading sale invoice data');
        }
    }

    
    // Load currencies from API
    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-currencies.php');
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
    
    // Update currency symbols in labels and values
    function updateCurrencySymbols() {
        const currencySelect = document.getElementById('currency');
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        const symbol = selectedOption ? selectedOption.getAttribute('data-symbol') : '';
        
        if (symbol) {
            // Update table headers
            document.getElementById('salePriceLabel').textContent = `Sale Price (${symbol})`;
            document.getElementById('grossAmountLabel').textContent = `Gross Amount (${symbol})`;
            document.getElementById('discountAmountLabel').textContent = `Discount Amount (${symbol})`;
            document.getElementById('netAmountLabel').textContent = `Net Amount (${symbol})`;
            
            // Update summary labels
            document.getElementById('totalBillLabel').textContent = `Total Bill (${symbol})`;
            document.getElementById('totalDiscountAmountLabel').textContent = `Discount Amount (${symbol})`;
            document.getElementById('netAmountSummaryLabel').textContent = `Net Amount (${symbol})`;
            document.getElementById('amountPaidLabel').textContent = `Amount Refunded (${symbol})`;
            document.getElementById('remainingBalanceLabel').textContent = `Remaining Balance (${symbol})`;
        }
    }
    
    // Add currency change event listener
    document.getElementById('currency').addEventListener('change', updateCurrencySymbols);
    
    // Load invoice data for editing
    async function loadInvoiceData(invoiceId) {
        try {
            const response = await fetch(`../../../../server/api/sale/sale_return/return-edit.php?id=${invoiceId}`);
            const data = await response.json();
            
            console.log('Invoice data:', data);
            
            if (data.success) {
                const invoice = data.invoice;
                
                // Populate form fields
                document.getElementById('saleDate').value = invoice.sale_date;
                document.getElementById('customerCodeSearch').value = invoice.customer_name;
                document.getElementById('customerCode').value = invoice.customer_id;
                document.getElementById('branchSearch').value = invoice.branch_name;
                document.getElementById('branch').value = invoice.branch_id;
                document.getElementById('currency').value = invoice.currency_id;
                document.getElementById('previousBalance').value = invoice.previous_balance;
                
                // Set company if exists
                if (invoice.company_id) {
                    document.getElementById('company').value = invoice.company_id;
                }
                
                // Set sales officer if exists
                if (invoice.sale_officer_id) {
                    document.getElementById('salesOfficer').value = invoice.sale_officer_id;
                }
                
                // Set supplier man if exists
                if (invoice.supplier_man_id) {
                    document.getElementById('supplierMan').value = invoice.supplier_man_id;
                }
                
                // Load sub-accounts and set value
                if (invoice.customer_id) {
                    await loadSubAccounts(invoice.customer_id);
                    if (invoice.sub_account_id) {
                        document.getElementById('subAccount').value = invoice.sub_account_id;
                    }
                }
                
                // Update customer address
                let addressText = invoice.customer_address || '';
                document.getElementById('customerAddress').textContent = addressText || 'No address available';
                
                // Update currency symbols
                updateCurrencySymbols();
                
                // Load invoice items
                data.items.forEach(item => {
                    addRow();
                    const lastRow = itemsTable.rows[itemsTable.rows.length - 1];
                    
                    // Populate item data
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
                    lastRow.cells[16].querySelector('select').value = item.stock_status || 'sellable';
                    lastRow.cells[17].querySelector('input').value = item.net_amount;
                });
                
                // Update summary
                document.getElementById('totalDiscountPercent').value = invoice.total_discount_percent;
                document.getElementById('totalDiscountAmount').value = invoice.total_discount_amount;
                document.getElementById('paymentMethod').value = invoice.payment_method || '';
                
                // Show bank account if payment method is bank_transfer
                if (invoice.payment_method === 'bank_transfer') {
                    document.getElementById('bankAccountContainer').style.display = 'flex';
                    document.getElementById('bankAccount').value = invoice.bank_account_id || '';
                }
                
                document.getElementById('amountPaid').value = invoice.amount_paid || 0;
                updateInvoiceSummary();
                
                // Update page title
                document.querySelector('.page-title').textContent = `Edit Sale Return - ${invoice.bill_no}`;
                
            } else {
                alert('Error loading return: ' + data.message);
                window.location.href = 'return-add.php';
            }
        } catch (error) {
            console.error('Error loading return:', error);
            alert('Error loading return data');
            window.location.href = 'return-add.php';
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
                    const prevBalanceEl = document.getElementById('previousBalance');
                    if (prevBalanceEl) {
                        prevBalanceEl.value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
                    }
                    
                    // Update customer address
                    const addressEl = document.getElementById('customerAddress');
                    if (addressEl) {
                        const address = e.target.getAttribute('data-address');
                        addressEl.textContent = address || 'No address available';
                    }
                    
                    // Auto-populate Supplier Man
                    const supplierManId = e.target.getAttribute('data-supplier-man');
                    const supplierManSelect = document.getElementById('supplierMan');
                    if (supplierManSelect && supplierManId && supplierManId !== 'null' && supplierManId !== '') {
                        supplierManSelect.value = supplierManId;
                    }
                    
                    // Load sub-accounts for selected customer
                    loadSubAccounts(value);
                    
                    // Load price history for all rows
                    loadPriceHistoryForAllRows();
                }
                
                // Save branch selection
                if (hiddenInputId === 'branch') {
                    localStorage.setItem('lastSelectedBranch', value);
                }
                
                // Load sale invoice data
                if (hiddenInputId === 'saleInvoice') {
                    loadSaleInvoiceData(value);
                }

                // Hide options
                optionsContainer.style.display = 'none';

                // Remove error state if any
                hiddenInput.classList.remove('error');
                const errorEl = document.getElementById(hiddenInputId + 'Error');
                if (errorEl) errorEl.style.display = 'none';
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
        const codeOptionsHtml = productsData.map(product => {
            const codes = [];
            if (product.qr_code) codes.push(product.qr_code);
            if (product.barcode) codes.push(product.barcode);
            const codeDisplay = codes.length > 0 ? ` (${codes.join(' - ')})` : '';
            const photoHtml = product.photo ? `<img src="../../../assets/uploads/products/${product.photo}" alt="${product.name}" style="width: 30px; height: 30px; object-fit: cover; margin-right: 8px; border-radius: 4px;">` : '';
            const priceType = document.querySelector('input[name="priceType"]:checked')?.value || 'mrp';
            const price = priceType === 'tp' ? product.trade_price : product.sale_price;
            return `<div class="dropdown-option" data-value="${product.id}" data-price="${price}" data-tp="${product.trade_price}" data-mrp="${product.sale_price}" data-unit="${product.default_unit_id}" style="display: flex; align-items: center;">${photoHtml}${product.code} - ${product.name}${codeDisplay}</div>`;
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

        // Qty (input - editable)
        const cell3 = row.insertCell(3);
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'table-input';
        qtyInput.min = '0';
        qtyInput.step = '0.01';
        qtyInput.required = true;
        qtyInput.value = '1';
        cell3.appendChild(qtyInput);

        // Pcs (input)
        const cell4Pcs = row.insertCell(4);
        const pcsInput = document.createElement('input');
        pcsInput.type = 'number';
        pcsInput.className = 'table-input';
        pcsInput.min = '0';
        pcsInput.step = '1';
        pcsInput.value = '0';
        pcsInput.addEventListener('input', () => calculateQtyFromUnits(row));
        cell4Pcs.appendChild(pcsInput);

        // Ctn (input)
        const cell5Ctn = row.insertCell(5);
        const ctnInput = document.createElement('input');
        ctnInput.type = 'number';
        ctnInput.className = 'table-input';
        ctnInput.min = '0';
        ctnInput.step = '1';
        ctnInput.value = '0';
        ctnInput.addEventListener('input', () => calculateQtyFromUnits(row));
        cell5Ctn.appendChild(ctnInput);

        // Dz (input)
        const cell6Dz = row.insertCell(6);
        const dzInput = document.createElement('input');
        dzInput.type = 'number';
        dzInput.className = 'table-input';
        dzInput.min = '0';
        dzInput.step = '1';
        dzInput.value = '0';
        dzInput.addEventListener('input', () => calculateQtyFromUnits(row));
        cell6Dz.appendChild(dzInput);

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
        
        // Inventory Status (dropdown)
        const cell16Status = row.insertCell(16);
        const statusSelect = document.createElement('select');
        statusSelect.className = 'table-input';
        statusSelect.innerHTML = `
            <option value="sellable" selected>Sellable</option>
            <option value="damaged">Damaged</option>
        `;
        statusSelect.tabIndex = -1;
        cell16Status.appendChild(statusSelect);
        
        // Apply settings to new row
        const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
        const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
        const enableTradeOfferDiscount = localStorage.getItem('enableTradeOfferDiscount') === 'true';
        const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC = localStorage.getItem('enableFOC') === 'true';
        const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
        const enablePcs = localStorage.getItem('enablePcs') === 'true';
        const enableCtn = localStorage.getItem('enableCtn') === 'true';
        const enableDz = localStorage.getItem('enableDz') === 'true';
        
        cell4Pcs.style.display = enablePcs ? '' : 'none';
        cell5Ctn.style.display = enableCtn ? '' : 'none';
        cell6Dz.style.display = enableDz ? '' : 'none';
        cell9.style.display = enableCashDiscountPercent ? '' : 'none';
        cell10.style.display = enableCashDiscountAmount ? '' : 'none';
        cell11.style.display = enableTradeOfferDiscount ? '' : 'none';
        cell12.style.display = enableTradeOfferAmount ? '' : 'none';
        cell13.style.display = enableTaxation ? '' : 'none';
        cell14.style.display = enableTaxation ? '' : 'none';
        cell15.style.display = enableFOC ? '' : 'none';

        // Net Amount (readonly)
        const cell17 = row.insertCell(17);
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
        
        cell17.appendChild(netAmount);

        // Actions (delete button)
        const cell18 = row.insertCell(18);
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-sm';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.title = 'Delete row';
        deleteBtn.tabIndex = -1;
        cell18.appendChild(deleteBtn);

    // Calculate Qty from Pcs, Ctn, Dz
    function calculateQtyFromUnits(row) {
        const productId = row.cells[1].querySelector('.item-code').value;
        const pcs = parseFloat(row.cells[4].querySelector('input').value) || 0;
        const ctn = parseFloat(row.cells[5].querySelector('input').value) || 0;
        const dz = parseFloat(row.cells[6].querySelector('input').value) || 0;
        
        const cartonConv = parseFloat(row.dataset.cartonConversion) || productCartonConversion.get(parseInt(productId)) || 1;
        const totalQty = pcs + (ctn * cartonConv) + (dz * 12);
        
        row.cells[3].querySelector('input').value = totalQty.toFixed(2);
        updateInvoiceSummary();
    }

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
                row.remove();
                updateSerialNumbers();
                updateInvoiceSummary();
            } else {
                alert('Cannot delete the only row. Add another row first.');
            }
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

                // Set the search input to display the selected option
                searchInput.value = displayText;
                hiddenInput.value = value;
                
                // Store carton conversion in row data
                const product = dataCache.products.get(parseInt(value));
                if (product) {
                    row.dataset.cartonConversion = product.carton_conversion || 1;
                }

                // Update related fields
                row.cells[7].querySelector('input').value = price;
                if (unitId) {
                    row.cells[2].querySelector('select').value = unitId;
                    row.cells[2].querySelector('select').dataset.previousValue = unitId;
                }
                
                // Auto-focus quantity for faster entry
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
                        row.cells[6].querySelector('input').value = product.default_discount;
                    }
                    if (enableTradeOfferDiscount && product.trade_offer_discount) {
                        row.cells[8].querySelector('input').value = product.trade_offer_discount;
                    }
                    if (enableFOC && product.default_foc) {
                        row.cells[12].querySelector('input').value = product.default_foc;
                    }
                    if (enableTaxation && product.sales_tax) {
                        row.cells[10].querySelector('input').value = product.sales_tax;
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
        // Use requestAnimationFrame for smooth UI updates
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
                const netAmountInput = rows[i].cells[17].querySelector('input');
                
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

            // Batch DOM updates
            document.getElementById('totalQty').textContent = totalQty.toFixed(2);
            document.getElementById('totalPcs').textContent = totalPcs.toFixed(0);
            document.getElementById('totalCtn').textContent = totalCtn.toFixed(0);
            document.getElementById('totalDz').textContent = totalDz.toFixed(0);
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
            
            updateRemainingBalance();
        });
    }
    
    // Update remaining balance
    function updateRemainingBalance() {
        const netAmount = parseFloat(document.getElementById('netAmount').textContent) || 0;
        const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
        const remainingBalance = netAmount - amountPaid;
        document.getElementById('remainingBalance').value = remainingBalance.toFixed(2);
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
    document.getElementById('amountPaid').addEventListener('input', updateRemainingBalance);
    
    // Add event listener for payment method to show/hide bank account
    document.getElementById('paymentMethod').addEventListener('change', function() {
        const bankAccountContainer = document.getElementById('bankAccountContainer');
        if (this.value === 'bank_transfer') {
            bankAccountContainer.style.display = 'flex';
        } else {
            bankAccountContainer.style.display = 'none';
            document.getElementById('bankAccount').value = '';
        }
    });
    
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
            { id: 'company', errorId: 'companyError' },
            { id: 'customerCode', errorId: 'customerCodeError' },
            { id: 'branch', errorId: 'branchError' },
            { id: 'currency', errorId: 'currencyError' }
        ];

        requiredFields.forEach(field => {
            const element = document.getElementById(field.id);
            if (!element.value) {
                element.classList.add('error');
                document.getElementById(field.errorId).style.display = 'block';
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
            const price = rows[i].cells[4].querySelector('input');

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


// Modified saveInvoice to accept status parameter
function saveInvoice(status = 'Posted') {
    const saveBtn = document.getElementById('saveBtn');
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    
    saveBtn.disabled = true;
    if (saveDraftBtn) saveDraftBtn.disabled = true;
    
    if (status === 'Draft') {
        saveDraftBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    } else {
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    const formData = {
        saleDate: document.getElementById('saleDate').value,
        companyId: document.getElementById('company').value || null,
        customerId: document.getElementById('customerCode').value,
        branchId: document.getElementById('branch').value,
        currencyId: document.getElementById('currency').value,
        previousBalance: parseFloat(document.getElementById('previousBalance').value) || 0,
        saleInvoiceId: document.getElementById('saleInvoice').value || null,
        totalBill: parseFloat(document.getElementById('totalBill').textContent),
        totalDiscountPercent: parseFloat(document.getElementById('totalDiscountPercent').value) || 0,
        totalDiscountAmount: parseFloat(document.getElementById('totalDiscountAmount').value),
        netAmount: parseFloat(document.getElementById('netAmount').textContent),
        paymentMethod: document.getElementById('paymentMethod').value,
        bankAccountId: document.getElementById('bankAccount').value || null,
        salesOfficerId: document.getElementById('salesOfficer').value || null,
        supplierManId: document.getElementById('supplierMan').value || null,
        subAccountId: document.getElementById('subAccount').value || null,
        amountPaid: parseFloat(document.getElementById('amountPaid').value) || 0,
        remainingBalance: parseFloat(document.getElementById('remainingBalance').value) || 0,
        remarks: document.getElementById('remarks').value,
        status: status,
        items: []
    };
    
    console.log('Sales Officer ID:', formData.salesOfficerId);
    
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
            salePrice: parseFloat(row.cells[7].querySelector('input').value),
            grossAmount: parseFloat(row.cells[8].querySelector('input').value),
            discountPercent: parseFloat(row.cells[9].querySelector('input').value) || 0,
            discountAmount: parseFloat(row.cells[10].querySelector('input').value),
            tradeOfferPercent: parseFloat(row.cells[11].querySelector('input').value) || 0,
            tradeOfferAmount: parseFloat(row.cells[12].querySelector('input').value) || 0,
            gstPercent: parseFloat(row.cells[13].querySelector('input').value) || 0,
            gstAmount: parseFloat(row.cells[14].querySelector('input').value) || 0,
            focQty: parseFloat(row.cells[15].querySelector('input').value) || 0,
            stockStatus: row.cells[16].querySelector('select').value,
            netAmount: parseFloat(row.cells[17].querySelector('input').value)
        };
        formData.items.push(item);
    }

    const apiUrl = isEditMode ? 
        '../../../../server/api/sale/sale_return/return-edit.php' : 
        '../../../../server/api/sale/sale_return/return-add.php';
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
                window.location.href = 'return-add.php';
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
        if (saveDraftBtn) saveDraftBtn.disabled = false;
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
    document.getElementById('enablePcs').checked = localStorage.getItem('enablePcs') === 'true';
    document.getElementById('enableCtn').checked = localStorage.getItem('enableCtn') === 'true';
    document.getElementById('enableDz').checked = localStorage.getItem('enableDz') === 'true';
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
    localStorage.setItem('enablePcs', document.getElementById('enablePcs').checked);
    localStorage.setItem('enableCtn', document.getElementById('enableCtn').checked);
    localStorage.setItem('enableDz', document.getElementById('enableDz').checked);
    alert('Invoice settings saved successfully!');
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
    const enablePcs = localStorage.getItem('enablePcs') === 'true';
    const enableCtn = localStorage.getItem('enableCtn') === 'true';
    const enableDz = localStorage.getItem('enableDz') === 'true';
    
    // Get table
    const table = document.getElementById('itemsTable');
    const headerRow = table.querySelector('thead tr');
    const footerRow = table.querySelector('tfoot tr');
    
    // Column indices (0-based)
    const QTY_COL = 3;
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
    const itemsTable = document.getElementById('itemsTable');
    if (!itemsTable) return;
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    const rows = tbody.rows;
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
        const response = await fetch(`../../../../server/api/sale/sale_return/get-price-history.php?customer_id=${customerId}&product_id=${productId}`);
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
    if (!productId) {
        document.getElementById('stockContainer').style.display = 'none';
        return;
    }
    
    try {
        const url = branchId 
            ? `../../../../server/api/sale/sale_return/get-product-stock.php?product_id=${productId}&branch_id=${branchId}`
            : `../../../../server/api/sale/sale_return/get-product-stock.php?product_id=${productId}`;
        
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


// Save sales officer selection
const salesOfficerEl = document.getElementById('salesOfficer');
if (salesOfficerEl) {
    salesOfficerEl.addEventListener('change', function() {
        if (this.value) {
            localStorage.setItem('lastSelectedSalesOfficer', this.value);
        }
    });
}


// Field Settings
const fieldSettingsBtnEl = document.getElementById('fieldSettingsBtn');
if (fieldSettingsBtnEl) {
    fieldSettingsBtnEl.addEventListener('click', function() {
        loadFieldSettings();
        document.getElementById('fieldSettingsModal').style.display = 'flex';
    });
}

const closeFieldSettingsBtnEl = document.getElementById('closeFieldSettingsBtn');
if (closeFieldSettingsBtnEl) {
    closeFieldSettingsBtnEl.addEventListener('click', function() {
        document.getElementById('fieldSettingsModal').style.display = 'none';
    });
}

const saveFieldSettingsBtnEl = document.getElementById('saveFieldSettingsBtn');
if (saveFieldSettingsBtnEl) {
    saveFieldSettingsBtnEl.addEventListener('click', function() {
        saveFieldSettings();
        document.getElementById('fieldSettingsModal').style.display = 'none';
        applyFieldVisibility();
    });
}

const fieldSettingsModalEl = document.getElementById('fieldSettingsModal');
if (fieldSettingsModalEl) {
    fieldSettingsModalEl.addEventListener('click', function(e) {
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
    const modeInput = document.querySelector('input[name="invoiceMode"]:checked');
    if (!modeInput) return;
    const mode = modeInput.value;
    
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
const standardModeEl = document.getElementById('standardMode');
if (standardModeEl) {
    standardModeEl.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('invoiceMode', 'standard');
            applyFieldVisibility();
        }
    });
}

const posModeEl = document.getElementById('posMode');
if (posModeEl) {
    posModeEl.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('invoiceMode', 'pos');
            applyFieldVisibility();
        }
    });
}

const savedMode = localStorage.getItem('invoiceMode') || 'standard';
if (savedMode === 'pos' && posModeEl) {
    posModeEl.checked = true;
} else if (standardModeEl) {
    standardModeEl.checked = true;
}
if (typeof applyFieldVisibility === 'function') {
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
        const response = await fetch('../../../../server/api/sale/sale_return/get-next-invoice-number.php');
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





