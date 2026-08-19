// Global variables for max unit columns
let maxUnitColumns = 0;
let allUnitHeaders = [];

// Get product UOM details with conversion factors
function getProductUOMDetails(product) {
    console.log('getProductUOMDetails called for product:', product.name);
    const details = {
        type: product.uom_type || 'unit',
        units: []
    };

    if (details.type === 'group' && product.group_units && product.group_units.length > 0) {
        // UOM Group - multiple units
        // Backend already provides correct conversion_factor from product_uom_conversions for per_product units
        details.units = product.group_units.map(unit => {
            console.log('Processing unit:', unit.uom_name, 'CF:', unit.conversion_factor, 'is_base:', unit.is_base_unit);
            return {
                id: unit.id,
                name: unit.uom_name,
                conversionFactor: unit.is_base_unit == 1 ? 1 : (parseFloat(unit.conversion_factor) || 1)
            };
        });
    } else if (product.default_unit_id) {
        // Single unit
        details.units = [{
            id: product.default_unit_id,
            name: product.default_unit_name || 'Unit',
            conversionFactor: product.is_base_unit == 1 ? 1 : (parseFloat(product.unit_conversion_factor) || 1)
        }];
    }

    console.log('Returning UOM details:', details);
    return details;
}

// Update table headers based on max units
function updateTableHeaders() {
    const headerRow = document.querySelector('#itemsTable thead tr');

    // Clear existing dynamic headers
    const existingHeaders = headerRow.querySelectorAll('.unit-header');
    existingHeaders.forEach(header => header.remove());

    // Insert right before the Trade Price header - this is the SAME anchor
    // point used for the row body cells (see updateRowUnitCells, which
    // inserts right before .price-cell). Anchoring both to the same
    // reference point keeps header and body columns aligned no matter how
    // many fixed columns (Size / Batch No / Expiry Date) sit in front of
    // the unit columns.
    const priceLabelSpan = headerRow.querySelector('#purchasePriceLabel');
    const priceHeader = priceLabelSpan ? priceLabelSpan.closest('th') : null;

    for (let i = 0; i < maxUnitColumns; i++) {
        const th = document.createElement('th');
        th.width = '8%';
        th.className = 'unit-header';
        th.textContent = `Unit ${i + 1}`;
        if (priceHeader) {
            headerRow.insertBefore(th, priceHeader);
        } else {
            headerRow.appendChild(th);
        }
    }
}

// Update footer totals
function updateFooterTotals() {
    const footerRow = document.querySelector('#itemsTable tfoot tr');
    if (!footerRow) return; // Exit if footer doesn't exist

    const totalsLabel = footerRow.cells[0]; // "Totals" cell

    // Clear existing unit totals
    const existingTotals = footerRow.querySelectorAll('.unit-total');
    existingTotals.forEach(total => total.remove());

    // Calculate totals for each unit column
    const unitTotals = new Array(maxUnitColumns).fill(0);
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;

    for (let row of rows) {
        const unitCells = row.querySelectorAll('.unit-cell');
        unitCells.forEach((cell, index) => {
            const input = cell.querySelector('input');
            if (input && !input.readOnly) {
                unitTotals[index] += parseFloat(input.value) || 0;
            }
        });
    }

    // Insert right before the Total Purchase Price footer cell - same
    // anchor point as the header and row body cells, so all three rows
    // (thead / tbody / tfoot) stay column-aligned.
    const totalPriceCell = document.getElementById('totalPurchasePrice');

    for (let i = 0; i < maxUnitColumns; i++) {
        const th = document.createElement('th');
        th.className = 'unit-total';
        th.textContent = unitTotals[i].toFixed(3);
        if (totalPriceCell) {
            footerRow.insertBefore(th, totalPriceCell);
        } else {
            footerRow.appendChild(th);
        }
    }

    // Update other totals
    let totalPrice = 0, totalGross = 0, totalDisc = 0, totalTO = 0, totalGST = 0, totalFOC = 0, totalNet = 0;

    for (let row of rows) {
        const priceCell = row.querySelector('.price-cell');
        const grossCell = row.querySelector('.gross-cell');
        const discAmountCell = row.querySelector('.disc-amount-cell');
        const toAmountCell = row.querySelector('.to-amount-cell');
        const taxAmountCell = row.querySelector('.tax-amount-cell');
        const focCell = row.querySelector('.foc-cell');
        const netCell = row.querySelector('.net-cell');

        if (priceCell) totalPrice += parseFloat(priceCell.querySelector('input').value) || 0;
        if (grossCell) totalGross += parseFloat(grossCell.querySelector('input').value) || 0;
        if (discAmountCell) totalDisc += parseFloat(discAmountCell.querySelector('input').value) || 0;
        if (toAmountCell) totalTO += parseFloat(toAmountCell.querySelector('input').value) || 0;
        if (taxAmountCell) totalGST += parseFloat(taxAmountCell.querySelector('input').value) || 0;
        if (focCell) totalFOC += parseFloat(focCell.querySelector('input').value) || 0;
        if (netCell) totalNet += parseFloat(netCell.querySelector('input').value) || 0;
    }

    const totalPurchasePriceEl = document.getElementById('totalPurchasePrice');
    if (totalPurchasePriceEl) totalPurchasePriceEl.textContent = totalPrice.toFixed(3);

    const totalGrossAmountEl = document.getElementById('totalGrossAmount');
    if (totalGrossAmountEl) totalGrossAmountEl.textContent = totalGross.toFixed(3);

    const totalDiscountAmountItemsEl = document.getElementById('totalDiscountAmountItems');
    if (totalDiscountAmountItemsEl) totalDiscountAmountItemsEl.textContent = totalDisc.toFixed(3);

    const totalTradeOfferAmountItemsEl = document.getElementById('totalTradeOfferAmountItems');
    if (totalTradeOfferAmountItemsEl) totalTradeOfferAmountItemsEl.textContent = totalTO.toFixed(3);

    const totalGSTAmountItemsEl = document.getElementById('totalTaxAmountItems');
    if (totalGSTAmountItemsEl) totalGSTAmountItemsEl.textContent = totalGST.toFixed(3);

    const totalFOCQtyEl = document.getElementById('totalFOCQty');
    if (totalFOCQtyEl) totalFOCQtyEl.textContent = totalFOC.toFixed(3);

    const totalNetAmountItemsEl = document.getElementById('totalNetAmountItems');
    if (totalNetAmountItemsEl) totalNetAmountItemsEl.textContent = totalNet.toFixed(3);
}

