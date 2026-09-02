import re

results = []

def patch(filepath, old, new, label):
    data = open(filepath, 'rb').read()
    if old.encode('utf-8') in data:
        data = data.replace(old.encode('utf-8'), new.encode('utf-8'), 1)
        open(filepath, 'wb').write(data)
        results.append(f"OK: {label}")
    else:
        results.append(f"FAIL: {label}")

# ── purchase-add.js ───────────────────────────────────────────────────────────
js = r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\purchase\purchase_invoice\purchase-add.js'

patch(js,
    "                    discountPercent: parseFloat(row.querySelector('.disc-percent-cell input').value) || 0,\r\n                    discountAmount: parseFloat(row.querySelector('.disc-amount-cell input').value),\r\n                    tradeOfferPercent:",
    "                    discountPercent: parseFloat(row.querySelector('.disc-percent-cell input').value) || 0,\r\n                    discountAmount: parseFloat(row.querySelector('.disc-amount-cell input').value),\r\n                    extraDiscountPercent: parseFloat(row.querySelector('.extra-disc-percent-cell input')?.value) || 0,\r\n                    tradeOfferPercent:",
    "purchase-add.js save item"
)

patch(js,
    "                        const discAmountCell = row.querySelector('.disc-amount-cell input');\r\n                        if (discAmountCell) discAmountCell.value = item.discount_amount || 0;\r\n\r\n                        const toPercentCell",
    "                        const discAmountCell = row.querySelector('.disc-amount-cell input');\r\n                        if (discAmountCell) discAmountCell.value = item.discount_amount || 0;\r\n\r\n                        const extraDiscCell = row.querySelector('.extra-disc-percent-cell input');\r\n                        if (extraDiscCell) extraDiscCell.value = item.extra_discount_percent || 0;\r\n\r\n                        const toPercentCell",
    "purchase-add.js edit load"
)

# ── server/purchase-add.php ───────────────────────────────────────────────────
api_add = r'C:\xampp\htdocs\ledgerone_erp\server\api\purchase\purchase_invoice\purchase-add.php'

patch(api_add,
    "            discount_amount, trade_offer_percent, trade_offer_amount,\r\n            tax_percent, tax_amount, foc_quantity, net_amount, size,\r\n            batch_no, batch_expiry_date,\r\n            created_by, updated_by\r\n        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    "            discount_amount, extra_discount_percent, trade_offer_percent, trade_offer_amount,\r\n            tax_percent, tax_amount, foc_quantity, net_amount, size,\r\n            batch_no, batch_expiry_date,\r\n            created_by, updated_by\r\n        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    "purchase-add.php INSERT cols"
)

patch(api_add,
    "                $item['discountPercent'] ?? 0.00,\r\n                $isFirstEntry ? $item['discountAmount'] : 0,\r\n                $item['tradeOfferPercent'] ?? 0.00,",
    "                $item['discountPercent'] ?? 0.00,\r\n                $isFirstEntry ? $item['discountAmount'] : 0,\r\n                $item['extraDiscountPercent'] ?? 0.00,\r\n                $item['tradeOfferPercent'] ?? 0.00,",
    "purchase-add.php INSERT vals"
)

# ── server/purchase-edit.php ──────────────────────────────────────────────────
api_edit = r'C:\xampp\htdocs\ledgerone_erp\server\api\purchase\purchase_invoice\purchase-edit.php'

patch(api_edit,
    "                discount_amount, trade_offer_percent, trade_offer_amount,\r\n                tax_percent, tax_amount, foc_quantity, net_amount, size,\r\n                batch_no, batch_expiry_date,\r\n                created_by, updated_by\r\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    "                discount_amount, extra_discount_percent, trade_offer_percent, trade_offer_amount,\r\n                tax_percent, tax_amount, foc_quantity, net_amount, size,\r\n                batch_no, batch_expiry_date,\r\n                created_by, updated_by\r\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    "purchase-edit.php INSERT cols"
)

patch(api_edit,
    "                    $item['discountPercent'] ?? 0.00,\r\n                    $isFirstEntry ? $item['discountAmount'] : 0,\r\n                    $item['tradeOfferPercent'] ?? 0.00,",
    "                    $item['discountPercent'] ?? 0.00,\r\n                    $isFirstEntry ? $item['discountAmount'] : 0,\r\n                    $item['extraDiscountPercent'] ?? 0.00,\r\n                    $item['tradeOfferPercent'] ?? 0.00,",
    "purchase-edit.php INSERT vals"
)

patch(api_edit,
    "                        'discount_percent' => floatval($firstItem['discount_percent'] ?? 0),\r\n                        'discount_amount' => $totalDiscount,\r\n                        'trade_offer_percent'",
    "                        'discount_percent' => floatval($firstItem['discount_percent'] ?? 0),\r\n                        'discount_amount' => $totalDiscount,\r\n                        'extra_discount_percent' => floatval($firstItem['extra_discount_percent'] ?? 0),\r\n                        'trade_offer_percent'",
    "purchase-edit.php GET grouped"
)

open(r'C:\xampp\htdocs\ledgerone_erp\patch_results.txt', 'w').write('\n'.join(results))
print('\n'.join(results))
