// Dynamic UOM + Scheme System for Sale Order
let maxUnitColumns = 0;
let allUnitHeaders = [];

const rowSchemeData = new Map();

const SCHEME_TYPES = {
    SALE_ON_TP:   'sale_on_tp',
    LESS:         'less',
    LESS_SPECIAL: 'less_special',
    GIVEN:        'given'
};

const SCHEME_OPTIONS = [
    { value: 'sale_on_tp',   label: 'Sale On TP' },
    { value: 'less',         label: 'Less' },
    { value: 'less_special', label: 'Less Special' },
    { value: 'given',        label: 'Given' }
];

// ─── Scheme Cell ────────────────────────────────────────────────────────────

function createSchemeCell(row) {
    const select = document.createElement('select');
    select.className = 'table-input scheme-select';
    select.tabIndex = -1;

    SCHEME_OPTIONS.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.value;
        opt.textContent = s.label;
        select.appendChild(opt);
    });

    const saved = localStorage.getItem('defaultScheme') || SCHEME_TYPES.SALE_ON_TP;
    select.value = saved;

    select.addEventListener('change', async function () {
        await onSchemeChange(row, this.value);
    });

    return select;
}

async function onSchemeChange(row, schemeType) {
    const productId = row.dataset.productId;
    if (!productId) return;

    rowSchemeData.set(row, { schemeType, productId });

    switch (schemeType) {
        case SCHEME_TYPES.SALE_ON_TP:   applyDefaultScheme(row);              break;
        case SCHEME_TYPES.LESS:         await applyLessScheme(row);           break;
        case SCHEME_TYPES.LESS_SPECIAL: await applyLessSpecialScheme(row);    break;
        case SCHEME_TYPES.GIVEN:        await applyGivenScheme(row);          break;
    }
}

// ─── Scheme: Sale On TP ─────────────────────────────────────────────────────

function applyDefaultScheme(row) {
    row.dataset.useSchemeTO = 'false';
    row.dataset.schemeToAmount = '0';

    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');

    if (toInput)  { toInput.readOnly  = true;  toInput.style.background  = '#f0f0f0'; toInput.value  = '0.00'; }
    if (focInput) { focInput.readOnly = true;  focInput.style.background = '#f0f0f0'; focInput.value = '0'; }

    _recalcRow(row);
}

// ─── Scheme: Less ───────────────────────────────────────────────────────────

async function applyLessScheme(row) {
    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');

    if (toInput)  { toInput.readOnly  = false; toInput.style.background  = ''; }
    if (focInput) { focInput.readOnly = true;  focInput.style.background = '#e8e8e8'; focInput.value = '0'; }

    const schemes = await fetchProductSchemes(row.dataset.productId);
    rowSchemeData.set(row, { schemeType: SCHEME_TYPES.LESS, productId: row.dataset.productId, schemes });
    await calculateLessSchemeAmounts(row);
}

async function calculateLessSchemeAmounts(row) {
    const data    = rowSchemeData.get(row) || {};
    let schemes   = data.schemes;
    if (!schemes) {
        schemes = await fetchProductSchemes(row.dataset.productId);
        rowSchemeData.set(row, { ...data, schemes });
    }

    let totalTO = 0;
    row.querySelectorAll('.unit-input').forEach(input => {
        const qty    = parseFloat(input.value) || 0;
        const unitId = input.dataset.unitId;
        const scheme = (schemes || []).find(s => parseFloat(s.unit_id) == parseFloat(unitId));
        if (scheme && qty > 0 && scheme.to_qty > 0) {
            totalTO += Math.floor(qty / scheme.to_qty) * scheme.to_rs;
        }
    });

    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    if (toInput)  toInput.value  = totalTO.toFixed(2);
    if (focInput) focInput.value = '0';

    row.dataset.useSchemeTO    = 'true';
    row.dataset.schemeToAmount = totalTO.toFixed(2);
    _recalcRow(row);
}

// ─── Scheme: Less Special ───────────────────────────────────────────────────

async function applyLessSpecialScheme(row) {
    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');

    if (toInput)  { toInput.readOnly  = false; toInput.style.background  = ''; }
    if (focInput) { focInput.readOnly = true;  focInput.style.background = '#f0f0f0'; focInput.value = '0'; }

    const schemes = await fetchProductSchemes(row.dataset.productId);
    rowSchemeData.set(row, { schemeType: SCHEME_TYPES.LESS_SPECIAL, productId: row.dataset.productId, schemes });
    await calculateLessSpecialSchemeAmounts(row);
}

