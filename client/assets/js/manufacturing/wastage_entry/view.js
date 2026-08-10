(function () {
    const LIST_API = '../../../../server/api/manufacturing/wastage_entry/list.php';

    async function loadWastage() {
        const res  = await fetch(`${LIST_API}?action=view&id=${WASTAGE_ID}`);
        const data = await res.json();

        if (!data.success || !data.data) {
            document.getElementById('wastageNoTitle').textContent = 'Not Found';
            return;
        }

        const w = data.data;

        document.getElementById('wastageNoTitle').textContent  = w.wastage_no;
        document.getElementById('wastageTypeBadge').innerHTML  =
            `<span class="badge ${w.wastage_type === 'percentage' ? 'type-pct' : 'type-qty'}">
                ${w.wastage_type === 'percentage' ? 'Percentage' : 'Quantity'}
            </span>`;

        document.getElementById('dWastageNo').textContent    = w.wastage_no;
        document.getElementById('dOrderNo').textContent      = w.order_no;
        document.getElementById('dProductName').textContent  = w.product_name;
        document.getElementById('dBranchName').textContent   = w.branch_name;
        document.getElementById('dWastageDate').textContent  = formatDate(w.wastage_date);
        document.getElementById('dWastageType').textContent  =
            w.wastage_type === 'percentage' ? 'Percentage (%)' : 'Quantity';
        document.getElementById('dRemarks').textContent      = w.remarks || '—';

        const inputLabel = w.wastage_type === 'percentage' ? 'Wastage %' : 'Wastage Input';
        document.getElementById('inputHeader').textContent   = inputLabel;
        document.getElementById('fgInputHeader').textContent = inputLabel;

        // Finished Good Wastage
        renderFGSection(w);

        // Raw Material Wastage
        renderItems(w.items || [], w.wastage_type);
    }

    function renderFGSection(w) {
        const fgQty = parseFloat(w.fg_wastage_qty) || 0;
        if (fgQty <= 0) return;

        document.getElementById('fgSection').style.display = 'block';

        const inputDisplay = w.wastage_type === 'percentage'
            ? `${parseFloat(w.fg_wastage_input).toFixed(2)}%`
            : parseFloat(w.fg_wastage_input).toFixed(2);

        document.getElementById('fgTable').innerHTML = `
            <tr>
                <td><strong>${w.product_name}</strong></td>
                <td>${parseFloat(w.order_qty || 0).toFixed(2)}</td>
                <td>—</td>
                <td>${inputDisplay}</td>
                <td><strong>${fgQty.toFixed(2)}</strong></td>
            </tr>
        `;
    }

    function renderItems(items, wastageType) {
        const section = document.getElementById('materialsSection');
        const tbody   = document.getElementById('itemsTable');

        if (items.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';
        tbody.innerHTML = items.map((item, i) => {
            const inputDisplay = wastageType === 'percentage'
                ? `${parseFloat(item.wastage_input).toFixed(2)}%`
                : parseFloat(item.wastage_input).toFixed(2);

            return `
                <tr>
                    <td style="color:#9AA1AE;">${i + 1}</td>
                    <td><strong>${item.material_code} — ${item.material_name}</strong></td>
                    <td>${parseFloat(item.ordered_qty).toFixed(2)}</td>
                    <td>${item.uom_name}</td>
                    <td>${inputDisplay}</td>
                    <td><strong>${parseFloat(item.wastage_qty).toFixed(2)}</strong></td>
                </tr>
            `;
        }).join('');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    loadWastage();
})();
