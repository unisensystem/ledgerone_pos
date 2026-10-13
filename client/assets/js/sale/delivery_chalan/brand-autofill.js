// Brand autofill functionality for POS Invoice
document.addEventListener('DOMContentLoaded', function() {
    // Wait for main script to load customers data
    const checkAndSetupBrandAutofill = setInterval(function() {
        if (typeof customersData !== 'undefined' && customersData.length > 0) {
            clearInterval(checkAndSetupBrandAutofill);
            setupBrandAutofill();
        }
    }, 100);
});

function setupBrandAutofill() {
    // Override the customer selection event to include brand autofill
    const originalListener = document.addEventListener;
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-option') && e.target.closest('#customerCodeOptions')) {
            const customerId = e.target.getAttribute('data-value');
            if (customerId) {
                // Auto-populate brand from customer's brand_id
                const customer = customersData.find(c => c.id == customerId);
                if (customer && customer.brand_id) {
                    const brandSelect = document.getElementById('brand');
                    if (brandSelect) {
                        brandSelect.value = customer.brand_id;
                    }
                }
            }
        }
    });
}
