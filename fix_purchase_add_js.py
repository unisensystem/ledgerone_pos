f = open(r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/purchase/purchase_invoice/purchase-add.js', 'r', encoding='utf-8')
d = f.read()
f.close()

# Fix 1: loadSuppliers
old1 = "    async function loadSuppliers() {\n        try {\n            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-suppliers.php');"
new1 = "    async function loadSuppliers(companyId = null) {\n        try {\n            const url = companyId\n                ? `../../../../server/api/purchase/purchase_invoice/get-suppliers.php?company_id=${companyId}`\n                : '../../../../server/api/purchase/purchase_invoice/get-suppliers.php';\n            const response = await fetch(url);"
print('Fix1 found:', old1 in d)
d = d.replace(old1, new1, 1)

# Fix 2: loadProducts
old2 = "    // Load products from API\n    async function loadProducts() {\n        try {\n            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-products.php');"
new2 = "    // Load products from API\n    async function loadProducts(companyId = null) {\n        try {\n            const url = companyId\n                ? `../../../../server/api/purchase/purchase_invoice/get-products.php?company_id=${companyId}`\n                : '../../../../server/api/purchase/purchase_invoice/get-products.php';\n            const response = await fetch(url);"
print('Fix2 found:', old2 in d)
d = d.replace(old2, new2, 1)

# Fix 3: loadNextBillNo function — add it before company change handler
old3 = "    // Add event listener for company selection to reload invoice-level taxes"
new3 = "    // Load next bill number per company\n    async function loadNextBillNo(companyId = null) {\n        try {\n            const url = companyId\n                ? `../../../../server/api/purchase/purchase_invoice/purchase-next-bill-no.php?company_id=${companyId}`\n                : '../../../../server/api/purchase/purchase_invoice/purchase-next-bill-no.php';\n            const res = await fetch(url);\n            const data = await res.json();\n            if (data.success) document.getElementById('billNo').value = data.bill_no;\n        } catch(e) { console.error('loadNextBillNo error:', e); }\n    }\n\n    // Add event listener for company selection to reload invoice-level taxes"
print('Fix3 found:', old3 in d)
d = d.replace(old3, new3, 1)

# Fix 4: company change — reload suppliers, products, bill number
old4 = "    document.getElementById('company').addEventListener('change', function() {\n        const supplierId = document.getElementById('supplierCode').value;\n        const companyId = this.value;\n        if (supplierId && window.loadInvoiceLevelTaxRegimes) {\n            window.loadInvoiceLevelTaxRegimes(supplierId, companyId);\n        }\n    });"
new4 = "    document.getElementById('company').addEventListener('change', function() {\n        const supplierId = document.getElementById('supplierCode').value;\n        const companyId = this.value;\n        if (supplierId && window.loadInvoiceLevelTaxRegimes) {\n            window.loadInvoiceLevelTaxRegimes(supplierId, companyId);\n        }\n        loadSuppliers(companyId);\n        loadProducts(companyId);\n        document.getElementById('supplierCodeSearch').value = '';\n        document.getElementById('supplierCode').value = '';\n        loadNextBillNo(companyId);\n    });"
print('Fix4 found:', old4 in d)
d = d.replace(old4, new4, 1)

f = open(r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/purchase/purchase_invoice/purchase-add.js', 'w', encoding='utf-8')
f.write(d)
f.close()
print('Done')
