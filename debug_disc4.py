data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()
idx = data.find(b"discPercentInput.addEventListener('input'")
# find 'calculateRowAmounts' after idx
calc_idx = data.find(b'calculateRowAmounts', idx)
# find end of that line
end = data.find(b'\r\n', calc_idx)
# get next line
next_line_end = data.find(b'\r\n', end+2)
chunk = data[idx:next_line_end+2]
open(r'C:\xampp\htdocs\ledgerone_erp\debug_disc4.txt', 'wb').write(b'REPR:\n' + repr(chunk).encode())
