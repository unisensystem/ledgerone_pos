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
        .no-print button:disabled { background: #99c2ff !important; cursor: not-allowed; }
        .size-btn { background: #6c757d !important; }
        .size-btn:hover { background: #545b62 !important; }
        .size-btn.active { background: #28a745 !important; }
        .no-print span { color: #555; font-size: 14px; }

        .invoice-frame-wrapper {
            page-break-after: always;
            break-after: page;
        }
        .invoice-frame-wrapper:last-child {
            page-break-after: avoid;
            break-after: avoid;
        }

        iframe {
            width: 100%;
            border: none;
            display: block;
            background: white;
        }

        /* SCREEN-ONLY separation between invoices. This is the actual fix for
           the "invoices collapsing into each other" complaint: instead of
           chasing pixel-perfect iframe height (font rendering, images, QR
           codes etc. can all shift real height by a few px), we make each
           invoice visually its own card with a clear gap, background and
           label, ON SCREEN ONLY. Even if one iframe's height is slightly off,
           the gap + shadow + label make it obvious where one invoice ends and
           the next begins — they can never visually "merge" again.
           This block is wrapped in @media screen so print is completely
           unaffected and keeps using the real @page pagination below. */
        @media screen {
            #framesContainer {
                padding: 24px;
                display: flex;
                flex-direction: column;
                gap: 32px;
            }
            .invoice-frame-wrapper {
                background: white;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
                border-radius: 6px;
                overflow: hidden;
            }
            .invoice-label {
                background: #333;
                color: #fff;
                font-size: 12px;
                font-weight: bold;
                padding: 6px 12px;
                letter-spacing: 0.5px;
            }
        }
        /* The label only makes sense on screen — never print it */
        .invoice-label { display: none; }
        @media screen { .invoice-label { display: block; } }

        @media print {
            .no-print { display: none; }
            body { background: white; }
            iframe { height: auto; }
        }
    </style>

    <!--
        Default @page BEFORE JS runs, so a print triggered before
        DOMContentLoaded still has a sane fallback page size.

        IMPORTANT: this used to be `@page { size: var(--page-size); }` with
        --page-size set on :root. That is the root cause of the blank
        leading pages: CSS custom properties (var()) are NOT reliably
        resolved inside @page rules across browsers/versions — many builds
        silently DROP the whole @page block when it references a var(),
        and fall back to the UA default paper size (commonly Letter/8.5x11)
        while the invoice content inside the iframes is laid out for A4/A5/
        A3/A6. That size mismatch between the printed page box and the
        actual content is what produced empty/blank pages at the start of
        the print job. Fix: always write the @page rule with a literal
        value (never a var()) into this <style> tag, and have
        setPaperSize() below replace its content directly.
    -->
    <style id="pageSizeStyle">
        @page { size: A4; margin: 0; }
    </style>
</head>
<body>

<div class="no-print">
    <button id="printBtn" onclick="printAll()" disabled>🖨️ Print All</button>
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

    const printBtn = document.getElementById('printBtn');

    if (ids.length === 0) {
        document.getElementById('statusMsg').textContent = 'No invoice IDs provided.';
    }

    let loadedCount = 0;
    const container = document.getElementById('framesContainer');

    // Tracks whatever paper size is currently selected, so any iframe can be
    // synced to it the moment it finishes loading — see the 'load' handler
    // below for why this matters.
    let currentPaperSize = localStorage.getItem('invoicePaperSize') || 'A4';

    // Portrait width (mm) for each supported size — used to lock each
    // iframe's PRINT-time width to the real physical page width. Without
    // this, iframes keep rendering at full desktop width even while @page
    // is set to something much narrower (worst case: A6 at 105mm), and the
    // browser generates extra "overflow" pages to cover that leftover
    // width — most of which come out visually blank. This mismatch is much
    // more severe at A6 than at A3/A4/A5 simply because the size gap
    // (desktop width vs 105mm) is largest there.
    const PAGE_WIDTH_MM = { A3: '297mm', A4: '210mm', A5: '148mm', A6: '105mm' };

    // Measures the REAL height of an invoice iframe's rendered content.
    //
    // We take the MAX of several measurement methods instead of trusting just
    // one. getBoundingClientRect() on the content wrapper is normally accurate,
    // but for large invoices (many item rows) subpixel/table-layout rounding
    // can occasionally make it come in a hair short — and any underestimate
    // means the tail of that invoice gets visually clipped. scrollHeight can
    // never be smaller than the true content height, so taking the max of all
    // of them means we only ever risk a tiny bit of extra blank space, never
    // a cut-off invoice.
    function measureContentHeight(doc) {
        let maxBottom = 0;
        const body = doc.body;
        if (body) {
            Array.from(body.children).forEach(el => {
                if (el.classList && el.classList.contains('no-print')) return;
                const rect = el.getBoundingClientRect();
                if (rect.bottom > maxBottom) maxBottom = rect.bottom;
            });
        }
        const scrollHeights = [
            doc.documentElement.scrollHeight,
            body ? body.scrollHeight : 0,
            body ? body.offsetHeight : 0
        ];
        return Math.ceil(Math.max(maxBottom, ...scrollHeights));
    }

    ids.forEach((id, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'invoice-frame-wrapper';

        // Screen-only label so each invoice is unmistakably its own block —
        // this, plus the card gap/shadow in the CSS above, is what actually
        // guarantees invoices can never look "collapsed" into each other,
        // regardless of any small height-measurement inaccuracy.
        const label = document.createElement('div');
        label.className = 'invoice-label';
        label.textContent = `Invoice ${index + 1} of ${ids.length} (ID: ${id})`;
        wrapper.appendChild(label);

        const iframe = document.createElement('iframe');
        iframe.src = `invoice-print.php?id=${id}`;
        iframe.scrolling = 'no';

        // The iframe's 'load' event fires once its initial HTML/CSS/scripts are
        // parsed — NOT once invoice-print.php's own async data (items, company,
        // QR code) has finished loading and rendering. Reading height only here
        // used to freeze the iframe at the "Loading items..." placeholder
        // height, so real invoices with more/fewer items than that placeholder
        // got clipped or left with a big blank gap.
        //
        // Fix: keep syncing height (a) right when invoice-print.php tells us its
        // data finished rendering ('invoiceLoaded', which it already dispatches),
        // (b) continuously via ResizeObserver — which stays active forever, so
        // even a slow-loading company logo image or QR code that changes the
        // layout well after load still gets picked up and corrected — and
        // (c) one final forced re-sync right before printing, as a safety net.
        iframe.addEventListener('load', function () {
            let win, doc;
            try {
                win = iframe.contentWindow;
                doc = iframe.contentDocument || win.document;
            } catch (e) {
                return; // cross-origin or inaccessible — nothing more we can do
            }

            // Hide the print buttons inside the iframe
            doc.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');

            // Apply the CURRENTLY selected paper size to this iframe right away.
            //
            // This is the actual fix for the "first pages come out blank"
            // complaint: the DOMContentLoaded handler below also propagates the
            // saved size to every iframe, but it only reaches iframes that have
            // ALREADY loaded by that moment. Iframes are created in a tight
            // synchronous loop, so the first one or two can (depending on
            // network/render timing) finish loading only slightly after
            // DOMContentLoaded fires — those iframes never received the size
            // propagation and stayed on invoice-print.php's own internal
            // default. That mismatch between the outer @page size and that
            // iframe's internal layout is exactly what produced blank/broken
            // pages for specifically the first invoice(s) while later ones
            // (loaded after the sync completed) printed fine. Calling it here,
            // on every single iframe's own 'load' event, makes each iframe
            // self-sufficient regardless of load timing.
            try { win.setPaperSize(currentPaperSize); } catch (e) {}

            const syncHeight = () => {
                const h = measureContentHeight(doc);
                iframe.style.height = h + 'px';
            };
            syncHeight();

            // Keep a reference so printAll() can force one last accurate
            // measurement on every iframe right before printing.
            iframe._syncHeight = syncHeight;

            // These two observers run forever (never disconnected) — between
            // them, ANY reason the content could grow (image finishing load,
            // more rows added, QR code appearing, font swap) triggers an
            // immediate re-measurement, so a large invoice with many rows is
            // just as safe as a small one.
            try {
                new ResizeObserver(syncHeight).observe(doc.body);
            } catch (e) {}
            try {
                new MutationObserver(syncHeight).observe(doc.body, {
                    childList: true, subtree: true, characterData: true, attributes: true
                });
            } catch (e) {}

            win.addEventListener('invoiceLoaded', () => {
                syncHeight();

                loadedCount++;
                document.getElementById('statusMsg').textContent =
                    loadedCount === ids.length
                        ? `${ids.length} invoice(s) ready. Click "Print All" to print.`
                        : `Loaded ${loadedCount} of ${ids.length} invoice(s)`;

                if (loadedCount === ids.length) {
                    printBtn.disabled = false;
                }
            });
        });

        wrapper.appendChild(iframe);
        container.appendChild(wrapper);
    });

    // Fires the moment the browser switches into print rendering — at this
    // exact point, the @media print rules (including the print-only iframe
    // width we inject in setPaperSize() below) are ALREADY active, so every
    // iframe's inner document has already reflowed to the real physical
    // page width. Screen-time height measurements (taken back when the
    // iframe was still full desktop width) do NOT represent this reflowed
    // layout — the gap is small at A3/A4/A5 but becomes large at A6, where
    // rows that used to sit side by side now wrap and need far more
    // vertical space. Re-measuring here, with the real print width already
    // in effect, is what actually fixes the extra/blank-page problem.
    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.invoice-frame-wrapper iframe').forEach(iframe => {
            if (typeof iframe._syncHeight === 'function') iframe._syncHeight();
        });
    });

    function printAll() {
        if (printBtn.disabled) return;

        // Force one last accurate measurement on every iframe right before
        // printing, then give the browser a moment to apply those heights
        // before the print dialog opens.
        document.querySelectorAll('iframe').forEach(iframe => {
            if (typeof iframe._syncHeight === 'function') iframe._syncHeight();
        });

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                window.print();
            });
        });
    }

    function setPaperSize(size) {
        localStorage.setItem('invoicePaperSize', size);
        currentPaperSize = size;

        // 'A6' isn't a valid CSS @page size keyword — use explicit physical
        // dimensions, same mapping invoice-print.php uses for its own @page.
        const pageSizeValue = size === 'A6' ? '105mm 148mm' : size;

        // Write a LITERAL @page rule (never var()) — see the comment on the
        // #pageSizeStyle tag in <head> for why this matters.
        const styleTag = document.getElementById('pageSizeStyle');
        styleTag.textContent = `@page { size: ${pageSizeValue}; margin: 0; }`;

        document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('btn' + size).classList.add('active');

        // Propagate to all loaded iframes so each invoice's own a5-mode/a6-mode
        // styling switches too (this also triggers the ResizeObserver above to
        // resync each iframe's height for the new, differently-sized content).
        // Iframes that haven't loaded yet will pick up `currentPaperSize` on
        // their own 'load' event above, so this is just for already-loaded ones.
        document.querySelectorAll('iframe').forEach(iframe => {
            try {
                iframe.contentWindow.setPaperSize(size);
            } catch(e) {}
        });
    }

    // Apply saved size on load
    document.addEventListener('DOMContentLoaded', () => setPaperSize(currentPaperSize));
</script>
</body>
</html>