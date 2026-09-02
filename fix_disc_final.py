data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()

old = (
    b"discPercentInput.addEventListener('input', function() {\r\n"
    b"        const unitInputs = row.querySelectorAll('.unit-input');\r\n"
    b"        let totalQty = 0;\r\n"
    b"        unitInputs.forEach(input => {\r\n"
    b"            const qty = parseFloat(input.value) || 0;\r\n"
    b"            const cf = parseFloat(input.dataset.conversionFactor) || 1;\r\n"
    b"            totalQty = totalQty + (qty * cf);\r\n"
    b"        });\r\n"
    b"        const price = parseFloat(row.querySelector('.price-cell input').value) || 0;\r\n"
    b"        calculateRowAmounts(row, totalQty, price);\r\n"
    b"    });"
)

new = (
    b"discPercentInput.addEventListener('input', function() {\r\n"
    b"        const unitInputs = row.querySelectorAll('.unit-input');\r\n"
    b"        let totalQty = 0;\r\n"
    b"        unitInputs.forEach(input => {\r\n"
    b"            const qty = parseFloat(input.value) || 0;\r\n"
    b"            const cf = parseFloat(input.dataset.conversionFactor) || 1;\r\n"
    b"            totalQty = totalQty + (qty * cf);\r\n"
    b"        });\r\n"
    b"        const price = parseFloat(row.querySelector('.price-cell input').value) || 0;\r\n"
    b"        calculateRowAmounts(row, totalQty, price);\r\n"
    b"        validateDiscVsPurchase(row);\r\n"
    b"    });"
)

if old in data:
    data = data.replace(old, new, 1)
    open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'wb').write(data)
    print("OK: disc% listener patched")
else:
    print("FAIL: not found")
