/**
 * Bulk Branch-wise Opening Stock - Row Management
 */

let rowCounter = 0;
let allBranches = [];
let allVendors = [];

document.addEventListener('DOMContentLoaded', function() {
    loadBranches();
    loadVendors();
    attachEventListeners();
    addRow();
});

function loadBranches() {
    fetch('../../../../server/api/inventory/products/branch-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.branches) {
                allBranches = data.branches;
            }
        })
        .catch(error => console.error('Error loading branches:', error));
}

function loadVendors() {
    fetch('../../../../server/api/inventory/products/supplier-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suppliers) {
                allVendors = data.suppliers;
            }
        })
        .catch(error => console.error('Error loading vendors:', error));
}

function attachEventListeners() {
    const form = document.getElementById('bulkOpeningStockForm');
    const addRowBtn = document.getElementById('addRowBtn');
    
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addRow();
        });
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveOpeningStock();
        });
    }
}

function addRow() {
    const container = document.getElementById('stockEntries');
    rowCounter++;
    
    const row = document.createElement('div');
    row.className = 'stock-entry';
    row.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label>Product</label>
                <div class="custom-dropdown">
                    <input type="text" class="product-search" placeholder="Search product..." autocomplete="off">
                    <input type="hidden" name="product[]" class="product-id">
                    <input type="hidden" class="uom-type">
                    <input type="hidden" class="uom-group-id">
                    <input type="hidden" class="default-unit-id">
                    <div class="dropdown-list" style="display: none;"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Branch</label>
                <div class="custom-dropdown">
                    <input type="text" class="branch-search" placeholder="Search branches..." autocomplete="off">
                    <input type="hidden" name="branch[]" class="branch-id">
                    <div class="dropdown-list" style="display: none;"></div>
                </div>
            </div>
            <div class="qty-columns"></div>
            <div class="form-group">
                <label class="opening-price-label">Opening Price / Unit</label>
                <input type="number" name="openingPrice[]" class="opening-price" step="0.01" min="0" value="">
            </div>
            <div class="form-group">
                <label>Vendor</label>
                <div class="custom-dropdown">
                    <input type="text" class="vendor-search" placeholder="Search vendor..." autocomplete="off">
                    <input type="hidden" name="vendor[]" class="vendor-id">
                    <div class="dropdown-list" style="display: none;"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Batch No</label>
                <input type="text" name="batchNo[]" class="batch-no" placeholder="e.g., B-2026-001">
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiryDate[]" class="expiry-date">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger remove-stock-entry" style="margin-top: 24px;">Remove</button>
            </div>
        </div>
    `;
    
    container.appendChild(row);
    initializeRowControls(row);
    updateSummary();
}

function initializeRowControls(row) {
    const branchDropdown = row.querySelector('.custom-dropdown:has(.branch-search)');
    if (branchDropdown) {
        initBranchDropdown(branchDropdown);
    }

    const vendorDropdown = row.querySelector('.custom-dropdown:has(.vendor-search)');
    if (vendorDropdown) {
        initVendorDropdown(vendorDropdown);
    }
    
    const productSearch = row.querySelector('.product-search');
    const productDropdown = row.querySelector('.custom-dropdown:has(.product-search)');
    const dropdownList = productDropdown ? productDropdown.querySelector('.dropdown-list') : null;
    
    if (productSearch && dropdownList) {
        let selectedIndex = -1;

        productSearch.addEventListener('focus', function() {
            if (this.value.trim().length >= 2) {
                dropdownList.style.display = 'block';
            }
        });
        
        productSearch.addEventListener('input', function(e) {
            const searchText = this.value.trim();
            selectedIndex = -1;
            if (searchText.length >= 2) {
                searchProducts(searchText, dropdownList, row);
            } else {
                dropdownList.style.display = 'none';
                if (searchText.length === 0) {
                    clearProductData(row);
                    updateSummary();
                }
            }
        });

        productSearch.addEventListener('keydown', function(e) {
            const items = dropdownList.querySelectorAll('.dropdown-item:not([style*="cursor: default"])');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateDropdownSelection(items, selectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateDropdownSelection(items, selectedIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    items[selectedIndex].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            } else if (e.key === 'Escape') {
                dropdownList.style.display = 'none';
                selectedIndex = -1;
            }
        });
        
        document.addEventListener('click', (e) => {
            if (productDropdown && !productDropdown.contains(e.target)) {
                dropdownList.style.display = 'none';
                selectedIndex = -1;
            }
        });
    }
    
    const removeBtn = row.querySelector('.remove-stock-entry');
    if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            row.remove();
            updateSummary();
        });
    }
    
    const priceInput = row.querySelector('.opening-price');
    if (priceInput) {
        priceInput.addEventListener('change', updateSummary);
        priceInput.addEventListener('input', updateSummary);
    }
}

function initBranchDropdown(container) {
    const searchInput = container.querySelector('.branch-search');
    const hiddenInput = container.querySelector('.branch-id');
    const dropdownList = container.querySelector('.dropdown-list');
    
    if (!searchInput || !hiddenInput || !dropdownList) return;

    let selectedIndex = -1;
    
    searchInput.addEventListener('focus', () => {
        renderBranchList(searchInput.value.toLowerCase(), dropdownList, searchInput, hiddenInput);
        dropdownList.style.display = 'block';
    });
    
    searchInput.addEventListener('input', () => {
        selectedIndex = -1;
        renderBranchList(searchInput.value.toLowerCase(), dropdownList, searchInput, hiddenInput);
        dropdownList.style.display = 'block';
    });

    searchInput.addEventListener('keydown', function(e) {
        const items = dropdownList.querySelectorAll('.dropdown-item:not([style*="cursor: default"])');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateDropdownSelection(items, selectedIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            updateDropdownSelection(items, selectedIndex);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                items[selectedIndex].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            }
        } else if (e.key === 'Escape') {
            dropdownList.style.display = 'none';
            selectedIndex = -1;
        }
    });
    
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdownList.style.display = 'none';
            selectedIndex = -1;
        }
    });
}

function renderBranchList(query, dropdownList, searchInput, hiddenInput) {
    dropdownList.innerHTML = '';
    
    if (!allBranches || allBranches.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">Loading branches...</div>';
        return;
    }
    
    const filtered = allBranches.filter(b =>
        query === '' || b.branch_name.toLowerCase().includes(query)
    );
    
    if (filtered.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">No branches found</div>';
        return;
    }
    
    filtered.forEach(branch => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = branch.parent_branch_name
            ? `${branch.branch_name} (${branch.branch_type}) — ${branch.parent_branch_name}`
            : `${branch.branch_name} (${branch.branch_type})`;
        item.dataset.id = branch.id;
        
        item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            e.stopPropagation();
            searchInput.value = branch.parent_branch_name
                ? `${branch.branch_name} (${branch.branch_type}) — ${branch.parent_branch_name}`
                : `${branch.branch_name} (${branch.branch_type})`;
            hiddenInput.value = branch.id;
            dropdownList.style.display = 'none';
            updateSummary();
        });
        
        dropdownList.appendChild(item);
    });
}

function initVendorDropdown(container) {
    const searchInput = container.querySelector('.vendor-search');
    const hiddenInput = container.querySelector('.vendor-id');
    const dropdownList = container.querySelector('.dropdown-list');

    if (!searchInput || !hiddenInput || !dropdownList) return;

    let selectedIndex = -1;

    searchInput.addEventListener('focus', () => {
        renderVendorList(searchInput.value.toLowerCase(), dropdownList, searchInput, hiddenInput);
        dropdownList.style.display = 'block';
    });

    searchInput.addEventListener('input', () => {
        selectedIndex = -1;
        renderVendorList(searchInput.value.toLowerCase(), dropdownList, searchInput, hiddenInput);
        dropdownList.style.display = 'block';
        if (searchInput.value.trim() === '') {
            hiddenInput.value = '';
        }
    });

    searchInput.addEventListener('keydown', function(e) {
        const items = dropdownList.querySelectorAll('.dropdown-item:not([style*="cursor: default"])');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateDropdownSelection(items, selectedIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            updateDropdownSelection(items, selectedIndex);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                items[selectedIndex].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            }
        } else if (e.key === 'Escape') {
            dropdownList.style.display = 'none';
            selectedIndex = -1;
        }
    });

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdownList.style.display = 'none';
            selectedIndex = -1;
        }
    });
}

function renderVendorList(query, dropdownList, searchInput, hiddenInput) {
    dropdownList.innerHTML = '';

    if (!allVendors || allVendors.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">Loading vendors...</div>';
        return;
    }

    const filtered = allVendors.filter(v =>
        query === '' || v.supplier_name.toLowerCase().includes(query)
    );

    if (filtered.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">No vendors found</div>';
        return;
    }

    filtered.forEach(vendor => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = vendor.supplier_name;
        item.dataset.id = vendor.id;

        item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            e.stopPropagation();
            searchInput.value = vendor.supplier_name;
            hiddenInput.value = vendor.id;
            dropdownList.style.display = 'none';
        });

        dropdownList.appendChild(item);
    });
}

function clearProductData(row) {
    row.querySelector('.product-id').value = '';
    row.querySelector('.uom-type').value = '';
    row.querySelector('.uom-group-id').value = '';
    row.querySelector('.default-unit-id').value = '';
    row.querySelector('.product-search').value = '';
    row.querySelector('.qty-columns').innerHTML = '';
}

function updateDropdownSelection(items, index) {
    items.forEach((item, i) => {
        item.classList.toggle('selected', i === index);
        if (i === index) item.scrollIntoView({ block: 'nearest' });
    });
}

function searchProducts(searchText, dropdownList, row) {
    fetch('../../../../server/api/inventory/products/search-products.php?q=' + encodeURIComponent(searchText))
        .then(response => response.json())
        .then(data => {
            dropdownList.innerHTML = '';
            
            if (!data.success || !data.products || data.products.length === 0) {
                const item = document.createElement('div');
                item.className = 'dropdown-item';
                item.textContent = 'No products found';
                item.style.color = 'var(--subtext)';
                item.style.cursor = 'default';
                dropdownList.appendChild(item);
            } else {
                data.products.forEach(product => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item dropdown-child';
                    item.innerHTML = `<strong>${product.name}</strong> (${product.code})`;
                    item.dataset.id = product.id;
                    item.style.cursor = 'pointer';
                    
                    item.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectProduct(row, product);
                    });
                    
                    dropdownList.appendChild(item);
                });
            }
            
            dropdownList.style.display = 'block';
        })
        .catch(error => {
            console.error('Error searching products:', error);
            dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">Error loading products</div>';
            dropdownList.style.display = 'block';
        });
}

function selectProduct(row, product) {
    const productSearch = row.querySelector('.product-search');
    const productId = row.querySelector('.product-id');
    const uomType = row.querySelector('.uom-type');
    const uomGroupId = row.querySelector('.uom-group-id');
    const defaultUnitId = row.querySelector('.default-unit-id');
    const qtyColumnsContainer = row.querySelector('.qty-columns');
    const productDropdown = row.querySelector('.custom-dropdown:has(.product-search)');
    const dropdownList = productDropdown ? productDropdown.querySelector('.dropdown-list') : null;

    productSearch.value = `${product.name} (${product.code})`;
    productId.value = product.id;
    uomType.value = product.uom_type;
    uomGroupId.value = product.uom_group_id || '';
    defaultUnitId.value = product.default_unit_id || '';
    row.dataset.priceEntryUnitId = product.price_entry_unit_id || '';

    // Reset until the group-unit/conversion lookups (if any) resolve.
    delete row.dataset.priceEntryFactor;
    resetOpeningPriceLabel(row);

    if (dropdownList) {
        dropdownList.style.display = 'none';
    }

    qtyColumnsContainer.innerHTML = '';

    if (product.uom_type === 'unit') {
        const formGroup = document.createElement('div');
        formGroup.className = 'form-group';
        formGroup.innerHTML = `
            <label>Opening Qty</label>
            <input type="number" name="openingQty[]" step="0.01" min="0" placeholder="Qty" class="qty-input">
        `;
        qtyColumnsContainer.appendChild(formGroup);

        const input = formGroup.querySelector('input');
        input.addEventListener('change', updateSummary);
        input.addEventListener('input', updateSummary);
    } else if (product.uom_type === 'group' && product.uom_group_id) {
        fetchUomGroupUnits(product.uom_group_id, row);
    }

    updateSummary();
}

function escBulkText(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function resetOpeningPriceLabel(row) {
    const label = row.querySelector('.opening-price-label');
    if (label) label.textContent = 'Opening Price / Unit';
}

// Effective base-unit conversion factor for one of this row's units — universal
// units (e.g. Dozen) carry their own factor; per_product units (e.g. Carton) are
// looked up per-product since the same unit can mean a different quantity on
// different products.
function getRowUnitFactor(unit, productConversions) {
    if (unit.is_base_unit == 1) return 1;
    if (unit.unit_scope === 'per_product') {
        const conv = productConversions.find(c => String(c.uom_id) === String(unit.id));
        const factor = conv ? parseFloat(conv.conversion_factor) : NaN;
        return (!isNaN(factor) && factor > 0) ? factor : null;
    }
    const factor = parseFloat(unit.conversion_factor);
    return (!isNaN(factor) && factor > 0) ? factor : null;
}

function fetchUomGroupUnits(groupId, row) {
    const productId = row.querySelector('.product-id').value;

    Promise.all([
        fetch('../../../../server/api/inventory/products/uom-group-units.php?group_id=' + groupId).then(r => r.json()),
        fetch('../../../../server/api/inventory/products/product-uom-conversions.php?product_id=' + productId).then(r => r.json())
    ])
        .then(([unitsData, convData]) => {
            if (unitsData.success && unitsData.units && unitsData.units.length > 0) {
                const productConversions = (convData.success && convData.conversions) ? convData.conversions : [];
                const qtyColumnsContainer = row.querySelector('.qty-columns');
                qtyColumnsContainer.innerHTML = '';

                unitsData.units.forEach(unit => {
                    const factor = getRowUnitFactor(unit, productConversions);

                    const formGroup = document.createElement('div');
                    formGroup.className = 'form-group';
                    formGroup.innerHTML = `
                        <label>${escBulkText(unit.uom_name)}</label>
                        <input type="number" name="openingQty[${unit.id}][]" step="0.01" min="0" placeholder="Qty" class="qty-input">
                    `;
                    qtyColumnsContainer.appendChild(formGroup);

                    const input = formGroup.querySelector('input');
                    input.dataset.conversionFactor = factor || 1;
                    input.addEventListener('change', updateSummary);
                    input.addEventListener('input', updateSummary);

                    // This product's own "Enter Trade Price / MRP By" unit (if set) —
                    // Opening Price follows the same unit instead of always Piece.
                    if (row.dataset.priceEntryUnitId && String(unit.id) === String(row.dataset.priceEntryUnitId) && unit.is_base_unit != 1) {
                        if (factor) {
                            row.dataset.priceEntryFactor = factor;
                            const label = row.querySelector('.opening-price-label');
                            if (label) label.textContent = `Opening Price / ${escBulkText(unit.uom_name)}`;
                        }
                    }
                });

                updateSummary();
            }
        })
        .catch(error => console.error('Error loading UOM group units:', error));
}

function updateSummary() {
    const rows = document.querySelectorAll('.stock-entry');
    let totalEntries = 0;
    let totalQty = 0;
    let totalValue = 0;
    
    rows.forEach(row => {
        const productId = row.querySelector('.product-id').value;
        const branchId = row.querySelector('.branch-id').value;
        const priceInput = row.querySelector('.opening-price');
        
        if (productId && productId.trim() !== '' && branchId && branchId.trim() !== '') {
            const priceEntryFactor = parseFloat(row.dataset.priceEntryFactor) || 1;
            const price = (parseFloat(priceInput.value) || 0) / priceEntryFactor;

            const qtyInputs = row.querySelectorAll('input[name^="openingQty"]');
            let rowQty = 0;
            qtyInputs.forEach(input => {
                const factor = parseFloat(input.dataset.conversionFactor) || 1;
                rowQty += (parseFloat(input.value) || 0) * factor;
            });

            if (rowQty > 0 || price > 0) {
                totalEntries++;
                totalQty += rowQty;
                totalValue += (rowQty * price);
            }
        }
    });
    
    document.getElementById('totalEntries').textContent = totalEntries;
    document.getElementById('totalQty').textContent = totalQty.toFixed(2);
    document.getElementById('totalValue').textContent = totalValue.toFixed(2);
}

function saveOpeningStock() {
    const rows = document.querySelectorAll('.stock-entry');
    const entries = [];
    
    let hasError = false;
    let rowIndex = 0;
    
    rows.forEach((row) => {
        const productId = row.querySelector('.product-id').value;
        const branchId = row.querySelector('.branch-id').value;
        const uomType = row.querySelector('.uom-type').value;
        const priceEntryFactor = parseFloat(row.dataset.priceEntryFactor) || 1;
        // Opening Price may have been entered against this product's own Carton/Dozen
        // "Enter Trade Price / MRP By" unit — convert it down to per-Piece before
        // saving, since stock_opening/stock_ledger always store cost per base unit.
        const price = ((parseFloat(row.querySelector('.opening-price').value) || 0) / priceEntryFactor);
        const vendorId = row.querySelector('.vendor-id').value || null;
        const batchNo = row.querySelector('.batch-no').value || null;
        const expiryDate = row.querySelector('.expiry-date').value || null;
        
        rowIndex++;
        
        if (!productId || !branchId) {
            if (!productId) {
                showToast('Row ' + rowIndex + ': Please select a product', 'error');
            } else if (!branchId) {
                showToast('Row ' + rowIndex + ': Please select a branch', 'error');
            }
            hasError = true;
            return;
        }
        
        if (uomType === 'group') {
            const qtyInputs = row.querySelectorAll('input[name^="openingQty"]');
            qtyInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                if (qty > 0 || price > 0) {
                    const unitId = input.name.match(/\[(\d+)\]/)[1];
                    entries.push({
                        product_id: productId,
                        branch_id: branchId,
                        unit_id: unitId,
                        opening_qty: qty,
                        opening_price: price,
                        vendor_id: vendorId,
                        batch_no: batchNo,
                        expiry_date: expiryDate
                    });
                }
            });
        } else {
            const qtyInput = row.querySelector('input[name="openingQty[]"]');
            const qty = qtyInput ? parseFloat(qtyInput.value) || 0 : 0;
            
            if (qty > 0 || price > 0) {
                entries.push({
                    product_id: productId,
                    branch_id: branchId,
                    unit_id: row.querySelector('.default-unit-id').value,
                    opening_qty: qty,
                    opening_price: price,
                    vendor_id: vendorId,
                    batch_no: batchNo,
                    expiry_date: expiryDate
                });
            }
        }
    });
    
    if (hasError) return;
    
    if (entries.length === 0) {
        showToast('Please add at least one opening stock entry', 'error');
        return;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) submitBtn.disabled = true;
    
    fetch('../../../../server/api/inventory/products/bulk-opening-stock-save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            stock_data: entries
        })
    })
    .then(response => response.json())
    .then(data => {
        if (submitBtn) submitBtn.disabled = false;
        
        if (data.success) {
            showToast(data.message || 'Opening stock saved successfully', 'success');
            setTimeout(() => {
                location.href = 'product-list.php';
            }, 1500);
        } else {
            showToast(data.message || 'Error saving opening stock', 'error');
        }
    })
    .catch(error => {
        if (submitBtn) submitBtn.disabled = false;
        console.error('Error:', error);
        showToast('Error saving opening stock. Please try again.', 'error');
    });
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.style.backgroundColor = type === 'error' ? '#f44336' : 
                                   type === 'success' ? '#4caf50' : '#2196f3';
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}