async function calculateLessSpecialSchemeAmounts(row) {
    const data    = rowSchemeData.get(row) || {};
    let schemes   = data.schemes;
    if (!schemes) {
        schemes = await fetchProductSchemes(row.dataset.productId);
        rowSchemeData.set(row, { ...data, schemes });
    }

    const priceInput = row.querySelector('.price-cell input');
    const salePrice  = parseFloat(priceInput?.value) || 0;

    let totalQty = 0;
    row.querySelectorAll('.unit-input').forEach(input => {
        totalQty += (parseFloat(input.value) || 0) * (parseFloat(input.dataset.conversionFactor) || 1);
    });

    let totalTO = 0;
    if (schemes && schemes.length > 0 && totalQty > 0 && salePrice > 0) {
        const s = schemes[0];
        const denom = parseFloat(s.promo_qty || 0) + parseFloat(s.bonus_qty || 0);
        if (denom > 0) totalTO = (totalQty * salePrice) / denom;
    }

    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    if (toInput)  toInput.value  = totalTO.toFixed(2);
    if (focInput) focInput.value = '0';

    row.dataset.useSchemeTO    = 'true';
    row.dataset.schemeToAmount = totalTO.toFixed(2);
    _recalcRow(row);
}

// ─── Scheme: Given ──────────────────────────────────────────────────────────

async function applyGivenScheme(row) {
    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');

    if (toInput)  { toInput.readOnly  = true;  toInput.style.background  = '#f0f0f0'; toInput.value  = '0.00'; }
    if (focInput) { focInput.readOnly = false; focInput.style.background = ''; }

    const schemes = await fetchProductSchemes(row.dataset.productId);
    rowSchemeData.set(row, { schemeType: SCHEME_TYPES.GIVEN, productId: row.dataset.productId, schemes });
    await calculateGivenSchemeAmounts(row);
}

async function calculateGivenSchemeAmounts(row) {
    const data    = rowSchemeData.get(row) || {};
    let schemes   = data.schemes;
    if (!schemes) {
        schemes = await fetchProductSchemes(row.dataset.productId);
        rowSchemeData.set(row, { ...data, schemes });
    }

    let totalFOC = 0;
    row.querySelectorAll('.unit-input').forEach(input => {
        const qty    = parseFloat(input.value) || 0;
        const unitId = input.dataset.unitId;
        const scheme = (schemes || []).find(s => parseFloat(s.unit_id) == parseFloat(unitId));
        if (scheme && qty > 0 && scheme.promo_qty > 0) {
            totalFOC += Math.floor(qty / scheme.promo_qty) * scheme.bonus_qty;
        }
    });

    const toInput  = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    if (toInput)  toInput.value  = '0.00';
    if (focInput) focInput.value = totalFOC.toFixed(2);

    row.dataset.useSchemeTO    = 'false';
    row.dataset.schemeToAmount = '0';
    _recalcRow(row);
}

// ─── Fetch Schemes ──────────────────────────────────────────────────────────

async function fetchProductSchemes(productId) {
    if (!productId) return [];
    try {
        const res  = await fetch(`../../../../server/api/sale/pos_invoice/get-product-schemes.php?product_id=${productId}`);
        const data = await res.json();
        return (data.success && data.schemes) ? data.schemes : [];
    } catch (e) {
        return [];
    }
}

// ─── Core Row Calculation ───────────────────────────────────────────────────

function _recalcRow(row) {
    let totalQty = 0;
    row.querySelectorAll('.unit-input').forEach(input => {
        totalQty += (parseFloat(input.value) || 0) * (parseFloat(input.dataset.conversionFactor) || 1);
    });
    const price = parseFloat(row.querySelector('.price-cell input')?.value) || 0;
    calculateRowAmounts(row, totalQty, price);
}

function calculateRowAmounts(row, qty, price) {
    qty   = Number(qty)   || 0;
    price = Number(price) || 0;

    const gross = qty * price;
    const grossInput = row.querySelector('.gross-cell input');
    if (grossInput) grossInput.value = gross.toFixed(2);

    const discPercent = parseFloat(row.querySelector('.disc-percent-cell input')?.value) || 0;
    const discAmt     = gross * (discPercent / 100);
    const discAmtInput = row.querySelector('.disc-amount-cell input');
    if (discAmtInput) discAmtInput.value = discAmt.toFixed(2);

    const afterDiscount = gross - discAmt;

    // T.O Amount — read from input (scheme sets it; Sale On TP = 0)
    const toInput = row.querySelector('.to-amount-cell input');
    const toAmt   = parseFloat(toInput?.value) || 0;
    const afterTO = afterDiscount - toAmt;

    // GST
    const gstPercent = parseFloat(row.querySelector('.gst-percent-cell input')?.value) || 0;
    const gstAmt     = afterTO * (gstPercent / 100);
    const gstInput   = row.querySelector('.gst-amount-cell input');
    if (gstInput) gstInput.value = gstAmt.toFixed(2);

    const net = afterTO + gstAmt;
    const netInput = row.querySelector('.net-cell input');
    if (netInput) netInput.value = net.toFixed(2);

    updateInvoiceSummaryDynamic();
}

