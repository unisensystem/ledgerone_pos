data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()
idx = data.find(b'validateDiscVsPurchase')
end = data.find(b'\r\nfunction ', idx+10)
chunk = data[idx:end]
open(r'C:\xampp\htdocs\ledgerone_erp\debug_vdp.txt', 'wb').write(b'REPR:\n' + repr(chunk).encode())
print("done, len=", len(chunk))
