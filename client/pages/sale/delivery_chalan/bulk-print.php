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
</script>
</body>
</html>
