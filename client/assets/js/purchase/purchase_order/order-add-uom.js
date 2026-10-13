// Global variables for max unit columns
let maxUnitColumns = 0;
let allUnitHeaders = [];

// Get product UOM details with conversion factors
function getProductUOMDetails(product) {
    const details = {
        type: product.uom_type || 'unit',
        units: []
    };

    if (details.type === 'group' && product.group_units && product.group_units.length > 0) {
        // UOM Group - multiple units
        details.units = product.group_units.map(unit => ({
            id: unit.id,
            name: unit.uom_name,
            conversionFactor: getUnitConversionFactor(unit, product)
        }));
    } else if (product.default_unit_id) {
        // Single unit
        details.units = [{
            id: product.default_unit_id,
            name: product.default_unit_name || 'Unit',
            conversionFactor: getUnitConversionFactor({
                unit_scope: product.unit_scope,
                is_base_unit: product.is_base_unit,
                conversion_factor: product.unit_conversion_factor
            }, product)
        }];
    }

    return details;
}

// Get conversion factor for a unit
function getUnitConversionFactor(unit, product) {
    if (unit.is_base_unit == 1) {
        return 1;
    }

    if (unit.unit_scope === 'per_product') {
        return parseFloat(unit.product_conversion_factor) || 1;
    } else if (unit.unit_scope === 'universal') {
        return parseFloat(unit.conversion_factor) || 1;
    }

    return 1;
}

// Update table headers based on max units
function updateTableHeaders() {
    const headerRow = document.querySelector('#itemsTable thead tr');
    const productHeader = headerRow.cells[1]; // Product Code / Name column
    
    // Clear existing dynamic headers
    const existingHeaders = headerRow.querySelectorAll('.unit-header');
    existingHeaders.forEach(header => header.remove());

    // Add unit headers after product column (in reverse order for correct display)
    for (let i = maxUnitColumns - 1; i >= 0; i--) {
        const th = document.createElement('th');
        th.width = '8%';
        th.className = 'unit-header';
        th.textContent = `Unit ${i + 1}`;
        productHeader.insertAdjacentElement('afterend', th);
    }
}

// Update footer totals
function updateFooterTotals() {
    const footerRow = document.querySelector('#itemsTable tfoot tr');
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

    // Add total cells after "Totals" label (in reverse order for correct display)
    for (let i = maxUnitColumns - 1; i >= 0; i--) {
        const th = document.createElement('th');
        th.className = 'unit-total';
        th.textContent = unitTotals[i].toFixed(2);
        totalsLabel.insertAdjacentElement('afterend', th);
    }
    
    // Update other totals
    let totalPrice = 0, totalGross = 0, totalDisc = 0, totalTO = 0, totalGST = 0, totalFOC = 0, totalNet = 0;
    
    for (let row of rows) {
        const priceCell = row.querySelector('.price-cell');
        const grossCell = row.querySelector('.gross-cell');
        const discAmountCell = row.querySelector('.disc-amount-cell');
        const toAmountCell = row.querySelector('.to-amount-cell');
        const gstAmountCell = row.querySelector('.gst-amount-cell');
        const focCell = row.querySelector('.foc-cell');
        const netCell = row.querySelector('.net-cell');
        
        if (priceCell) totalPrice += parseFloat(priceCell.querySelector('input').value) || 0;
        if (grossCell) totalGross += parseFloat(grossCell.querySelector('input').value) || 0;
        if (discAmountCell) totalDisc += parseFloat(discAmountCell.querySelector('input').value) || 0;
        if (toAmountCell) totalTO += parseFloat(toAmountCell.querySelector('input').value) || 0;
        if (gstAmountCell) totalGST += parseFloat(gstAmountCell.querySelector('input').value) || 0;
        if (focCell) totalFOC += parseFloat(focCell.querySelector('input').value) || 0;
        if (netCell) totalNet += parseFloat(netCell.querySelector('input').value) || 0;
    }
    
    document.getElementById('totalPurchasePrice').textContent = totalPrice.toFixed(2);
    document.getElementById('totalGrossAmount').textContent = totalGross.toFixed(2);
    document.getElementById('totalDiscountAmountItems').textContent = totalDisc.toFixed(2);
    document.getElementById('totalTradeOfferAmountItems').textContent = totalTO.toFixed(2);
    document.getElementById('totalGSTAmountItems').textContent = totalGST.toFixed(2);
    document.getElementById('totalFOCQty').textContent = totalFOC.toFixed(2);
    document.getElementById('totalNetAmountItems').textContent = totalNet.toFixed(2);
}