// Recalculate max columns when products change
function recalculateMaxColumns() {
    console.log('recalculateMaxColumns called');
    maxUnitColumns = 0;
    allUnitHeaders = [];

    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    console.log('Number of rows:', rows.length);

    for (let row of rows) {
        const productData = row.dataset.productUomData;
        if (productData) {
            const uomDetails = JSON.parse(productData);
            console.log('Row UOM details:', uomDetails);
            if (uomDetails.units.length > maxUnitColumns) {
                maxUnitColumns = uomDetails.units.length;
            }

            // Collect unique unit names
            uomDetails.units.forEach((unit, index) => {
                if (!allUnitHeaders[index]) {
                    allUnitHeaders[index] = unit.name;
                }
            });
        }
    }

    console.log('Max unit columns:', maxUnitColumns);
    updateTableHeaders();
    updateAllRowUnitCells();
    updateFooterTotals();
}

// Update unit cells for all rows
function updateAllRowUnitCells() {
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;

    for (let row of rows) {
        // Only update rows that have product selected
        if (row.dataset.productUomData) {
            updateRowUnitCells(row);
        }
    }
}

// Update unit cells for a specific row
function updateRowUnitCells(row) {
    console.log('updateRowUnitCells called');
    // Skip if no product selected
    if (!row.dataset.productUomData) {
        console.log('No product UOM data, skipping');
        return;
    }

    const productData = row.dataset.productUomData;
    const uomDetails = productData ? JSON.parse(productData) : { units: [] };
    console.log('UOM Details for row:', uomDetails);

    // Store existing values BEFORE removing cells
    const existingValues = {};
    const oldUnitInputs = row.querySelectorAll('.unit-input');
    oldUnitInputs.forEach(input => {
        const unitId = input.dataset.unitId;
        if (unitId) {
            existingValues[unitId] = input.value;
        }
    });

    // NOW remove existing unit cells
    const existingUnitCells = row.querySelectorAll('.unit-cell');
    existingUnitCells.forEach(cell => cell.remove());

    // Find insertion point (right before the Price column) - this matches
    // the header/footer anchor above, keeping everything aligned.
    const priceCell = row.querySelector('.price-cell');
    if (!priceCell) return; // Safety check
    const priceCellIndex = Array.from(row.cells).indexOf(priceCell);

    // Add unit cells
    for (let i = 0; i < maxUnitColumns; i++) {
        const cell = row.insertCell(priceCellIndex + i);
        cell.className = 'unit-cell';

        if (i < uomDetails.units.length) {
            const unit = uomDetails.units[i];

            // Create vertical compact display with label on top
            const container = document.createElement('div');
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.alignItems = 'center';
            container.style.gap = '2px';
            container.style.padding = '2px';

            const label = document.createElement('div');
            label.textContent = unit.name;
            label.style.color = 'var(--subtext)';
            label.style.fontSize = '9px';
            label.style.fontWeight = '500';
            label.style.whiteSpace = 'nowrap';
            label.style.textAlign = 'center';

            const input = document.createElement('input');
            input.type = 'number';
            input.className = 'table-input unit-input';
            input.min = '0';
            input.step = '0.001';
            // Restore existing value if available, otherwise set to 0
            input.value = existingValues[unit.id] || '0';
            input.placeholder = '0';
            input.dataset.unitId = unit.id;
            input.dataset.conversionFactor = unit.conversionFactor;

            console.log(`Setting up unit input - ID: ${unit.id}, Name: ${unit.name}, CF: ${unit.conversionFactor}`);

            input.addEventListener('input', function() {
                calculateTotalQuantity(row);
            });

            container.appendChild(label);
            container.appendChild(input);
            cell.appendChild(container);
        } else {
            // Readonly placeholder for missing units
            cell.textContent = '-';
            cell.style.textAlign = 'center';
            cell.style.color = 'var(--subtext)';
            cell.style.fontSize = '11px';
        }
    }
}

