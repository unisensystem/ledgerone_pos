import re

results = []

def patch(filepath, old, new, label):
    data = open(filepath, 'rb').read()
    if old.encode('utf-8') in data:
        data = data.replace(old.encode('utf-8'), new.encode('utf-8'), 1)
        open(filepath, 'wb').write(data)
        results.append(f"OK: {label}")
        return True
    else:
        results.append(f"FAIL: {label}")
        return False

# ── 1. get-purchase-history.php — add discount_percent & extra_discount_percent ──
api = r'C:\xampp\htdocs\ledgerone_erp\server\api\sale\pos_invoice\get-purchase-history.php'
patch(api,
    "        SELECT \n            pi.purchase_date,\n            pii.purchase_price,\n            pii.quantity,\n            cur.symbol as currency_symbol",
    "        SELECT \n            pi.purchase_date,\n            pii.purchase_price,\n            pii.quantity,\n            pii.discount_percent,\n            pii.extra_discount_percent,\n            pii.batch_no,\n            cur.symbol as currency_symbol",
    "get-purchase-history.php: add discount fields"
)

# ── 2. pos-add.js — hide Last 3 Purchase Prices section ──────────────────────
js = r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js'
data = open(js, 'rb').read()

# Hide purchaseHistoryInner permanently — just never show it
old2 = b"document.getElementById('purchaseHistoryInner').style.display = 'block';"
new2 = b"document.getElementById('purchaseHistoryInner').style.display = 'none'; // hidden by design"
if old2 in data:
    data = data.replace(old2, new2, 1)
    results.append("OK: pos-add.js hide purchaseHistoryInner")
else:
    results.append("FAIL: pos-add.js hide purchaseHistoryInner")

open(js, 'wb').write(data)

# ── 3. pos-add.js — store purchase disc data on row when product selected ─────
# After loadPurchaseHistory stores latestPurchasePrice, also store disc data
data = open(js, 'rb').read()

old3 = (
    b"            const latestPurchasePrice = parseFloat(data.history[0].purchase_price);\r\n"
    b"            const content = data.history.map((item, index) => {"
)
new3 = (
    b"            const latestPurchasePrice = parseFloat(data.history[0].purchase_price);\r\n"
    b"            const latestDiscPercent = parseFloat(data.history[0].discount_percent || 0);\r\n"
    b"            const latestExtraDiscPercent = parseFloat(data.history[0].extra_discount_percent || 0);\r\n"
    b"            const latestBatchNo = data.history[0].batch_no || '';\r\n"
    b"            const content = data.history.map((item, index) => {"
)
if old3 in data:
    data = data.replace(old3, new3, 1)
    results.append("OK: pos-add.js store disc data from history")
else:
    results.append("FAIL: pos-add.js store disc data from history")

# Store on row dataset
old4 = (
    b"                        rows[i].dataset.latestPurchasePrice = latestPurchasePrice;\r\n"
    b"                        validateSalePriceVsPurchase(rows[i]);"
)
new4 = (
    b"                        rows[i].dataset.latestPurchasePrice = latestPurchasePrice;\r\n"
    b"                        rows[i].dataset.purchaseDiscPercent = latestDiscPercent;\r\n"
    b"                        rows[i].dataset.purchaseExtraDiscPercent = latestExtraDiscPercent;\r\n"
    b"                        rows[i].dataset.purchaseBatchNo = latestBatchNo;\r\n"
    b"                        validateSalePriceVsPurchase(rows[i]);"
)
if old4 in data:
    data = data.replace(old4, new4, 1)
    results.append("OK: pos-add.js store disc on row dataset")
else:
    results.append("FAIL: pos-add.js store disc on row dataset")

open(js, 'wb').write(data)

# ── 4. pos-add.js — add validateDiscVsPurchase call on disc% and extraDisc% input ──
data = open(js, 'rb').read()

# Find disc-percent-cell input event in addRowDynamic — after calculateRowAmounts call add validation
# The disc percent input listener in addRowDynamic calls calculateRowAmounts then we add validation
old5 = (
    b"        discPercentInput.addEventListener('input', function() {\r\n"
    b"            const unitInputs = row.querySelectorAll('.unit-input');\r\n"
    b"            let totalQty = Number(0);\r\n"
    b"            unitInputs.forEach(input => {\r\n"
    b"                const qty = Number(input.value) || 0;\r\n"
    b"                const cf = Number(input.dataset.conversionFactor) || 1;\r\n"
    b"                totalQty = Number(totalQty) + Number(qty * cf);\r\n"
    b"            });\r\n"
    b"            const price = Number(row.querySelector('.price-cell input').value) || 0;\r\n"
    b"            calculateRowAmounts(row, Number(totalQty), price);\r\n"
    b"        });"
)
new5 = (
    b"        discPercentInput.addEventListener('input', function() {\r\n"
    b"            const unitInputs = row.querySelectorAll('.unit-input');\r\n"
    b"            let totalQty = Number(0);\r\n"
    b"            unitInputs.forEach(input => {\r\n"
    b"                const qty = Number(input.value) || 0;\r\n"
    b"                const cf = Number(input.dataset.conversionFactor) || 1;\r\n"
    b"                totalQty = Number(totalQty) + Number(qty * cf);\r\n"
    b"            });\r\n"
    b"            const price = Number(row.querySelector('.price-cell input').value) || 0;\r\n"
    b"            calculateRowAmounts(row, Number(totalQty), price);\r\n"
    b"            validateDiscVsPurchase(row);\r\n"
    b"        });"
)
if old5 in data:
    data = data.replace(old5, new5, 1)
    results.append("OK: pos-add.js disc% input validation call")
