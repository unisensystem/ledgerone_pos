// Dynamic UOM System for POS Invoice - Handles both single units and UOM groups

// Get product UOM configuration
function getProductUOMConfig(product) {
    const config = {
        uomType: product.uom_type,
        units: []
    };

    if (product.uom_type === 'unit') {
        // Single unit mode
        const unitId = product.default_unit_id;
        const unit = uomData.find(u => u.id == unitId);
        
        if (unit) {
            let conversionFactor = 1;
            if (unit.unit_scope === 'universal') {
                conversionFactor = parseFloat(unit.conversion_factor) || 1;
            } else if (unit.unit_scope === 'per_product') {
                conversionFactor = parseFloat(product.product_conversion_factor) || 1;
            }
            
            config.units.push({
                unitId: unit.id,
                unitName: unit.uom_name,
                conversionFactor: conversionFactor,
                isBaseUnit: unit.is_base_unit == 1
            });
        }
    } else if (product.uom_type === 'group') {
        // Group mode
        const groupId = product.uom_group_id;
        const groupUnits = uomGroupUnitsData.filter(gu => gu.uom_group_id == groupId);
        
        groupUnits.forEach(gu => {
            const unit = uomData.find(u => u.id == gu.uom_id);
            if (unit) {
                let conversionFactor = 1;
                if (unit.unit_scope === 'universal') {
                    conversionFactor = parseFloat(unit.conversion_factor) || 1;
                } else if (unit.unit_scope === 'per_product') {
                    conversionFactor = parseFloat(product.product_conversion_factor) || 1;
                }
                
                config.units.push({
                    unitId: unit.id,
                    unitName: unit.uom_name,
                    conversionFactor: conversionFactor,
                    isBaseUnit: unit.is_base_unit == 1
                });
            }
        });
    }

    return config;
}

// Update table headers based on max units
function updateTableHeadersDynamic() {
    const itemsTable = document.getElementById('itemsTable');
    const thead = itemsTable.querySelector('thead tr');
    const tfoot = itemsTable.querySelector('tfoot tr');
    
    let maxUnits = 0;
    const tbody = itemsTable.querySelector('tbody');
    
    // Find max units across all rows
    for (let row of tbody.rows) {
        const uomConfig = row.dataset.productUomConfig;
        if (uomConfig) {
            const config = JSON.parse(uomConfig);
            maxUnits = Math.max(maxUnits, config.units.length);
        }
    }

    // Remove old unit columns (after Product column - index 1)
    while (thead.cells.length > 2 && thead.cells[2].classList.contains('unit-header')) {
        thead.deleteCell(2);
        tfoot.deleteCell(2);
    }

    // Add new unit columns
    for (let i = 0; i < maxUnits; i++) {
        const th = thead.insertCell(2 + i);
        th.className = 'unit-header';
        th.width = '8%';
        th.innerHTML = `<span>Unit ${i + 1}</span>`;
        
        const tf = tfoot.insertCell(2 + i);
        tf.className = 'unit-footer';
        tf.id = `totalUnit${i + 1}`;
        tf.textContent = '0.00';
    }
}

// Update row unit cells
function updateRowUnitCells(row, uomConfig, maxUnits, existingValues = []) {
    // Find price cell index
    const priceCell = row.querySelector('.price-cell');
    if (!priceCell) return;
    
    const priceCellIndex = priceCell.cellIndex;
    
    // Remove existing unit cells
    while (row.cells.length > 2 && row.cells[2].cellIndex < priceCellIndex) {
        if (row.cells[2].querySelector('.unit-input')) {
            row.deleteCell(2);
        } else {
            break;
        }
    }
    
    // Add unit cells
    for (let i = 0; i < maxUnits; i++) {
        const cell = row.insertCell(2 + i);
        cell.className = 'unit-cell';
        
        if (i < uomConfig.units.length) {
            const unit = uomConfig.units[i];
            const input = document.createElement('input');
            input.type = 'number';
            input.className = 'table-input unit-input';
            input.min = '0';
            input.step = '0.01';
            input.value = '0';
            input.dataset.unitId = unit.unitId;
            input.dataset.conversionFactor = unit.conversionFactor;
            input.setAttribute('placeholder', unit.unitName);
            
            const existingValue = existingValues.find(v => v.unitId == unit.unitId);
            if (existingValue) {
                input.value = existingValue.value;
            }
            
            input.addEventListener('input', function() {
                calculateTotalQuantityDynamic(row);
            });
            
            cell.appendChild(input);
        } else {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'table-input';
            input.value = '-';
            input.readOnly = true;
            input.tabIndex = -1;
            cell.appendChild(input);
        }
    }
}

