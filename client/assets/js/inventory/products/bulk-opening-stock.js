/**
 * Bulk Opening Stock - Dynamic Table Management
 * Dynamically creates columns based on UOM type (unit vs. group)
 */

let selectedProducts = {};      // { product_id: { id, code, name, uom_type, uom_group_id, default_unit_id, groupUnits: [] } }
let allBranches = [];           // Array of all branch objects
let existingOpeningStock = {};  // { product_id: { branch_id: { qty, price, unit_id } } }

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadBranches();
    initProductSearch();
});

/**
 * Load all branches from database
 */
function loadBranches() {
    fetch('../../../../server/api/inventory/products/branch-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.branches) {
                allBranches = data.branches;
                console.log('Branches loaded:', allBranches);
            }
        })
        .catch(error => console.error('Error loading branches:', error));
}

/**
 * Initialize product search
 */
function initProductSearch() {
    const searchInput = document.getElementById('productSearch');
    const dropdown = document.getElementById('productDropdown');

    if (!searchInput || !dropdown) return;

    searchInput.addEventListener('input', function(e) {
        const query = this.value.trim();
        
        if (query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        // Fetch products matching search query
        fetch(`../../../../server/api/inventory/products/search-products.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products.length > 0) {
                    displayProductDropdown(data.products, dropdown);
                    dropdown.style.display = 'block';
                } else {
                    dropdown.innerHTML = '<div class="dropdown-item disabled">No products found</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                dropdown.style.display = 'none';
            });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && searchInput) {
            dropdown.style.display = 'none';
        }
    });

    // Focus to show dropdown
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            dropdown.style.display = 'block';
        }
    });

    // Blur to hide dropdown
    searchInput.addEventListener('blur', function() {
        setTimeout(() => {
            dropdown.style.display = 'none';
        }, 150);
    });
}

/**
 * Display product dropdown with search results
 */
function displayProductDropdown(products, dropdown) {
    dropdown.innerHTML = '';
    
    products.forEach(product => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        
        // Disable if already selected
        if (selectedProducts[product.id]) {
            item.classList.add('disabled');
        }
        
        item.innerHTML = `
            <strong>${product.code}</strong> - ${product.name}
            ${product.uom_type === 'group' ? '<small>(Multiple Units)</small>' : '<small>(Single Unit)</small>'}
        `;
        
        if (!selectedProducts[product.id]) {
            item.addEventListener('click', function() {
                addProductToTable(product);
                document.getElementById('productSearch').value = '';
                dropdown.style.display = 'none';
            });
        }
        
        dropdown.appendChild(item);
    });
}

/**
 * Add product to table and fetch UOM details if needed
 */
function addProductToTable(product) {
    // Add to selected products
    selectedProducts[product.id] = {
        id: product.id,
        code: product.code,
        name: product.name,
        product_type: product.product_type,
        uom_type: product.uom_type,
        uom_group_id: product.uom_group_id,
        default_unit_id: product.default_unit_id,
        unit_name: product.unit_name || 'Unit',
        groupUnits: []  // Will be populated if uom_type = 'group'
    };

    // Load existing opening stock for this product
    loadExistingOpeningStock(product.id);

    // If group UOM, fetch the units
    if (product.uom_type === 'group' && product.uom_group_id) {
        fetchUomGroupDetails(product.id, product.uom_group_id);
    } else {
        // Update table immediately for single unit products
        updateTable();
    }

    // Show selected products list
    document.getElementById('selectedProductsInfo').style.display = 'block';
    updateSelectedProductsList();

    // Hide empty state
    document.getElementById('emptyState').style.display = 'none';

    // Show save button
    document.getElementById('saveBtn').style.display = 'inline-block';
}

/**
 * Fetch UOM group units/details
 */
function fetchUomGroupDetails(productId, groupId) {
    fetch(`../../../../server/api/inventory/products/uom-group-details.php?group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.units && data.units.length > 0) {
                selectedProducts[productId].groupUnits = data.units;
                console.log(`Loaded ${data.units.length} units for product ${productId}:`, data.units);
            }
            // Update table after loading units
            updateTable();
        })
        .catch(error => {
            console.error('Error loading UOM group details:', error);
            updateTable();
        });
}

// Load existing opening stock
function loadExistingOpeningStock(productId) {
    fetch(`../../../../server/api/inventory/products/stock-opening-get.php?product_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stock_entries) {
                existingOpeningStock[productId] = {};
                data.stock_entries.forEach(entry => {
                    existingOpeningStock[productId][entry.branch_id] = {
                        qty: entry.opening_qty,
                        price: entry.opening_price,
                        unit_id: entry.unit_id
                    };
                });
            }
        })
        .catch(error => console.error('Error loading existing stock:', error));
}

// Update selected products list
function updateSelectedProductsList() {
    const list = document.getElementById('selectedProductsList');
    list.innerHTML = '';

    Object.values(selectedProducts).forEach((product, index) => {
        const tag = document.createElement('span');
        tag.className = 'product-tag';
        tag.innerHTML = `
            ${product.code} - ${product.name}
            <button type="button" class="remove-btn" onclick="removeProduct(${product.id})">&times;</button>
        `;
        list.appendChild(tag);
    });
}

// Remove product from table
function removeProduct(productId) {
    delete selectedProducts[productId];
    delete existingOpeningStock[productId];
    
    if (Object.keys(selectedProducts).length === 0) {
        document.getElementById('selectedProductsInfo').style.display = 'none';
        document.getElementById('emptyState').style.display = 'block';
        document.getElementById('saveBtn').style.display = 'none';
    } else {
        updateSelectedProductsList();
    }

    updateTable();
}

// Update table
function updateTable() {
    const thead = document.querySelector('#openingStockTable thead tr');
    const tbody = document.getElementById('tableBody');

    // Clear existing product columns
    const existingColumns = document.querySelectorAll('#openingStockTable thead .product-column');
    existingColumns.forEach(col => col.remove());

    // Add product columns to header
    Object.values(selectedProducts).forEach(product => {
        const th = document.createElement('th');
        th.className = 'product-column';
        th.colSpan = 2;
        th.innerHTML = `
            <div class="product-col-header">
                <strong>${product.code}</strong>
                <small>${product.unit_name || 'N/A'}</small>
            </div>
        `;
        thead.appendChild(th);
    });

    // Add sub-headers for Qty and Price
    if (Object.keys(selectedProducts).length > 0) {
        thead.innerHTML += Object.keys(selectedProducts).map(() => `
            <th class="sub-header">Qty</th>
            <th class="sub-header">Price</th>
        `).join('');
    }

    // Build table body
    tbody.innerHTML = '';
    
    allBranches.forEach(branch => {
        const row = document.createElement('tr');
        row.className = 'data-row';
        
        // Branch columns
        row.innerHTML = `
            <td class="branch-name"><strong>${branch.branch_name}</strong></td>
            <td class="branch-type"><small>${branch.branch_type}</small></td>
        `;

        // Product columns with Qty and Price inputs
        Object.values(selectedProducts).forEach(product => {
            const existing = existingOpeningStock[product.id]?.[branch.id];
            const qtyValue = existing ? existing.qty : '';
            const priceValue = existing ? existing.price : '';

            const qtyTd = document.createElement('td');
            qtyTd.className = 'input-cell';
            qtyTd.innerHTML = `
                <input 
                    type="number" 
                    class="form-control qty-input" 
                    name="qty_${product.id}_${branch.id}"
                    step="0.01" 
                    min="0" 
                    value="${qtyValue}"
                    placeholder="0"
                    data-product-id="${product.id}"
                    data-branch-id="${branch.id}"
                    ${existing ? 'readonly title="Read-only: existing stock"' : ''}
                    style="${existing ? 'background-color: #f5f5f5; color: #999;' : ''}">
            `;

            const priceTd = document.createElement('td');
            priceTd.className = 'input-cell';
            priceTd.innerHTML = `
                <input 
                    type="number" 
                    class="form-control price-input" 
                    name="price_${product.id}_${branch.id}"
                    step="0.01" 
                    min="0" 
                    value="${priceValue}"
                    placeholder="0"
                    data-product-id="${product.id}"
                    data-branch-id="${branch.id}"
                    ${existing ? 'readonly title="Read-only: existing stock"' : ''}
                    style="${existing ? 'background-color: #f5f5f5; color: #999;' : ''}">
            `;

            row.appendChild(qtyTd);
            row.appendChild(priceTd);
        });

        tbody.appendChild(row);
    });
}

// Save opening stock
function saveOpeningStock() {
    // Validate data
    const qtyInputs = document.querySelectorAll('.qty-input:not([readonly])');
    let hasData = false;
    let isValid = true;

    qtyInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const price = parseFloat(input.parentElement.nextElementSibling.querySelector('input').value) || 0;
        
        if (qty > 0 || price > 0) {
            hasData = true;
        }

        if ((qty > 0 || price > 0) && qty < 0) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });

    if (!hasData) {
        showToast('Please enter at least one opening stock value', 'warning');
        return;
    }

    if (!isValid) {
        showToast('Please correct errors in the form', 'error');
        return;
    }

    // Collect data
    const stockData = [];
    
    Object.values(selectedProducts).forEach(product => {
        allBranches.forEach(branch => {
            const qtyInput = document.querySelector(`input[name="qty_${product.id}_${branch.id}"]:not([readonly])`);
            const priceInput = document.querySelector(`input[name="price_${product.id}_${branch.id}"]:not([readonly])`);
            
            if (qtyInput && priceInput) {
                const qty = parseFloat(qtyInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;

                if (qty > 0 || price > 0) {
                    stockData.push({
                        product_id: product.id,
                        branch_id: branch.id,
                        opening_qty: qty,
                        opening_price: price,
                        unit_id: product.default_unit_id
                    });
                }
            }
        });
    });

    if (stockData.length === 0) {
        showToast('No data to save', 'warning');
        return;
    }

    // Show loading
    document.getElementById('loadingSpinner').style.display = 'flex';

    // Send to backend
    fetch('../../../../server/api/inventory/products/bulk-opening-stock-save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ stock_data: stockData })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').style.display = 'none';
        
        if (data.success) {
            showToast(`Successfully saved ${data.count} opening stock entries`, 'success');
            resetForm();
        } else {
            showToast(data.message || 'Error saving opening stock', 'error');
        }
    })
    .catch(error => {
        document.getElementById('loadingSpinner').style.display = 'none';
        console.error('Error:', error);
        showToast('Error saving opening stock: ' + error.message, 'error');
    });
}

// Reset form
function resetForm() {
    selectedProducts = {};
    existingOpeningStock = {};
    document.getElementById('productSearch').value = '';
    document.getElementById('selectedProductsInfo').style.display = 'none';
    document.getElementById('emptyState').style.display = 'block';
    document.getElementById('saveBtn').style.display = 'none';
    document.getElementById('tableBody').innerHTML = '';
    document.querySelectorAll('#openingStockTable thead .product-column').forEach(el => el.remove());
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast toast-${type}`;
    toast.style.display = 'block';

    setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}
