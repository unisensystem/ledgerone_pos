f = open(r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/purchase/purchase_invoice/purchase-add.js', 'r', encoding='utf-8')
d = f.read()
f.close()

# Remove loadNextBillNo function
old1 = """    // Load next bill number per company
    async function loadNextBillNo(companyId = null) {
        try {
            const url = companyId
                ? `../../../../server/api/purchase/purchase_invoice/purchase-next-bill-no.php?company_id=${companyId}`
                : '../../../../server/api/purchase/purchase_invoice/purchase-next-bill-no.php';
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) document.getElementById('billNo').value = data.bill_no;
        } catch(e) { console.error('loadNextBillNo error:', e); }
    }

    // Add event listener for company selection to reload invoice-level taxes"""
new1 = "    // Add event listener for company selection to reload invoice-level taxes"
print('Fix1 found:', old1 in d)
d = d.replace(old1, new1, 1)

# Remove loadNextBillNo call from company change handler
old2 = "        // Reload bill number for selected company\n        loadNextBillNo(companyId);\n    });"
new2 = "    });"
print('Fix2 found:', old2 in d)
d = d.replace(old2, new2, 1)

f = open(r'c:/xampp/htdocs/ledgerone_erp/client/assets/js/purchase/purchase_invoice/purchase-add.js', 'w', encoding='utf-8')
f.write(d)
f.close()
print('Done')
