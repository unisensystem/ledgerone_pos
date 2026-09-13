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
            /* Prevents any transient overlap from being visible while the iframe
               height is still settling — each wrapper only shows what its own
               iframe currently reports as its height. */
            overflow: hidden;
        }
        .invoice-frame-wrapper:last-child {
            page-break-after: avoid;
        }

        iframe {
            width: 100%;
            border: none;
            display: block;
            background: white;
            /* Smooth out visible height jumps as async content (logo/QR/fonts)
               finishes loading, instead of a sudden snap that looks like a glitch. */
            transition: height 0.15s ease-out;
        }

        @media print {
            .no-print { display: none; }
            body { background: white; }
            iframe { height: auto; transition: none; }
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

    // POLL_DURATION_MS: how long we keep re-checking each iframe's real content
    // height after it loads. invoice-print.php's content height can keep
    // changing after the initial paint — the company logo <img> and signature
    // <img> load asynchronously, the QR code library renders on a 400ms
    // setTimeout, and web fonts can reflow text — so a single one-time height
    // measurement (even after waiting for the 'invoiceLoaded' event) can still
    // be wrong. Watching continuously for a few seconds is what actually
    // guarantees no overlap, regardless of exactly when each async piece settles.
    const POLL_DURATION_MS = 6000;
    const POLL_INTERVAL_MS = 300;

    ids.forEach((id, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'invoice-frame-wrapper';

        const iframe = document.createElement('iframe');
        iframe.src = `invoice-print.php?id=${id}`;
        iframe.scrolling = 'no';

        iframe.addEventListener('load', function () {
            let doc;
            try {
                doc = iframe.contentDocument || iframe.contentWindow.document;
                doc.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');
            } catch (e) {
                loadedCount++;
                updateStatus();
                return;
            }

            function measureAndApply() {
                try {
                    // documentElement.scrollHeight is generally the most reliable
                    // cross-browser measure of true content height; body.scrollHeight
                    // is kept as a fallback/comparison in case margins collapse oddly.
                    const h = Math.max(
                        doc.documentElement.scrollHeight,
                        doc.body.scrollHeight
                    );
                    // Small buffer so nothing is ever clipped by a 1-2px rounding gap.
                    iframe.style.height = (h + 4) + 'px';
                } catch (e) {}
            }

            // 1) Measure immediately.
            measureAndApply();

            // 2) Keep watching for live size changes (images finishing load, QR
            //    code appearing, font reflow, etc.) via ResizeObserver — the most
            //    reliable signal, since it fires exactly when the box actually
            //    changes size rather than guessing a delay.
            try {
                const ro = new ResizeObserver(() => measureAndApply());
                ro.observe(doc.documentElement);
                setTimeout(() => ro.disconnect(), POLL_DURATION_MS);
            } catch (e) {
                // ResizeObserver not available inside this iframe context — polling
                // fallback below still covers it.
            }

            // 3) Belt-and-braces polling fallback, in case ResizeObserver misses
            //    something (e.g. a change that doesn't affect box size directly
            //    but affects layout of a sibling element).
            let elapsed = 0;
            const pollTimer = setInterval(() => {
                measureAndApply();
                elapsed += POLL_INTERVAL_MS;
                if (elapsed >= POLL_DURATION_MS) clearInterval(pollTimer);
            }, POLL_INTERVAL_MS);

            // 4) Also react to the explicit signal invoice-print.php gives us once
            //    its fetched data has rendered, for a fast first accurate paint.
            try {
                iframe.contentWindow.addEventListener('invoiceLoaded', function () {
                    setTimeout(measureAndApply, 50);
                    setTimeout(measureAndApply, 500);
                });
            } catch (e) {}

            loadedCount++;
            updateStatus();
        });

        wrapper.appendChild(iframe);
        container.appendChild(wrapper);
    });

    function updateStatus() {
        document.getElementById('statusMsg').textContent =
            `Loaded ${loadedCount} of ${ids.length} invoice(s)`;
        if (loadedCount === ids.length) {
            document.getElementById('statusMsg').textContent =
                `${ids.length} invoice(s) ready. Click "Print All" to print.`;
        }
    }

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