// Tax Integration for Product Selection
// This script ensures tax is loaded when a product is selected

document.addEventListener('DOMContentLoaded', function() {
    // Monitor for product selection in the items table
    const itemsTable = document.getElementById('itemsTable');
    if (!itemsTable) return;
    
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    // Setup existing rows immediately
    const rows = tbody.rows;
    for (let i = 0; i < rows.length; i++) {
        setupRowTaxLoading(rows[i]);
        // Load tax for rows that already have products
        const itemCodeInput = rows[i].querySelector('.item-code');
        if (itemCodeInput && itemCodeInput.value) {
            loadTaxForProductSelection(rows[i]);
        }
    }
    
    // Use MutationObserver to detect when rows are added or product codes change
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // New row added
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.tagName === 'TR') {
                        setupRowTaxLoading(node);
                    }
                });
            } else if (mutation.type === 'attributes') {
                // Attribute changed (like value)
                if (mutation.target.classList && mutation.target.classList.contains('item-code')) {
                    const row = mutation.target.closest('tr');
                    if (row && mutation.target.value) {
                        loadTaxForProductSelection(row);
                    }
                }
            }
        });
    });
    
    observer.observe(tbody, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
});

function setupRowTaxLoading(row) {
    const itemCodeInput = row.querySelector('.item-code');
    if (!itemCodeInput) return;
    
    // Listen for product code changes
    itemCodeInput.addEventListener('change', function() {
        if (this.value) {
            loadTaxForProductSelection(row);
        }
    });
}

async function loadTaxForProductSelection(row) {
    const customerId = document.getElementById('customerCode')?.value;
    const productId = row.querySelector('.item-code')?.value;
    
    if (!customerId || !productId) return;
    
    console.log('Loading tax for product:', productId, 'customer:', customerId);
    
    // Call the tax loading function
    if (typeof loadTaxForNewProduct === 'function') {
        await loadTaxForNewProduct(row, customerId, productId);
        
        console.log('Tax loaded. Formula:', row.dataset.formulaTemplate, 'BasePrice:', row.dataset.basePrice);
        
        // Trigger recalculation
        const unitInputs = row.querySelectorAll('.unit-input');
        if (unitInputs.length > 0) {
            unitInputs[0].dispatchEvent(new Event('input'));
        }
    }
}

// Also listen for customer changes to reload tax for all products
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('dropdown-option') && e.target.closest('#customerCodeOptions')) {
        // Customer changed, reload tax for all rows
        setTimeout(function() {
            const customerId = document.getElementById('customerCode')?.value;
            if (customerId && typeof loadTaxRatesForCustomer === 'function') {
                console.log('Customer changed, reloading tax for all rows');
                loadTaxRatesForCustomer(customerId);
            }
        }, 100);
    }
});