else:
    results.append("FAIL: pos-add.js disc% input validation call")

open(js, 'wb').write(data)

# ── 5. pos-add.js — add extraDisc% input validation call ─────────────────────
data = open(js, 'rb').read()

# Find extraDiscInput listener — it calls calculateRowAmounts, add validateDiscVsPurchase after
old6 = (
    b"        extraDiscInput.addEventListener('input', function() {\r\n"
    b"            const unitInputs = row.querySelectorAll('.unit-input');\r\n"
    b"            let totalQty = Number(0);\r\n"
    b"            unitInputs.forEach(input => {\r\n"
    b"                const qty = Number(input.value) || 0;\r\n"
    b"                const cf = Number(input.dataset.conversionFactor) || 1;\r\n"
    b"                totalQty = Number(totalQty) + Number(qty * cf);\r\n"
    b"            });\r\n"
    b"            const price = Number(row.querySelector('.price-cell input').value) || 0;\r\n"
    b"            calculateRowAmounts(row, Number(totalQty), price);\r\n"
    b"        });"
)
new6 = (
    b"        extraDiscInput.addEventListener('input', function() {\r\n"
    b"            const unitInputs = row.querySelectorAll('.unit-input');\r\n"
    b"            let totalQty = Number(0);\r\n"
    b"            unitInputs.forEach(input => {\r\n"
    b"                const qty = Number(input.value) || 0;\r\n"
    b"                const cf = Number(input.dataset.conversionFactor) || 1;\r\n"
    b"                totalQty = Number(totalQty) + Number(qty * cf);\r\n"
    b"            });\r\n"
    b"            const price = Number(row.querySelector('.price-cell input').value) || 0;\r\n"
    b"            calculateRowAmounts(row, Number(totalQty), price);\r\n"
    b"            validateDiscVsPurchase(row);\r\n"
    b"        });"
)
if old6 in data:
    data = data.replace(old6, new6, 1)
    results.append("OK: pos-add.js extraDisc% input validation call")
else:
    results.append("FAIL: pos-add.js extraDisc% input validation call")

open(js, 'wb').write(data)

# ── 6. pos-add.js — add validateDiscVsPurchase function after validateSalePriceVsPurchase ──
data = open(js, 'rb').read()

marker = b"// Validate sale price vs latest purchase price\r\n// Load product stock"
new_func = (
    b"// Validate disc% and extra disc% vs purchase disc%\r\n"
    b"function validateDiscVsPurchase(row) {\r\n"
    b"    const purchaseDisc = parseFloat(row.dataset.purchaseDiscPercent);\r\n"
    b"    const purchaseExtraDisc = parseFloat(row.dataset.purchaseExtraDiscPercent);\r\n"
    b"    const purchaseBatch = row.dataset.purchaseBatchNo || '';\r\n"
    b"\r\n"
    b"    const discInput = row.querySelector('.disc-percent-cell input');\r\n"
    b"    const extraDiscInput = row.querySelector('.extra-disc-percent-cell input');\r\n"
    b"\r\n"
    b"    // Remove old warnings\r\n"
    b"    row.querySelectorAll('.disc-warning').forEach(el => el.remove());\r\n"
    b"\r\n"
    b"    if (discInput && !isNaN(purchaseDisc) && purchaseDisc > 0) {\r\n"
    b"        const saleDisc = parseFloat(discInput.value) || 0;\r\n"
    b"        if (saleDisc > purchaseDisc) {\r\n"
    b"            const batchInfo = purchaseBatch ? ` (Batch: ${purchaseBatch})` : '';\r\n"
    b"            const warn = document.createElement('div');\r\n"
    b"            warn.className = 'disc-warning';\r\n"
    b"            warn.style.cssText = 'color:#e53e3e;font-size:11px;margin-top:2px;';\r\n"
    b"            warn.textContent = `\u26a0\ufe0f Purchase Disc was ${purchaseDisc.toFixed(2)}%${batchInfo}`;\r\n"
    b"            discInput.parentNode.appendChild(warn);\r\n"
    b"        }\r\n"
    b"    }\r\n"
    b"\r\n"
    b"    if (extraDiscInput && !isNaN(purchaseExtraDisc) && purchaseExtraDisc > 0) {\r\n"
    b"        const saleExtraDisc = parseFloat(extraDiscInput.value) || 0;\r\n"
    b"        if (saleExtraDisc > purchaseExtraDisc) {\r\n"
    b"            const batchInfo = purchaseBatch ? ` (Batch: ${purchaseBatch})` : '';\r\n"
    b"            const warn = document.createElement('div');\r\n"
    b"            warn.className = 'disc-warning';\r\n"
    b"            warn.style.cssText = 'color:#e53e3e;font-size:11px;margin-top:2px;';\r\n"
    b"            warn.textContent = `\u26a0\ufe0f Purchase Extra Disc was ${purchaseExtraDisc.toFixed(2)}%${batchInfo}`;\r\n"
    b"            extraDiscInput.parentNode.appendChild(warn);\r\n"
    b"        }\r\n"
    b"    }\r\n"
    b"}\r\n\r\n"
    b"// Validate sale price vs latest purchase price\r\n"
    b"// Load product stock"
)

if marker in data:
    data = data.replace(marker, new_func, 1)
    results.append("OK: pos-add.js validateDiscVsPurchase function added")
else:
    results.append("FAIL: pos-add.js validateDiscVsPurchase function marker not found")

open(js, 'wb').write(data)

open(r'C:\xampp\htdocs\ledgerone_erp\patch_disc_warn.txt', 'w', encoding='utf-8').write('\n'.join(results))
print('\n'.join(results))
