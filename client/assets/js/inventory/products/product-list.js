let products = [];
let currentPage = 1;
let pageSize = 10;
let totalPages = 1;
let totalRecords = 0;
let collapsedParents = new Set();
let selectedProducts = new Set();
let loadRequestId = 0; // stale response ko ignore karne ke liye (scanner fast requests bhejta hai)

document.addEventListener('DOMContentLoaded', function () {
    // Load and render products
    loadProducts();

    // Add Product button
    document.getElementById('addProductBtn').addEventListener('click', function () {
        window.location.href = 'product-add.php';
    });

    document.getElementById('pageSize').addEventListener('change', function () {
        pageSize = parseInt(this.value);
        loadProducts(1); // page size change → wapas page 1 se start
    });

    // Bulk Opening Stock button
    document.getElementById('bulkOpeningStockBtn').addEventListener('click', function () {
        window.location.href = 'bulk-opening-stock.php';
    });

    // Add First Product button
    document.getElementById('addFirstProduct').addEventListener('click', function () {
        window.location.href = 'product-add.php';
    });

    // AIQ button
    if (document.getElementById('aiqBtn')) {
        document.getElementById('aiqBtn').addEventListener('click', openAiqModal);
        document.getElementById('closeAiqModal').addEventListener('click', closeAiqModal);
        document.getElementById('cancelAiq').addEventListener('click', closeAiqModal);
        document.getElementById('confirmAiqPrint').addEventListener('click', handleAiqPrint);
        document.getElementById('aiqModal').addEventListener('click', function (e) {
            if (e.target === this) closeAiqModal();
        });
    }

    // Select All checkbox
    document.getElementById('selectAll').addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            if (this.checked) {
                selectedProducts.add(parseInt(cb.value));
            } else {
                selectedProducts.delete(parseInt(cb.value));
            }
        });
        updateAssignUomButton();
        updateBulkDeleteButton();
    });

    // Assign UOM button (for selected products)
    document.getElementById('assignUomBtn').addEventListener('click', function () {
        openBulkUomModal(false);
    });

    // Delete Selected button (for selected products)
    if (document.getElementById('deleteSelectedBtn')) {
        document.getElementById('deleteSelectedBtn').addEventListener('click', function () {
            if (selectedProducts.size === 0) return;
            showBulkDeleteModal();
        });
    }

    // Bulk UOM modal handlers
    document.getElementById('closeBulkUomModal').addEventListener('click', closeBulkUomModal);
    document.getElementById('cancelBulkUom').addEventListener('click', closeBulkUomModal);
    document.getElementById('bulkUomForm').addEventListener('submit', handleBulkUomSubmit);

    // Bulk UOM Type radio handlers
    document.querySelectorAll('input[name="bulkUomType"]').forEach(radio => {
        radio.addEventListener('change', handleBulkUomTypeChange);
    });

    document.getElementById('bulkUomModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeBulkUomModal();
        }
    });

    // Export dropdown
    document.getElementById('exportBtn').addEventListener('click', function (e) {
        e.stopPropagation();
        const dropdown = document.getElementById('exportDropdown');
        dropdown.classList.toggle('show');
    });

    // Export options
    document.getElementById('printList').addEventListener('click', function () {
        const params = new URLSearchParams({
            search: document.getElementById('search').value,
            category: document.getElementById('category').value,
            type: document.getElementById('type').value,
            status: document.getElementById('status').value,
            vendor: document.getElementById('vendor').value,
            item_company: document.getElementById('itemCompany').value
        });

        window.open(`print.php?${params}`, '_blank');
        document.getElementById('exportDropdown').classList.remove('show');
    });

    document.getElementById('exportExcel').addEventListener('click', function () {
        exportProductsToExcel();
        document.getElementById('exportDropdown').classList.remove('show');
    });

    document.getElementById('exportJson').addEventListener('click', function () {
        alert('Export JSON functionality would be implemented here');
        document.getElementById('exportDropdown').classList.remove('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function () {
        document.getElementById('exportDropdown').classList.remove('show');
    });

    // Auto-apply filters on change (not require Apply button click)
    document.getElementById('applyFilters').addEventListener('click', applyFilters);
    document.getElementById('resetFilters').addEventListener('click', resetFilters);

    // Auto-apply search on input (with debounce to avoid excessive API calls)
    let searchTimeout;
    const searchInput = document.getElementById('search');

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500); // Wait 500ms after user stops typing, then search
    });

    // Search on Enter key
    // Barcode scanner code type karne ke baad automatically Enter bhejta hai,
    // is liye Enter par foran search karte hain (debounce cancel karke) aur
    // text select kar dete hain taake agla scan purane code ko replace kar de.
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimeout);
            applyFilters();
            this.select();
        }
    });

    // Page khulte hi search box par focus, taake seedha scan kiya ja sake
    // (agar URL se search aaya ho to focus bhi theek hai, text select ho jayega)
    searchInput.focus();
    if (searchInput.value) searchInput.select();

    // Auto-apply on any other filter change (immediate)
    document.getElementById('category').addEventListener('change', () => applyFilters());
    document.getElementById('type').addEventListener('change', () => applyFilters());
    document.getElementById('status').addEventListener('change', () => applyFilters());
    document.getElementById('vendor').addEventListener('change', () => applyFilters());
    document.getElementById('itemCompany').addEventListener('change', () => applyFilters());
    document.getElementById('uomFilter').addEventListener('change', () => applyFilters());

    // Price type change - auto apply
    document.querySelectorAll('input[name="priceType"]').forEach(radio => {
        radio.addEventListener('change', () => applyFilters());
    });

    // Load categories for filter
    loadCategoriesFilter();
    // Column show/hide
    initColumnToggle();
    // Load vendors for filter
    loadVendorsFilter();
    loadItemCompaniesFilter();

    // Pagination buttons
    document.getElementById('prevPage').addEventListener('click', function () {
        if (currentPage > 1) {
            loadProducts(currentPage - 1);
        }
    });

    document.getElementById('nextPage').addEventListener('click', function () {
        if (currentPage < totalPages) {
            loadProducts(currentPage + 1);
        }
    });
});

