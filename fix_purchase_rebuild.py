f = open(r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/purchase/purchase_invoice/purchase-add.js', 'r', encoding='utf-8')
d = f.read()
f.close()

# Add rebuildProductDropdowns call after loadProducts in company change handler
old1 = "        // Reload suppliers and products filtered by selected company\n        loadSuppliers(companyId);\n        loadProducts(companyId);"
new1 = "        // Reload suppliers and products filtered by selected company\n        loadSuppliers(companyId);\n        loadProducts(companyId).then(() => rebuildProductDropdowns());"
print('Fix1 found:', old1 in d)
d = d.replace(old1, new1, 1)

# Add rebuildProductDropdowns function before updateInvoiceSummaryFromAmount
old2 = "    function updateInvoiceSummaryFromAmount() {"
new2 = """    function rebuildProductDropdowns() {
        const tbody = document.getElementById('itemsTable')?.getElementsByTagName('tbody')[0];
        if (!tbody) return;
        Array.from(tbody.rows).forEach(row => {
            const searchInput = row.querySelector('.search-input');
            if (!searchInput || !searchInput.dropdownOptions) return;
            const optionsContainer = searchInput.dropdownOptions;
            optionsContainer.innerHTML = productsData.map(product => {
                return `<div class="dropdown-option" data-product='${JSON.stringify(product)}' style="display:flex;align-items:center;">${product.code} - ${product.name}</div>`;
            }).join('');
        });
    }

    function updateInvoiceSummaryFromAmount() {"""
print('Fix2 found:', old2 in d)
d = d.replace(old2, new2, 1)

f = open(r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/purchase/purchase_invoice/purchase-add.js', 'w', encoding='utf-8')
f.write(d)
f.close()
print('Done')
