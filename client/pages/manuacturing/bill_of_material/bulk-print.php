<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Print BOMs</title>
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
            background: #1F7BFF;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .no-print button:hover { background: #0056b3; }
        .no-print span { color: #555; font-size: 14px; }

        .bom-frame-wrapper {
            background: white;
            margin: 24px auto;
            width: 210mm;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            page-break-after: always;
        }
        .bom-frame-wrapper:last-child {
            page-break-after: avoid;
        }

        iframe {
            width: 100%;
            border: none;
            display: block;
        }

        @media print {
            .no-print { display: none; }
            body { background: white; }
            .bom-frame-wrapper {
                margin: 0;
                width: 100%;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="printAll()">🖨️ Print All</button>
    <span id="statusMsg">Loading BOMs...</span>
</div>

<div id="framesContainer"></div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const idsParam = urlParams.get('ids') || '';
    const ids = idsParam.split(',').map(s => s.trim()).filter(Boolean);

    if (ids.length === 0) {
        document.getElementById('statusMsg').textContent = 'No BOM IDs provided.';
    }

    let loadedCount = 0;
    const container = document.getElementById('framesContainer');

    ids.forEach(id => {
        const wrapper = document.createElement('div');
        wrapper.className = 'bom-frame-wrapper';

        const iframe = document.createElement('iframe');
        iframe.src = `print.php?id=${id}`;
        iframe.scrolling = 'no';
        iframe.style.height = '297mm'; // A4 default before load

        iframe.addEventListener('load', function () {
            try {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                // Hide print controls inside iframe
                doc.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');
                // Fit iframe to content height
                const h = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                iframe.style.height = h + 'px';
            } catch(e) {}

            loadedCount++;
            document.getElementById('statusMsg').textContent =
                `Loaded ${loadedCount} of ${ids.length} BOM(s)`;

            if (loadedCount === ids.length) {
                document.getElementById('statusMsg').textContent =
                    `${ids.length} BOM(s) ready. Click "Print All" to print.`;
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
