import sys

path = r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print.php'

with open(path, 'rb') as f:
    content = f.read()

# 1. Add CSS for hidden warranty section
old1 = b'        body.a6-mode { font-size: 7px; }\r\n        body.a6-mode .invoice-container { max-width: 400px; padding: 6px; }\r\n    </style>'
new1 = b'        body.a6-mode { font-size: 7px; }\r\n        body.a6-mode .invoice-container { max-width: 400px; padding: 6px; }\r\n        .warranty-section.hidden { display: none; }\r\n    </style>'

# 2. Add toggle button in no-print div
old2 = b'        <button class="print-btn" id="downloadPdfBtn" onclick="downloadAsPdf()" style="background:#28a745;">&#8595; Download PDF</button>\r\n    </div>'
new2 = b'        <button class="print-btn" id="downloadPdfBtn" onclick="downloadAsPdf()" style="background:#28a745;">&#8595; Download PDF</button>\r\n        <button class="print-btn" id="warrantyToggleBtn" onclick="toggleWarranty()">Hide Warranty</button>\r\n    </div>'

# 3. Add toggleWarranty function + DOMContentLoaded restore before loadData()
old3 = b'        loadData();\r\n    </script>'
new3 = b'        function toggleWarranty() {\r\n            const sec = document.querySelector(\'.warranty-section\');\r\n            const btn = document.getElementById(\'warrantyToggleBtn\');\r\n            const hidden = sec.classList.toggle(\'hidden\');\r\n            btn.textContent = hidden ? \'Show Warranty\' : \'Hide Warranty\';\r\n            btn.style.background = hidden ? \'#6c757d\' : \'\';\r\n            localStorage.setItem(\'warrantyHidden_print1\', hidden ? \'1\' : \'0\');\r\n        }\r\n        document.addEventListener(\'DOMContentLoaded\', function() {\r\n            if (localStorage.getItem(\'warrantyHidden_print1\') === \'1\') {\r\n                const sec = document.querySelector(\'.warranty-section\');\r\n                const btn = document.getElementById(\'warrantyToggleBtn\');\r\n                sec.classList.add(\'hidden\');\r\n                btn.textContent = \'Show Warranty\';\r\n                btn.style.background = \'#6c757d\';\r\n            }\r\n        });\r\n\r\n        loadData();\r\n    </script>'

if old1 not in content:
    print('ERROR: patch 1 not found'); sys.exit(1)
if old2 not in content:
    print('ERROR: patch 2 not found'); sys.exit(1)
if old3 not in content:
    print('ERROR: patch 3 not found'); sys.exit(1)

content = content.replace(old1, new1, 1)
content = content.replace(old2, new2, 1)
content = content.replace(old3, new3, 1)

with open(path, 'wb') as f:
    f.write(content)

print('Done! All 3 patches applied.')
