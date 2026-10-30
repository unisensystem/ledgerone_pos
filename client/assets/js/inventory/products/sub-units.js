// Product-specific selling units and packs. Self-contained: it keeps a hidden input
// (name="subUnitsJson") inside #productForm up to date, so the existing FormData
// submit in product-add.js sends it automatically — no change needed there.
// Place in: client/assets/js/inventory/products/sub-units.js
(function () {
    const PRESETS = {
        kg: [
            { key: 'half', label: '500 Gram', qty: 0.5 },
            { key: 'pao', label: '250 Gram', qty: 0.25 },
            { key: 'halfpao', label: 'Half Pao', qty: 0.125 },
            { key: 'chhatank', label: 'Chhatank', qty: 0.0625 }
        ],
        litre: [
            { key: 'half', label: '500 mL', qty: 0.5 },
            { key: 'pao', label: '250 mL', qty: 0.25 },
            { key: 'halfpao', label: 'Half Pao Litre', qty: 0.125 },
            { key: 'chhatank', label: 'Chhatank Litre', qty: 0.0625 }
        ]
    };

    let currentType = null;
    let editLoaded = null;  // saved sub-units fetched in edit mode

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function selectedBaseUnit() {
        const groupMode = document.getElementById('uomTypeGroup');
        if (groupMode && groupMode.checked) {
            const units = window.currentGroupUnits || [];
            const base = units.find(unit => unit.is_base_unit == 1);
            return base ? { id: base.id, name: base.uom_name } : null;
        }

        const select = document.getElementById('defaultUnit');
        if (!select || !select.value) return null;
        const selected = (window.unitsData || []).find(unit => String(unit.id) === String(select.value));
        if (!selected) return { id: select.value, name: select.options[select.selectedIndex].text };
        if (selected.is_base_unit == 1) return { id: selected.id, name: selected.uom_name };

        const base = (window.unitsData || []).find(unit => String(unit.id) === String(selected.base_unit_id));
        return base ? { id: base.id, name: base.uom_name } : null;
    }

    let barcodeSeq = 0;
    function generateBarcodeValue() {
        // 13 digits: unique enough per tenant, easy for scanners (CODE128)
        barcodeSeq = (barcodeSeq + 1) % 100;
        return String(Date.now()).slice(-10) + String(Math.floor(Math.random() * 10)) + String(barcodeSeq).padStart(2, '0');
    }

    function syncHidden() {
        const section = document.getElementById('subUnitsSection');
        const hidden = document.getElementById('subUnitsJson');
        if (!hidden) return;
        const rows = [];
        if (section && section.style.display !== 'none') {
            section.querySelectorAll('.sub-unit-row').forEach(row => {
                const cb = row.querySelector('.sub-unit-check');
                if (!cb.checked) return;
                const label = row.querySelector('.sub-unit-label').value.trim();
                const qty = parseFloat(row.querySelector('.sub-unit-qty').value);
                const price = parseFloat(row.querySelector('.sub-unit-price').value);
                const barcode = row.querySelector('.sub-unit-barcode').value.trim();
                if (!label || !(qty > 0)) return;
                rows.push({
                    label: label,
                    qty_in_base: qty,
                    price: isNaN(price) ? 0 : price,
                    barcode: barcode,
                    is_custom: row.dataset.custom === '1' ? 1 : 0
                });
            });
        }
        hidden.value = JSON.stringify(rows);
    }

    function buildRow(opts) {
        // opts: {label, qty, price, checked, custom, key}
        const row = document.createElement('div');
        row.className = 'sub-unit-row';
        row.dataset.custom = opts.custom ? '1' : '0';
        if (opts.key) row.dataset.key = opts.key;
        row.style.cssText = 'display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap;';

        const labelReadonly = opts.custom ? '' : 'readonly';
        const qtyReadonly = opts.custom ? '' : 'readonly';
        row.innerHTML = `
            <input type="checkbox" class="sub-unit-check" ${opts.checked ? 'checked' : ''} style="width:18px;height:18px;">
            <input type="text" class="sub-unit-label" value="${esc(opts.label)}" placeholder="Name (e.g. 750 gram)" ${labelReadonly} style="flex:1;min-width:140px;height:36px;padding:0 10px;">
            <input type="number" class="sub-unit-qty" value="${opts.qty ?? ''}" step="0.000001" min="0" placeholder="Qty in base" title="Fraction of base unit (0.5 = half)" ${qtyReadonly} style="width:110px;height:36px;padding:0 10px;">
            <input type="number" class="sub-unit-price" value="${opts.price ?? ''}" step="0.001" min="0" placeholder="Price" style="width:120px;height:36px;padding:0 10px;">
            <input type="text" class="sub-unit-barcode" value="${esc(opts.barcode || '')}" placeholder="Barcode (scan / type)" style="width:170px;height:36px;padding:0 10px;">
            <button type="button" class="btn btn-secondary sub-unit-autobarcode" style="height:36px;padding:0 12px;">Auto</button>
            ${opts.custom ? '<button type="button" class="btn btn-danger sub-unit-remove" style="height:36px;padding:0 12px;">-</button>' : ''}
        `;
        return row;
    }

    function render(type, baseName) {
        const list = document.getElementById('subUnitsList');
        const unitLabel = document.getElementById('subUnitsBaseLabel');
        if (!list) return;
        list.innerHTML = '';
        if (unitLabel) unitLabel.textContent = baseName;

        const saved = editLoaded || [];
        const used = new Set();

        (PRESETS[type] || []).forEach(p => {
            // match a saved row by qty (labels may differ between kg / litre)
            const match = saved.find(s => Math.abs(parseFloat(s.qty_in_base) - p.qty) < 0.000001 && s.is_custom != 1);
            if (match) used.add(match.id);
            list.appendChild(buildRow({
                label: match ? match.label : p.label,
                qty: p.qty,
                price: match ? match.price : '',
                barcode: match ? match.barcode : '',
                checked: !!match,
                custom: false,
                key: p.key
            }));
        });

        saved.filter(s => !used.has(s.id)).forEach(s => {
            list.appendChild(buildRow({ label: s.label, qty: s.qty_in_base, price: s.price, barcode: s.barcode, checked: true, custom: true }));
        });

        syncHidden();
    }

    function refresh() {
        const section = document.getElementById('subUnitsSection');
        if (!section) return;
        const base = selectedBaseUnit();
        if (!base) {
            section.style.display = 'none';
            currentType = null;
            syncHidden();
            return;
        }
        const normalized = base.name.trim().toLowerCase();
        const type = /^(kg|kgs|kilo|kilogram|kilograms)\b/.test(normalized)
            ? 'kg'
            : (/^(l|ltr|ltrs|liter|litre|liters|litres)\b/.test(normalized) ? 'litre' : `unit:${base.id}`);
        section.style.display = '';
        if (type !== currentType) {
            currentType = type;
            render(type, base.name);
        } else {
            const unitLabel = document.getElementById('subUnitsBaseLabel');
            if (unitLabel) unitLabel.textContent = base.name;
        }
    }

    function init() {
        const section = document.getElementById('subUnitsSection');
        if (!section) return;

        document.getElementById('defaultUnit').addEventListener('change', refresh);
        document.querySelectorAll('input[name="uomType"]').forEach(r => r.addEventListener('change', refresh));
        document.getElementById('uomGroup').addEventListener('change', refresh);
        window.addEventListener('product-uom-group-loaded', refresh);
        window.addEventListener('product-units-loaded', refresh);

        section.addEventListener('input', syncHidden);
        section.addEventListener('change', function (e) {
            // Ticking a pack with an empty barcode auto-fills a unique one
            if (e.target.classList.contains('sub-unit-check') && e.target.checked) {
                const bc = e.target.closest('.sub-unit-row').querySelector('.sub-unit-barcode');
                if (!bc.value.trim()) bc.value = generateBarcodeValue();
            }
            syncHidden();
        });

        section.addEventListener('click', function (e) {
            if (e.target.classList.contains('sub-unit-autobarcode')) {
                const input = e.target.closest('.sub-unit-row').querySelector('.sub-unit-barcode');
                input.value = generateBarcodeValue();
                syncHidden();
                return;
            }
            if (e.target.classList.contains('sub-unit-remove')) {
                e.target.closest('.sub-unit-row').remove();
                syncHidden();
            }
        });

        document.getElementById('addCustomSubUnit').addEventListener('click', function () {
            document.getElementById('subUnitsList').appendChild(buildRow({ label: '', qty: '', price: '', checked: true, custom: true }));
            syncHidden();
        });

        // Edit mode: fetch saved sub-units, then wait until product-add.js has
        // populated the Default Unit dropdown (it does that after a short delay).
        const productId = new URLSearchParams(window.location.search).get('id');
        if (productId) {
            fetch(`../../../../server/api/inventory/products/sub-units-get.php?product_id=${encodeURIComponent(productId)}`)
                .then(r => r.json())
                .then(data => {
                    editLoaded = (data.success && data.sub_units) ? data.sub_units : [];
                    let tries = 0;
                    const timer = setInterval(() => {
                        tries++;
                        const select = document.getElementById('defaultUnit');
                        if ((select && select.value) || tries > 40) {
                            clearInterval(timer);
                            currentType = null;
                            refresh();
                        }
                    }, 300);
                })
                .catch(err => console.error('Error loading sub-units:', err));
        }

        refresh();
    }

    window.addEventListener('load', init);
})();