file = r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/sale/pos_invoice/pos-add.js'

with open(file, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix 1: function signature
old1 = 'async function preloadNextInvoiceNumber() {'
new1 = 'async function preloadNextInvoiceNumber(companyId = null) {'
assert old1 in content, 'Fix1 NOT FOUND'
content = content.replace(old1, new1, 1)

# Fix 2: fetch URL -> use companyId
old2 = "        const response = await fetch('../../../../server/api/sale/pos_invoice/get-next-invoice-number.php');"
new2 = "        const url = companyId\n            ? `../../../../server/api/sale/pos_invoice/get-next-invoice-number.php?company_id=${companyId}`\n            : '../../../../server/api/sale/pos_invoice/get-next-invoice-number.php';\n        const response = await fetch(url);"
assert old2 in content, 'Fix2 NOT FOUND'
content = content.replace(old2, new2, 1)

# Fix 3: call preloadNextInvoiceNumber on company change
old3 = '                    loadCustomers(value);\n                    loadProducts(value);\n\n                    // Clear current customer selection'
new3 = '                    loadCustomers(value);\n                    loadProducts(value);\n\n                    // Reload invoice number for selected company\n                    preloadNextInvoiceNumber(value);\n\n                    // Clear current customer selection'
assert old3 in content, 'Fix3 NOT FOUND'
content = content.replace(old3, new3, 1)

with open(file, 'w', encoding='utf-8') as f:
    f.write(content)

print('All fixes applied successfully.')
