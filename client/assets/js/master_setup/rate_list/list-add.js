// Sample data for dropdowns
let customers = [];
let suppliers = [];
let products = [];

// State management
let selectedEntities = [];
let items = [];
let serialNumber = 1;
let editMode = false;
let editRateListId = null;
let currentType = 'customer'; // 'customer' or 'supplier'

// Initialize the form
document.addEventListener('DOMContentLoaded', function () {
    // Check if editing
    const urlParams = new URLSearchParams(window.location.search);
    editRateListId = urlParams.get('id');
    editMode = !!editRateListId;
    
    // Update form title if editing
    if (editMode) {
        document.querySelector('.form-title').textContent = 'Edit Rate List';
        document.getElementById('postBtn').innerHTML = '<i class="fas fa-save"></i> Update Rate List';
    }
    
    // Fetch customers and products from API
    fetchCustomers();
    fetchProducts();
    
    // Load rate list data if editing
    if (editMode) {
        loadRateListData(editRateListId);
    } else {
        // Add first item row for new entry
        addItemRow();
    }

    // Set up event listeners
    document.getElementById('typeCustomer').addEventListener('change', handleTypeChange);
    document.getElementById('typeSupplier').addEventListener('change', handleTypeChange);
    document.getElementById('entitySelect').addEventListener('click', toggleEntityDropdown);
    document.getElementById('addItemBtn').addEventListener('click', addItemRow);
    document.getElementById('postBtn').addEventListener('click', postRateList);
    document.getElementById('resetBtn').addEventListener('click', resetForm);

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.multiselect-container')) {
            document.getElementById('entityDropdown').classList.remove('active');
        }
    });
});

// Handle rate list type change
function handleTypeChange(e) {
    currentType = e.target.value;
    selectedEntities = [];
    updateSelectedEntitiesDisplay();
    
    const label = document.getElementById('entityLabel');
    const placeholder = document.getElementById('entityPlaceholder');
    
    if (currentType === 'customer') {
        label.textContent = 'Customer Name *';
        placeholder.textContent = 'Select customer(s)';
        initEntityDropdown();
    } else {
        label.textContent = 'Supplier Name *';
        placeholder.textContent = 'Select supplier(s)';
        if (suppliers.length === 0) {
            fetchSuppliers();
        } else {
            initEntityDropdown();
        }
    }
}