// ─── UOM Columns ────────────────────────────────────────────────────────────

function getProductUOMDetails(product) {
    const details = { type: product.uom_type || 'unit', units: [] };

    if (details.type === 'group' && product.group_units && product.group_units.length > 0) {
        details.units = product.group_units.map(u => ({
            id: u.id,
            name: u.uom_name,
            conversionFactor: parseFloat(u.conversion_factor) || 1
        }));
    } else if (product.default_unit_id) {
        details.units = [{
            id: product.default_unit_id,
            name: product.default_unit_name || 'Unit',
            conversionFactor: 1
        }];
    }
    return details;
}

function recalculateMaxColumns() {
    maxUnitColumns = 0;
    allUnitHeaders = [];

    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    for (let row of rows) {
        if (row.dataset.productUomData) {
            const uomDetails = JSON.parse(row.dataset.productUomData);
            const units = uomDetails.units || uomDetails;
            const len = Array.isArray(units) ? units.length : (uomDetails.units?.length || 0);
            if (len > maxUnitColumns) maxUnitColumns = len;
        }
    }

    updateTableHeaders();
    updateAllRowUnitCells();
    updateFooterTotals();
}

function updateTableHeaders() {
    const headerRow = document.querySelector('#itemsTable thead tr');
    headerRow.querySelectorAll('.unit-header').forEach(h => h.remove());

    const schemeHeader = headerRow.querySelector('.scheme-header');
    // Insert unit headers AFTER scheme header (Unit 1, Unit 2, Unit 3...)
    for (let i = maxUnitColumns - 1; i >= 0; i--) {
        const th = document.createElement('th');
        th.width = '8%';
        th.className = 'unit-header';
        th.textContent = `Unit ${i + 1}`;
        schemeHeader.insertAdjacentElement('afterend', th);
    }
}

function updateAllRowUnitCells() {
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    for (let row of rows) {
        if (row.dataset.productUomData) updateRowUnitCells(row);
    }
}

function updateRowUnitCells(row) {
    if (!row.dataset.productUomData) return;

    const uomData    = JSON.parse(row.dataset.productUomData);
    const unitsList  = uomData.units || uomData;

    // Save existing values
    const existing = {};
    row.querySelectorAll('.unit-input').forEach(inp => {
        if (inp.dataset.unitId) existing[inp.dataset.unitId] = inp.value;
    });

    // Remove old unit cells
    row.querySelectorAll('.unit-cell').forEach(c => c.remove());

    // Insert unit cells AFTER the scheme cell (Unit 1, Unit 2, Unit 3...)
    const schemeCell = row.querySelector('.scheme-cell');
    if (!schemeCell) return;
    const baseSchemeCellIndex = Array.from(row.cells).indexOf(schemeCell);

    for (let i = 0; i < maxUnitColumns; i++) {
        const cell = row.insertCell(baseSchemeCellIndex + 1 + i);
        cell.className = 'unit-cell';

        if (i < unitsList.length) {
            const unit = unitsList[i];

            const container = document.createElement('div');
            container.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:2px;padding:2px';

            const label = document.createElement('div');
            label.textContent = unit.name;
            label.style.cssText = 'color:var(--subtext);font-size:9px;font-weight:500;white-space:nowrap;text-align:center';

            const input = document.createElement('input');
            input.type  = 'number';
            input.className = 'table-input unit-input';
            input.min   = '0';
            input.step  = '0.01';
            input.value = existing[unit.id] || '0';
            input.dataset.unitId           = unit.id;
            input.dataset.conversionFactor = unit.conversionFactor || 1;

            input.addEventListener('input', function () {
                const schemeSelect = row.querySelector('.scheme-select');
                const scheme = schemeSelect ? schemeSelect.value : SCHEME_TYPES.SALE_ON_TP;

                if (scheme === SCHEME_TYPES.LESS) {
                    calculateLessSchemeAmounts(row);
                } else if (scheme === SCHEME_TYPES.LESS_SPECIAL) {
                    calculateLessSpecialSchemeAmounts(row);
                } else if (scheme === SCHEME_TYPES.GIVEN) {
                    calculateGivenSchemeAmounts(row);
                } else {
                    calculateTotalQuantity(row);
                }
            });

            container.appendChild(label);
            container.appendChild(input);
            cell.appendChild(container);
        } else {
            cell.textContent = '-';
            cell.style.cssText = 'text-align:center;color:var(--subtext);font-size:11px';
        }
    }
}

