import sys

files = [
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print.php',
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print2.php',
]

old1 = b"        .group-total-row td {\r\n            font-weight: bold;\r\n            background: #f7f7f7;\r\n        }"
new1 = b"        .group-total-row td {\r\n            font-weight: bold;\r\n        }"

old2 = b"        .grand-total-row td {\r\n            font-weight: bold;\r\n            background: #eee;\r\n            border-top: 2px solid #333;\r\n        }"
new2 = b"        .grand-total-row td {\r\n            font-weight: bold;\r\n            border-top: 2px solid #333;\r\n        }"

for path in files:
    with open(path, 'rb') as f:
        content = f.read()
    for old, new in [(old1, new1), (old2, new2)]:
        if old not in content:
            print(f'ERROR: pattern not found in {path}'); sys.exit(1)
        content = content.replace(old, new, 1)
    with open(path, 'wb') as f:
        f.write(content)
    print(f'Patched: {path}')

print('Done!')