// Calculate total quantity from all units
function calculateTotalQuantityDynamic(row) {
    const unitInputs = row.querySelectorAll('.unit-input');
    let totalQty = 0;
    
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const conversionFactor = parseFloat(input.dataset.conversionFactor) || 1;
        totalQty += qty * conversionFactor;
    });
    
    const priceInput = row.querySelector('.price-cell input');
    const price = priceInput ? (Number(priceInput.value) || 0) : 0;
    
    // Check active scheme and recalculate accordingly
    if (typeof SCHEME_TYPES !== 'undefined') {
        const schemeSelect = row.querySelector('.scheme-select');
        const activeScheme = schemeSelect ? schemeSelect.value : SCHEME_TYPES.SALE_ON_TP;
        
        if (activeScheme === SCHEME_TYPES.LESS && typeof calculateLessSchemeAmounts === 'function') {
            // For Less scheme (Original): FOC and T.O both work
            calculateLessSchemeAmounts(row, {});
            updateFooterTotalsDynamic();
            updateInvoiceSummaryDynamic();
            return;
        } else if (activeScheme === SCHEME_TYPES.LESS_SPECIAL && typeof calculateLessSpecialSchemeAmounts === 'function') {
            // For Less Special scheme: T.O formula-based
            calculateLessSpecialSchemeAmounts(row, {});
            updateFooterTotalsDynamic();
            updateInvoiceSummaryDynamic();
            return;
        } else if (activeScheme === SCHEME_TYPES.GIVEN && typeof calculateGivenSchemeAmounts === 'function') {
            // For Given scheme: FOC Qty works
            calculateGivenSchemeAmounts(row, {});
            updateFooterTotalsDynamic();
            updateInvoiceSummaryDynamic();
            return;
        }
    }
    
    // For Sale On TP and others: use standard calculation
    calculateRowAmountsDynamic(row, totalQty);
    updateFooterTotalsDynamic();
    updateInvoiceSummaryDynamic();
}
}

// Calculate row amounts
function calculateRowAmountsDynamic(row, totalQty) {
    const priceInput = row.querySelector('.price-cell input');
    const grossInput = row.querySelector('.gross-cell input');
    const discPercentInput = row.querySelector('.disc-percent-cell input');
    const discAmountInput = row.querySelector('.disc-amount-cell input');
    const toAmountInput = row.querySelector('.to-amount-cell input');
    const gstPercentInput = row.querySelector('.gst-percent-cell input');
    const gstAmountInput = row.querySelector('.gst-amount-cell input');
    const netInput = row.querySelector('.net-cell input');
    
    const price = parseFloat(priceInput?.value) || 0;
    const discPercent = parseFloat(discPercentInput?.value) || 0;
    const gstPercent = parseFloat(gstPercentInput?.value) || 0;
    
    const grossAmount = totalQty * price;
    const discountAmount = grossAmount * (discPercent / 100);
    const afterDiscount = grossAmount - discountAmount;
    const tradeOfferAmount = parseFloat(toAmountInput?.value) || 0;
    const afterTO = afterDiscount - tradeOfferAmount;

    // Inclusive: tax is embedded in the price — extract it (net stays at afterTO)
    // Exclusive: tax is added on top of afterTO
    const isTaxInclusive = row.dataset.isTaxInclusive === '1';
    let gstAmount, netAmount;
    if (isTaxInclusive) {
        gstAmount = gstPercent > 0 ? afterTO * gstPercent / (100 + gstPercent) : 0;
        netAmount = afterTO;
    } else {
        gstAmount = afterTO * (gstPercent / 100);
        netAmount = afterTO + gstAmount;
    }
    
    if (grossInput) grossInput.value = grossAmount.toFixed(2);
    if (discAmountInput) discAmountInput.value = discountAmount.toFixed(2);
    if (toAmountInput) toAmountInput.value = tradeOfferAmount.toFixed(2);
    if (gstAmountInput) gstAmountInput.value = gstAmount.toFixed(2);
    if (netInput) netInput.value = netAmount.toFixed(2);
}

