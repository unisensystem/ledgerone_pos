import sys

files = [
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print.php',
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print2.php',
]

old = b"        .items-table thead th {\r\n            background: #f0f0f0;\r\n            font-weight: bold;\r\n            font-size: 10px;\r\n            text-align: center;\r\n        }"

new = b"        .items-table thead th {\r\n            font-weight: bold;\r\n            font-size: 10px;\r\n            text-align: center;\r\n        }"

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
