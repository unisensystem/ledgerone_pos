// Global variables for products and UOM data
let productsData = [];
let uomData = [];

document.addEventListener('DOMContentLoaded', function () {
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('purchaseDate').value = today;

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
    Promise.all([loadSuppliers(), loadBranches(), loadProducts(), loadUOM(), loadCurrencies(), loadCompanies(), loadPurchaseInvoices(), loadProjects()]).then(() => {
        initSearchableDropdown('supplierCodeSearch', 'supplierCodeOptions', 'supplierCode');
        initSearchableDropdown('branchSearch', 'branchOptions', 'branch');
        initSearchableDropdown('purchaseInvoiceSearch', 'purchaseInvoiceOptions', 'purchaseInvoice');
        initSearchableDropdown('subAccountSearch', 'subAccountOptions', 'subAccount');

        // Apply return settings
        applyreturnSettings();

        if (isEditMode) {
            loadreturnData(editId);
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Update Return';
        } else {
            // Add first row after data is loaded
            addRowDynamic();
        }
    });

    // Add row button event
    addRowBtn.addEventListener('click', addRowDynamic);

    // Add supplier button event
    document.getElementById('addSupplierBtn').addEventListener('click', function () {
        document.getElementById('supplierIframe').src = '../../customer_supplier/suppliers/supplier-add.php';
        document.getElementById('addSupplierModal').style.display = 'flex';
    });

    // Add product button event
    document.getElementById('addProductBtn').addEventListener('click', function () {
        document.getElementById('productIframe').src = '../../inventory/products/product-add.php';
        document.getElementById('addProductModal').style.display = 'flex';
    });

    // Close supplier modal
    document.getElementById('closeSupplierModalBtn').addEventListener('click', function () {
        document.getElementById('addSupplierModal').style.display = 'none';
        document.getElementById('supplierIframe').src = '';
        loadSuppliers();
    });

    // Close product modal
    document.getElementById('closeProductModalBtn').addEventListener('click', function () {
        document.getElementById('addProductModal').style.display = 'none';
        document.getElementById('productIframe').src = '';
        loadProducts();
    });

    // Close modals when clicking outside
    document.getElementById('addSupplierModal').addEventListener('click', function (e) {
        if (e.target === this) {
            document.getElementById('closeSupplierModalBtn').click();
        }
    });

    document.getElementById('addProductModal').addEventListener('click', function (e) {
        if (e.target === this) {
            document.getElementById('closeProductModalBtn').click();
        }
    });

    // Reset button event
    resetBtn.addEventListener('click', resetForm);

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (validateForm()) {
            savereturn();
        }
    });

    // Tab navigation from Save return button to Supplier Code
    saveBtn.addEventListener('keydown', function (e) {
        if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('supplierCodeSearch').focus();
        }
    });

    // Load suppliers from API
    async function loadSuppliers(companyId = null) {
        try {
            const url = companyId
                ? `../../../../server/api/purchase/purchase_return/get-suppliers.php?company_id=${companyId}`
                : '../../../../server/api/purchase/purchase_return/get-suppliers.php';
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                const codeOptions = document.getElementById('supplierCodeOptions');
                codeOptions.innerHTML = '';
                data.suppliers.forEach(supplier => {
                    const codeOption = document.createElement('div');
                    codeOption.className = 'dropdown-option';
                    codeOption.setAttribute('data-value', supplier.id);
                    codeOption.setAttribute('data-balance', supplier.current_balance);
                    codeOption.textContent = `${supplier.supplier_code} - ${supplier.supplier_name}`;
                    codeOptions.appendChild(codeOption);
                });
            }
        } catch (error) {
            console.error('Error loading suppliers:', error);
        }
    }

    // Load branches from API
    async function loadBranches(companyId = null) {
        try {
            const url = companyId
                ? `../../../../server/api/purchase/purchase_return/get-branches.php?company_id=${companyId}`
                : '../../../../server/api/purchase/purchase_return/get-branches.php';
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                const branchOptions = document.getElementById('branchOptions');
                branchOptions.innerHTML = '';
                data.branches.forEach(branch => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', branch.id);
                    const displayText = branch.parent_branch_name
                        ? `${branch.parent_branch_name} > ${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`
                        : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                    option.textContent = displayText;
                    branchOptions.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading branches:', error);
        }
    }



    // Load products from API
    async function loadProducts(companyId = null) {
        try {
            const url = companyId
                ? `../../../../server/api/purchase/purchase_return/get-products.php?company_id=${companyId}`
                : '../../../../server/api/purchase/purchase_return/get-products.php';
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                productsData = data.products;
            }
        } catch (error) {
            console.error('Error loading products:', error);
        }
    }

    // Load UOM from API
    async function loadUOM() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_return/get-uom.php');
            const data = await response.json();

            if (data.success) {
                uomData = data.uoms;
            }
        } catch (error) {
            console.error('Error loading UOM:', error);
        }
    }

    // Load currencies from API
    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_return/get-currencies.php');
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
            const response = await fetch('../../../../server/api/purchase/purchase_return/get-companies.php');
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

                // Auto-select if only one company
                if (data.companies.length === 1) {
                    const autoId = data.companies[0].id;
                    companySelect.value = autoId;
                    await Promise.all([loadSuppliers(autoId), loadBranches(autoId), loadProducts(autoId), loadPurchaseInvoices(autoId)]);
                }

                // Company change handler
                companySelect.addEventListener('change', async function() {
                    const companyId = this.value || null;
                    // Clear dependent fields
                    document.getElementById('supplierCodeSearch').value = '';
                    document.getElementById('supplierCode').value = '';
                    document.getElementById('branchSearch').value = '';
                    document.getElementById('branch').value = '';
                    document.getElementById('purchaseInvoiceSearch').value = '';
                    document.getElementById('purchaseInvoice').value = '';
                    document.getElementById('previousBalance').value = '0.00';
                    await Promise.all([loadSuppliers(companyId), loadBranches(companyId), loadProducts(companyId), loadPurchaseInvoices(companyId)]);
                });
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    // Load projects from API
    async function loadProjects() {
        try {
            const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            const projectSelect = document.getElementById('project');
            projectSelect.innerHTML = '<option value="">Select Project</option>';
            
            if (data.success && Array.isArray(data.projects) && data.projects.length > 0) {
                data.projects.forEach(project => {
                    const cityName = project.city_name || 'N/A';
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = `${project.project_code} - ${project.project_name} (${cityName})`;
                    projectSelect.appendChild(option);
                });
                
                if (data.projects.length === 1) {
                    projectSelect.value = data.projects[0].id;
                }
            } else if (!data.success) {
                console.warn('Projects API returned error:', data.message);
            }
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    }

    // Load purchase invoices from API
    async function loadPurchaseInvoices(companyId = null, supplierId = null) {
        try {
            const params = new URLSearchParams();
            if (companyId)  params.append('company_id',  companyId);
            if (supplierId) params.append('supplier_id', supplierId);
            const url = `../../../../server/api/purchase/purchase_return/get-purchase-invoices.php${params.toString() ? '?' + params.toString() : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                const poOptions = document.getElementById('purchaseInvoiceOptions');
                poOptions.innerHTML = '';
                data.invoices.forEach(invoice => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', invoice.id);
                    option.textContent = `${invoice.bill_no} - ${invoice.supplier_name} (${invoice.purchase_date})`;
                    poOptions.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading purchase invoices:', error);
        }
    }

    // Load sub accounts from API
    async function loadSubAccounts(supplierId) {
        try {
            if (!supplierId) {
                document.getElementById('subAccountOptions').innerHTML = '';
                document.getElementById('subAccountSearch').value = '';
                document.getElementById('subAccount').value = '';
                return;
            }

            const response = await fetch(`../../../../server/api/purchase/purchase_return/get-sub-accounts.php?supplier_id=${supplierId}`);
            const data = await response.json();

            if (data.success && data.sub_accounts) {
                const subAccountOptions = document.getElementById('subAccountOptions');
                subAccountOptions.innerHTML = '';

                data.sub_accounts.forEach(account => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', account.id);
                    option.textContent = account.sub_account_name;
                    subAccountOptions.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading sub accounts:', error);
        }
    }

    // Load purchase invoice details and populate form
    async function loadPurchaseInvoiceDetails(invoiceId) {
        try {
            const response = await fetch(`../../../../server/api/purchase/purchase_return/get-purchase-invoice-details.php?invoice_id=${invoiceId}`);
            const data = await response.json();

            if (data.success) {
                const invoice = data.invoice;
                const items = data.items;
                
                console.log('Loaded items:', items);
                console.log('First item unit_entries:', items[0]?.unit_entries);

                // Populate form fields
                document.getElementById('supplierCodeSearch').value = `${invoice.supplier_code} - ${invoice.supplier_name}`;
                document.getElementById('supplierCode').value = invoice.supplier_id;
                document.getElementById('previousBalance').value = invoice.previous_balance;
                
                document.getElementById('branchSearch').value = invoice.parent_branch_name 
                    ? `${invoice.parent_branch_name} > ${invoice.branch_code} - ${invoice.branch_name} (${invoice.branch_type})`
                    : `${invoice.branch_code} - ${invoice.branch_name} (${invoice.branch_type})`;
                document.getElementById('branch').value = invoice.branch_id;
                
                document.getElementById('currency').value = invoice.currency_id;
                document.getElementById('biltyNo').value = invoice.bilty_no || '';
                document.getElementById('transportName').value = invoice.transport_name || '';
                document.getElementById('remarks').value = invoice.remarks || '';

                // Load sub accounts for supplier
                await loadSubAccounts(invoice.supplier_id);

                // Update currency symbols
                updateCurrencySymbols();

                // Clear existing items
                const itemsTable = document.getElementById('itemsTable');
                const tbody = itemsTable.getElementsByTagName('tbody')[0];
                while (tbody.rows.length > 0) {
                    tbody.deleteRow(0);
                }

                // First pass: add rows and set productUomData
                items.forEach(item => {
                    addRowDynamic();
                    const lastRow = tbody.rows[tbody.rows.length - 1];
                    lastRow.cells[1].querySelector('.search-input').value = `${item.product_code} - ${item.product_name}`;
                    lastRow.cells[1].querySelector('.item-code').value = item.product_id;
                    const product = productsData.find(p => p.id == item.product_id);
                    const uomDetails = product ? getProductUOMDetails(product) : {
                        type: item.uom_type || 'unit',
                        units: (item.unit_entries || []).map(entry => ({ id: entry.uom_id, name: entry.uom_name, conversionFactor: 1 }))
                    };
                    lastRow.dataset.productUomData = JSON.stringify(uomDetails);
                    lastRow.dataset.productId = item.product_id;
                });

                // Recalculate columns ONCE after all rows added
                recalculateMaxColumns();

                // Second pass: set field values and unit quantities AFTER unit cells exist
                items.forEach((item, index) => {
                    const row = tbody.rows[index];
                    row.querySelector('.price-cell input').value = item.purchase_price;
                    row.querySelector('.disc-percent-cell input').value = item.discount_percent || 0;
                    row.querySelector('.disc-amount-cell input').value = item.discount_amount || 0;
                    row.querySelector('.to-percent-cell input').value = item.trade_offer_percent || 0;
                    row.querySelector('.to-amount-cell input').value = item.trade_offer_amount || 0;
                    row.querySelector('.tax-percent-cell input').value = item.tax_percent || 0;
                    row.querySelector('.foc-cell input').value = item.foc_quantity || 0;
                    row.querySelector('.gross-cell input').value = item.gross_amount || 0;
                    row.querySelector('.net-cell input').value = item.net_amount || 0;
                    item.unit_entries.forEach(entry => {
                        const unitInput = row.querySelector(`.unit-input[data-unit-id="${entry.uom_id}"]`);
                        if (unitInput) unitInput.value = entry.quantity;
                    });
                    calculateTotalQuantity(row);
                });

                // Autopopulate discount and shipping fees from invoice
                document.getElementById('totalDiscountPercent').value = parseFloat(invoice.total_discount_percent || 0).toFixed(2);
                document.getElementById('totalDiscountAmount').value = parseFloat(invoice.total_discount_amount || 0).toFixed(2);
                const shippingFees = data.shipping_fees || 0;
                const shippingFeesType = data.shipping_fees_type || 'add';
                document.getElementById('shippingFees').value = shippingFees;
                document.getElementById('shippingFees').dataset.type = shippingFeesType;

                // Update summary
                updateReturnSummaryDynamic();
            } else {
            }
        } catch (error) {
            console.error('Error loading purchase order details:', error);
            alert('Error loading purchase order details');
        }
    }

    // Update currency symbols in labels and values
    function updateCurrencySymbols() {
        const currencySelect = document.getElementById('currency');
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        const symbol = selectedOption ? selectedOption.getAttribute('data-symbol') : '';

        if (symbol) {
            // Update table headers
            document.getElementById('purchasePriceLabel').textContent = `Purchase Price (${symbol})`;
            document.getElementById('grossAmountLabel').textContent = `Gross Amount (${symbol})`;
            document.getElementById('discountAmountLabel').textContent = `Discount Amount (${symbol})`;
            document.getElementById('netAmountLabel').textContent = `Net Amount (${symbol})`;

            // Update summary labels
            document.getElementById('totalBillLabel').textContent = `Total Bill (${symbol})`;
            document.getElementById('totalDiscountAmountLabel').textContent = `Discount Amount (${symbol})`;
            document.getElementById('netAmountSummaryLabel').textContent = `Net Amount (${symbol})`;
        }
    }

    // Add currency change event listener
    document.getElementById('currency').addEventListener('change', updateCurrencySymbols);

    async function loadreturnData(returnId) {
        try {
            const response = await fetch(`../../../../server/api/purchase/purchase_return/return-edit.php?id=${returnId}`);
            const data = await response.json();

            if (data.success) {
                const returnData = data.invoice;

                document.getElementById('purchaseDate').value = returnData.purchase_date;
                if (document.getElementById('supplierInvoiceNo')) {
                    document.getElementById('supplierInvoiceNo').value = returnData.supplier_invoice_no || '';
                }
                if (document.getElementById('supplierInvoiceDate')) {
                    document.getElementById('supplierInvoiceDate').value = returnData.supplier_invoice_date || '';
                }
                if (document.getElementById('biltyNo')) {
                    document.getElementById('biltyNo').value = returnData.bilty_no || '';
                }
                if (document.getElementById('transportName')) {
                    document.getElementById('transportName').value = returnData.transport_name || '';
                }
                document.getElementById('company').value = returnData.company_id || '';
                document.getElementById('supplierCodeSearch').value = `${returnData.supplier_code} - ${returnData.supplier_name}`;
                document.getElementById('supplierCode').value = returnData.supplier_id;
                const branchText = returnData.parent_branch_name
                    ? `${returnData.parent_branch_name} > ${returnData.branch_code} - ${returnData.branch_name} (${returnData.branch_type})`
                    : `${returnData.branch_code} - ${returnData.branch_name} (${returnData.branch_type})`;
                document.getElementById('branchSearch').value = branchText;
                document.getElementById('branch').value = returnData.branch_id;
                document.getElementById('currency').value = returnData.currency_id;
                document.getElementById('previousBalance').value = returnData.previous_balance;
                document.getElementById('remarks').value = returnData.remarks || '';
                document.getElementById('project').value = returnData.project_id || '';

                updateCurrencySymbols();

                // Clear existing items
                while (itemsTable.rows.length > 0) {
                    const si = itemsTable.rows[0].cells[1]?.querySelector('.search-input');
                    if (si?.dropdownOptions) si.dropdownOptions.remove();
                    itemsTable.deleteRow(0);
                }

                // First pass: add rows and set productUomData
                data.items.forEach(item => {
                    addRowDynamic();
                    const lastRow = itemsTable.rows[itemsTable.rows.length - 1];
                    lastRow.cells[1].querySelector('.search-input').value = `${item.product_code} - ${item.product_name}`;
                    lastRow.cells[1].querySelector('.item-code').value = item.product_id;
                    const product = productsData.find(p => p.id == item.product_id);
                    const uomDetails = product ? getProductUOMDetails(product) : {
                        type: item.uom_type || 'unit',
                        units: (item.unit_entries || []).map(entry => ({ id: entry.uom_id, name: entry.uom_name, conversionFactor: 1 }))
                    };
                    lastRow.dataset.productUomData = JSON.stringify(uomDetails);
                    lastRow.dataset.productId = item.product_id;
                });

                // Recalculate columns ONCE
                recalculateMaxColumns();

                // Second pass: set field values AFTER unit cells exist
                data.items.forEach((item, idx) => {
                    const row = itemsTable.rows[idx];
                    row.querySelector('.price-cell input').value = item.purchase_price || 0;
                    row.querySelector('.disc-percent-cell input').value = item.discount_percent || 0;
                    row.querySelector('.disc-amount-cell input').value = item.discount_amount || 0;
                    row.querySelector('.to-percent-cell input').value = item.trade_offer_percent || 0;
                    row.querySelector('.to-amount-cell input').value = item.trade_offer_amount || 0;
                    row.querySelector('.tax-percent-cell input').value = item.tax_percent || item.gst_percent || 0;
                    row.querySelector('.foc-cell input').value = item.foc_quantity || 0;
                    row.querySelector('.gross-cell input').value = item.gross_amount || 0;
                    row.querySelector('.net-cell input').value = item.net_amount || 0;
                    (item.unit_entries || []).forEach(entry => {
                        const unitInput = row.querySelector(`.unit-input[data-unit-id="${entry.uom_id}"]`);
                        if (unitInput) unitInput.value = entry.quantity;
                    });
                    calculateTotalQuantity(row);
                });

                // Update summary
                document.getElementById('totalDiscountPercent').value = returnData.total_discount_percent;
                document.getElementById('totalDiscountAmount').value = returnData.total_discount_amount;
                document.getElementById('shippingFees').value = returnData.shipping_fees || 0;
                document.getElementById('shippingFees').dataset.type = returnData.shipping_fees_type || 'add';
                updateReturnSummaryDynamic();

                document.querySelector('.page-title').textContent = `Edit Purchase Return - ${returnData.bill_no}`;

            } else {
                console.error('API returned error:', data.message);
                alert('Error loading return: ' + data.message);
                window.location.href = 'return-list.php';
            }
        } catch (error) {
            console.error('Error loading return:', error);
            console.error('Error details:', error.message);
            alert('Error loading return data: ' + error.message);
            window.location.href = 'return-list.php';
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
        searchInput.addEventListener('keydown', function (e) {
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
        searchInput.addEventListener('input', function () {
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

                // Update related fields for supplier selection
                if (hiddenInputId === 'supplierCode') {
                    const balance = parseFloat(e.target.getAttribute('data-balance'));
                    document.getElementById('previousBalance').value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
                    
                    // Load sub accounts for selected supplier
                    loadSubAccounts(value);

                    // Reload purchase invoices filtered by company + supplier
                    const companyId = document.getElementById('company').value || null;
                    loadPurchaseInvoices(companyId, value);
                }
                
                // Load purchase invoice details when selected
                if (hiddenInputId === 'purchaseInvoice') {
                    loadPurchaseInvoiceDetails(value);
                }

                // Hide options
                optionsContainer.style.display = 'none';

                // Remove error state if any
                hiddenInput.classList.remove('error');
                const errorElement = document.getElementById(hiddenInputId + 'Error');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
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
            return `<div class="dropdown-option" data-value="${product.id}" data-price="${product.purchase_price}" data-unit="${product.default_unit_id}" data-carton-conversion="${product.carton_conversion || 0}" data-discount="${product.default_discount || 0}" data-trade-offer="${product.trade_offer_discount || 0}" data-foc="${product.default_foc || 0}" data-gst="${product.sales_tax || 0}" style="display: flex; align-items: center;">${photoHtml}${product.code} - ${product.name}${codeDisplay}</div>`;
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
        unitSelect.addEventListener('change', function () {
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

        // Purchase Price (input)
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

        // Trade Offer % (input)
        const cell11 = row.insertCell(11);
        const tradeOfferPercent = document.createElement('input');
        tradeOfferPercent.type = 'number';
        tradeOfferPercent.className = 'table-input';
        tradeOfferPercent.min = '0';
        tradeOfferPercent.max = '100';
        tradeOfferPercent.step = '0.01';
        tradeOfferPercent.value = '0';
        cell11.appendChild(tradeOfferPercent);

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

        // FOC Quantity (input)
        const cell15 = row.insertCell(15);
        const focQty = document.createElement('input');
        focQty.type = 'number';
        focQty.className = 'table-input';
        focQty.min = '0';
        focQty.step = '0.01';
        focQty.value = '0';
        cell15.appendChild(focQty);

        // Apply settings to new row
        const enablePcs = localStorage.getItem('enablePcs') === 'true';
        const enableCtn = localStorage.getItem('enableCtn') === 'true';
        const enableDz = localStorage.getItem('enableDz') === 'true';
        const enableInlineCashDiscount = localStorage.getItem('enableInlineCashDiscount') === 'true';
        const enableInlineCashDiscountAmount = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
        const enableTradeOffer = localStorage.getItem('enableTradeOffer') === 'true';
        const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC = localStorage.getItem('enableFOC') === 'true';
        const enableTaxation = localStorage.getItem('enableTaxation') === 'true';

        cell4.style.display = enablePcs ? '' : 'none';
        cell5.style.display = enableCtn ? '' : 'none';
        cell6.style.display = enableDz ? '' : 'none';
        cell9.style.display = enableInlineCashDiscount ? '' : 'none';
        cell10.style.display = enableInlineCashDiscountAmount ? '' : 'none';
        cell11.style.display = enableTradeOffer ? '' : 'none';
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
        netAmount.addEventListener('keydown', function (e) {
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
                        updatereturnSummary();
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
        pcsInput.addEventListener('input', calculateQtyFromParts);
        ctnInput.addEventListener('input', calculateQtyFromParts);
        dzInput.addEventListener('input', calculateQtyFromParts);
        qtyInput.addEventListener('input', calculateRow);
        priceInput.addEventListener('input', calculateRow);
        discountPercent.addEventListener('input', calculateRow);
        discountAmount.addEventListener('input', calculateRowFromAmount);
        tradeOfferPercent.addEventListener('input', calculateRow);
        tradeOfferAmount.addEventListener('input', calculateRowFromTradeOfferAmount);
        gstPercent.addEventListener('input', calculateRow);
        gstAmount.addEventListener('input', calculateRowFromGSTAmount);
        focQty.addEventListener('input', updatereturnSummary);

        // Add event listener for delete button
        deleteBtn.addEventListener('click', function () {
            if (itemsTable.rows.length > 1) {
                row.remove();
                updateSerialNumbers();
                updatereturnSummary();
            } else {
                alert('Cannot delete the only row. Add another row first.');
            }
        });

        // Add event listener for variants button
        addVariantsBtn.addEventListener('click', function () {
            openVariantsModal(row);
        });

        function calculateQtyFromParts() {
            const pcs = parseFloat(pcsInput.value) || 0;
            const ctn = parseFloat(ctnInput.value) || 0;
            const dz = parseFloat(dzInput.value) || 0;
            const cartonConversion = parseFloat(row.dataset.cartonConversion) || 0;

            const calculatedQty = pcs + (ctn * cartonConversion) + (dz * 12);
            qtyInput.value = calculatedQty.toFixed(2);
            calculateRow();
        }

        function calculateRow() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountPct = parseFloat(discountPercent.value) || 0;
            const tradeOfferPct = parseFloat(tradeOfferPercent.value) || 0;
            const gstPct = parseFloat(gstPercent.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate discount amount
            const discountAmt = gross * (discountPct / 100);
            discountAmount.value = discountAmt.toFixed(2);

            // Calculate after discount
            const afterDiscount = gross - discountAmt;

            // Calculate trade offer amount
            const tradeOfferAmt = afterDiscount * (tradeOfferPct / 100);
            tradeOfferAmount.value = tradeOfferAmt.toFixed(2);

            // Calculate after trade offer
            const afterTradeOffer = afterDiscount - tradeOfferAmt;

            // Calculate GST amount
            const gstAmt = afterTradeOffer * (gstPct / 100);
            gstAmount.value = gstAmt.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update return summary
            updatereturnSummary();
        }

        function calculateRowFromAmount() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountAmt = parseFloat(discountAmount.value) || 0;
            const tradeOfferPct = parseFloat(tradeOfferPercent.value) || 0;
            const gstPct = parseFloat(gstPercent.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate discount percentage from amount
            const discountPct = gross > 0 ? (discountAmt / gross) * 100 : 0;
            discountPercent.value = discountPct.toFixed(2);

            // Calculate after discount
            const afterDiscount = gross - discountAmt;

            // Calculate trade offer amount
            const tradeOfferAmt = afterDiscount * (tradeOfferPct / 100);
            tradeOfferAmount.value = tradeOfferAmt.toFixed(2);

            // Calculate after trade offer
            const afterTradeOffer = afterDiscount - tradeOfferAmt;

            // Calculate GST amount
            const gstAmt = afterTradeOffer * (gstPct / 100);
            gstAmount.value = gstAmt.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update return summary
            updatereturnSummary();
        }

        function calculateRowFromTradeOfferAmount() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountAmt = parseFloat(discountAmount.value) || 0;
            const tradeOfferAmt = parseFloat(tradeOfferAmount.value) || 0;
            const gstPct = parseFloat(gstPercent.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate after discount
            const afterDiscount = gross - discountAmt;

            // Calculate trade offer percentage from amount
            const tradeOfferPct = afterDiscount > 0 ? (tradeOfferAmt / afterDiscount) * 100 : 0;
            tradeOfferPercent.value = tradeOfferPct.toFixed(2);

            // Calculate after trade offer
            const afterTradeOffer = afterDiscount - tradeOfferAmt;

            // Calculate GST amount
            const gstAmt = afterTradeOffer * (gstPct / 100);
            gstAmount.value = gstAmt.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update return summary
            updatereturnSummary();
        }

        function calculateRowFromGSTAmount() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const discountAmt = parseFloat(discountAmount.value) || 0;
            const tradeOfferAmt = parseFloat(tradeOfferAmount.value) || 0;
            const gstAmt = parseFloat(gstAmount.value) || 0;

            // Calculate gross amount
            const gross = qty * price;
            grossAmount.value = gross.toFixed(2);

            // Calculate after discount
            const afterDiscount = gross - discountAmt;

            // Calculate after trade offer
            const afterTradeOffer = afterDiscount - tradeOfferAmt;

            // Calculate GST percentage from amount
            const gstPct = afterTradeOffer > 0 ? (gstAmt / afterTradeOffer) * 100 : 0;
            gstPercent.value = gstPct.toFixed(2);

            // Calculate net amount
            const net = afterTradeOffer + gstAmt;
            netAmount.value = net.toFixed(2);

            // Update return summary
            updatereturnSummary();
        }
    }

    // Add row with dynamic UOM columns
    function addRowDynamic() {
        const rowCount = itemsTable.rows.length;
        const row = itemsTable.insertRow();
        
        // Serial number
        const cell0 = row.insertCell(0);
        cell0.textContent = rowCount + 1;
        
        // Product dropdown
        const cell1 = row.insertCell(1);
        const codeContainer = document.createElement('div');
        codeContainer.className = 'searchable-dropdown';
        const codeOptionsHtml = productsData.map(product => {
            const codes = [];
            if (product.qr_code) codes.push(product.qr_code);
            if (product.barcode) codes.push(product.barcode);
            const codeDisplay = codes.length > 0 ? ` (${codes.join(' - ')})` : '';
            const photoHtml = product.photo ? `<img src="../../../assets/uploads/products/${product.photo}" alt="${product.name}" style="width: 30px; height: 30px; object-fit: cover; margin-right: 8px; border-radius: 4px;">` : '';
            return `<div class="dropdown-option" data-product='${JSON.stringify(product)}' style="display: flex; align-items: center;">${photoHtml}${product.code} - ${product.name}${codeDisplay}</div>`;
        }).join('');
        
        codeContainer.innerHTML = `
            <input type="text" class="search-input table-input" placeholder="Search product...">
            <input type="hidden" class="item-code" required>
        `;
        
        const dropdownOptions = document.createElement('div');
        dropdownOptions.className = 'dropdown-options';
        dropdownOptions.innerHTML = codeOptionsHtml;
        dropdownOptions.style.position = 'absolute';
        dropdownOptions.style.display = 'none';
        dropdownOptions.style.zIndex = '9999';
        document.body.appendChild(dropdownOptions);
        
        codeContainer.querySelector('.search-input').dropdownOptions = dropdownOptions;
        cell1.appendChild(codeContainer);
        initTableDropdownDynamic(codeContainer, row);
        
        // Unit cells will be added dynamically
        // Price
        const priceCell = row.insertCell(2);
        priceCell.className = 'price-cell';
        const priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.className = 'table-input';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.required = true;
        priceInput.addEventListener('input', function() {
            const unitInputs = row.querySelectorAll('.unit-input');
            let totalQty = 0;
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                const cf = parseFloat(input.dataset.conversionFactor) || 1;
                totalQty += qty * cf;
            });
            calculateRowAmounts(row, totalQty, parseFloat(this.value) || 0);
        });
        priceCell.appendChild(priceInput);
        
        // Gross Amount
        const grossCell = row.insertCell(3);
        grossCell.className = 'gross-cell';
        const grossInput = document.createElement('input');
        grossInput.type = 'text';
        grossInput.className = 'table-input';
        grossInput.readOnly = true;
        grossInput.value = '0.00';
        grossInput.tabIndex = -1;
        grossCell.appendChild(grossInput);
        
        // Discount %
        const discPercentCell = row.insertCell(4);
        discPercentCell.className = 'disc-percent-cell';
        const discPercentInput = document.createElement('input');
        discPercentInput.type = 'number';
        discPercentInput.className = 'table-input';
        discPercentInput.min = '0';
        discPercentInput.max = '100';
        discPercentInput.step = '0.01';
        discPercentInput.value = '0';
        discPercentInput.addEventListener('input', function() {
            const unitInputs = row.querySelectorAll('.unit-input');
            let totalQty = 0;
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                const cf = parseFloat(input.dataset.conversionFactor) || 1;
                totalQty += qty * cf;
            });
            const price = parseFloat(row.querySelector('.price-cell input').value) || 0;
            calculateRowAmounts(row, totalQty, price);
        });
        discPercentCell.appendChild(discPercentInput);
        
        // Discount Amount
        const discAmountCell = row.insertCell(5);
        discAmountCell.className = 'disc-amount-cell';
        const discAmountInput = document.createElement('input');
        discAmountInput.type = 'number';
        discAmountInput.className = 'table-input';
        discAmountInput.min = '0';
        discAmountInput.step = '0.01';
        discAmountInput.value = '0.00';
        discAmountCell.appendChild(discAmountInput);
        
        // TO %
        const toPercentCell = row.insertCell(6);
        toPercentCell.className = 'to-percent-cell';
        const toPercentInput = document.createElement('input');
        toPercentInput.type = 'number';
        toPercentInput.className = 'table-input';
        toPercentInput.min = '0';
        toPercentInput.max = '100';
        toPercentInput.step = '0.01';
        toPercentInput.value = '0';
        toPercentInput.addEventListener('input', function() {
            const unitInputs = row.querySelectorAll('.unit-input');
            let totalQty = 0;
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                const cf = parseFloat(input.dataset.conversionFactor) || 1;
                totalQty += qty * cf;
            });
            const price = parseFloat(row.querySelector('.price-cell input').value) || 0;
            calculateRowAmounts(row, totalQty, price);
        });
        toPercentCell.appendChild(toPercentInput);
        
        // TO Amount
        const toAmountCell = row.insertCell(7);
        toAmountCell.className = 'to-amount-cell';
        const toAmountInput = document.createElement('input');
        toAmountInput.type = 'number';
        toAmountInput.className = 'table-input';
        toAmountInput.min = '0';
        toAmountInput.step = '0.01';
        toAmountInput.value = '0.00';
        toAmountCell.appendChild(toAmountInput);
        
        // Tax %
        const gstPercentCell = row.insertCell(8);
        gstPercentCell.className = 'tax-percent-cell';
        const gstPercentInput = document.createElement('input');
        gstPercentInput.type = 'number';
        gstPercentInput.className = 'table-input';
        gstPercentInput.min = '0';
        gstPercentInput.max = '100';
        gstPercentInput.step = '0.01';
        gstPercentInput.value = '0';
        gstPercentInput.addEventListener('input', function() {
            const unitInputs = row.querySelectorAll('.unit-input');
            let totalQty = 0;
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                const cf = parseFloat(input.dataset.conversionFactor) || 1;
                totalQty += qty * cf;
            });
            const price = parseFloat(row.querySelector('.price-cell input').value) || 0;
            calculateRowAmounts(row, totalQty, price);
        });
        gstPercentCell.appendChild(gstPercentInput);
        
        // Tax Amount
        const gstAmountCell = row.insertCell(9);
        gstAmountCell.className = 'tax-amount-cell';
        const gstAmountInput = document.createElement('input');
        gstAmountInput.type = 'number';
        gstAmountInput.className = 'table-input';
        gstAmountInput.min = '0';
        gstAmountInput.step = '0.01';
        gstAmountInput.value = '0.00';
        gstAmountCell.appendChild(gstAmountInput);
        
        // FOC Qty
        const focCell = row.insertCell(10);
        focCell.className = 'foc-cell';
        const focInput = document.createElement('input');
        focInput.type = 'number';
        focInput.className = 'table-input';
        focInput.min = '0';
        focInput.step = '0.01';
        focInput.value = '0';
        focCell.appendChild(focInput);
        
        // Net Amount
        const netCell = row.insertCell(11);
        netCell.className = 'net-cell';
        const netInput = document.createElement('input');
        netInput.type = 'text';
        netInput.className = 'table-input';
        netInput.readOnly = true;
        netInput.value = '0.00';
        netInput.tabIndex = 0;
        netCell.appendChild(netInput);
        
        // Actions
        const actionsCell = row.insertCell(12);
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-sm';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.title = 'Delete row';
        deleteBtn.tabIndex = -1;
        deleteBtn.addEventListener('click', function() {
            if (itemsTable.rows.length > 1) {
                const searchInput = row.cells[1].querySelector('.search-input');
                if (searchInput.dropdownOptions) {
                    searchInput.dropdownOptions.remove();
                }
                row.remove();
                updateSerialNumbers();
                recalculateMaxColumns();
                updatereturnSummaryDynamic();
            } else {
                alert('Cannot delete the only row.');
            }
        });
        actionsCell.appendChild(deleteBtn);

        // Apply current settings to this new row
        applySettingsToRow(row, {
            enableTradeOffer: localStorage.getItem('enableTradeOffer') === 'true',
            enableTradeOfferAmount: localStorage.getItem('enableTradeOfferAmount') === 'true',
            enableFOC: localStorage.getItem('enableFOC') === 'true',
            enableTaxation: localStorage.getItem('enableTaxation') === 'true',
            enableInlineCashDiscount: localStorage.getItem('enableInlineCashDiscount') === 'true',
            enableInlineCashDiscountAmount: localStorage.getItem('enableInlineCashDiscountAmount') === 'true'
        });
    }
    
    // Initialize dropdown for dynamic rows
    function initTableDropdownDynamic(container, row) {
        const searchInput = container.querySelector('.search-input');
        const optionsContainer = searchInput.dropdownOptions;
        const hiddenInput = container.querySelector('input[type="hidden"]');
        let selectedIndex = -1;
        
        searchInput.addEventListener('click', function(e) {
            e.stopPropagation();
            const rect = searchInput.getBoundingClientRect();
            optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
            optionsContainer.style.left = rect.left + 'px';
            optionsContainer.style.width = rect.width + 'px';
            optionsContainer.style.display = 'block';
            filterOptionsDynamic();
        });
        
        searchInput.addEventListener('focus', function(e) {
            const rect = searchInput.getBoundingClientRect();
            optionsContainer.style.top = (rect.bottom + window.scrollY) + 'px';
            optionsContainer.style.left = rect.left + 'px';
            optionsContainer.style.width = rect.width + 'px';
            optionsContainer.style.display = 'block';
            filterOptionsDynamic();
        });
        
        searchInput.addEventListener('keydown', function(e) {
            const visibleOptions = Array.from(optionsContainer.getElementsByClassName('dropdown-option'))
                .filter(option => option.style.display !== 'none');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, visibleOptions.length - 1);
                updateSelectionDynamic(visibleOptions);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelectionDynamic(visibleOptions);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && visibleOptions[selectedIndex]) {
                    visibleOptions[selectedIndex].click();
                } else if (visibleOptions.length > 0) {
                    visibleOptions[0].click();
                }
            }
        });
        
        function updateSelectionDynamic(visibleOptions) {
            visibleOptions.forEach((option, index) => {
                option.style.backgroundColor = index === selectedIndex ? 'var(--surface-1)' : '';
            });
        }
        
        searchInput.addEventListener('input', function() {
            selectedIndex = -1;
            filterOptionsDynamic();
        });
        
        optionsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('dropdown-option')) {
                const product = JSON.parse(e.target.getAttribute('data-product'));
                searchInput.value = `${product.code} - ${product.name}`;
                hiddenInput.value = product.id;
                
                // Get UOM details
                const uomDetails = getProductUOMDetails(product);
                row.dataset.productUomData = JSON.stringify(uomDetails);
                row.dataset.productId = product.id;
                
                // Set price
                row.querySelector('.price-cell input').value = product.purchase_price || 0;
                
                // Set default values
                row.querySelector('.disc-percent-cell input').value = product.default_discount || 0;
                row.querySelector('.to-percent-cell input').value = product.trade_offer_discount || 0;
                row.querySelector('.foc-cell input').value = product.default_foc || 0;
                row.querySelector('.tax-percent-cell input').value = product.sales_tax || 0;
                
                optionsContainer.style.display = 'none';
                
                // Recalculate columns
                recalculateMaxColumns();
                
                // Focus first unit input
                const firstUnitInput = row.querySelector('.unit-input');
                if (firstUnitInput) {
                    firstUnitInput.focus();
                }
            }
        });
        
        document.addEventListener('click', function() {
            optionsContainer.style.display = 'none';
        });
        
        function filterOptionsDynamic() {
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

        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function (e) {
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
        searchInput.addEventListener('input', function () {
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
                const cartonConversion = e.target.getAttribute('data-carton-conversion');
                const discount = e.target.getAttribute('data-discount');
                const tradeOffer = e.target.getAttribute('data-trade-offer');
                const foc = e.target.getAttribute('data-foc');
                const gst = e.target.getAttribute('data-gst');

                searchInput.value = displayText;
                hiddenInput.value = value;

                row.dataset.cartonConversion = cartonConversion || '0';
                row.dataset.productId = value;

                checkAndShowVariantsButton(row, value);

                // Update related fields
                row.cells[7].querySelector('input').value = price;
                if (unitId) row.cells[2].querySelector('select').value = unitId;

                const enablePcs = localStorage.getItem('enablePcs') === 'true';
                const enableCtn = localStorage.getItem('enableCtn') === 'true';
                const enableDz = localStorage.getItem('enableDz') === 'true';
                const enableInlineCashDiscount = localStorage.getItem('enableInlineCashDiscount') === 'true';
                const enableTradeOffer = localStorage.getItem('enableTradeOffer') === 'true';
                const enableFOC = localStorage.getItem('enableFOC') === 'true';
                const enableTaxation = localStorage.getItem('enableTaxation') === 'true';

                if (enableInlineCashDiscount && discount) {
                    row.cells[9].querySelector('input').value = discount;
                }
                if (enableTradeOffer && tradeOffer) {
                    row.cells[11].querySelector('input').value = tradeOffer;
                }
                if (enableFOC && foc) {
                    row.cells[15].querySelector('input').value = foc;
                }
                if (enableTaxation && gst) {
                    row.cells[13].querySelector('input').value = gst;
                }

                optionsContainer.style.display = 'none';

                row.cells[3].querySelector('input').focus();
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

    // Update return summary
    function updatereturnSummary() {
        let totalBill = 0;
        let totalPcs = 0;
        let totalCtn = 0;
        let totalDz = 0;
        let totalQty = 0;
        let totalPurchasePrice = 0;
        let totalGrossAmount = 0;
        let totalDiscountAmountItems = 0;
        let totalNetAmountItems = 0;
        const rows = itemsTable.rows;

        for (let i = 0; i < rows.length; i++) {
            const pcsInput = rows[i].cells[4].querySelector('input');
            const ctnInput = rows[i].cells[5].querySelector('input');
            const dzInput = rows[i].cells[6].querySelector('input');
            const qtyInput = rows[i].cells[3].querySelector('input');
            const purchasePriceInput = rows[i].cells[7].querySelector('input');
            const grossAmountInput = rows[i].cells[8].querySelector('input');
            const discountAmountInput = rows[i].cells[10].querySelector('input');
            const tradeOfferAmountInput = rows[i].cells[12].querySelector('input');
            const gstAmountInput = rows[i].cells[14].querySelector('input');
            const focQtyInput = rows[i].cells[15].querySelector('input');
            const netAmountInput = rows[i].cells[16].querySelector('input');

            if (netAmountInput) {
                totalPcs += parseFloat(pcsInput.value) || 0;
                totalCtn += parseFloat(ctnInput.value) || 0;
                totalDz += parseFloat(dzInput.value) || 0;
                totalQty += parseFloat(qtyInput.value) || 0;
                totalPurchasePrice += parseFloat(purchasePriceInput.value) || 0;
                totalGrossAmount += parseFloat(grossAmountInput.value) || 0;
                totalDiscountAmountItems += parseFloat(discountAmountInput.value) || 0;
                totalNetAmountItems += parseFloat(netAmountInput.value) || 0;
                totalBill += parseFloat(netAmountInput.value) || 0;
            }
        }

        // Calculate total Trade Offer Amount
        let totalTradeOfferAmountItems = 0;
        for (let i = 0; i < rows.length; i++) {
            const tradeOfferAmountInput = rows[i].cells[12].querySelector('input');
            if (tradeOfferAmountInput) {
                totalTradeOfferAmountItems += parseFloat(tradeOfferAmountInput.value) || 0;
            }
        }

        // Calculate total Tax Amount
        let totalGSTAmountItems = 0;
        for (let i = 0; i < rows.length; i++) {
            const taxAmountInput = rows[i].querySelector('.tax-amount-cell input');
            if (taxAmountInput) {
                totalGSTAmountItems += parseFloat(taxAmountInput.value) || 0;
            }
        }

        // Calculate total FOC Qty
        let totalFOCQty = 0;
        for (let i = 0; i < rows.length; i++) {
            const focQtyInput = rows[i].cells[15].querySelector('input');
            if (focQtyInput) {
                totalFOCQty += parseFloat(focQtyInput.value) || 0;
            }
        }

        // Update totals row
        document.getElementById('totalQty').textContent = totalQty.toFixed(2);
        document.getElementById('totalPcs').textContent = totalPcs.toFixed(2);
        document.getElementById('totalCtn').textContent = totalCtn.toFixed(2);
        document.getElementById('totalDz').textContent = totalDz.toFixed(2);
        document.getElementById('totalPurchasePrice').textContent = totalPurchasePrice.toFixed(2);
        document.getElementById('totalGrossAmount').textContent = totalGrossAmount.toFixed(2);
        document.getElementById('totalDiscountAmountItems').textContent = totalDiscountAmountItems.toFixed(2);
        document.getElementById('totalTradeOfferAmountItems').textContent = totalTradeOfferAmountItems.toFixed(2);
        document.getElementById('totalTaxAmountItems').textContent = totalGSTAmountItems.toFixed(2);
        document.getElementById('totalFOCQty').textContent = totalFOCQty.toFixed(2);
        document.getElementById('totalNetAmountItems').textContent = totalNetAmountItems.toFixed(2);

        document.getElementById('totalBill').textContent = totalBill.toFixed(2);

        const discountPercent = parseFloat(document.getElementById('totalDiscountPercent').value) || 0;
        const discountAmount = totalBill * (discountPercent / 100);
        const afterDiscount = totalBill - discountAmount;

        const gstPercent = parseFloat(document.getElementById('totalTaxPercent')?.value) || 0;
        const gstAmount = afterDiscount * (gstPercent / 100);
        const shippingFeesEl = document.getElementById('shippingFees');
        const shippingFees = parseFloat(shippingFeesEl.value) || 0;
        const shippingFeesType = shippingFeesEl.dataset.type || 'add';
        const netAmount = afterDiscount + gstAmount + (shippingFeesType === 'subtract' ? -shippingFees : shippingFees);

        document.getElementById('totalDiscountAmount').value = discountAmount.toFixed(2);
        if (document.getElementById('totalTaxAmount')) document.getElementById('totalTaxAmount').value = gstAmount.toFixed(2);
        document.getElementById('netAmount').textContent = netAmount.toFixed(2);
    }

    // Add event listener for total discount percent
    document.getElementById('totalDiscountPercent').addEventListener('input', updatereturnSummary);

    // Add event listener for total discount amount
    document.getElementById('totalDiscountAmount').addEventListener('input', updatereturnSummaryFromAmount);

    // Add event listener for shipping fees
    document.getElementById('shippingFees').addEventListener('input', updatereturnSummary);

    function updatereturnSummaryFromAmount() {
        let totalBill = 0;
        const rows = itemsTable.rows;

        for (let i = 0; i < rows.length; i++) {
            const netAmountInput = rows[i].cells[16].querySelector('input');
            if (netAmountInput) {
                totalBill += parseFloat(netAmountInput.value) || 0;
            }
        }

        document.getElementById('totalBill').textContent = totalBill.toFixed(2);

        const discountAmount = parseFloat(document.getElementById('totalDiscountAmount').value) || 0;
        const discountPercent = totalBill > 0 ? (discountAmount / totalBill) * 100 : 0;
        const afterDiscount = totalBill - discountAmount;

        const gstPercent = parseFloat(document.getElementById('totalTaxPercent')?.value) || 0;
        const gstAmount = afterDiscount * (gstPercent / 100);
        const shippingFeesEl = document.getElementById('shippingFees');
        const shippingFees = parseFloat(shippingFeesEl.value) || 0;
        const shippingFeesType = shippingFeesEl.dataset.type || 'add';
        const netAmount = afterDiscount + gstAmount + (shippingFeesType === 'subtract' ? -shippingFees : shippingFees);

        document.getElementById('totalDiscountPercent').value = discountPercent.toFixed(2);
        if (document.getElementById('totalTaxAmount')) document.getElementById('totalTaxAmount').value = gstAmount.toFixed(2);
        document.getElementById('netAmount').textContent = netAmount.toFixed(2);
    }

    function updatereturnSummaryFromGSTAmount() {
        let totalBill = 0;
        const rows = itemsTable.rows;

        for (let i = 0; i < rows.length; i++) {
            const netAmountInput = rows[i].cells[16].querySelector('input');
            if (netAmountInput) {
                totalBill += parseFloat(netAmountInput.value) || 0;
            }
        }

        document.getElementById('totalBill').textContent = totalBill.toFixed(2);

        const discountPercent = parseFloat(document.getElementById('totalDiscountPercent').value) || 0;
        const discountAmount = totalBill * (discountPercent / 100);
        const afterDiscount = totalBill - discountAmount;

        const gstAmount = parseFloat(document.getElementById('totalTaxAmount')?.value) || 0;
        const gstPercent = afterDiscount > 0 ? (gstAmount / afterDiscount) * 100 : 0;
        const shippingFeesEl = document.getElementById('shippingFees');
        const shippingFees = parseFloat(shippingFeesEl.value) || 0;
        const shippingFeesType = shippingFeesEl.dataset.type || 'add';
        const netAmount = afterDiscount + gstAmount + (shippingFeesType === 'subtract' ? -shippingFees : shippingFees);

        document.getElementById('totalDiscountAmount').value = discountAmount.toFixed(2);
        if (document.getElementById('totalTaxPercent')) document.getElementById('totalTaxPercent').value = gstPercent.toFixed(2);
        document.getElementById('netAmount').textContent = netAmount.toFixed(2);
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
            { id: 'supplierCode', errorId: 'supplierCodeError' },
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
            alert('Please add at least one item to the returnData.');
            isValid = false;
        }

        const rows = itemsTable.rows;
        for (let i = 0; i < rows.length; i++) {
            const code = rows[i].cells[1].querySelector('.item-code');
            const priceCell = rows[i].querySelector('.price-cell');
            const price = priceCell ? priceCell.querySelector('input') : null;
            const unitInputs = rows[i].querySelectorAll('.unit-input');
            let hasQty = false;
            
            unitInputs.forEach(input => {
                if (parseFloat(input.value) > 0) hasQty = true;
            });

            if (!code || !code.value || !price || !price.value || !hasQty) {
                alert('Please fill all required fields in the items table.');
                isValid = false;
                break;
            }
        }

        return isValid;
    }

    // Save return
    function savereturn() {
        // Disable save button to prevent multiple clicks
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        // Collect form data
        const formData = {
            purchaseDate: document.getElementById('purchaseDate').value,
            supplierInvoiceNo: document.getElementById('supplierInvoiceNo')?.value || null,
            supplierInvoiceDate: document.getElementById('supplierInvoiceDate')?.value || null,
            biltyNo: document.getElementById('biltyNo')?.value || null,
            transportName: document.getElementById('transportName')?.value || null,
            companyId: document.getElementById('company').value,
            supplierId: document.getElementById('supplierCode').value,
            branchId: document.getElementById('branch').value,
            purchaseInvoiceId: document.getElementById('purchaseInvoice').value || null,
            subAccountId: document.getElementById('subAccount').value || null,
            currencyId: document.getElementById('currency').value,
            projectId: document.getElementById('project')?.value || null,
            previousBalance: parseFloat(document.getElementById('previousBalance').value) || 0,
            totalBill: parseFloat(document.getElementById('totalBill').textContent),
            totalDiscountPercent: parseFloat(document.getElementById('totalDiscountPercent').value) || 0,
            totalDiscountAmount: parseFloat(document.getElementById('totalDiscountAmount').value),
            totalGSTPercent: 0,
            totalGSTAmount: 0,
            shippingFees: parseFloat(document.getElementById('shippingFees').value) || 0,
            shippingFeesType: document.getElementById('shippingFees').dataset.type || 'add',
            netAmount: parseFloat(document.getElementById('netAmount').textContent),
            remarks: document.getElementById('remarks').value,
            items: []
        };

        // Add return_id if in edit mode
        if (isEditMode) {
            formData.invoice_id = editId;
        }

        const rows = itemsTable.rows;
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const unitInputs = row.querySelectorAll('.unit-input');
            const unitEntries = [];
            
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                if (qty > 0) {
                    unitEntries.push({
                        uomId: input.dataset.unitId,
                        quantity: qty
                    });
                }
            });
            
            if (unitEntries.length > 0) {
                const item = {
                    productId: row.cells[1].querySelector('.item-code').value,
                    unitEntries: unitEntries,
                    purchasePrice: parseFloat(row.querySelector('.price-cell input').value),
                    grossAmount: parseFloat(row.querySelector('.gross-cell input').value),
                    discountPercent: parseFloat(row.querySelector('.disc-percent-cell input').value) || 0,
                    discountAmount: parseFloat(row.querySelector('.disc-amount-cell input').value),
                    tradeOfferPercent: parseFloat(row.querySelector('.to-percent-cell input').value) || 0,
                    tradeOfferAmount: parseFloat(row.querySelector('.to-amount-cell input').value) || 0,
                    gstPercent: parseFloat(row.querySelector('.tax-percent-cell input').value) || 0,
                    gstAmount: parseFloat(row.querySelector('.tax-amount-cell input').value) || 0,
                    focQty: parseFloat(row.querySelector('.foc-cell input').value) || 0,
                    netAmount: parseFloat(row.querySelector('.net-cell input').value)
                };
                formData.items.push(item);
            }
        }

        // Send to API
        const apiUrl = isEditMode ?
            '../../../../server/api/purchase/purchase_return/return-edit.php' :
            '../../../../server/api/purchase/purchase_return/return-add.php';
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
                    // Store return ID for print functionality
                    window.lastreturnId = data.return_id;
                    document.getElementById('successModal').style.display = 'flex';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error saving return: ' + error.message);
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = isEditMode ? '<i class="fas fa-save"></i> Update Return' : '<i class="fas fa-save"></i> Save Return';
            });
    }

    // Reset form with confirmation
    function resetForm() {
        if (confirm('Are you sure you want to reset the form? All data will be lost.')) {
            performReset();
        }
    }

    // Silent reset without confirmation
    function performReset() {
        // Clean up dropdown options from table rows
        const rows = itemsTable.rows;
        for (let i = 0; i < rows.length; i++) {
            const searchInput = rows[i].cells[1].querySelector('.search-input');
            if (searchInput && searchInput.dropdownOptions) {
                searchInput.dropdownOptions.remove();
            }
        }
        form.reset();

        // Clear items table
        while (itemsTable.rows.length > 0) {
            itemsTable.deleteRow(0);
        }

        // Reset max columns
        maxUnitColumns = 0;
        allUnitHeaders = [];
        updateTableHeaders();
        updateFooterTotals();

        // Add one empty row
        addRowDynamic();

        // Reset summary
        document.getElementById('totalBill').textContent = '0.00';
        document.getElementById('totalDiscountAmount').value = '0.00';
        document.getElementById('netAmount').textContent = '0.00';

        // Set default date to today
        document.getElementById('purchaseDate').value = today;

        // Reset searchable dropdowns
        document.getElementById('supplierCodeSearch').value = '';
        document.getElementById('supplierCode').value = '';
        document.getElementById('branchSearch').value = '';
        document.getElementById('branch').value = '';
        document.getElementById('previousBalance').value = '0.00';

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
        const returnId = isEditMode ? editId : window.lastreturnId;
        if (returnId) {
            window.open(`return-print.php?id=${returnId}`, '_blank');
        }
        performReset();
    });

    // return Settings Modal
    document.getElementById('invoiceSettingsBtn').addEventListener('click', function () {
        // Load settings from localStorage
        document.getElementById('enableTradeOffer').checked = localStorage.getItem('enableTradeOffer') === 'true';
        document.getElementById('enableTradeOfferAmount').checked = localStorage.getItem('enableTradeOfferAmount') === 'true';
        document.getElementById('enableFOC').checked = localStorage.getItem('enableFOC') === 'true';
        document.getElementById('enableTaxation').checked = localStorage.getItem('enableTaxation') === 'true';
        document.getElementById('enableInlineCashDiscount').checked = localStorage.getItem('enableInlineCashDiscount') === 'true';
        document.getElementById('enableInlineCashDiscountAmount').checked = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
        document.getElementById('enableInvoiceCashDiscount').checked = localStorage.getItem('enablereturnCashDiscount') === 'true';
        document.getElementById('enableInvoiceCashDiscountAmount').checked = localStorage.getItem('enablereturnCashDiscountAmount') === 'true';
        document.getElementById('enableShippingFees').checked = localStorage.getItem('enableShippingFees') === 'true';
        document.getElementById('settingsModal').style.display = 'flex';
    });

    document.getElementById('closeSettingsBtn').addEventListener('click', function () {
        document.getElementById('settingsModal').style.display = 'none';
    });

    document.getElementById('saveSettingsBtn').addEventListener('click', function () {
        // Save settings to localStorage
        localStorage.setItem('enableTradeOffer', document.getElementById('enableTradeOffer').checked);
        localStorage.setItem('enableTradeOfferAmount', document.getElementById('enableTradeOfferAmount').checked);
        localStorage.setItem('enableFOC', document.getElementById('enableFOC').checked);
        localStorage.setItem('enableTaxation', document.getElementById('enableTaxation').checked);
        localStorage.setItem('enableInlineCashDiscount', document.getElementById('enableInlineCashDiscount').checked);
        localStorage.setItem('enableInlineCashDiscountAmount', document.getElementById('enableInlineCashDiscountAmount').checked);
        localStorage.setItem('enablereturnCashDiscount', document.getElementById('enableInvoiceCashDiscount').checked);
        localStorage.setItem('enablereturnCashDiscountAmount', document.getElementById('enableInvoiceCashDiscountAmount').checked);
        localStorage.setItem('enableShippingFees', document.getElementById('enableShippingFees').checked);
        document.getElementById('settingsModal').style.display = 'none';

        // Apply settings immediately
        applyreturnSettings();
    });

    document.getElementById('settingsModal').addEventListener('click', function (e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

    // Apply return settings to show/hide columns
    function applyreturnSettings() {
        const enableTradeOffer = localStorage.getItem('enableTradeOffer') === 'true';
        const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC = localStorage.getItem('enableFOC') === 'true';
        const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
        const enableInlineCashDiscount = localStorage.getItem('enableInlineCashDiscount') === 'true';
        const enableInlineCashDiscountAmount = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
        const enablereturnCashDiscount = localStorage.getItem('enablereturnCashDiscount') === 'true';
        const enablereturnCashDiscountAmount = localStorage.getItem('enablereturnCashDiscountAmount') === 'true';
        const enableShippingFees = localStorage.getItem('enableShippingFees') === 'true';

        // Show/hide thead and tfoot columns via data-col attribute
        const colMap = [
            { col: 'disc-percent',  show: enableInlineCashDiscount },
            { col: 'disc-amount',   show: enableInlineCashDiscountAmount },
            { col: 'to-percent',    show: enableTradeOffer },
            { col: 'to-amount',     show: enableTradeOfferAmount },
            { col: 'tax-percent',   show: enableTaxation },
            { col: 'tax-amount',    show: enableTaxation },
            { col: 'foc',           show: enableFOC }
        ];
        colMap.forEach(({ col, show }) => {
            document.querySelectorAll(`#itemsTable [data-col="${col}"]`).forEach(el => {
                el.style.display = show ? '' : 'none';
            });
        });

        // Show/hide cells in all existing rows
        const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
        for (let row of rows) {
            applySettingsToRow(row, { enableTradeOffer, enableTradeOfferAmount, enableFOC, enableTaxation, enableInlineCashDiscount, enableInlineCashDiscountAmount });
        }

        // Hide/show return summary fields
        const discountPercentItem = document.getElementById('totalDiscountPercent')?.closest('.summary-item');
        const discountAmountItem = document.getElementById('totalDiscountAmount')?.closest('.summary-item');
        const gstPercentItem = document.getElementById('totalTaxPercent')?.closest('.summary-item');
        const gstAmountItem = document.getElementById('totalTaxAmount')?.closest('.summary-item');
        const shippingFeesItem = document.getElementById('shippingFees')?.closest('.summary-item');

        if (discountPercentItem) discountPercentItem.style.display = enablereturnCashDiscount ? '' : 'none';
        if (discountAmountItem) discountAmountItem.style.display = enablereturnCashDiscountAmount ? '' : 'none';
        if (gstPercentItem) gstPercentItem.style.display = enableTaxation ? '' : 'none';
        if (gstAmountItem) gstAmountItem.style.display = enableTaxation ? '' : 'none';
        if (shippingFeesItem) shippingFeesItem.style.display = enableShippingFees ? '' : 'none';
    }

    function applySettingsToRow(row, settings) {
        const { enableTradeOffer, enableTradeOfferAmount, enableFOC, enableTaxation, enableInlineCashDiscount, enableInlineCashDiscountAmount } = settings;
        const toPercentCell = row.querySelector('.to-percent-cell');
        const toAmountCell = row.querySelector('.to-amount-cell');
        const focCell = row.querySelector('.foc-cell');
        const taxPercentCell = row.querySelector('.tax-percent-cell');
        const taxAmountCell = row.querySelector('.tax-amount-cell');
        const discPercentCell = row.querySelector('.disc-percent-cell');
        const discAmountCell = row.querySelector('.disc-amount-cell');

        if (toPercentCell) toPercentCell.style.display = enableTradeOffer ? '' : 'none';
        if (toAmountCell) toAmountCell.style.display = enableTradeOfferAmount ? '' : 'none';
        if (focCell) focCell.style.display = enableFOC ? '' : 'none';
        if (taxPercentCell) taxPercentCell.style.display = enableTaxation ? '' : 'none';
        if (taxAmountCell) taxAmountCell.style.display = enableTaxation ? '' : 'none';
        if (discPercentCell) discPercentCell.style.display = enableInlineCashDiscount ? '' : 'none';
        if (discAmountCell) discAmountCell.style.display = enableInlineCashDiscountAmount ? '' : 'none';
    }

    // Handle UOM change for price conversion
    function handleUOMChange(row, unitSelect) {
        const oldUnitId = unitSelect.dataset.previousValue || unitSelect.value;
        const newUnitId = unitSelect.value;

        // Store current value for next change
        unitSelect.dataset.previousValue = newUnitId;

        // Only convert if both units are set and different
        if (!oldUnitId || !newUnitId || oldUnitId === newUnitId) return;

        const priceInput = row.cells[7].querySelector('input');
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
});


