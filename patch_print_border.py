import sys

files = [
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print.php',
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print2.php',
]

old = b"        @media print {\r\n            body { margin: 0; }\r\n            .no-print { display: none; }\r\n        }"
new = b"        @media print {\r\n            body { margin: 0; }\r\n            .no-print { display: none; }\r\n            .invoice-container { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: 100% !important; }\r\n        }"

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