// Update footer totals
function updateFooterTotalsDynamic() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.querySelector('tbody');
    
    let maxUnits = 0;
    for (let row of tbody.rows) {
        const unitInputs = row.querySelectorAll('.unit-input');
        maxUnits = Math.max(maxUnits, unitInputs.length);
    }
    
    // Calculate totals for each unit column
    for (let i = 0; i < maxUnits; i++) {
        let total = 0;
        for (let row of tbody.rows) {
            const unitInputs = row.querySelectorAll('.unit-input');
            if (unitInputs[i]) {
                total += parseFloat(unitInputs[i].value) || 0;
            }
        }
        
        const footerCell = document.getElementById(`totalUnit${i + 1}`);
        if (footerCell) {
            footerCell.textContent = total.toFixed(2);
        }
    }
    
    // Update other totals
    let totalSalePrice = 0, totalGrossAmount = 0, totalDiscountAmount = 0, totalTradeOfferAmount = 0, totalGstAmount = 0, totalFocQty = 0, totalNetAmount = 0;
    
    for (let row of tbody.rows) {
        const priceInput = row.querySelector('.price-cell input');
        const grossInput = row.querySelector('.gross-cell input');
        const discAmountInput = row.querySelector('.disc-amount-cell input');
        const toAmountInput = row.querySelector('.to-amount-cell input');
        const gstAmountInput = row.querySelector('.gst-amount-cell input');
        const focInput = row.querySelector('.foc-cell input');
        const netInput = row.querySelector('.net-cell input');
        
        if (priceInput) totalSalePrice += parseFloat(priceInput.value) || 0;
        if (grossInput) totalGrossAmount += parseFloat(grossInput.value) || 0;
        if (discAmountInput) totalDiscountAmount += parseFloat(discAmountInput.value) || 0;
        if (toAmountInput) totalTradeOfferAmount += parseFloat(toAmountInput.value) || 0;
        if (gstAmountInput) totalGstAmount += parseFloat(gstAmountInput.value) || 0;
        if (focInput) totalFocQty += parseFloat(focInput.value) || 0;
        if (netInput) totalNetAmount += parseFloat(netInput.value) || 0;
    }
    
    const totalSalePriceEl = document.getElementById('totalSalePrice');
    const totalGrossAmountEl = document.getElementById('totalGrossAmount');
    const totalDiscountAmountEl = document.getElementById('totalDiscountAmountItems');
    const totalTradeOfferAmountEl = document.getElementById('totalTradeOfferAmount');
    const totalGstAmountEl = document.getElementById('totalGstAmount');
    const totalFocQtyEl = document.getElementById('totalFocQty');
    const totalNetAmountEl = document.getElementById('totalNetAmountItems');
    
    if (totalSalePriceEl) totalSalePriceEl.textContent = totalSalePrice.toFixed(2);
    if (totalGrossAmountEl) totalGrossAmountEl.textContent = totalGrossAmount.toFixed(2);
    if (totalDiscountAmountEl) totalDiscountAmountEl.textContent = totalDiscountAmount.toFixed(2);
    if (totalTradeOfferAmountEl) totalTradeOfferAmountEl.textContent = totalTradeOfferAmount.toFixed(2);
    if (totalGstAmountEl) totalGstAmountEl.textContent = totalGstAmount.toFixed(2);
    if (totalFocQtyEl) totalFocQtyEl.textContent = totalFocQty.toFixed(2);
    if (totalNetAmountEl) totalNetAmountEl.textContent = totalNetAmount.toFixed(2);
}

// Recalculate max columns
function recalculateMaxColumnsDynamic() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.querySelector('tbody');
    
    let maxUnits = 0;
    const rowsData = [];
    
    for (let row of tbody.rows) {
        const uomConfig = row.dataset.productUomConfig;
        if (uomConfig) {
            const config = JSON.parse(uomConfig);
            maxUnits = Math.max(maxUnits, config.units.length);
            
            const unitValues = [];
            const unitInputs = row.querySelectorAll('.unit-input');
            unitInputs.forEach(input => {
                unitValues.push({
                    unitId: input.dataset.unitId,
                    value: input.value
                });
            });
            
            rowsData.push({
                row: row,
                config: config,
                unitValues: unitValues
            });
        }
    }
    
    updateTableHeadersDynamic();
    
    rowsData.forEach(data => {
        updateRowUnitCells(data.row, data.config, maxUnits, data.unitValues);
    });
    
    updateFooterTotalsDynamic();
}

