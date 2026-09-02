data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()
idx = data.find(b"discPercentInput.addEventListener('input'")
# find closing }); after this
end = data.find(b'});', idx+100)
chunk = data[idx:end+3]
open(r'C:\xampp\htdocs\ledgerone_erp\debug_disc3.txt', 'wb').write(b'REPR:\n' + repr(chunk).encode() + b'\n\nRAW:\n' + chunk)
