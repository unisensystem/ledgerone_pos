document.addEventListener('DOMContentLoaded', function () {
    // Check if editing
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    
    // Set current date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date').value = today;

    // Fetch branches from API
    let branches = [];
    let products = [];
    let units = [];
    let companies = [];
    let containers = [];
    fetchCompanies();
    fetchBranches();
    fetchProducts();
    fetchUnits();
    fetchContainers();

    // Initialize items table with one row
    let itemCounter = 1;

    // Add Item Button
    document.getElementById('add-item-btn').addEventListener('click', addItemRow);

    // Post Transfer Button
    document.getElementById('post-transfer-btn').addEventListener('click', postTransfer);

    // Reset Form Button
    document.getElementById('reset-btn').addEventListener('click', resetForm);

    // Functions
    async function fetchCompanies() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-companies.php');
            const data = await response.json();
            
            if (data.success) {
                companies = data.companies;
                const companySelect = document.getElementById('company');
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companySelect.appendChild(option);
                });
                
                if (companies.length === 1) {
                    companySelect.value = companies[0].id;
                }
            }
        } catch (error) {
            console.error('Error fetching companies:', error);
        }
    }

    async function fetchBranches() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-branches.php');
            const data = await response.json();
            
            if (data.success) {
                branches = data.branches;
                initializeDropdown('from-branch', branches);
                initializeDropdown('to-branch', branches);
            } else {
                console.error('Failed to fetch branches:', data.message);
                alert('Failed to load branches');
            }
        } catch (error) {
            console.error('Error fetching branches:', error);
            alert('Error loading branches');
        }
    }

    async function fetchProducts() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-products.php');
            const data = await response.json();
            
            if (data.success) {
                products = data.products;
                console.log('Products loaded:', products.length);
            } else {
                console.error('Failed to fetch products:', data.message);
                alert('Failed to load products');
            }
        } catch (error) {
            console.error('Error fetching products:', error);
            alert('Error loading products');
        }
    }

    async function fetchUnits() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-units.php');
            const data = await response.json();
            
            if (data.success) {
                units = data.units;
                
                // Load transfer data if editing
                if (editId) {
                    loadTransferData(editId);
                } else {
                    addItemRow(); // Add first row after all data is loaded
                }
            } else {
                console.error('Failed to fetch units:', data.message);
            }
        } catch (error) {
            console.error('Error fetching units:', error);
        }
    }

    async function fetchContainers() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-containers.php');
            const data = await response.json();
            
            if (data.success && data.containers && data.containers.length > 0) {
                containers = data.containers;
                const containerSelect = document.getElementById('container');
                containers.forEach(container => {
                    const option = document.createElement('option');
                    option.value = container.id;
                    option.textContent = container.container_code + ' - ' + container.container_name;
                    containerSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching containers:', error);
        }
    }

    async function loadTransferData(id) {
        try {
            const response = await fetch(`../../../../server/api/inventory/stock_transfer/transfer-get.php?id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                const transfer = data.transfer;
                
                // Populate form fields
                document.getElementById('date').value = transfer.date;
                document.getElementById('remarks').value = transfer.remarks || '';
                
                // Set company
                if (transfer.company_id) {
                    setTimeout(() => {
                        document.getElementById('company').value = transfer.company_id;
                    }, 100);
                }
                
                // Set container
                if (transfer.container_id) {
                    document.getElementById('container').value = transfer.container_id;
                }
                
                // Set branches
                const fromBranch = branches.find(b => b.id == transfer.from_location_id);
                const toBranch = branches.find(b => b.id == transfer.to_location_id);
                
                if (fromBranch && fromBranch.selectable) {
                    document.getElementById('from-branch').value = fromBranch.name;
                    document.getElementById('from-branch').dataset.branchId = fromBranch.id;
                }
                
                if (toBranch && toBranch.selectable) {
                    document.getElementById('to-branch').value = toBranch.name;
                    document.getElementById('to-branch').dataset.branchId = toBranch.id;
                }
                
                // Add items
                transfer.items.forEach((item) => {
                    addItemRow();
                    
                    const rows = document.querySelectorAll('#items-table-body tr');
                    const row = rows[rows.length - 1];
                    const product = products.find(p => p.id == item.product_id);
                    
                    if (product) {
                        row.querySelector('.product-code').value = `${product.code} - ${product.name}`;
                        row.querySelector('.product-code').dataset.productId = product.id;
                    }
                    
                    row.querySelector('.engine-no').value = item.engine_no || '';
                    row.querySelector('.chassis-no').value = item.chassis_no || '';
                    row.querySelector('.unit').value = item.default_unit_id || '';
                    row.querySelector('.quantity').value = item.qty;
                    row.querySelector('.rate').value = item.rate;
                    row.querySelector('.stock-value').value = item.stock_value;
                });
                
                // Change button text
                document.getElementById('post-transfer-btn').innerHTML = '<i class="fas fa-save"></i> Update Transfer';
            } else {
                alert('Error loading transfer: ' + data.message);
                window.location.href = 'transfer-list.php';
            }
        } catch (error) {
            console.error('Error loading transfer:', error);
            alert('Failed to load transfer data');
        }
    }

    function initializeDropdown(dropdownId, options) {
        const dropdownInput = document.getElementById(dropdownId);
        const dropdownOptions = document.getElementById(`${dropdownId}-options`);

        // Populate dropdown options
        dropdownOptions.innerHTML = '';
        options.forEach(option => {
            const optionElement = document.createElement('div');
            optionElement.className = 'dropdown-option';
            if (!option.selectable) {
                optionElement.classList.add('non-selectable');
            }
            optionElement.textContent = option.display;
            optionElement.dataset.id = option.id;
            optionElement.dataset.name = option.name;
            optionElement.dataset.selectable = option.selectable;
            optionElement.addEventListener('click', function () {
                if (option.selectable) {
                    dropdownInput.value = option.name;
                    dropdownInput.dataset.branchId = option.id;
                    dropdownOptions.style.display = 'none';
                }
            });
            dropdownOptions.appendChild(optionElement);
        });

        // Show dropdown on focus
        dropdownInput.addEventListener('focus', function () {
            dropdownOptions.style.display = 'block';
        });

        // Filter options on input
        dropdownInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            const allOptions = dropdownOptions.querySelectorAll('.dropdown-option');

            allOptions.forEach(option => {
                const optionName = option.dataset.name.toLowerCase();
                if (optionName.includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });

            dropdownOptions.style.display = 'block';
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', function (event) {
            if (!dropdownInput.contains(event.target) && !dropdownOptions.contains(event.target)) {
                dropdownOptions.style.display = 'none';
            }
        });
    }

    function addItemRow() {
        const tableBody = document.getElementById('items-table-body');
        const row = document.createElement('tr');
        const rowId = itemCounter;

        row.innerHTML = `
            <td>${rowId}</td>
            <td>
                <div class="searchable-dropdown">
                    <div class="dropdown-input-container">
                        <input 
                            type="text" 
                            class="table-input product-code" 
                            placeholder="Select product"
                            data-row="${rowId}"
                        >
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-options product-options" data-row="${rowId}">
                        <!-- Product options will be populated by JS -->
                    </div>
                </div>
            </td>
            <td>
                <input 
                    type="text" 
                    class="table-input engine-no" 
                    placeholder="Engine No"
                    data-row="${rowId}"
                >
            </td>
            <td>
                <input 
                    type="text" 
                    class="table-input chassis-no" 
                    placeholder="Chassis No"
                    data-row="${rowId}"
                >
            </td>
            <td>
                <select class="table-select unit" data-row="${rowId}">
                    <option value="">Select Unit</option>
                    ${units.map(unit => `<option value="${unit.id}">${unit.uom_name}</option>`).join('')}
                </select>
            </td>
            <td>
                <input 
                    type="number" 
                    class="table-input quantity" 
                    min="1" 
                    value="1" 
                    data-row="${rowId}"
                >
            </td>
            <td>
                <input 
                    type="number" 
                    class="table-input rate" 
                    min="0" 
                    step="0.01" 
                    value="0" 
                    data-row="${rowId}"
                >
            </td>
            <td>
                <input 
                    type="text" 
                    class="table-input stock-value readonly" 
                    value="0.00" 
                    readonly
                    data-row="${rowId}"
                >
            </td>
            <td>
                <div class="action-buttons">
                    <button class="icon-btn add-btn" data-action="add" data-row="${rowId}">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="icon-btn remove-btn" data-action="remove" data-row="${rowId}">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </td>
        `;

        tableBody.appendChild(row);

        // Initialize product dropdown for this row
        const productInput = row.querySelector('.product-code');
        const productOptions = row.querySelector('.product-options');

        // Populate product options
        products.forEach(product => {
            const optionElement = document.createElement('div');
            optionElement.className = 'dropdown-option';
            optionElement.textContent = `${product.code} - ${product.name}`;
            optionElement.dataset.productId = product.id;
            optionElement.dataset.productCode = product.code;
            optionElement.addEventListener('click', function () {
                productInput.value = `${product.code} - ${product.name}`;
                productInput.dataset.productId = product.id;
                productOptions.style.display = 'none';

                // Set purchase price as rate
                const rateInput = row.querySelector('.rate');
                rateInput.value = parseFloat(product.purchase_price || 0).toFixed(2);
                
                // Set default unit
                const unitSelect = row.querySelector('.unit');
                if (product.default_unit_id) {
                    unitSelect.value = product.default_unit_id;
                }
                
                calculateStockValue(rowId);
            });
            productOptions.appendChild(optionElement);
        });

        // Add event listeners for quantity and rate inputs
        const quantityInput = row.querySelector('.quantity');
        const rateInput = row.querySelector('.rate');

        quantityInput.addEventListener('input', function () {
            calculateStockValue(rowId);
        });

        rateInput.addEventListener('input', function () {
            calculateStockValue(rowId);
        });

        // Add event listeners for action buttons
        row.querySelector('.add-btn').addEventListener('click', function () {
            addItemRow();
        });

        row.querySelector('.remove-btn').addEventListener('click', function () {
            if (itemCounter > 1) {
                row.remove();
                itemCounter--;
                updateSerialNumbers();
            } else {
                alert('At least one item is required');
            }
        });

        // Initialize dropdown behavior for product
        initializeProductDropdown(productInput, productOptions);

        itemCounter++;
    }

    function initializeProductDropdown(input, optionsContainer) {
        // Show dropdown on focus
        input.addEventListener('focus', function () {
            optionsContainer.style.display = 'block';
        });

        // Filter options on input
        input.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            const allOptions = optionsContainer.querySelectorAll('.dropdown-option');

            allOptions.forEach(option => {
                const optionText = option.textContent.toLowerCase();
                if (optionText.includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });

            optionsContainer.style.display = 'block';
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', function (event) {
            if (!input.contains(event.target) && !optionsContainer.contains(event.target)) {
                optionsContainer.style.display = 'none';
            }
        });
    }

    function calculateStockValue(rowId) {
        const rows = document.querySelectorAll('#items-table-body tr');
        rows.forEach(row => {
            const rowIdAttr = row.querySelector('[data-row]')?.dataset.row;
            if (rowIdAttr == rowId) {
                const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                const rate = parseFloat(row.querySelector('.rate').value) || 0;
                const stockValue = (quantity * rate).toFixed(2);
                row.querySelector('.stock-value').value = stockValue;
            }
        });
    }

    function updateSerialNumbers() {
        const rows = document.querySelectorAll('#items-table-body tr');
        rows.forEach((row, index) => {
            row.cells[0].textContent = index + 1;
        });
    }

    function postTransfer() {
        // Form validation
        let isValid = true;
        const requiredFields = document.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });

        const companyId = document.getElementById('company').value;
        if (!companyId) {
            alert('Please select a company');
            return;
        }

        // Validate branches
        const fromBranchId = document.getElementById('from-branch').dataset.branchId;
        const toBranchId = document.getElementById('to-branch').dataset.branchId;
        
        if (!fromBranchId || !toBranchId) {
            alert('Please select valid branches');
            return;
        }

        // Validate at least one item has product selected
        const productInputs = document.querySelectorAll('.product-code');
        let hasProduct = false;

        productInputs.forEach(input => {
            if (input.value.trim() && input.dataset.productId) {
                hasProduct = true;
            }
        });

        if (!hasProduct) {
            alert('Please add at least one product to the transfer');
            isValid = false;
        }

        if (!isValid) {
            alert('Please fill all required fields correctly');
            return;
        }

        // Collect items data
        const items = [];
        document.querySelectorAll('#items-table-body tr').forEach(row => {
            const productId = row.querySelector('.product-code').dataset.productId;
            const unitId = row.querySelector('.unit').value;
            const quantity = row.querySelector('.quantity').value;
            const rate = row.querySelector('.rate').value;
            const stockValue = row.querySelector('.stock-value').value;
            const engineNo = row.querySelector('.engine-no').value;
            const chassisNo = row.querySelector('.chassis-no').value;

            if (productId) {
                items.push({
                    product_id: productId,
                    unit_id: unitId,
                    quantity: quantity,
                    rate: rate,
                    stock_value: stockValue,
                    engine_no: engineNo,
                    chassis_no: chassisNo
                });
            }
        });

        const transferData = {
            company_id: companyId,
            date: document.getElementById('date').value,
            from_location_id: fromBranchId,
            to_location_id: toBranchId,
            container_id: document.getElementById('container').value || null,
            remarks: document.getElementById('remarks').value,
            items: items
        };

        // Determine endpoint
        const endpoint = editId 
            ? '../../../../server/api/inventory/stock_transfer/transfer-update.php'
            : '../../../../server/api/inventory/stock_transfer/transfer-add.php';
        
        if (editId) {
            transferData.transfer_id = editId;
        }

        // Post to server
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(transferData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = editId 
                    ? 'Stock Transfer updated successfully!'
                    : 'Stock Transfer posted successfully! Transfer Code: ' + data.transfer_code;
                alert(message);
                window.location.href = 'transfer-list.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to post transfer');
        });
    }

    function resetForm() {
        if (!confirm('Are you sure you want to reset the form? All data will be lost.')) {
            return;
        }

        // Reset date to today
        document.getElementById('date').value = today;

        // Clear all inputs
        document.getElementById('from-branch').value = '';
        document.getElementById('to-branch').value = '';
        document.getElementById('remarks').value = '';

        // Clear all error classes
        document.querySelectorAll('.error').forEach(el => {
            el.classList.remove('error');
        });

        // Reset items table to one empty row
        const tableBody = document.getElementById('items-table-body');
        tableBody.innerHTML = '';
        itemCounter = 1;
        addItemRow();

        alert('Form has been reset.');
    }
});
