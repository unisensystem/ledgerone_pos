// Tax Integration for Purchase Invoice
document.addEventListener('DOMContentLoaded', function() {
    const itemsTable = document.getElementById('itemsTable');
    if (!itemsTable) return;
    
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    // Monitor for product selection via data attribute
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-product-id') {
                const row = mutation.target;
                if (row.dataset.productId) {
                    setTimeout(() => loadTaxForProduct(row), 100);
                }
            }
        });
    });
    
    observer.observe(tbody, { attributes: true, subtree: true, attributeFilter: ['data-product-id'] });
    
    async function loadTaxForProduct(row) {
        const supplierId = document.getElementById('supplierCode')?.value;
        const productId = row.dataset.productId;
        
        console.log('Loading tax - Supplier:', supplierId, 'Product:', productId);
        
        if (!supplierId || !productId) {
            console.warn('Missing supplier or product ID');
            return;
        }
        
        try {
            const taxResult = await calculateProductTax(supplierId, productId);
            console.log('Tax result:', taxResult);
            
            if (taxResult.success) {
                row.dataset.taxRate = taxResult.tax_rate;
                row.dataset.basePrice = taxResult.base_price;
                row.dataset.formulaTemplate = taxResult.formula_template;
                row.dataset.taxBase = taxResult.tax_base;
                
                const taxPercentInput = row.querySelector('.tax-percent-cell input');
                if (taxPercentInput) {
                    taxPercentInput.value = parseFloat(taxResult.tax_rate).toFixed(3);
                    taxPercentInput.readOnly = false;
                    taxPercentInput.disabled = false;
                    console.log('Tax % set to:', taxPercentInput.value);
                }
                
                // Trigger recalculation
                const unitInputs = row.querySelectorAll('.unit-input');
                if (unitInputs.length > 0) {
                    unitInputs[0].dispatchEvent(new Event('input'));
                }
            } else {
                console.warn('Tax calculation failed:', taxResult.message);
            }
        } catch (error) {
            console.error('Error loading tax:', error);
        }
    }
    
    // Reload tax when supplier changes
    document.addEventListener('change', function(e) {
        if (e.target.id === 'supplierCode' && e.target.value) {
            const supplierId = e.target.value;
            const rows = tbody.rows;
            for (let i = 0; i < rows.length; i++) {
                const productId = rows[i].dataset.productId;
                if (productId) {
                    loadTaxForProduct(rows[i]);
                }
            }
        }
    });
    
    // Expose function globally for manual calls
    window.loadTaxForProduct = loadTaxForProduct;
});