async function checkAndShowVariantsButton(row, productId) {
    try {
        const response = await fetch(`../../../../server/api/purchase/purchase_return/get-child-products.php?parent_id=${productId}`);
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

    fetch(`../../../../server/api/purchase/purchase_return/get-child-products.php?parent_id=${productId}`)
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
                <input type="checkbox" class="variant-checkbox" data-id="${child.id}" data-name="${child.name}" data-code="${child.code}" data-unit="${child.default_unit_id}" data-stock-affects="${child.stock_affects}" data-return-affects="${child.return_affects}" style="width: auto;">
                <span style="flex: 1;">${child.code} - ${child.name}</span>
                <input type="number" class="variant-qty table-input" data-id="${child.id}" min="0" step="0.01" value="0" placeholder="Qty" style="width: 80px;" disabled>
                <input type="number" class="variant-price table-input" data-id="${child.id}" min="0" step="0.01" value="0" placeholder="Price" style="width: 100px;" disabled>
            </div>
        `).join('');

    modal.innerHTML = `
            <div class="modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
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
        checkbox.addEventListener('change', function () {
            const qtyInput = modal.querySelector(`.variant-qty[data-id="${this.dataset.id}"]`);
            const priceInput = modal.querySelector(`.variant-price[data-id="${this.dataset.id}"]`);
            qtyInput.disabled = !this.checked;
            priceInput.disabled = !this.checked;
            if (this.checked && qtyInput.value == 0) qtyInput.value = 1;
        });
    });

    document.getElementById('selectAllVariants').addEventListener('change', function () {
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
            const priceInput = modal.querySelector(`.variant-price[data-id="${checkbox.dataset.id}"]`);
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            if (qty > 0) {
                selected.push({
                    id: checkbox.dataset.id,
                    name: checkbox.dataset.name,
                    code: checkbox.dataset.code,
                    unitId: checkbox.dataset.unit,
                    qty: qty,
                    price: price,
                    stockAffects: checkbox.dataset.stockAffects,
                    returnAffects: checkbox.dataset.returnAffects
                });
            }
        });

        if (selected.length > 0) {
            addChildProducts(parentRow, selected);
            modal.remove();
        } else {
            alert('Please select at least one variant with quantity.');
        }
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

function addChildProducts(parentRow, children) {
    const totalChildQty = children.reduce((sum, child) => sum + child.qty, 0);
    const parentQtyInput = parentRow.cells[3].querySelector('input');
    parentQtyInput.value = totalChildQty.toFixed(2);
    parentQtyInput.dispatchEvent(new Event('input'));

    const childProducts = children.map(child => ({
        product_id: child.id,
        product_name: child.name,
        product_code: child.code,
        uom_id: child.unitId,
        quantity: child.qty,
        purchase_price: child.price,
        stock_affects: child.stockAffects,
        return_affects: child.returnAffects
    }));
    parentRow.dataset.childProducts = JSON.stringify(childProducts);
}