// Update invoice summary
function updateInvoiceSummaryDynamic() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.querySelector('tbody');
    
    let totalBill = 0;
    
    for (let row of tbody.rows) {
        const netInput = row.querySelector('.net-cell input');
        if (netInput) {
            totalBill += parseFloat(netInput.value) || 0;
        }
    }
    
    const totalDiscountPercent = parseFloat(document.getElementById('totalDiscountPercent')?.value) || 0;
    const totalDiscountAmount = parseFloat(document.getElementById('totalDiscountAmount')?.value) || 0;
    const shippingFees = parseFloat(document.getElementById('shippingFees')?.value) || 0;
    
    let discountFromPercent = totalBill * (totalDiscountPercent / 100);
    let finalDiscount = Math.max(discountFromPercent, totalDiscountAmount);
    
    const netAmount = totalBill - finalDiscount + shippingFees;
    
    document.getElementById('totalBill').textContent = totalBill.toFixed(2);
    document.getElementById('netAmount').textContent = netAmount.toFixed(2);
    
    // Update withholding tax
    const withholdingTaxPercent = parseFloat(document.getElementById('withholdingTaxPercent')?.value) || 0;
    const withholdingTaxAmount = netAmount * (withholdingTaxPercent / 100);
    const netReceivable = netAmount - withholdingTaxAmount;
    
    if (document.getElementById('withholdingTaxAmount')) {
        document.getElementById('withholdingTaxAmount').textContent = withholdingTaxAmount.toFixed(2);
    }
    if (document.getElementById('netReceivable')) {
        document.getElementById('netReceivable').textContent = netReceivable.toFixed(2);
    }
    
    // Update remaining balance
    const amountPaid = parseFloat(document.getElementById('amountPaid')?.value) || 0;
    const remainingBalance = netReceivable - amountPaid;
    if (document.getElementById('remainingBalance')) {
        document.getElementById('remainingBalance').value = remainingBalance.toFixed(2);
    }
}

// Collect items data for saving
function collectItemsDataDynamic() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.querySelector('tbody');
    const items = [];
    
    for (let row of tbody.rows) {
        const productId = row.cells[1].querySelector('.product-id')?.value;
        if (!productId) continue;
        
        const uomConfig = row.dataset.productUomConfig;
        if (!uomConfig) continue;
        
        const config = JSON.parse(uomConfig);
        const unitInputs = row.querySelectorAll('.unit-input');
        
        // Create entry for each unit with quantity > 0
        unitInputs.forEach(input => {
            const qty = parseFloat(input.value) || 0;
            if (qty > 0) {
                const unitId = input.dataset.unitId;
                const priceInput = row.querySelector('.price-cell input');
                const discPercentInput = row.querySelector('.disc-percent-cell input');
                const discAmountInput = row.querySelector('.disc-amount-cell input');
                const toAmountInput = row.querySelector('.to-amount-cell input');
                const gstPercentInput = row.querySelector('.gst-percent-cell input');
                const gstAmountInput = row.querySelector('.gst-amount-cell input');
                const focInput = row.querySelector('.foc-cell input');
                const grossInput = row.querySelector('.gross-cell input');
                const netInput = row.querySelector('.net-cell input');
                
                items.push({
                    productId: productId,
                    uomId: unitId,
                    quantity: qty,
                    salePrice: parseFloat(priceInput?.value) || 0,
                    grossAmount: parseFloat(grossInput?.value) || 0,
                    discountPercent: parseFloat(discPercentInput?.value) || 0,
                    discountAmount: parseFloat(discAmountInput?.value) || 0,
                    tradeOfferAmount: parseFloat(toAmountInput?.value) || 0,
                    gstPercent: parseFloat(gstPercentInput?.value) || 0,
                    gstAmount: parseFloat(gstAmountInput?.value) || 0,
                    focQty: parseFloat(focInput?.value) || 0,
                    netAmount: parseFloat(netInput?.value) || 0
                });
            }
        });
    }
    
    return items;
}
