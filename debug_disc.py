data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()

out = open(r'C:\xampp\htdocs\ledgerone_erp\debug_disc.txt', 'wb')

idx = data.find(b'discPercentInput.addEventListener')
out.write(b"discPercentInput listener at: " + str(idx).encode() + b"\n")
if idx >= 0:
    out.write(repr(data[idx:idx+400]).encode() + b"\n\n")

idx2 = data.find(b'extraDiscInput.addEventListener')
out.write(b"extraDiscInput listener at: " + str(idx2).encode() + b"\n")
if idx2 >= 0:
    out.write(repr(data[idx2:idx2+400]).encode() + b"\n")

out.close()
