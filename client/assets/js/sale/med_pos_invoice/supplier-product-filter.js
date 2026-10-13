// SIMPLE PRODUCT FILTERING - MINIMAL WORKING VERSION
// ===================================================

let productFilteringMode = 'showAll';
let currentSalesOfficerId = null;
let currentBrandId = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✓ Product Filter Initialized');
    
    // Load saved mode
    const saved = localStorage.getItem('productFilteringMode');
    if (saved) {
        productFilteringMode = saved;
        const radio = document.querySelector(`input[name="productFilteringMode"][value="${saved}"]`);
        if (radio) radio.checked = true;
    }
    
    // Setup listeners
    setupRadioListeners();
    setupSalesOfficerListener();
    setupCustomerListener();
    setupBrandListener();
});

// Setup radio button listeners
function setupRadioListeners() {
    const radios = document.querySelectorAll('input[name="productFilteringMode"]');
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            productFilteringMode = this.value;
            localStorage.setItem('productFilteringMode', this.value);
            console.log('Mode changed to:', this.value);
            
            // Apply filtering immediately
            const salesOfficer = document.getElementById('salesOfficer');
            if (salesOfficer && salesOfficer.value) {
                applyFilter(salesOfficer.value);
            }
        });
    });
}

// Setup sales officer listener
function setupSalesOfficerListener() {
    const salesOfficer = document.getElementById('salesOfficer');
    if (!salesOfficer) return;
    
    salesOfficer.addEventListener('change', function() {
        currentSalesOfficerId = this.value;
        localStorage.setItem('lastSelectedSalesOfficer', this.value);
        console.log('Sales Officer changed to:', this.value, 'Mode:', productFilteringMode);
        
        if (this.value && productFilteringMode === 'salesOfficerFilter') {
            applyFilter(this.value);
        } else {
            showAllProducts();
        }
    });
}

// Setup customer listener for auto-populate
function setupCustomerListener() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-option') && e.target.closest('#customerCodeOptions')) {
            // Was reading data-supplier-man here, which overwrote the Sales Officer field
            // with the customer's Supplier Man id — a different person — silently clobbering
            // whatever the user had actually selected as Sales Officer (manually or via the
            // correct customer-default auto-fill in pos-add.js's own click handler).
            const salesOfficerId = e.target.getAttribute('data-sales-officer');
            console.log('Customer clicked, data-sales-officer:', salesOfficerId);

            if (salesOfficerId && salesOfficerId !== 'null' && salesOfficerId !== '') {
                const salesOfficer = document.getElementById('salesOfficer');
                if (salesOfficer) {
                    console.log('Setting Sales Officer to:', salesOfficerId);
                    salesOfficer.value = salesOfficerId;
                    localStorage.setItem('lastSelectedSalesOfficer', salesOfficerId);
                    currentSalesOfficerId = salesOfficerId;
                    console.log('Auto-populated Sales Officer:', salesOfficerId, 'Mode:', productFilteringMode);

                    // Directly apply filter if mode is salesOfficerFilter
                    if (productFilteringMode === 'salesOfficerFilter') {
                        console.log('Mode is salesOfficerFilter, calling applyFilter');
                        applyFilter(salesOfficerId);
                    } else {
                        console.log('Mode is showAll, calling showAllProducts');
                        showAllProducts();
                    }

                    salesOfficer.dispatchEvent(new Event('change'));
                }
            } else {
                console.log('No sales officer found or empty');
            }
        }
    });
}

// Setup brand listener
function setupBrandListener() {
    const brandSelect = document.getElementById('brand');
    if (!brandSelect) return;

    brandSelect.addEventListener('change', function () {
        currentBrandId = this.value || null;
        if (currentBrandId) {
            applyBrandFilter(currentBrandId);
        } else {
            showAllProducts();
        }
    });
}

