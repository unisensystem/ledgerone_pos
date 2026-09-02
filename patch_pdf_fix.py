import sys

files = [
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print.php',
    r'C:\xampp\htdocs\ledgerone_erp\client\pages\sale\med_pos_invoice\invoice-print2.php',
]

old = b"async function downloadAsPdf() {\r\n            const btn = document.getElementById('downloadPdfBtn');\r\n            btn.disabled = true;\r\n            btn.textContent = 'Generating...';\r\n            document.querySelectorAll('.no-print').forEach(el => el.style.setProperty('display', 'none', 'important'));\r\n\r\n            const invoiceNo = document.getElementById('invoiceNo').textContent || 'Invoice';\r\n            const paperSize = localStorage.getItem('invoicePaperSize') || 'A4';\r\n            const sizeMap = { A4: 'a4', A5: 'a5', A6: [105, 148] };\r\n            const format = sizeMap[paperSize] || 'a4';\r\n\r\n            await html2pdf().set({\r\n                margin: 8,\r\n                filename: `Invoice-${invoiceNo}.pdf`,\r\n                image: { type: 'jpeg', quality: 0.98 },\r\n                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false },\r\n                jsPDF: { unit: 'mm', format: format, orientation: 'portrait' }\r\n            }).from(document.getElementById('invoiceContainer')).save();\r\n\r\n            document.querySelectorAll('.no-print').forEach(el => el.style.removeProperty('display'));\r\n            btn.disabled = false;\r\n            btn.textContent = '\\u2193 Download PDF';\r\n        }"

new = b"async function downloadAsPdf() {\r\n            const btn = document.getElementById('downloadPdfBtn');\r\n            btn.disabled = true;\r\n            btn.textContent = 'Generating...';\r\n            document.querySelectorAll('.no-print').forEach(el => el.style.setProperty('display', 'none', 'important'));\r\n\r\n            const container = document.getElementById('invoiceContainer');\r\n            const prevStyle = container.getAttribute('style') || '';\r\n            container.style.cssText = 'max-width:100% !important; margin:0 !important; padding:10px !important; box-shadow:none !important; border:none !important;';\r\n\r\n            const invoiceNo = document.getElementById('invoiceNo').textContent || 'Invoice';\r\n            const paperSize = localStorage.getItem('invoicePaperSize') || 'A4';\r\n            const sizeMap = { A4: 'a4', A5: 'a5', A6: [105, 148] };\r\n            const format = sizeMap[paperSize] || 'a4';\r\n            const widthMap = { A4: 794, A5: 559, A6: 397 };\r\n            const pxWidth = widthMap[paperSize] || 794;\r\n\r\n            await html2pdf().set({\r\n                margin: [5, 5, 5, 5],\r\n                filename: `Invoice-${invoiceNo}.pdf`,\r\n                image: { type: 'jpeg', quality: 0.98 },\r\n                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false, windowWidth: pxWidth },\r\n                jsPDF: { unit: 'mm', format: format, orientation: 'portrait' },\r\n                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }\r\n            }).from(container).save();\r\n\r\n            container.setAttribute('style', prevStyle);\r\n            document.querySelectorAll('.no-print').forEach(el => el.style.removeProperty('display'));\r\n            btn.disabled = false;\r\n            btn.textContent = '\\u2193 Download PDF';\r\n        }"

for path in files:
    with open(path, 'rb') as f:
        content = f.read()
    if old not in content:
        print(f'ERROR: pattern not found in {path}'); sys.exit(1)
    content = content.replace(old, new, 1)
    with open(path, 'wb') as f:
        f.write(content)
    print(f'Patched: {path}')

print('Done!')
