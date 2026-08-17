<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Print Invoices</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f0f0; font-family: Arial, sans-serif; }

        .no-print {
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .no-print button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .no-print button:hover { background: #0056b3; }
        .size-btn { background: #6c757d !important; }
        .size-btn:hover { background: #545b62 !important; }
        .size-btn.active { background: #28a745 !important; }
        .no-print span { color: #555; font-size: 14px; }

        .invoice-frame-wrapper {
            page-break-after: always;
        }
        .invoice-frame-wrapper:last-child {
            page-break-after: avoid;
        }

        iframe {
            width: 100%;
            border: none;
            display: block;
            background: white;
        }

        @media print {
            .no-print { display: none; }
            body { background: white; }
            iframe { height: auto; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="printAll()">🖨️ Print All</button>
    <button class="size-btn" onclick="setPaperSize('A3')" id="btnA3">A3</button>
    <button class="size-btn" onclick="setPaperSize('A4')" id="btnA4">A4</button>
    <button class="size-btn" onclick="setPaperSize('A5')" id="btnA5">A5</button>
    <button class="size-btn" onclick="setPaperSize('A6')" id="btnA6">A6</button>
    <span id="statusMsg">Loading invoices...</span>
</div>

<div id="framesContainer"></div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const idsParam = urlParams.get('ids') || '';
    const ids = idsParam.split(',').map(s => s.trim()).filter(Boolean);

    if (ids.length === 0) {
        document.getElementById('statusMsg').textContent = 'No invoice IDs provided.';
    }

    let loadedCount = 0;
    const container = document.getElementById('framesContainer');

    ids.forEach((id, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'invoice-frame-wrapper';

        const iframe = document.createElement('iframe');
        iframe.src = `invoice-print.php?id=${id}`;
        iframe.scrolling = 'no';

        // Resize iframe to fit content after load
        iframe.addEventListener('load', function () {
            try {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                // Hide the print buttons inside the iframe
                doc.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');
                // Set height to content
                iframe.style.height = doc.body.scrollHeight + 'px';
            } catch(e) {}

            loadedCount++;
            document.getElementById('statusMsg').textContent =
                `Loaded ${loadedCount} of ${ids.length} invoice(s)`;

            if (loadedCount === ids.length) {
                document.getElementById('statusMsg').textContent =
                    `${ids.length} invoice(s) ready. Click "Print All" to print.`;
            }
        });

        wrapper.appendChild(iframe);
        container.appendChild(wrapper);
    });

    function printAll() {
        window.print();
    }

    function setPaperSize(size) {
        localStorage.setItem('invoicePaperSize', size);
        document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('btn' + size).classList.add('active');
        // Propagate to all loaded iframes
        document.querySelectorAll('iframe').forEach(iframe => {
            try {
                iframe.contentWindow.setPaperSize(size);
            } catch(e) {}
        });
    }

    // Apply saved size on load
    const savedSize = localStorage.getItem('invoicePaperSize') || 'A4';
    document.addEventListener('DOMContentLoaded', () => setPaperSize(savedSize));
</script>
</body>
</html>
