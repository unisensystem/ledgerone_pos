data = open(r'c:\xampp\htdocs\ledgerone_erp\client\assets\js\sale\pos_invoice\pos-add.js', encoding='utf-8').read()
idx = data.find('function updatePriceHistoryContainerVisibility')
chunk = data[idx:idx+700]
for i, ch in enumerate(chunk):
    print(i, repr(ch), end='  ')
    if i % 10 == 9:
        print()