function calculateTotalQuantity(row) {
    let totalQty = 0;
    row.querySelectorAll('.unit-input').forEach(input => {
        totalQty += (parseFloat(input.value) || 0) * (parseFloat(input.dataset.conversionFactor) || 1);
    });
    const priceCell = row.querySelector('.price-cell');
    if (priceCell) {
        const price = parseFloat(priceCell.querySelector('input').value) || 0;
        calculateRowAmounts(row, totalQty, price);
    }
    updateFooterTotals();
}

// ─── Footer & Summary ───────────────────────────────────────────────────────

function updateFooterTotals() {
    const footerRow = document.querySelector('#itemsTable tfoot tr');
    if (!footerRow) return;

    // Remove previously inserted unit total cells
    footerRow.querySelectorAll('.unit-total').forEach(c => c.remove());

    const unitTotals = new Array(maxUnitColumns).fill(0);
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;

    for (let row of rows) {
        row.querySelectorAll('.unit-cell').forEach((cell, idx) => {
            const inp = cell.querySelector('input');
            if (inp) unitTotals[idx] += parseFloat(inp.value) || 0;
        });
    }

    // Insert unit total cells AFTER the Scheme empty cell
    const totalsLabelCell = footerRow.cells[0]; // colspan=2 cell (Totals)
    const schemeEmptyCell = totalsLabelCell.nextElementSibling; // Scheme empty th
    for (let i = 0; i < maxUnitColumns; i++) {
        const th = document.createElement('th');
        th.className = 'unit-total';
        th.textContent = unitTotals[i].toFixed(2);
        const existing = footerRow.querySelectorAll('.unit-total');
        const insertAfter = existing.length > 0 ? existing[existing.length - 1] : schemeEmptyCell;
        insertAfter.insertAdjacentElement('afterend', th);
    }

    let totalPrice = 0, totalGross = 0, totalDisc = 0, totalTO = 0, totalGST = 0, totalFOC = 0, totalNet = 0;
    for (let row of rows) {
        totalPrice += parseFloat(row.querySelector('.price-cell input')?.value)      || 0;
        totalGross += parseFloat(row.querySelector('.gross-cell input')?.value)      || 0;
        totalDisc  += parseFloat(row.querySelector('.disc-amount-cell input')?.value)|| 0;
        totalTO    += parseFloat(row.querySelector('.to-amount-cell input')?.value)  || 0;
        totalGST   += parseFloat(row.querySelector('.gst-amount-cell input')?.value) || 0;
        totalFOC   += parseFloat(row.querySelector('.foc-cell input')?.value)        || 0;
        totalNet   += parseFloat(row.querySelector('.net-cell input')?.value)        || 0;
    }

    document.getElementById('totalSalePrice').textContent          = totalPrice.toFixed(2);
    document.getElementById('totalGrossAmount').textContent        = totalGross.toFixed(2);
    document.getElementById('totalDiscountAmountItems').textContent = totalDisc.toFixed(2);
    document.getElementById('totalTradeOfferAmount').textContent   = totalTO.toFixed(2);
    document.getElementById('totalGstAmount').textContent          = totalGST.toFixed(2);
    document.getElementById('totalFocQty').textContent             = totalFOC.toFixed(2);
    document.getElementById('totalNetAmountItems').textContent     = totalNet.toFixed(2);
}

function updateInvoiceSummaryDynamic() {
    let totalBill = 0;
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    for (let row of rows) {
        totalBill += parseFloat(row.querySelector('.net-cell input')?.value) || 0;
    }

    document.getElementById('totalBill').textContent = totalBill.toFixed(2);

    const discPct  = parseFloat(document.getElementById('totalDiscountPercent')?.value) || 0;
    const discAmt  = totalBill * (discPct / 100);
    const afterDisc = totalBill - discAmt;

    const gstPct   = parseFloat(document.getElementById('totalGstPercent')?.value) || 0;
    const gstAmt   = afterDisc * (gstPct / 100);
    const shipping  = parseFloat(document.getElementById('shippingFees')?.value) || 0;
    const netAmount = afterDisc + gstAmt + shipping;

    const discAmtEl = document.getElementById('totalDiscountAmount');
    if (discAmtEl) discAmtEl.value = discAmt.toFixed(2);

    const gstSummaryEl = document.getElementById('totalGstAmountSummary');
    if (gstSummaryEl) gstSummaryEl.value = gstAmt.toFixed(2);

    document.getElementById('netAmount').textContent = netAmount.toFixed(2);

    updateFooterTotals();
}
