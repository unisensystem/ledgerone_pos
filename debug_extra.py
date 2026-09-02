data = open(r'C:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\med_pos_invoice\pos-add.js', 'rb').read()

# Check if validateDiscVsPurchase already in extraDisc listener
idx = data.find(b"extraDiscInput.addEventListener('input'")
calc_idx = data.find(b'calculateRowAmounts', idx)
end_line = data.find(b'\r\n', calc_idx)
next_end = data.find(b'\r\n', end_line+2)
next_end2 = data.find(b'\r\n', next_end+2)
chunk = data[idx:next_end2+2]

out = open(r'C:\xampp\htdocs\ledgerone_erp\debug_extra.txt', 'wb')
out.write(b'REPR:\n' + repr(chunk).encode())
out.close()
print("done")
