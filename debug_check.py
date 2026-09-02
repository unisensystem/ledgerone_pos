import os, sys

path = r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\purchase\purchase_invoice\purchase-add-uom.js'
data = open(path, 'rb').read()

with open(r'C:\xampp\htdocs\ledgerone_erp\debug_out.txt', 'w') as out:
    out.write(f"size: {len(data)}\n")
    out.write(f"has CRLF: {b'chr(13)chr(10)' in data}\n")
    out.write(f"has LF only: {b'chr(10)' in data}\n")
    idx = data.find(b'afterDiscount')
    out.write(f"afterDiscount pos: {idx}\n")
    if idx >= 0:
        out.write(repr(data[idx:idx+300]) + "\n")
    idx2 = data.find(b'calculateRowAmounts')
    out.write(f"calculateRowAmounts pos: {idx2}\n")
    if idx2 >= 0:
        out.write(repr(data[idx2:idx2+400]) + "\n")
