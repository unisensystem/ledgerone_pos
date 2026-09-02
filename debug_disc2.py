data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()
idx = data.find(b"discPercentInput.addEventListener('input'")
chunk = data[idx:idx+500]
open(r'C:\xampp\htdocs\ledgerone_erp\debug_disc2.txt', 'wb').write(chunk)
print(repr(chunk))
