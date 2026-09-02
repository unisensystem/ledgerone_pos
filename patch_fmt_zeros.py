import sys

files = [
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print.php',
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print2.php',
]

old = b"return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 });"
new = b"const v = parseFloat(n || 0); return v.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 });"

for path in files:
    with open(path, 'rb') as f:
        content = f.read()
    if old not in content:
        print(f'ERROR: pattern not found in {path}'); sys.exit(1)
    content = content.replace(old, new, 1)
    with open(path, 'wb') as f:
        f.write(content)
    print(f'Patched: {path}')

print('Done!')
