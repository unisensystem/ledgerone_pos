f = open(r'c:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\pos_invoice\pos-list.js', 'r', encoding='utf-8')
c = f.read()
f.close()

# Fix 1: literal \r\n between currencySymbol and dcStatus
c = c.replace("currency_symbol || '',\\r\\n                    dcStatus", "currency_symbol || '',\r\n                    dcStatus")

# Fix 2: unquoted href in createDcBtn
c = c.replace(
    'window.location.href = ../delivery_chalan/pos-add.php?invoice_id=)',
    'window.location.href = `../delivery_chalan/pos-add.php?invoice_id=${invoice.id}`)'
)

# Fix 3: unquoted fetch URL
c = c.replace(
    'fetch(../../../../server/api/sale/delivery_chalan/get-dc-fulfillment.php?invoice_id=)',
    'fetch(`../../../../server/api/sale/delivery_chalan/get-dc-fulfillment.php?invoice_id=${invoiceId}`)'
)

# Fix 4: row.innerHTML missing template literal - find and replace the broken block
old = '''                    row.innerHTML = 
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);"></td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"> </td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"> </td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"> </td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"><span class="status ">%</span></td>
                    ;'''

new = '''                    row.innerHTML = `
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);">${item.product_name} (${item.uom_name})</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${item.invoiced_qty}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${item.delivered_qty}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${item.remaining_qty}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"><span class="status ${cls}">${pct.toFixed(0)}%</span></td>
                    `;'''

if old in c:
    c = c.replace(old, new)
    print('Fix 4: applied')
else:
    print('Fix 4: NOT FOUND - printing surrounding context')
    idx = c.find('row.innerHTML = \n')
    if idx == -1:
        idx = c.find('row.innerHTML =\n')
    if idx != -1:
        print(repr(c[idx:idx+500]))

open(r'c:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\pos_invoice\pos-list.js', 'w', encoding='utf-8').write(c)

print('fix1 ok:', '\\r\\n                    dcStatus' not in c)
print('fix2 ok:', 'href = ../delivery_chalan' not in c)
print('fix3 ok:', 'fetch(../../../../server/api/sale/delivery_chalan/get-dc-fulfillment.php?invoice_id=)' not in c)