// Recalculate max columns when products change
function recalculateMaxColumns() {
    maxUnitColumns = 0;
    allUnitHeaders = [];
    
    const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
    
    for (let row of rows) {
        const productData = row.dataset.productUomData;
        if (productData) {
            const uomDetails = JSON.parse(productData);
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
    // Skip if no product selected
    if (!row.dataset.productUomData) return;
    
    const productData = row.dataset.productUomData;
    const uomDetails = productData ? JSON.parse(productData) : { units: [] };
    
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
    
    // Find insertion point (after product column, before price column)
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
            input.step = '0.01';
            // Restore existing value if available, otherwise set to 0
            input.value = existingValues[unit.id] || '0';
            input.placeholder = '0';
            input.dataset.unitId = unit.id;
            input.dataset.conversionFactor = unit.conversionFactor;
            
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
        totalQty += qty * conversionFactor;
    });
    
    // Update price input (now at dynamic position)
    const priceCell = row.querySelector('.price-cell');
    if (priceCell) {
        const priceInput = priceCell.querySelector('input');
        const price = parseFloat(priceInput.value) || 0;
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
    const gstPercentCell = row.querySelector('.gst-percent-cell');
    const gstAmountCell = row.querySelector('.gst-amount-cell');
    const netCell = row.querySelector('.net-cell');
    
    const gross = qty * price;
    grossCell.querySelector('input').value = gross.toFixed(2);
    
    const discPercent = parseFloat(discPercentCell.querySelector('input').value) || 0;
    const discAmount = gross * (discPercent / 100);
    discAmountCell.querySelector('input').value = discAmount.toFixed(2);
    
    const afterDiscount = gross - discAmount;
    
    const toPercent = parseFloat(toPercentCell.querySelector('input').value) || 0;
    const toAmount = afterDiscount * (toPercent / 100);
    toAmountCell.querySelector('input').value = toAmount.toFixed(2);
    
    const afterTO = afterDiscount - toAmount;
    
    const gstPercent = parseFloat(gstPercentCell.querySelector('input').value) || 0;
    const gstAmount = afterTO * (gstPercent / 100);
    gstAmountCell.querySelector('input').value = gstAmount.toFixed(2);
    
    const net = afterTO + gstAmount;
    netCell.querySelector('input').value = net.toFixed(2);
    
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
    
    document.getElementById('totalBill').textContent = totalBill.toFixed(2);
    
    const discountPercent = parseFloat(document.getElementById('totalDiscountPercent').value) || 0;
    const discountAmount = totalBill * (discountPercent / 100);
    const afterDiscount = totalBill - discountAmount;
    
    const gstPercent = parseFloat(document.getElementById('totalGSTPercent').value) || 0;
    const gstAmount = afterDiscount * (gstPercent / 100);
    const shippingFees = parseFloat(document.getElementById('shippingFees').value) || 0;
    const netAmount = afterDiscount + gstAmount + shippingFees;
    
    document.getElementById('totalDiscountAmount').value = discountAmount.toFixed(2);
    document.getElementById('totalGSTAmount').value = gstAmount.toFixed(2);
    document.getElementById('netAmount').textContent = netAmount.toFixed(2);
    
    updateFooterTotals();
}