// Apply brand filter
async function applyBrandFilter(brandId) {
    console.log('Applying brand filter for brand:', brandId);
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-products-by-brand.php?brand_id=${brandId}`);
        const data = await response.json();

        if (data.success) {
            console.log('Brand filter: loaded', data.products.length, 'products');
            data.products.forEach(product => {
                window.dataCache.products.set(parseInt(product.id), product);
            });
            window.filteredProducts = data.products;
            updateAllDropdowns(data.products);
        } else {
            console.error('Brand filter error:', data.message);
            showAllProducts();
        }
    } catch (error) {
        console.error('Brand filter fetch error:', error);
        showAllProducts();
    }
}

// Apply filtering
async function applyFilter(salesOfficerId) {
    console.log('Applying filter for Sales Officer:', salesOfficerId);
    
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-products-by-sales-officer.php?sales_officer_id=${salesOfficerId}`);
        const data = await response.json();
        
        if (data.success) {
            console.log('Loaded', data.products.length, 'products');
            // Store filtered products in dataCache so they can be accessed when selected
            data.products.forEach(product => {
                window.dataCache.products.set(parseInt(product.id), product);
            });
            updateAllDropdowns(data.products);
        } else {
            console.error('Error:', data.message);
            showAllProducts();
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showAllProducts();
    }
}

// Show all products
function showAllProducts() {
    console.log('Showing all products');
    window.filteredProducts = null;
    currentBrandId = null;
    if (typeof productsData !== 'undefined') {
        productsData.forEach(product => {
            window.dataCache.products.set(parseInt(product.id), product);
        });
        updateAllDropdowns(productsData);
    }
}

// Update all dropdowns
function updateAllDropdowns(products) {
    const table = document.getElementById('itemsTable');
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    const rows = tbody.rows;
    for (let i = 0; i < rows.length; i++) {
        const searchInput = rows[i].cells[1]?.querySelector('.search-input');
        if (searchInput && searchInput.dropdownOptions) {
            updateDropdown(searchInput.dropdownOptions, products);
        }
    }
}

// Update single dropdown
function updateDropdown(dropdownElement, products) {
    // Add products to dataCache so they can be accessed when selected
    products.forEach(p => {
        window.dataCache.products.set(parseInt(p.id), p);
    });

    const displayMode = localStorage.getItem('productDisplayMode') || 'code_and_name';
    const html = products.map(p => {
        const codes = [];
        if (p.qr_code) codes.push(p.qr_code);
        if (p.barcode) codes.push(p.barcode);
        const codeDisplay = displayMode === 'code_and_name' && codes.length > 0 ? ` (${codes.join(' - ')})` : '';
        const photo = p.photo ? `<img src="../../../assets/uploads/products/${p.photo}" alt="${p.name}" style="width: 30px; height: 30px; object-fit: cover; margin-right: 8px; border-radius: 4px;">` : '';
        let displayText = '';
        if (displayMode === 'code_only') {
            displayText = p.code;
        } else if (displayMode === 'name_only') {
            displayText = p.name;
        } else {
            displayText = `${p.code} - ${p.name}`;
        }
        return `<div class="dropdown-option" data-product='${JSON.stringify(p)}' style="display: flex; align-items: center;">${photo}${displayText}${codeDisplay}</div>`;
    }).join('');

    dropdownElement.innerHTML = html;
}

// Hook into addRowDynamic
const origAddRow = window.addRowDynamic;
if (origAddRow) {
    window.addRowDynamic = function() {
        origAddRow.call(this);
        
        setTimeout(() => {
            const table = document.getElementById('itemsTable');
            if (table) {
                const tbody = table.getElementsByTagName('tbody')[0];
                if (tbody && tbody.rows.length > 0) {
                    const lastRow = tbody.rows[tbody.rows.length - 1];
                    const searchInput = lastRow.cells[1]?.querySelector('.search-input');
                    
                    if (searchInput && searchInput.dropdownOptions) {
                        const products = (productFilteringMode === 'salesOfficerFilter' && currentSalesOfficerId)
                            ? window.filteredProducts || productsData
                            : (currentBrandId ? window.filteredProducts || productsData : productsData);
                        updateDropdown(searchInput.dropdownOptions, products);
                    }
                }
            }
        }, 100);
    };
}

console.log('✓ Product Filter Script Loaded');
