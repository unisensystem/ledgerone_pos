// Stock validation for invoice saving
// This function is called before saveInvoice to validate stock levels

function validateStockBeforeSave() {
    const itemsTable = document.getElementById('itemsTable')?.getElementsByTagName('tbody')[0];
    if (!itemsTable) return true;

    const rows = itemsTable.rows;
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];

        // Skip child rows
        if (row.classList.contains('child-row')) continue;

        const unitInputs = row.querySelectorAll('.unit-input');
        let totalQty = 0;
        
        unitInputs.forEach(input => {
            const qty = parseFloat(input.value) || 0;
            const cf = parseFloat(input.dataset.conversionFactor) || 1;
            totalQty += qty * cf;
        });

        // Check if required quantity exceeds available stock
        const availableStock = parseFloat(row.dataset.availableStock) || 0;
        const productName = row.cells[1]?.querySelector('.search-input')?.value || 'Unknown Product';
        
        if (totalQty > 0 && availableStock > 0 && totalQty > availableStock) {
            alert(`❌ Stock Validation Error!\n\nProduct: ${productName}\nRequired: ${totalQty.toFixed(2)} units\nAvailable: ${availableStock.toFixed(2)} units\n\nPlease reduce the quantity or check stock availability.`);
            return false;
        }
    }
    
    return true;
}
