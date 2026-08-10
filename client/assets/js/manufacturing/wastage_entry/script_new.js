(function () {
    const API_URL = '../../../../server/api/manufacturing/wastage_entry/index.php';

    let orders = [];
    let loadedMaterials = [];
    let finishedGood = null; // { product_id, product_code, product_name, order_qty, uom_id, uom_name }

    async function init() {
        setDefaultDate();
        await generateWastageNo();
        await loadOrders();
        bindEvents();
    }

    function setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('wastageDate').value = today;
    }

    async function generateWastageNo() {
        const res = await fetch(`${API_URL}?action=next_wastage_no`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('wastageNo').value = data.wastage_no;
        }
    }

    async function loadOrders() {
        const res = await fetch(`${API_URL}?action=production_orders`);
        const data = await res.json();
        if (data.success) {
            orders = data.data;
            const datalist = document.getElementById('orderList');
            datalist.innerHTML = '';
            orders.forEach(o => {
                const opt = document.createElement('option');
                opt.value = `${o.order_no} — ${o.product_name}`;
                opt.dataset.id = o.id;
                datalist.appendChild(opt);
            });
        }
    }

    async function loadMaterials() {
        const orderValue = document.getElementById('productionOrderInput').value;
        const selectedOrder = orders.find(o => `${o.order_no} — ${o.product_name}` === orderValue);

        if (!selectedOrder) {
            alert('Please select a valid production order');
            return;
        }

        const res = await fetch(`${API_URL}?action=load_materials&po_id=${selectedOrder.id}`);
        const data = await res.json();

        if (!data.success) {
            alert('Error loading materials: ' + data.message);
            return;
        }

        finishedGood    = data.finished_good || null;
        loadedMaterials = data.materials || data.data || [];

        document.getElementById('emptyPlaceholder').style.display = 'none';

        renderFGSection(finishedGood);
        renderMaterialsTable(loadedMaterials);
        updateHeaders();
    }

    // ── Finished Good section ────────────────────────────────────────────────

    function renderFGSection(fg) {
        const section = document.getElementById('fgSection');
        const tbody   = document.getElementById('fgTable');

        if (!fg) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';
        const orderedQty = parseFloat(fg.order_qty) || 0;

        tbody.innerHTML = `
            <tr>
                <td><strong>${fg.product_code} — ${fg.product_name}</strong></td>
                <td>${orderedQty.toFixed(2)}</td>
                <td>${fg.uom_name}</td>
                <td>
                    <input type="number" class="form-control" id="fgWastageInput"
                        data-ordered="${orderedQty}"
                        min="0" step="0.01"
                        placeholder="0.00"
                        style="width:120px;">
                </td>
                <td class="wastage-qty-display" id="fgWastageQty">0.00</td>
            </tr>
        `;

        document.getElementById('fgWastageInput').addEventListener('input', recalculateFG);
    }

    function recalculateFG() {
        const input      = document.getElementById('fgWastageInput');
        const display    = document.getElementById('fgWastageQty');
        if (!input || !display) return;

        const orderedQty  = parseFloat(input.dataset.ordered) || 0;
        const wastageType = document.getElementById('wastageType').value;
        const inputVal    = parseFloat(input.value) || 0;
        let wastageQty    = 0;

        if (wastageType === 'percentage') {
            const capped  = Math.min(inputVal, 100);
            if (inputVal > 100) input.value = 100;
            wastageQty = (capped / 100) * orderedQty;
        } else {
            if (inputVal > orderedQty) input.value = orderedQty;
            wastageQty = Math.min(inputVal, orderedQty);
        }

        display.textContent = wastageQty.toFixed(2);
    }

    // ── Raw Material section ─────────────────────────────────────────────────

    function renderMaterialsTable(materials) {
        const section = document.getElementById('materialsSection');
        const tbody   = document.getElementById('materialsTable');

        if (materials.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';
        tbody.innerHTML = '';

        materials.forEach((mat, idx) => {
            const tr = document.createElement('tr');
            tr.dataset.idx = idx;
            tr.innerHTML = `
                <td><strong>${mat.material_code} — ${mat.material_name}</strong></td>
                <td>${parseFloat(mat.ordered_qty).toFixed(2)}</td>
                <td>${mat.uom_name}</td>
                <td>
                    <input type="number" class="form-control wastage-input"
                        data-idx="${idx}"
                        data-ordered="${mat.ordered_qty}"
                        min="0" step="0.01"
                        placeholder="0.00"
                        style="width:120px;">
                </td>
                <td class="wastage-qty-display" id="wq_${idx}">0.00</td>
            `;
            tbody.appendChild(tr);
        });

        bindInputEvents();
    }

    function recalculateRow(input) {
        const idx         = input.dataset.idx;
        const orderedQty  = parseFloat(input.dataset.ordered) || 0;
        const wastageType = document.getElementById('wastageType').value;
        const inputVal    = parseFloat(input.value) || 0;
        let wastageQty    = 0;

        if (wastageType === 'percentage') {
            if (inputVal > 100) input.value = 100;
            wastageQty = (Math.min(inputVal, 100) / 100) * orderedQty;
        } else {
            if (inputVal > orderedQty) input.value = orderedQty;
            wastageQty = Math.min(inputVal, orderedQty);
        }

        document.getElementById(`wq_${idx}`).textContent = wastageQty.toFixed(2);
    }

    function recalculateAll() {
        document.querySelectorAll('.wastage-input').forEach(input => recalculateRow(input));
        recalculateFG();
        updateHeaders();
    }

    function bindInputEvents() {
        document.querySelectorAll('.wastage-input').forEach(input => {
            input.addEventListener('input', () => recalculateRow(input));
        });
    }

    function updateHeaders() {
        const wastageType = document.getElementById('wastageType').value;
        const label = wastageType === 'percentage' ? 'Wastage %' : 'Wastage Qty';
        document.getElementById('wastageInputHeader').textContent = label;
        document.getElementById('fgInputHeader').textContent     = label;
    }

    // ── Save ─────────────────────────────────────────────────────────────────

    async function saveWastageEntry() {
        const wastageNo   = document.getElementById('wastageNo').value;
        const orderValue  = document.getElementById('productionOrderInput').value;
        const wastageDate = document.getElementById('wastageDate').value;
        const wastageType = document.getElementById('wastageType').value;
        const remarks     = document.getElementById('remarks').value;

        const selectedOrder = orders.find(o => `${o.order_no} — ${o.product_name}` === orderValue);

        if (!selectedOrder) {
            alert('Please select a valid production order');
            return;
        }

        if (!wastageDate) {
            alert('Please enter a wastage date');
            return;
        }

        if (!finishedGood && loadedMaterials.length === 0) {
            alert('Please load materials first');
            return;
        }

        // Collect raw material items
        const items = [];
        document.querySelectorAll('.wastage-input').forEach((input, i) => {
            const mat      = loadedMaterials[i];
            const inputVal = parseFloat(input.value) || 0;
            if (inputVal <= 0) return;

            const orderedQty = parseFloat(input.dataset.ordered) || 0;
            const wastageQty = wastageType === 'percentage'
                ? (Math.min(inputVal, 100) / 100) * orderedQty
                : Math.min(inputVal, orderedQty);

            items.push({
                material_id:   mat.material_id,
                uom_id:        mat.uom_id,
                ordered_qty:   orderedQty,
                wastage_input: inputVal,
                wastage_qty:   parseFloat(wastageQty.toFixed(2))
            });
        });

        // Collect FG wastage
        let fgWastageInput = 0;
        let fgWastageQty   = 0;

        const fgInput = document.getElementById('fgWastageInput');
        if (fgInput) {
            fgWastageInput = parseFloat(fgInput.value) || 0;
            const fgDisplay = document.getElementById('fgWastageQty');
            fgWastageQty = parseFloat(fgDisplay ? fgDisplay.textContent : 0) || 0;
        }

        if (items.length === 0 && fgWastageQty <= 0) {
            alert('Please enter wastage for at least one material or the finished good');
            return;
        }

        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="las la-spinner la-spin"></i> Saving...';

        const payload = {
            wastage_no:          wastageNo,
            production_order_id: selectedOrder.id,
            wastage_date:        wastageDate,
            wastage_type:        wastageType,
            remarks:             remarks || null,
            items:               items,
            fg_product_id:       finishedGood ? finishedGood.product_id   : null,
            fg_uom_id:           finishedGood ? finishedGood.uom_id       : null,
            fg_wastage_input:    fgWastageInput,
            fg_wastage_qty:      fgWastageQty
        };

        try {
            const res  = await fetch(API_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = 'list.php?success=1';
            } else {
                alert('Error: ' + data.message);
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="las la-save"></i> Save Wastage Entry';
            }
        } catch (err) {
            alert('Error saving wastage entry');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="las la-save"></i> Save Wastage Entry';
        }
    }

    function bindEvents() {
        document.getElementById('loadMaterialsBtn').addEventListener('click', loadMaterials);
        document.getElementById('saveBtn').addEventListener('click', saveWastageEntry);
        document.getElementById('cancelBtn').addEventListener('click', () => {
            if (!finishedGood && loadedMaterials.length === 0 || confirm('Discard changes?')) {
                window.location.href = 'list.php';
            }
        });
        document.getElementById('wastageType').addEventListener('change', recalculateAll);
    }

    init();
})();
