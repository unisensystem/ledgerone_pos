import re

# ── 1. patch purchase-add-uom.js ─────────────────────────────────────────────
uom_file = r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\purchase\purchase_invoice\purchase-add-uom.js'
with open(uom_file, 'r', encoding='utf-8') as f:
    content = f.read()

old = (
    "    const afterDiscount = gross - discAmount;\n"
    "\n"
    "\n"
    "    const toPercent = parseFloat(toPercentCell.querySelector('input').value) || 0;\n"
    "\n"
    "    const toAmount = afterDiscount * (toPercent / 100);\n"
    "    toAmountCell.querySelector('input').value = toAmount.toFixed(3);\n"
    "\n"
    "\n"
    "    const afterTO = afterDiscount - toAmount;"
)

new = (
    "    const afterDiscount = gross - discAmount;\n"
    "\n"
    "    const extraDiscPercentCell = row.querySelector('.extra-disc-percent-cell');\n"
    "    const extraDiscPercent = extraDiscPercentCell ? (parseFloat(extraDiscPercentCell.querySelector('input').value) || 0) : 0;\n"
    "    const afterExtraDiscount = afterDiscount - (afterDiscount * extraDiscPercent / 100);\n"
    "\n"
    "    const toPercent = parseFloat(toPercentCell.querySelector('input').value) || 0;\n"
    "\n"
    "    const toAmount = afterExtraDiscount * (toPercent / 100);\n"
    "    toAmountCell.querySelector('input').value = toAmount.toFixed(3);\n"
    "\n"
    "\n"
    "    const afterTO = afterExtraDiscount - toAmount;"
)

if old in content:
    content = content.replace(old, new, 1)
    print("uom.js: patched afterDiscount block")
else:
    # try normalised (strip extra blank lines)
    print("uom.js: exact match failed, trying regex")
    pattern = r'(    const afterDiscount = gross - discAmount;\s+)(    const toPercent)'
    replacement = (
        r'\1'
        "    const extraDiscPercentCell = row.querySelector('.extra-disc-percent-cell');\n"
        "    const extraDiscPercent = extraDiscPercentCell ? (parseFloat(extraDiscPercentCell.querySelector('input').value) || 0) : 0;\n"
        "    const afterExtraDiscount = afterDiscount - (afterDiscount * extraDiscPercent / 100);\n\n"
        r'\2'
    )
    new_content = re.sub(pattern, replacement, content, count=1)
    if new_content != content:
        content = new_content
        # also replace afterDiscount -> afterExtraDiscount for toAmount and afterTO
        content = content.replace(
            "    const toAmount = afterDiscount * (toPercent / 100);",
            "    const toAmount = afterExtraDiscount * (toPercent / 100);", 1
        )
        content = content.replace(
            "    const afterTO = afterDiscount - toAmount;",
            "    const afterTO = afterExtraDiscount - toAmount;", 1
        )
        print("uom.js: regex patch applied")
    else:
        print("uom.js: FAILED - no match found")

with open(uom_file, 'w', encoding='utf-8') as f:
    f.write(content)

# ── 2. patch purchase-add.js (save: add extraDiscountPercent to item) ─────────
js_file = r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\purchase\purchase_invoice\purchase-add.js'
with open(js_file, 'r', encoding='utf-8') as f:
    js = f.read()

old_save = "                    discountPercent: parseFloat(row.querySelector('.disc-percent-cell input').value) || 0,\r\n                    discountAmount: parseFloat(row.querySelector('.disc-amount-cell input').value),"
new_save = "                    discountPercent: parseFloat(row.querySelector('.disc-percent-cell input').value) || 0,\r\n                    discountAmount: parseFloat(row.querySelector('.disc-amount-cell input').value),\r\n                    extraDiscountPercent: parseFloat(row.querySelector('.extra-disc-percent-cell input')?.value) || 0,"

if old_save in js:
    js = js.replace(old_save, new_save, 1)
    print("purchase-add.js: save item patched")
else:
    print("purchase-add.js: save item FAILED")

# edit load: set extra_discount_percent
old_edit = "                        const discAmountCell = row.querySelector('.disc-amount-cell input');\r\n                        if (discAmountCell) discAmountCell.value = item.discount_amount || 0;"
new_edit = "                        const discAmountCell = row.querySelector('.disc-amount-cell input');\r\n                        if (discAmountCell) discAmountCell.value = item.discount_amount || 0;\r\n\r\n                        const extraDiscCell = row.querySelector('.extra-disc-percent-cell input');\r\n                        if (extraDiscCell) extraDiscCell.value = item.extra_discount_percent || 0;"

if old_edit in js:
    js = js.replace(old_edit, new_edit, 1)
    print("purchase-add.js: edit load patched")