function renderProductTable(productsToRender) {
    const tableBody = document.getElementById('tableBody');
    const emptyState = document.getElementById('emptyState');

    // Clear existing table rows
    tableBody.innerHTML = '';

    if (productsToRender.length === 0) {
        // Show empty state
        document.querySelector('.table-container').style.display = 'none';
        document.querySelector('.pagination').style.display = 'none';
        emptyState.style.display = 'block';
        return;
    } else {
        // Show table and pagination
        document.querySelector('.table-container').style.display = 'block';
        document.querySelector('.pagination').style.display = 'flex';
        emptyState.style.display = 'none';
    }

    // Populate table with products
    productsToRender.forEach(product => {
        const row = document.createElement('tr');

        // Hide child products if parent is collapsed
        if (product.parentProductId && collapsedParents.has(product.parentProductId)) {
            row.style.display = 'none';
        }
        row.dataset.productId = product.id;
        row.dataset.parentId = product.parentProductId || '';

        // Determine stock status
        let stockStatus = '';
        let stockClass = '';

        if (product.type === 'service' || product.noStockRecords) {
            stockStatus = 'N/A';
        } else {
            stockStatus = `${formatNumber(product.currentStock)}`;

            if (product.status === 'inactive') {
                stockClass = 'status-inactive';
            } else if (product.currentStock <= product.minStock) {
                stockClass = 'status-low-stock';
                stockStatus = `Low: ${formatNumber(product.currentStock)}`;
            }
        }

        // Determine status badge
        let statusBadge = '';
        if (product.status === 'active') {
            statusBadge = '<span class="status-badge status-active">Active</span>';
        } else if (product.status === 'inactive') {
            statusBadge = '<span class="status-badge status-inactive">Inactive</span>';
        } else if (product.status === 'low-stock') {
            statusBadge = '<span class="status-badge status-low-stock">Low Stock</span>';
        }

        // Determine type badge
        const typeBadge = product.type === 'physical'
            ? '<span class="type-badge type-product">Product</span>'
            : '<span class="type-badge type-service">Service</span>';

        // Check if this product has children
        const hasChildren = productsToRender.some(p => p.parentProductId === product.id);
        const isCollapsed = collapsedParents.has(product.id);

        row.innerHTML = `
                    <td style="text-align: center;">
                        <input type="checkbox" class="product-checkbox" value="${product.id}">
                    </td>
                    <td>${product.code}</td>
                    <td>
                        <div style="font-weight: 500; ${product.parentProductId ? 'padding-left: 24px;' : ''}; display: flex; align-items: center; gap: 4px;">
                            ${hasChildren ? `<span class="toggle-children" data-parent-id="${product.id}" style="cursor: pointer; user-select: none; width: 16px; text-align: center;">${isCollapsed ? '▶' : '▼'}</span>` : ''}
                            ${product.parentProductId ? '└─ ' : ''}${product.name}
                        </div>
                        ${product.subcategory ? `<div style="font-size: 12px; color: var(--subtext); ${product.parentProductId ? 'padding-left: 24px;' : ''}">${product.subcategory}</div>` : ''}
                    </td>
               <td data-col="type">${typeBadge}</td>
<td data-col="uom">${product.uomDisplay || '<span style="color: #999;">Not Set</span>'}</td>
<td data-col="category">${product.category}</td>
<td data-col="vendor">${product.vendor || '<span style="color: #999;">Not Set</span>'}</td>
<td data-col="itemCompany">${product.itemCompany || '<span style="color: #999;">-</span>'}</td>
<td data-col="price">${product.price > 0 ? `${product.currencySymbol}${product.price.toFixed(2)}` : 'Free'}</td>
<td data-col="purchasePrice">${product.purchasePrice > 0 ? `${product.currencySymbol}${product.purchasePrice.toFixed(2)}` : '-'}</td>
<td data-col="batchNo">${product.batchNo || '<span style="color: #999;">-</span>'}</td>
<td data-col="batchExpiryDate">${product.batchExpiryDate || '<span style="color: #999;">-</span>'}</td>
<td data-col="stock" class="${stockClass}">${stockStatus}</td>
<td data-col="status">${statusBadge}</td>
                    <td>
                        <div class="action-buttons">
                            ${product.qrCode || product.barcode || isLooseUnitName(product.defaultUnitName) ? `
                            <button class="btn btn-icon btn-ghost print-code" data-id="${product.id}" data-unit-name="${product.defaultUnitName || ''}" data-qr="${product.qrCode || ''}" data-barcode="${product.barcode || ''}" data-name="${product.name}" data-code="${product.code}" data-trade-price="${product.tradePrice}" data-wholesale-price="${product.wholesalePrice}" data-mrp-price="${product.mrp}" data-company="${product.companyName || ''}" data-currency="${product.currencySymbol}" title="Print Code">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 6V2H12V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M4 11H2V7H14V11H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M4 14H12V10H4V14Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            ` : ''}
                            <button class="btn btn-icon btn-ghost edit-product" data-id="${product.id}" title="Edit">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.3333 1.99996C11.5084 1.82485 11.7163 1.686 11.945 1.59124C12.1737 1.49648 12.4189 1.44763 12.6666 1.44763C12.9144 1.44763 13.1596 1.49648 13.3883 1.59124C13.617 1.686 13.8249 1.82485 14 1.99996C14.1751 2.17507 14.3139 2.38297 14.4087 2.61167C14.5035 2.84037 14.5523 3.08556 14.5523 3.33329C14.5523 3.58103 14.5035 3.82622 14.4087 4.05492C14.3139 4.28362 14.1751 4.49152 14 4.66663L5.00004 13.6666L1.33337 14.6666L2.33337 11L11.3333 1.99996Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <button class="btn btn-icon btn-danger delete-product" data-id="${product.id}" title="Delete">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 4H3.33333H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M5.33337 4V2.66667C5.33337 2.31305 5.47385 1.97391 5.7239 1.72386C5.97395 1.47381 6.31309 1.33334 6.66671 1.33334H9.33337C9.68699 1.33334 10.0261 1.47381 10.2762 1.72386C10.5262 1.97391 10.6667 2.31305 10.6667 2.66667V4M12.6667 4V13.3333C12.6667 13.687 12.5262 14.0261 12.2762 14.2761C12.0261 14.5262 11.687 14.6667 11.3334 14.6667H4.66671C4.31309 14.6667 3.97395 14.5262 3.7239 14.2761C3.47385 14.0261 3.33337 13.687 3.33337 13.3333V4H12.6667Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
    });

    // Add event listeners to action buttons
    document.querySelectorAll('.print-code').forEach(button => {
        button.addEventListener('click', function () {
            const qr = this.getAttribute('data-qr');
            const barcode = this.getAttribute('data-barcode');
            const name = this.getAttribute('data-name');
            const code = this.getAttribute('data-code');
            const tradePrice = this.getAttribute('data-trade-price');
            const wholesalePrice = this.getAttribute('data-wholesale-price');
            const mrpPrice = this.getAttribute('data-mrp-price');
            const company = this.getAttribute('data-company');
            const currency = this.getAttribute('data-currency');
            const unitName = this.getAttribute('data-unit-name');
            if (isLooseUnitName(unitName)) {
                // Kg / Litre product: may have Half / Pao / ... packs with their own barcodes
                printCodeWithSubUnits(this.getAttribute('data-id'), unitName, qr, barcode, name, code, tradePrice, company, currency, wholesalePrice, mrpPrice);
            } else {
                printCode(qr, barcode, name, code, tradePrice, company, currency, wholesalePrice, mrpPrice);
            }
        });
    });

    document.querySelectorAll('.edit-product').forEach(button => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-id');
            editProduct(productId);
        });
    });

    document.querySelectorAll('.delete-product').forEach(button => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-id');
            deleteProduct(productId);
        });
    });

    // Add toggle functionality
    document.querySelectorAll('.toggle-children').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const parentId = parseInt(this.dataset.parentId);

            if (collapsedParents.has(parentId)) {
                collapsedParents.delete(parentId);
                this.textContent = '▼';
            } else {
                collapsedParents.add(parentId);
                this.textContent = '▶';
            }

            // Toggle child rows
            document.querySelectorAll(`tr[data-parent-id="${parentId}"]`).forEach(row => {
                row.style.display = collapsedParents.has(parentId) ? 'none' : '';
            });
        });
    });

    // Add checkbox change listeners
    document.querySelectorAll('.product-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const productId = parseInt(this.value);
            if (this.checked) {
                selectedProducts.add(productId);
            } else {
                selectedProducts.delete(productId);
                document.getElementById('selectAll').checked = false;
            }
            updateAssignUomButton();
            updateBulkDeleteButton();
        });
    });

    applyColumnVisibility();
    // Pagination info will be updated by updatePagination function
}

function updateAssignUomButton() {
    const btn = document.getElementById('assignUomBtn');
    const count = document.getElementById('selectedCount');
    count.textContent = selectedProducts.size;
    btn.style.display = selectedProducts.size > 0 ? 'inline-flex' : 'none';
}

// Keeps the "Delete Selected" button in sync with the same selectedProducts
// set that already drives the Assign UOM button
function updateBulkDeleteButton() {
    const btn = document.getElementById('deleteSelectedBtn');
    if (!btn) return;
    const count = document.getElementById('deleteSelectedCount');
    if (count) count.textContent = selectedProducts.size;
    btn.style.display = selectedProducts.size > 0 ? 'inline-flex' : 'none';
}

function openBulkUomModal(showAllProducts = false) {
    const selectedProductsInfo = document.getElementById('selectedProductsInfo');
    const bulkSelectedCount = document.getElementById('bulkSelectedCount');

    if (showAllProducts || selectedProducts.size === 0) {
        // Show all products mode
        selectedProductsInfo.style.display = 'none';
    } else {
        // Show selected products count
        selectedProductsInfo.style.display = 'block';
        bulkSelectedCount.textContent = selectedProducts.size;
    }

    document.getElementById('bulkUomModal').style.display = 'flex';
    document.getElementById('bulkUomTypeUnit').checked = true;
    handleBulkUomTypeChange();
    loadBulkUnits();
    loadBulkUomGroups();
}

function closeBulkUomModal() {
    document.getElementById('bulkUomModal').style.display = 'none';
    document.getElementById('bulkUomForm').reset();
}

function handleBulkUomTypeChange() {
    const isUnit = document.getElementById('bulkUomTypeUnit').checked;
    const defaultUnitGroup = document.getElementById('bulkDefaultUnitGroup');
    const uomGroupField = document.getElementById('bulkUomGroupField');
    const defaultUnitSelect = document.getElementById('bulkDefaultUnit');
    const uomGroupSelect = document.getElementById('bulkUomGroup');

    if (isUnit) {
        defaultUnitGroup.style.display = '';
        uomGroupField.style.display = 'none';
        defaultUnitSelect.setAttribute('required', '');
        uomGroupSelect.removeAttribute('required');
        uomGroupSelect.value = '';
    } else {
        defaultUnitGroup.style.display = 'none';
        uomGroupField.style.display = '';
        defaultUnitSelect.removeAttribute('required');
        defaultUnitSelect.value = '';
        uomGroupSelect.setAttribute('required', '');
    }
}

function loadBulkUnits() {
    fetch('../../../../server/api/inventory/products/unit-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.units) {
                const select = document.getElementById('bulkDefaultUnit');
                select.innerHTML = '<option value="">Select Unit</option>';
                data.units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.uom_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading units:', error));
}

function exportProductsToExcel() {
    if (!products || products.length === 0) {
        alert('No products to export.');
        return;
    }

    // Strip HTML from uomDisplay for a clean spreadsheet value
    const stripHtml = (html) => {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return tmp.textContent.replace(/\s+/g, ' ').trim();
    };

    const rows = products.map(p => ({
        'Product Code': p.code,
        'Name': p.name,
        'Type': p.type === 'physical' ? 'Product' : 'Service',
        'UOM': stripHtml(p.uomDisplay),
        'Category': p.category,
        'Subcategory': p.subcategory || '',
        'Vendor': p.vendor || '',
        'Barcode': p.barcode || '',
        'Current Stock': p.stock !== null ? p.stock : 'N/A',
        'Purchase Price': p.purchasePrice,
        'Trade Price': p.tradePrice,
        'Wholesale Price': p.wholesalePrice,
        'MRP': p.mrp,
        'Batch No': p.batchNo,
        'Batch Expiry Date': p.batchExpiryDate,
    }));

    const worksheet = XLSX.utils.json_to_sheet(rows);

    // Reasonable column widths
    worksheet['!cols'] = [
        { wch: 14 }, { wch: 28 }, { wch: 10 }, { wch: 16 }, { wch: 16 },
        { wch: 16 }, { wch: 16 }, { wch: 18 }, { wch: 12 }, { wch: 14 },
        { wch: 12 }, { wch: 14 }, { wch: 10 }, { wch: 14 }, { wch: 16 }
    ];

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Products');

    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(workbook, `products-export-${dateStr}.xlsx`);
}

function loadBulkUomGroups() {
    fetch('../../../../server/api/inventory/products/uom-group-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.groups) {
                const select = document.getElementById('bulkUomGroup');
                select.innerHTML = '<option value="">Select UOM Group</option>';
                data.groups.forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.id;
                    option.textContent = group.group_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading UOM groups:', error));
}

function handleBulkUomSubmit(e) {
    e.preventDefault();

    const selectedProductsInfo = document.getElementById('selectedProductsInfo');
    const isAllProductsMode = selectedProductsInfo.style.display === 'none';

    if (!isAllProductsMode && selectedProducts.size === 0) {
        alert('Please select at least one product');
        return;
    }

    const formData = new FormData();
    const uomType = document.querySelector('input[name="bulkUomType"]:checked').value;
    formData.append('uomType', uomType);

    if (uomType === 'unit') {
        formData.append('defaultUnit', document.getElementById('bulkDefaultUnit').value);
    } else {
        formData.append('uomGroup', document.getElementById('bulkUomGroup').value);
    }

    if (isAllProductsMode) {
        // Apply to all products
        formData.append('applyToAll', '1');
    } else {
        // Apply to selected products only
        selectedProducts.forEach(id => {
            formData.append('productIds[]', id);
        });
    }

    fetch('../../../../server/api/inventory/products/bulk-uom-assign.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeBulkUomModal();
                selectedProducts.clear();
                document.getElementById('selectAll').checked = false;
                updateAssignUomButton();
                updateBulkDeleteButton();
                loadProducts(currentPage);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while assigning UOM');
        });
}

function applyFilters() {
    loadProducts(1); // Reset to page 1 when filters change
}

function resetFilters() {
    document.getElementById('search').value = '';
    document.getElementById('category').value = '';
    document.getElementById('type').value = '';
    document.getElementById('status').value = '';
    document.getElementById('vendor').value = '';
    document.getElementById('itemCompany').value = '';
    document.getElementById('uomFilter').value = '';
    document.querySelector('input[name="priceType"][value="tp"]').checked = true;
    collapsedParents.clear();

    loadProducts(1);
    document.getElementById('search').focus();
}

function editProduct(productId) {
    window.location.href = `product-add.php?id=${productId}`;
}

function deleteProduct(productId) {
    const product = products.find(p => p.id == productId);
    if (product) {
        showDeleteModal(product.name, productId);
    }
}

function showDeleteModal(productName, productId) {
    document.getElementById('deleteModalMessage').textContent =
        `Are you sure you want to delete "${productName}"? This action cannot be undone.`;

    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';

    // Remove previous event listeners
    const newCancelBtn = document.getElementById('cancelDelete').cloneNode(true);
    const newConfirmBtn = document.getElementById('confirmDelete').cloneNode(true);
    document.getElementById('cancelDelete').parentNode.replaceChild(newCancelBtn, document.getElementById('cancelDelete'));
    document.getElementById('confirmDelete').parentNode.replaceChild(newConfirmBtn, document.getElementById('confirmDelete'));

    // Add new event listeners
    newCancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    newConfirmBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        performDelete(productId);
    });

    // Close button
    document.getElementById('closeDeleteModal').onclick = () => {
        modal.style.display = 'none';
    };

    // Close on outside click
    modal.onclick = (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    };
}

// Same delete modal, reused for the bulk case - shows a count-based message
// and confirms into performBulkDelete instead of performDelete
function showBulkDeleteModal() {
    const count = selectedProducts.size;
    document.getElementById('deleteModalMessage').textContent =
        `Are you sure you want to delete ${count} selected product${count > 1 ? 's' : ''}? This action cannot be undone.`;

    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';

    // Remove previous event listeners
    const newCancelBtn = document.getElementById('cancelDelete').cloneNode(true);
    const newConfirmBtn = document.getElementById('confirmDelete').cloneNode(true);
    document.getElementById('cancelDelete').parentNode.replaceChild(newCancelBtn, document.getElementById('cancelDelete'));
    document.getElementById('confirmDelete').parentNode.replaceChild(newConfirmBtn, document.getElementById('confirmDelete'));

    // Add new event listeners
    newCancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    newConfirmBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        performBulkDelete(Array.from(selectedProducts));
    });

    // Close button
    document.getElementById('closeDeleteModal').onclick = () => {
        modal.style.display = 'none';
    };

    // Close on outside click
    modal.onclick = (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    };
}

function performDelete(productId) {
    fetch(`../../../../server/api/inventory/products/product-delete.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${productId}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Product deleted successfully!');
                loadProducts(); // Refresh the product list
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the product');
        });
}