// Load rate list data for editing
function loadRateListData(id) {
    fetch(`../../../../server/api/master_setup/rate_list/get-rate-list.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rateList = data.rateList;
                
                // Populate form fields
                document.getElementById('listCode').value = rateList.listCode;
                document.getElementById('listName').value = rateList.listName;
                
                // Set selected entities
                selectedEntities = rateList.customers;
                currentType = 'customer';
                document.getElementById('typeCustomer').checked = true;
                updateSelectedEntitiesDisplay();
                
                // Add items
                rateList.items.forEach(item => {
                    addItemRowWithData(item);
                });
            } else {
                showMessage(data.message || 'Failed to load rate list', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading rate list', 'error');
        });
}

// Fetch customers from API
function fetchCustomers() {
    fetch('../../../../server/api/master_setup/rate_list/get-customers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                customers = data.customers.map(c => ({
                    id: c.id,
                    name: `${c.customer_name} (${c.customer_code})`
                }));
                initEntityDropdown();
            } else {
                showMessage(data.message || 'Failed to load customers', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading customers', 'error');
        });
}

// Fetch suppliers from API
function fetchSuppliers() {
    fetch('../../../../server/api/master_setup/rate_list/get-suppliers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                suppliers = data.suppliers.map(s => ({
                    id: s.id,
                    name: `${s.supplier_name} (${s.supplier_code})`
                }));
                initEntityDropdown();
            } else {
                showMessage(data.message || 'Failed to load suppliers', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading suppliers', 'error');
        });
}

// Fetch products from API
function fetchProducts() {
    fetch('../../../../server/api/master_setup/rate_list/get-products.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                products = data.products;
            } else {
                showMessage('Failed to load products', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading products', 'error');
        });
}

// Entity dropdown functions (unified for customers and suppliers)
function initEntityDropdown() {
    const dropdown = document.getElementById('entityDropdown');
    dropdown.innerHTML = '';

    const entities = currentType === 'customer' ? customers : suppliers;

    entities.forEach(entity => {
        const option = document.createElement('div');
        option.className = 'multiselect-option';
        option.dataset.id = entity.id;
        option.textContent = entity.name;

        // Check if already selected
        if (selectedEntities.some(e => e.id === entity.id)) {
            option.classList.add('selected');
        }

        option.addEventListener('click', function () {
            toggleEntitySelection(entity);
        });

        dropdown.appendChild(option);
    });
}

function toggleEntityDropdown() {
    document.getElementById('entityDropdown').classList.toggle('active');
}

function toggleEntitySelection(entity) {
    const index = selectedEntities.findIndex(e => e.id === entity.id);

    if (index === -1) {
        selectedEntities.push(entity);
    } else {
        selectedEntities.splice(index, 1);
    }

    updateSelectedEntitiesDisplay();
    initEntityDropdown();
}

function updateSelectedEntitiesDisplay() {
    const container = document.getElementById('selectedEntities');
    container.innerHTML = '';

    selectedEntities.forEach(entity => {
        const value = document.createElement('div');
        value.className = 'multiselect-value';
        value.innerHTML = `
                    ${entity.name}
                    <span class="remove-value" data-id="${entity.id}">
                        <i class="fas fa-times"></i>
                    </span>
                `;

        container.appendChild(value);
    });

    // Add event listeners to remove buttons
    document.querySelectorAll('.remove-value').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = parseInt(this.dataset.id);
            selectedEntities = selectedEntities.filter(e => e.id !== id);
            updateSelectedEntitiesDisplay();
            initEntityDropdown();
        });
    });
}

// Items table functions
function addItemRow() {
    addItemRowWithData(null);
}

function addItemRowWithData(itemData) {
    const rowId = Date.now() + Math.floor(Math.random() * 10000);
    console.log('Adding row with ID:', rowId);
    items.push({
        id: rowId,
        serial: serialNumber,
        product: itemData ? itemData.product : null,
        salePrice: itemData ? itemData.salePrice : '',
        quantity: itemData ? itemData.quantity : 1,
        focQuantity: itemData ? itemData.focQuantity : 0,
        tradeOffer: itemData ? itemData.tradeOffer : 0
    });
    console.log('Items array now:', items);

    const tbody = document.getElementById('itemsTableBody');
    const row = document.createElement('tr');
    row.id = `item-row-${rowId}`;
    
    const productValue = itemData && itemData.product ? `${itemData.product.name} (${itemData.product.code})` : '';
    
    row.innerHTML = `
                <td>${serialNumber}</td>
                <td>
                    <div class="product-select-container">
                        <input type="text" class="table-input product-search" data-row="${rowId}" placeholder="Search product..." autocomplete="off" value="${productValue}">
                        <div class="product-dropdown" data-row="${rowId}" style="display: none;"></div>
                    </div>
                </td>
                <td><input type="number" class="table-input sale-price" data-row="${rowId}" placeholder="0.00" min="0" step="0.01" value="${itemData ? itemData.salePrice : ''}"></td>
                <td><input type="number" class="table-input quantity" data-row="${rowId}" value="${itemData ? itemData.quantity : 1}" min="1"></td>
                <td><input type="number" class="table-input foc-quantity" data-row="${rowId}" value="${itemData ? itemData.focQuantity : 0}" min="0"></td>
                <td><input type="number" class="table-input trade-offer" data-row="${rowId}" value="${itemData ? itemData.tradeOffer : 0}" min="0" step="0.01"></td>
                <td>
                    <button class="action-btn remove" data-row="${rowId}" title="Remove item">
                        <i class="fas fa-minus"></i>
                    </button>
                </td>
            `;

    tbody.appendChild(row);
    serialNumber++;

    // Setup product search
    const searchInput = row.querySelector('.product-search');
    const dropdown = row.querySelector('.product-dropdown');
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (this.value.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        const filtered = query.length === 0 ? products : products.filter(p => 
            p.name.toLowerCase().includes(query) || 
            p.code.toLowerCase().includes(query)
        );
        
        if (filtered.length > 0) {
            dropdown.innerHTML = filtered.map(p => 
                `<div class="product-option" data-id="${p.id}" data-name="${p.name}" data-code="${p.code}">${p.name} (${p.code})</div>`
            ).join('');
            dropdown.style.display = 'block';
            
            dropdown.querySelectorAll('.product-option').forEach(opt => {
                opt.addEventListener('click', function() {
                    const productId = parseInt(this.dataset.id);
                    const product = products.find(p => p.id === productId);
                    searchInput.value = `${this.dataset.name} (${this.dataset.code})`;
                    dropdown.style.display = 'none';
                    updateItemData(rowId, 'product-select', productId);
                });
            });
        } else {
            dropdown.innerHTML = '<div class="product-option" style="color: var(--subtext);">No products found</div>';
            dropdown.style.display = 'block';
        }
    });
    
    searchInput.addEventListener('blur', function() {
        setTimeout(() => dropdown.style.display = 'none', 200);
    });

    // Add event listeners for the new row
    const removeBtn = row.querySelector('.action-btn.remove');
    removeBtn.addEventListener('click', function () {
        removeItemRow(rowId);
    });

    // Add change listeners to inputs
    const inputs = row.querySelectorAll('input:not(.product-search)');
    inputs.forEach(input => {
        input.addEventListener('change', function () {
            updateItemData(rowId, this.className.split(' ')[1], this.value);
        });
        input.addEventListener('input', function () {
            updateItemData(rowId, this.className.split(' ')[1], this.value);
        });
    });
}

function removeItemRow(rowId) {
    items = items.filter(item => item.id !== rowId);
    const row = document.getElementById(`item-row-${rowId}`);
    if (row) row.remove();
    updateSerialNumbers();
}

function updateSerialNumbers() {
    const rows = document.querySelectorAll('#itemsTableBody tr');
    serialNumber = 1;

    rows.forEach((row, index) => {
        row.cells[0].textContent = index + 1;
        const rowIdStr = row.id.replace('item-row-', '');
        const rowId = parseFloat(rowIdStr);
        const itemIndex = items.findIndex(item => item.id === rowId);
        if (itemIndex !== -1) {
            items[itemIndex].serial = index + 1;
        }
    });

    serialNumber = rows.length + 1;
}

function updateItemData(rowId, field, value) {
    console.log('Updating item:', rowId, field, value);
    const itemIndex = items.findIndex(item => item.id === rowId);
    console.log('Found item at index:', itemIndex);
    if (itemIndex === -1) return;

    switch (field) {
        case 'product-select':
            const productId = parseInt(value);
            items[itemIndex].product = products.find(p => p.id === productId) || null;
            console.log('Updated product:', items[itemIndex].product);
            break;
        case 'sale-price':
            items[itemIndex].salePrice = parseFloat(value) || 0;
            break;
        case 'quantity':
            items[itemIndex].quantity = parseInt(value) || 1;
            break;
        case 'foc-quantity':
            items[itemIndex].focQuantity = parseInt(value) || 0;
            break;
        case 'trade-offer':
            items[itemIndex].tradeOffer = parseFloat(value) || 0;
            break;
    }
    console.log('Items array after update:', items);
}

// Form actions
function postRateList() {
    const listName = document.getElementById('listName').value.trim();

    if (!listName) {
        showMessage('Please fill in List Name.', 'error');
        return;
    }

    if (selectedEntities.length === 0) {
        showMessage('Please select at least one ' + (currentType === 'customer' ? 'customer' : 'supplier') + '.', 'error');
        return;
    }

    if (items.length === 0) {
        showMessage('Please add at least one item to the rate list.', 'error');
        return;
    }

    const incompleteItems = items.filter(item => !item.product);
    if (incompleteItems.length > 0) {
        showMessage('Please select a product for all items.', 'error');
        return;
    }

    const formData = {
        listName,
        type: currentType,
        entities: selectedEntities,
        items: items
    };

    const url = editMode 
        ? '../../../../server/api/master_setup/rate_list/list-edit.php'
        : '../../../../server/api/master_setup/rate_list/list-add.php';
    
    const method = editMode ? 'PUT' : 'POST';
    
    if (editMode) {
        formData.id = editRateListId;
    }

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = editMode 
                ? 'Rate list updated successfully!' 
                : `Rate list created successfully! Code: ${data.list_code}`;
            showMessage(message, 'success');
            setTimeout(() => {
                window.location.href = 'list-list.php';
            }, 1500);
        } else {
            showMessage(data.message || 'Failed to save rate list', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while saving the rate list', 'error');
    });
}

function resetForm() {
    if (editMode) {
        loadRateListData(editRateListId);
    } else {
        document.getElementById('listCode').value = '';
        document.getElementById('listName').value = '';
        currentType = 'customer';
        document.getElementById('typeCustomer').checked = true;
        document.getElementById('entityLabel').textContent = 'Customer Name *';
        document.getElementById('entityPlaceholder').textContent = 'Select customer(s)';
        selectedEntities = [];
        updateSelectedEntitiesDisplay();
        initEntityDropdown();
        items = [];
        document.getElementById('itemsTableBody').innerHTML = '';
        serialNumber = 1;
        addItemRow();
        document.getElementById('statusMessage').className = 'status-message';
        document.getElementById('statusMessage').textContent = '';
        showMessage('Form has been reset.', 'success');
    }
}

function showMessage(message, type) {
    const statusEl = document.getElementById('statusMessage');
    statusEl.textContent = message;
    statusEl.className = `status-message ${type}`;

    if (type === 'success') {
        setTimeout(() => {
            statusEl.className = 'status-message';
            statusEl.textContent = '';
        }, 5000);
    }
}
