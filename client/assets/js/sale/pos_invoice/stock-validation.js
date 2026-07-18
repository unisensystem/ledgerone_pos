// Stock validation for invoice saving

function validateStockBeforeSave(onConfirm) {
    const itemsTable = document.getElementById('itemsTable')?.getElementsByTagName('tbody')[0];
    if (!itemsTable) { if (onConfirm) onConfirm(); return true; }

    const violations = [];
    const rows = itemsTable.rows;

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.classList.contains('child-row')) continue;

        let totalQty = 0;
        row.querySelectorAll('.unit-input').forEach(input => {
            totalQty += (parseFloat(input.value) || 0) * (parseFloat(input.dataset.conversionFactor) || 1);
        });

        const availableStock = parseFloat(row.dataset.availableStock) || 0;
        const productName = row.cells[1]?.querySelector('.search-input')?.value || 'Unknown Product';

        if (totalQty > 0 && availableStock > 0 && totalQty > availableStock) {
            violations.push({ productName, required: totalQty, available: availableStock });
        }
    }

    if (violations.length === 0) {
        if (onConfirm) onConfirm();
        return true;
    }

    document.getElementById('stockViolationBody').innerHTML = violations.map(v => `
        <tr>
            <td style="padding:7px 10px;border-bottom:1px solid var(--border-default);">${v.productName}</td>
            <td style="padding:7px 10px;border-bottom:1px solid var(--border-default);text-align:center;color:var(--error);font-weight:600;">${v.required.toFixed(3)}</td>
            <td style="padding:7px 10px;border-bottom:1px solid var(--border-default);text-align:center;color:var(--success);font-weight:600;">${v.available.toFixed(3)}</td>
        </tr>`).join('');

    const modal = document.getElementById('stockViolationModal');
    modal.style.display = 'flex';

    document.getElementById('stockViolationCancel').onclick = () => { modal.style.display = 'none'; };
    document.getElementById('stockViolationConfirm').onclick = () => {
        modal.style.display = 'none';
        if (onConfirm) onConfirm();
    };

    return false;
}