function performBulkDelete(ids) {
    const body = ids.map(id => `ids[]=${id}`).join('&');

    fetch(`../../../../server/api/inventory/products/product-delete.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                selectedProducts.clear();
                document.getElementById('selectAll').checked = false;
                updateAssignUomButton();
                updateBulkDeleteButton();
                loadProducts(currentPage);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting products');
        });
}

// Formats a number for display: comma-separates the integer part only,
// leaves the decimal part untouched. Previously this ran a comma-insertion
// regex over the ENTIRE string (integer + decimal both), so a value like
// "1028.000000000000" (a long-precision decimal coming back from a SQL SUM)
// got corrupted into "1,028.000,000,000" — commas landing inside the
// decimals too. Splitting on the decimal point first fixes that.
function formatNumber(num) {
    const n = parseFloat(num);
    if (isNaN(n)) return num;

    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join('.');
}

function loadProducts(page = 1) {
    currentPage = page;
    const requestId = ++loadRequestId; // is request ki id yaad rakho
    const priceType = document.querySelector('input[name="priceType"]:checked').value;
    const params = new URLSearchParams({
        search: document.getElementById('search').value.trim(),
        category: document.getElementById('category').value,
        type: document.getElementById('type').value,
        status: document.getElementById('status').value,
        vendor: document.getElementById('vendor').value,
        item_company: document.getElementById('itemCompany').value,
        uom_filter: document.getElementById('uomFilter').value,
        page: page,
        per_page: pageSize
    });

    fetch(`../../../../server/api/inventory/products/product-list.php?${params}`)
        .then(response => response.json())
        .then(data => {
            // Agar is dauran naya search ho chuka hai to purana response ignore karo
            if (requestId !== loadRequestId) return;

            if (data.success) {
                // Reset selection whenever the underlying data set changes
                selectedProducts.clear();

                products = data.products.map(product => {
                    let uomDisplay = '';
                    if (product.uom_type === 'unit' && product.default_unit_name) {
                        uomDisplay = `<span style="color: var(--heading);">${product.default_unit_name}</span><br><span style="font-size: 11px; color: var(--subtext);">Default Unit</span>`;
                    } else if (product.uom_type === 'group' && product.uom_group_name) {
                        uomDisplay = `<span style="color: var(--heading);">${product.uom_group_name}</span><br><span style="font-size: 11px; color: var(--subtext);">UOM Group</span>`;
                    }

                    return {
                        id: product.id,
                        code: product.code,
                        name: product.name,
                        type: product.product_type,
                        uomDisplay: uomDisplay,
                        category: product.category_name || 'Uncategorized',
                        vendor: product.supplier_name || '',
                        itemCompany: product.item_company_name || '',
                        subcategory: product.subcategory_name || '',
                        currentStock: product.current_stock,
                        price: parseFloat(priceType === 'tp' ? product.trade_price : product.mrp),
                        tradePrice: parseFloat(product.trade_price),
                        wholesalePrice: parseFloat(product.wholesale_price),
                        mrp: parseFloat(product.mrp),
                        purchasePrice: parseFloat(product.purchase_price || 0),
                        batchExpiryDate: (product.batch_expiry_date && product.batch_expiry_date !== '0000-00-00') ? product.batch_expiry_date : '',
                        batchNo: product.batch_no || '',
                        stock: (product.no_stock_records == 1) ? null : parseFloat(product.current_stock || 0),
                        status: product.is_active ? 'active' : 'inactive',
                        noStockRecords: product.no_stock_records == 1,
                        currencySymbol: product.currency_symbol || '₹',
                        defaultUnitName: product.uom_type === 'unit' ? (product.default_unit_name || '') : '',
                        parentProductId: product.parent_product_id,
                        qrCode: product.qr_code,
                        barcode: product.barcode,
                        companyName: product.company_name || ''
                    };
                });

                // Update pagination info
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                totalRecords = data.pagination.total_records;

                renderProductTable(products);
                updatePagination();
                document.getElementById('selectAll').checked = false;
                updateAssignUomButton();
                updateBulkDeleteButton();
            } else {
                console.error('Error loading products:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function loadCategoriesFilter() {
    fetch('../../../../server/api/inventory/products/category-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.categories) {
                const select = document.getElementById('category');
                data.categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.category_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading categories:', error));
}

function loadVendorsFilter() {
    fetch('../../../../server/api/inventory/products/supplier-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suppliers) {
                const select = document.getElementById('vendor');
                data.suppliers.forEach(supplier => {
                    const option = document.createElement('option');
                    option.value = supplier.id;
                    option.textContent = supplier.supplier_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading vendors:', error));
}

function updatePagination() {
    const startRecord = totalRecords === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const endRecord = Math.min(currentPage * pageSize, totalRecords);

    document.getElementById('paginationInfo').textContent =
        `Showing ${startRecord}-${endRecord} of ${totalRecords} products`;

    // Update prev/next button states
    document.getElementById('prevPage').disabled = currentPage <= 1;
    document.getElementById('nextPage').disabled = currentPage >= totalPages;

    // Update page numbers
    const paginationControls = document.querySelector('.pagination-controls');
    const pageButtons = paginationControls.querySelectorAll('.pagination-btn:not(#prevPage):not(#nextPage)');
    pageButtons.forEach(btn => btn.remove());

    // Add page number buttons
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => loadProducts(i));

        paginationControls.insertBefore(pageBtn, document.getElementById('nextPage'));
    }
}

function updatePriceTypePreview() {
    const modal = document.getElementById('printCodeModal');
    const priceDisplay = document.querySelector('#printCodeContent .priceDisplay');

    if (!modal || !priceDisplay) {
        console.log('Modal or price display not found');
        return;
    }

    const showPrice = document.getElementById('showPrice').checked;
    const selectedRadio = document.querySelector('input[name="printPriceType"]:checked');

    if (!selectedRadio) {
        console.log('No price type selected');
        return;
    }

    const priceType = selectedRadio.value;
    const currencySymbol = modal.dataset.currencySymbol || 'Rs.';

    let displayPrice = modal.dataset.tradePrice || '0';

    if (priceType === 'wholesale') {
        displayPrice = modal.dataset.wholesalePrice || modal.dataset.tradePrice || '0';
    } else if (priceType === 'mrp') {
        displayPrice = modal.dataset.mrpPrice || modal.dataset.tradePrice || '0';
    }

    if (showPrice) {
        priceDisplay.style.display = 'block';
        priceDisplay.textContent = currencySymbol + ' ' + parseFloat(displayPrice).toFixed(2);
    } else {
        priceDisplay.style.display = 'none';
    }
}

function printCode(qr, barcode, productName, productCode, tradePrice, companyName, currencySymbol, wholesalePrice, mrpPrice) {
    const modal = document.getElementById('printCodeModal');
    const content = document.getElementById('printCodeContent');
    content.innerHTML = '';

    // Add company name at top if exists
    if (companyName) {
        const companyDiv = document.createElement('div');
        companyDiv.style.cssText = 'font-size: 18px; font-weight: 700; color: var(--heading); margin-bottom: 16px; text-align: center;';
        companyDiv.textContent = companyName;
        content.appendChild(companyDiv);
    }

    let canvas;
    let imageData = '';

    if (qr) {
        canvas = document.createElement('canvas');
        canvas.width = 200;
        canvas.height = 200;
        new QRious({ element: canvas, value: qr, size: 200 });
        content.appendChild(canvas);
        imageData = canvas.toDataURL();
    } else if (barcode) {
        canvas = document.createElement('canvas');
        JsBarcode(canvas, barcode, { format: "CODE128", width: 2, height: 80, displayValue: true });
        content.appendChild(canvas);
        imageData = canvas.toDataURL();
    }

    // Add product details below code
    const detailsDiv = document.createElement('div');
    detailsDiv.style.cssText = 'margin-top: 16px; text-align: center; line-height: 1.6;';
    detailsDiv.innerHTML = `
        <div style="font-size: 16px; font-weight: 600; color: var(--heading); margin-bottom: 8px;">${productName}</div>
        <div class="priceDisplay" style="font-size: 15px; font-weight: 700; color: var(--primary);">${currencySymbol} ${parseFloat(tradePrice).toFixed(2)}</div>
    `;
    content.appendChild(detailsDiv);

    modal.style.display = 'flex';
    document.getElementById('printCopies').value = 1;

    // Store prices and currency in dataset for easy access
    modal.dataset.tradePrice = tradePrice.toString();
    modal.dataset.wholesalePrice = (wholesalePrice || tradePrice).toString();
    modal.dataset.mrpPrice = (mrpPrice || tradePrice).toString();
    modal.dataset.currencySymbol = currencySymbol;
    modal.dataset.imageData = imageData;
    modal.dataset.productName = productName;
    modal.dataset.companyName = companyName;

    // Close handlers
    const closePrintCodeModal = document.getElementById('closePrintCodeModal');
    const cancelPrintCode = document.getElementById('cancelPrintCode');

    if (closePrintCodeModal) {
        closePrintCodeModal.onclick = () => modal.style.display = 'none';
    }
    if (cancelPrintCode) {
        cancelPrintCode.onclick = () => modal.style.display = 'none';
    }

    // Setup event listeners for price type and show price changes
    const priceTypeRadios = document.querySelectorAll('input[name="printPriceType"]');
    const showPriceCheckbox = document.getElementById('showPrice');

    // Setup radio button listeners with arrow function to ensure proper context
    priceTypeRadios.forEach(radio => {
        radio.onchange = () => {
            updatePriceTypePreview();
        };
    });

    // Setup checkbox listener
    if (showPriceCheckbox) {
        showPriceCheckbox.onchange = () => {
            updatePriceTypePreview();
        };
    }

    // Set confirmPrintCode handler
    const confirmPrintCode = document.getElementById('confirmPrintCode');
    if (confirmPrintCode) {
        confirmPrintCode.onclick = () => {
            const copies = parseInt(document.getElementById('printCopies').value) || 1;
            const showPrice = document.getElementById('showPrice').checked;
            const priceType = document.querySelector('input[name="printPriceType"]:checked').value;

            let displayPrice = tradePrice;
            if (priceType === 'wholesale') {
                displayPrice = wholesalePrice || tradePrice;
            } else if (priceType === 'mrp') {
                displayPrice = mrpPrice || tradePrice;
            }

            const printWindow = window.open('', '', 'width=800,height=600');
            printWindow.document.write('<html><head><title>Print Code</title>');
            printWindow.document.write('<style>body{font-family:Arial,sans-serif;text-align:center;padding:20px;}.print-item{margin:20px;page-break-inside:avoid;display:inline-block;}.company{font-size:18px;font-weight:700;margin-bottom:10px;}.details{margin-top:10px;}.name{font-size:16px;font-weight:600;margin-bottom:8px;}.price{font-size:15px;font-weight:700;color:#1f7bff;}</style>');
            printWindow.document.write('</head><body>');

            for (let i = 0; i < copies; i++) {
                printWindow.document.write('<div class="print-item">');
                if (companyName) {
                    printWindow.document.write(`<div class="company">${companyName}</div>`);
                }
                if (modal.dataset.imageData) {
                    printWindow.document.write(`<img src="${modal.dataset.imageData}">`);
                }
                printWindow.document.write('<div class="details">');
                printWindow.document.write(`<div class="name">${productName}</div>`);
                if (showPrice) {
                    printWindow.document.write(`<div class="price">${currencySymbol}${parseFloat(displayPrice).toFixed(2)}</div>`);
                }
                printWindow.document.write('</div></div>');
            }

            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
            modal.style.display = 'none';
        };
    }

    modal.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };

    // Update preview on initial load
    updatePriceTypePreview();
}

// Kg / Litre default units can be sold loose in packs (Half Kg, Pao, ...)
function isLooseUnitName(name) {
    return /^(kg|kgs|kilo|kilogram|kilograms|l|ltr|ltrs|liter|litre|liters|litres)\b/i.test((name || '').trim());
}

// Print-code modal for Kg / Litre products: lets the user pick WHICH pack's
// barcode to print (full Kg, Half Kg, Pao, ...). Each pack has its own barcode
// and price; the product itself stays one.
function printCodeWithSubUnits(productId, unitName, qr, barcode, name, code, tradePrice, company, currency, wholesalePrice, mrpPrice) {
    fetch(`../../../../server/api/inventory/products/sub-units-get.php?product_id=${encodeURIComponent(productId)}`)
        .then(response => response.json())
        .then(data => {
            const subUnits = (data.success && data.sub_units ? data.sub_units : []).filter(s => s.barcode);

            if (subUnits.length === 0) {
                if (qr || barcode) {
                    printCode(qr, barcode, name, code, tradePrice, company, currency, wholesalePrice, mrpPrice);
                } else {
                    alert('This product has no barcode or QR code yet. Add one from the Edit page.');
                }
                return;
            }

            const options = [];
            if (qr || barcode) {
                options.push({
                    label: `Full ${unitName}`,
                    qr: qr, barcode: barcode, name: name,
                    trade: tradePrice, wholesale: wholesalePrice, mrp: mrpPrice
                });
            }
            subUnits.forEach(s => {
                options.push({
                    label: s.label,
                    qr: '', barcode: s.barcode, name: `${name} (${s.label})`,
                    trade: s.price, wholesale: s.price, mrp: s.price
                });
            });

            const render = (index) => {
                const opt = options[index];
                printCode(opt.qr, opt.barcode, opt.name, code, opt.trade, company, currency, opt.wholesale, opt.mrp);

                const wrapper = document.createElement('div');
                wrapper.style.cssText = 'margin-bottom: 14px; text-align: left;';
                const label = document.createElement('label');
                label.textContent = 'Print barcode for:';
                label.style.cssText = 'display:block; font-size:13px; margin-bottom:4px;';
                const select = document.createElement('select');
                select.style.cssText = 'width:100%; height:36px; padding:0 10px;';
                options.forEach((o, i) => {
                    const optionEl = document.createElement('option');
                    optionEl.value = i;
                    optionEl.textContent = `${o.label}  (${parseFloat(o.trade || 0).toFixed(2)})`;
                    select.appendChild(optionEl);
                });
                select.value = index;
                select.addEventListener('change', () => render(parseInt(select.value)));
                wrapper.appendChild(label);
                wrapper.appendChild(select);

                const content = document.getElementById('printCodeContent');
                content.insertBefore(wrapper, content.firstChild);
            };

            render(0);
        })
        .catch(error => {
            console.error('Error loading sub-units:', error);
            printCode(qr, barcode, name, code, tradePrice, company, currency, wholesalePrice, mrpPrice);
        });
}

function openAiqModal() {
    const aiqModal = document.getElementById('aiqModal');
    if (!aiqModal) return;

    const mfgDateInput = document.getElementById('aiqMfgDate');
    const expDateInput = document.getElementById('aiqExpDate');
    const copiesInput = document.getElementById('aiqCopies');

    if (mfgDateInput) mfgDateInput.value = '';
    if (expDateInput) expDateInput.value = '';
    if (copiesInput) copiesInput.value = '1';

    aiqModal.style.display = 'flex';
}

function closeAiqModal() {
    const aiqModal = document.getElementById('aiqModal');
    if (aiqModal) aiqModal.style.display = 'none';
}

function handleAiqPrint() {
    const mfgDateInput = document.getElementById('aiqMfgDate');
    const expDateInput = document.getElementById('aiqExpDate');
    const copiesInput = document.getElementById('aiqCopies');

    if (!mfgDateInput || !expDateInput || !copiesInput) return;

    const mfgDate = mfgDateInput.value;
    const expDate = expDateInput.value;
    const copies = parseInt(copiesInput.value) || 1;

    const printWindow = window.open('', '', 'width=800,height=600');
    printWindow.document.write('<html><head><title></title>');
    printWindow.document.write(`<style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 10px; background: white; }
        .page { page-break-after: always; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; padding: 10px; }
        .label { border: 2px solid #333; padding: 12px; width: 100%; box-sizing: border-box; background: white; }
        .label-header { font-size: 11px; font-weight: bold; text-align: center; margin-bottom: 8px; color: #333; }
        .label-row { display: flex; justify-content: space-between; margin: 6px 0; font-size: 10px; }
        .label-key { font-weight: 600; width: 45%; }
        .label-value { border-bottom: 1px solid #333; width: 50%; text-align: center; }
        @media print { body { margin: 0; padding: 5px; } .page { page-break-after: always; } }
    </style>`);
    printWindow.document.write('</head><body>');

    let labelCount = 0;
    let pageLabels = 0;

    for (let i = 0; i < copies; i++) {
        if (labelCount === 0) {
            if (pageLabels > 0) printWindow.document.write('</div>');
            printWindow.document.write('<div class="page">');
            pageLabels++;
        }

        printWindow.document.write('<div class="label">');
        printWindow.document.write('<div class="label-header"></div>');
        if (mfgDate) {
            printWindow.document.write('<div class="label-row"><span class="label-key">Mfg Date:</span><div class="label-value">' + mfgDate + '</div></div>');
        }
        if (expDate) {
            printWindow.document.write('<div class="label-row"><span class="label-key">Exp Date:</span><div class="label-value">' + expDate + '</div></div>');
        }
        printWindow.document.write('</div>');

        labelCount++;
        if (labelCount === 20) {
            labelCount = 0;
        }
    }

    printWindow.document.write('</div></body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
    closeAiqModal();
}

const ALL_COLUMNS = [
    { key: 'code', label: 'Product Code' },
    { key: 'name', label: 'Name' },
    { key: 'type', label: 'Type' },
    { key: 'uom', label: 'UOM' },
    { key: 'category', label: 'Category' },
    { key: 'vendor', label: 'Vendor' },
    { key: 'itemCompany', label: 'Item Company' },
    { key: 'price', label: 'Price' },
    { key: 'purchasePrice', label: 'Purchase Price' },
    { key: 'batchNo', label: 'Batch No' },
    { key: 'batchExpiryDate', label: 'Batch Expiry Date' },
    { key: 'stock', label: 'Stock' },
    { key: 'status', label: 'Status' }
];

function getColumnPrefs() {
    return JSON.parse(localStorage.getItem('productListColumnPrefs') || '{}');
}

function initColumnToggle() {
    const dropdown = document.getElementById('columnsDropdown');
    const btn = document.getElementById('columnsBtn');
    if (!dropdown || !btn) return;

    const prefs = getColumnPrefs();

    dropdown.innerHTML = ALL_COLUMNS.map(col => `
        <label style="display:flex; align-items:center; gap:8px; padding:6px 4px; font-weight:normal; cursor:pointer;">
            <input type="checkbox" class="column-toggle" data-col="${col.key}" ${prefs[col.key] === false ? '' : 'checked'}>
            ${col.label}
        </label>
    `).join('');

    dropdown.querySelectorAll('.column-toggle').forEach(cb => {
        cb.addEventListener('change', function () {
            const currentPrefs = getColumnPrefs();
            currentPrefs[this.dataset.col] = this.checked;
            localStorage.setItem('productListColumnPrefs', JSON.stringify(currentPrefs));
            applyColumnVisibility();
        });
    });

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });

    document.addEventListener('click', function () {
        dropdown.classList.remove('show');
    });
    dropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    applyColumnVisibility();
}

function applyColumnVisibility() {
    const prefs = getColumnPrefs();
    ALL_COLUMNS.forEach(col => {
        const hidden = prefs[col.key] === false;
        document.querySelectorAll(`[data-col="${col.key}"]`).forEach(el => {
            el.style.display = hidden ? 'none' : '';
        });
    });
}

function loadItemCompaniesFilter() {
    fetch('../../../../server/api/inventory/products/item-company-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.item_companies) {
                const select = document.getElementById('itemCompany');
                data.item_companies.forEach(itemCompany => {
                    const option = document.createElement('option');
                    option.value = itemCompany.id;
                    option.textContent = itemCompany.company_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading item companies:', error));
}