// Calculate total quantity from unit inputs
function calculateTotalQuantity(row) {
    const unitInputs = row.querySelectorAll('.unit-input');
    let totalQty = 0;

    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const conversionFactor = parseFloat(input.dataset.conversionFactor) || 1;
        console.log(`Unit: ${input.dataset.unitId}, Qty: ${qty}, CF: ${conversionFactor}, Subtotal: ${qty * conversionFactor}`);
        totalQty += qty * conversionFactor;
    });

    console.log(`Total Quantity: ${totalQty}`);

    // Update price input (now at dynamic position)
    const priceCell = row.querySelector('.price-cell');
    if (priceCell) {
        const priceInput = priceCell.querySelector('input');
        const price = parseFloat(priceInput.value) || 0;
        console.log(`Price: ${price}, Gross: ${totalQty * price}`);
        calculateRowAmounts(row, totalQty, price);
    }

    updateFooterTotals();
}

// Calculate row amounts
function calculateRowAmounts(row, qty, price) {
    const grossCell = row.querySelector('.gross-cell');
    const discPercentCell = row.querySelector('.disc-percent-cell');
    const discAmountCell = row.querySelector('.disc-amount-cell');
    const toPercentCell = row.querySelector('.to-percent-cell');
    const toAmountCell = row.querySelector('.to-amount-cell');
    const taxPercentCell = row.querySelector('.tax-percent-cell');
    const taxAmountCell = row.querySelector('.tax-amount-cell');
    const netCell = row.querySelector('.net-cell');

    if (!grossCell || !discPercentCell || !discAmountCell || !toPercentCell || !toAmountCell || !taxPercentCell || !taxAmountCell || !netCell) {
        return; // Cells not yet created
    }

    const gross = qty * price;
    grossCell.querySelector('input').value = gross.toFixed(3);

    const discPercent = parseFloat(discPercentCell.querySelector('input').value) || 0;
    const discAmount = gross * (discPercent / 100);
    discAmountCell.querySelector('input').value = discAmount.toFixed(3);

    const afterDiscount = gross - discAmount;

    const toPercent = parseFloat(toPercentCell.querySelector('input').value) || 0;
    const toAmount = afterDiscount * (toPercent / 100);
    toAmountCell.querySelector('input').value = toAmount.toFixed(3);

    const afterTO = afterDiscount - toAmount;

    const taxPercent = parseFloat(taxPercentCell.querySelector('input').value) || 0;
    const taxAmount = afterTO * (taxPercent / 100);
    taxAmountCell.querySelector('input').value = taxAmount.toFixed(3);

    const net = afterTO + taxAmount;
    netCell.querySelector('input').value = net.toFixed(3);

    updateInvoiceSummaryDynamic();
}

// Update invoice summary
function updateInvoiceSummaryDynamic() {
    let totalBill = 0;
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;

    for (let row of rows) {
        const netCell = row.querySelector('.net-cell');
        if (netCell) {
            totalBill += parseFloat(netCell.querySelector('input').value) || 0;
        }
    }

    const totalBillElement = document.getElementById('totalBill');
    if (totalBillElement) totalBillElement.textContent = totalBill.toFixed(3);

    const discountAmount = parseFloat(document.getElementById('totalDiscountAmount')?.value) || 0;
    const afterDiscount = totalBill - discountAmount;

    const gstPercent = parseFloat(document.getElementById('totalGSTPercent')?.value) || 0;
    const gstAmount = afterDiscount * (gstPercent / 100);
    const shippingFees = parseFloat(document.getElementById('shippingFees')?.value) || 0;
    const shippingFeesType = document.querySelector('input[name="shippingFeesType"]:checked')?.value || 'add';
    const shippingAdjusted = shippingFeesType === 'subtract' ? -shippingFees : shippingFees;
    const baseNet = afterDiscount + gstAmount + shippingAdjusted;

    const gstAmountEl = document.getElementById('totalGSTAmount');
    if (gstAmountEl) gstAmountEl.value = gstAmount.toFixed(3);

    const netAmountElement = document.getElementById('netAmount');
    const summaryGrid = netAmountElement?.closest('.summary-grid');
    if (summaryGrid) summaryGrid.dataset.baseNet = baseNet.toFixed(3);
    if (netAmountElement) netAmountElement.textContent = baseNet.toFixed(3);

    updateFooterTotals();

    if (window.calculateInvoiceLevelTaxes) window.calculateInvoiceLevelTaxes();
}

// Helper function to attach price change listener (called from purchase-add.js)
function attachPriceChangeListener(row) {
    const priceCell = row.querySelector('.price-cell');
    if (priceCell) {
        const priceInput = priceCell.querySelector('input');
        if (priceInput) {
            priceInput.addEventListener('input', function() {
                calculateTotalQuantity(row);
            });
        }
    }
}