else:
    print("purchase-add.js: edit load FAILED")

with open(js_file, 'w', encoding='utf-8') as f:
    f.write(js)

# ── 3. patch server/purchase-add.php ─────────────────────────────────────────
api_add = r'C:\xampp\htdocs\ledgerone_erp\server\api\purchase\purchase_invoice\purchase-add.php'
with open(api_add, 'r', encoding='utf-8') as f:
    php = f.read()

old_cols = "            discount_amount, trade_offer_percent, trade_offer_amount,"
new_cols = "            discount_amount, extra_discount_percent, trade_offer_percent, trade_offer_amount,"
old_vals = "                $item['discountPercent'] ?? 0.00,\r\n                $isFirstEntry ? $item['discountAmount'] : 0,\r\n                $item['tradeOfferPercent'] ?? 0.00,"
new_vals = "                $item['discountPercent'] ?? 0.00,\r\n                $isFirstEntry ? $item['discountAmount'] : 0,\r\n                $item['extraDiscountPercent'] ?? 0.00,\r\n                $item['tradeOfferPercent'] ?? 0.00,"

old_insert = "            INSERT INTO purchase_invoice_items (\r\n            tenant_id, purchase_invoice_id, product_id, uom_id,\r\n            quantity, purchase_price, gross_amount, discount_percent,\r\n            discount_amount, trade_offer_percent, trade_offer_amount,"
new_insert = "            INSERT INTO purchase_invoice_items (\r\n            tenant_id, purchase_invoice_id, product_id, uom_id,\r\n            quantity, purchase_price, gross_amount, discount_percent,\r\n            discount_amount, extra_discount_percent, trade_offer_percent, trade_offer_amount,"

if old_insert in php:
    php = php.replace(old_insert, new_insert, 1)
    print("purchase-add.php: INSERT cols patched")
else:
    print("purchase-add.php: INSERT cols FAILED")

if old_vals in php:
    php = php.replace(old_vals, new_vals, 1)
    print("purchase-add.php: INSERT vals patched")
else:
    print("purchase-add.php: INSERT vals FAILED")

with open(api_add, 'w', encoding='utf-8') as f:
    f.write(php)

# ── 4. patch server/purchase-edit.php ────────────────────────────────────────
api_edit = r'C:\xampp\htdocs\ledgerone_erp\server\api\purchase\purchase_invoice\purchase-edit.php'
with open(api_edit, 'r', encoding='utf-8') as f:
    php = f.read()

old_insert2 = "            INSERT INTO purchase_invoice_items (\r\n                tenant_id, purchase_invoice_id, product_id, uom_id,\r\n                quantity, purchase_price, gross_amount, discount_percent,\r\n                discount_amount, trade_offer_percent, trade_offer_amount,"
new_insert2 = "            INSERT INTO purchase_invoice_items (\r\n                tenant_id, purchase_invoice_id, product_id, uom_id,\r\n                quantity, purchase_price, gross_amount, discount_percent,\r\n                discount_amount, extra_discount_percent, trade_offer_percent, trade_offer_amount,"

old_vals2 = "                    $item['discountPercent'] ?? 0.00,\r\n                    $isFirstEntry ? $item['discountAmount'] : 0,\r\n                    $item['tradeOfferPercent'] ?? 0.00,"
new_vals2 = "                    $item['discountPercent'] ?? 0.00,\r\n                    $isFirstEntry ? $item['discountAmount'] : 0,\r\n                    $item['extraDiscountPercent'] ?? 0.00,\r\n                    $item['tradeOfferPercent'] ?? 0.00,"

if old_insert2 in php:
    php = php.replace(old_insert2, new_insert2, 1)
    print("purchase-edit.php: INSERT cols patched")
else:
    print("purchase-edit.php: INSERT cols FAILED")

if old_vals2 in php:
    php = php.replace(old_vals2, new_vals2, 1)
    print("purchase-edit.php: INSERT vals patched")
else:
    print("purchase-edit.php: INSERT vals FAILED")

# GET response: add extra_discount_percent to grouped items
old_grouped = "                        'discount_percent' => floatval($firstItem['discount_percent'] ?? 0),\r\n                        'discount_amount' => $totalDiscount,"
new_grouped = "                        'discount_percent' => floatval($firstItem['discount_percent'] ?? 0),\r\n                        'discount_amount' => $totalDiscount,\r\n                        'extra_discount_percent' => floatval($firstItem['extra_discount_percent'] ?? 0),"

if old_grouped in php:
    php = php.replace(old_grouped, new_grouped, 1)
    print("purchase-edit.php: GET grouped patched")
else:
    print("purchase-edit.php: GET grouped FAILED")

with open(api_edit, 'w', encoding='utf-8') as f:
    f.write(php)

print("Done.")
