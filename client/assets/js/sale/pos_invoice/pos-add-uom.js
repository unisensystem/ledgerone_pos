// Dynamic UOM System for POS Invoice
let maxUnitColumns = 0;
let allUnitHeaders = [];

// Get product UOM details
async function getProductUOMDetails(product) {
    const uomDetails = [];
    
    if (product.uom_type === 'single' || product.uom_type === 'unit') {
        // Single UOM - just the default unit
        const defaultUom = uomData.find(u => u.id == product.default_unit_id);
        if (defaultUom) {
            // Check if it's a per-product unit
            if (defaultUom.unit_scope === 'per_product') {
                // Fetch from product_uom_conversions table
                const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-uom-conversions.php?product_id=${product.id}`);
                const data = await response.json();
                if (data.success && data.conversions.length > 0) {
                    const conversion = data.conversions.find(c => c.uom_id == defaultUom.id);
                    if (conversion) {
                        uomDetails.push({
                            id: defaultUom.id,
                            name: defaultUom.uom_name,
                            conversionFactor: parseFloat(conversion.conversion_factor) || 1,
                            isBase: true
                        });
                    }
                } else {
                    // Fallback to default
                    uomDetails.push({
                        id: defaultUom.id,
                        name: defaultUom.uom_name,
                        conversionFactor: 1,
                        isBase: true
                    });
                }
            } else {
                uomDetails.push({
                    id: defaultUom.id,
                    name: defaultUom.uom_name,
                    conversionFactor: parseFloat(defaultUom.conversion_factor) || 1,
                    isBase: defaultUom.is_base_unit == 1
                });
            }
        }
    } else if (product.uom_type === 'group' && product.uom_group_id) {
        // Group UOM - get all units from group
        const groupUnits = window.uomGroupUnits?.filter(gu => gu.uom_group_id == product.uom_group_id) || [];
        
        // Fetch product-specific conversions
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-uom-conversions.php?product_id=${product.id}`);
        const data = await response.json();
        const productConversions = data.success ? data.conversions : [];
        
        groupUnits.forEach(gu => {
            const uom = uomData.find(u => u.id == gu.uom_id);
            if (uom) {
                // Check if per-product conversion exists
                const productConversion = productConversions.find(pc => pc.uom_id == uom.id);
                if (uom.unit_scope === 'per_product' && productConversion) {
                    uomDetails.push({
                        id: uom.id,
                        name: uom.uom_name,
                        conversionFactor: parseFloat(productConversion.conversion_factor) || 1,
                        isBase: uom.is_base_unit == 1
                    });
                } else {
                    uomDetails.push({
                        id: uom.id,
                        name: uom.uom_name,
                        conversionFactor: parseFloat(uom.conversion_factor) || 1,
                        isBase: uom.is_base_unit == 1
                    });
                }
            }
        });
    }
    
    return uomDetails;
}

// Recalculate max columns across all rows
function recalculateMaxColumns() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    const rows = tbody.rows;
    
    // Find the maximum number of units any product has
    let maxUnits = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const uomDataStr = row.dataset.productUomData;
        if (uomDataStr) {
            const uomDetails = JSON.parse(uomDataStr);
            maxUnits = Math.max(maxUnits, uomDetails.length);
        }
    }
    
    maxUnitColumns = maxUnits;
    
    updateTableHeaders();
    
    for (let i = 0; i < rows.length; i++) {
        updateRowUnitCells(rows[i]);
    }
    
    updateFooterTotals();
}

// Update table headers
function updateTableHeaders() {
    const itemsTable = document.getElementById('itemsTable');
    const thead = itemsTable.getElementsByTagName('thead')[0];
    const headerRow = thead.rows[0];
    
    let safetyCounter = 0;
    while (headerRow.cells.length > 5 && safetyCounter < 20) {
        const cell = headerRow.cells[5];
        const cellText = cell.textContent.trim();
        // Stop if we reach Price or any of the fixed columns
        if (cellText.includes('Sale Price') || cellText.includes('Gross Amount') || 
            cellText.includes('Disc') || cellText.includes('T.O') || 
            cellText.includes('GST') || cellText.includes('FOC') || 
            cellText.includes('Net Amount') || cellText.includes('Actions')) {
            break;
        }
        headerRow.deleteCell(5);
        safetyCounter++;
    }
    
    // Insert generic unit headers (Unit 1, Unit 2, Unit 3, etc.) starting at index 5
    // (index 0=S#, 1=Product, 2=Scheme, 3=Batch No, 4=Expiry Date)
    for (let i = 0; i < maxUnitColumns; i++) {
        const th = headerRow.insertCell(5 + i);
        th.width = '8%';
        th.style.fontWeight = '600';
        th.style.fontSize = '12px';
        th.style.color = 'var(--heading, #212529)';
        th.innerHTML = `<span class="unit-header">Unit ${i + 1}</span>`;
    }
}

// Update row unit cells
function updateRowUnitCells(row) {
    const uomDataStr = row.dataset.productUomData;
    const productUomDetails = uomDataStr ? JSON.parse(uomDataStr) : [];
    
    // PRESERVE existing values before removing cells
    const existingValues = {};
    const existingUnitInputs = row.querySelectorAll('.unit-input');
    existingUnitInputs.forEach(input => {
        const unitId = input.dataset.unitId;
        if (unitId) {
            existingValues[unitId] = input.value;
        }
    });
    
    // Remove old unit cells (between Expiry Date and Price)
    // Keep removing cells at index 5 until we find a cell with price-cell class
    // (index 0=S#, 1=Product, 2=Scheme, 3=Batch No, 4=Expiry Date)
    while (row.cells.length > 5 && !row.cells[5].classList.contains('price-cell')) {
        row.deleteCell(5);
    }
    
    // Insert unit cells starting at index 5 (after Batch No / Expiry Date cells)
    for (let i = 0; i < maxUnitColumns; i++) {
        const cell = row.insertCell(5 + i);
        
        // Check if this product has a unit at this position
        if (i < productUomDetails.length) {
            const uomDetail = productUomDetails[i];
            
            // Create a container for label + input
            const container = document.createElement('div');
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '2px';
            container.style.padding = '4px';
            
            // Add unit label
            const label = document.createElement('div');
            label.className = 'unit-label';
            label.textContent = uomDetail.name;
            label.style.fontSize = '10px';
            label.style.fontWeight = '500';
            label.style.color = 'var(--text-secondary, #6c757d)';
            label.style.textAlign = 'left';
            label.style.marginBottom = '2px';
            label.style.opacity = '0.8';
            container.appendChild(label);
            
            // Add input
            const input = document.createElement('input');
            input.type = 'number';
            input.className = 'table-input unit-input';
            input.min = '0';
            input.step = '0.001';
            // RESTORE the existing value if it exists, otherwise default to '0'
            input.value = existingValues[uomDetail.id] || '0';
            input.dataset.unitId = uomDetail.id;
            input.dataset.conversionFactor = uomDetail.conversionFactor;
            input.dataset.isBase = uomDetail.isBase;
            input.title = `${uomDetail.name} (×${uomDetail.conversionFactor})`;
            
            input.addEventListener('input', function() {
                const unitInputs = row.querySelectorAll('.unit-input');
                let totalQty = Number(0);
                unitInputs.forEach(inp => {
                    const qty = Number(inp.value) || 0;
                    const cf = Number(inp.dataset.conversionFactor) || 1;
                    totalQty = Number(totalQty) + Number(qty * cf);
                });

                const priceInput = row.querySelector('.price-cell input');
                const price = priceInput ? (Number(priceInput.value) || 0) : 0;

                if (typeof validateStockForRow === 'function') {
                    validateStockForRow(row);
                }

                if (typeof SCHEME_TYPES !== 'undefined') {
                    const schemeSelect = row.querySelector('.scheme-select');
                    const activeScheme = schemeSelect ? schemeSelect.value : SCHEME_TYPES.SALE_ON_TP;

                    if ((activeScheme === SCHEME_TYPES.LESS || activeScheme === SCHEME_TYPES.LESS_SPECIAL) && typeof calculateLessSchemeAmounts === 'function') {
                        if (activeScheme === SCHEME_TYPES.LESS) {
                            calculateLessSchemeAmounts(row, {});
                        } else {
                            calculateLessSpecialSchemeAmounts(row, {});
                        }
                        return;
                    } else if (activeScheme === SCHEME_TYPES.GIVEN && typeof calculateGivenSchemeAmounts === 'function') {
                        calculateGivenSchemeAmounts(row, {});
                        return;
                    }
                }

                calculateRowAmounts(row, Number(totalQty), Number(price));
            });

            input.addEventListener('keydown', function(e) {
                const nextFieldKey = (typeof shortcuts !== 'undefined' && shortcuts.nextField) ? shortcuts.nextField : 'Tab';
                const prevFieldKey = (typeof shortcuts !== 'undefined' && shortcuts.prevField) ? shortcuts.prevField : 'Shift+Tab';
                let key = e.key;
                if (key.length === 1 && key.match(/[a-z]/i)) key = key.toUpperCase();
                let combo = '';
                if (e.ctrlKey) combo += 'Ctrl+';
                if (e.altKey) combo += 'Alt+';
                if (e.shiftKey && key.length > 1) combo += 'Shift+';
                combo += key;

                if (combo === nextFieldKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    const allUnitInputs = Array.from(row.querySelectorAll('.unit-input'));
                    const currentIdx = allUnitInputs.indexOf(this);
                    if (currentIdx < allUnitInputs.length - 1) {
                        allUnitInputs[currentIdx + 1].focus();
                        if (allUnitInputs[currentIdx + 1].select) allUnitInputs[currentIdx + 1].select();
                    } else {
                        const priceInput = row.querySelector('.price-cell input');
                        if (priceInput) {
                            priceInput.focus();
                            if (priceInput.select) setTimeout(() => priceInput.select(), 0);
                        }
                    }
                } else if (combo === prevFieldKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    const allUnitInputs = Array.from(row.querySelectorAll('.unit-input'));
                    const currentIdx = allUnitInputs.indexOf(this);
                    if (currentIdx > 0) {
                        allUnitInputs[currentIdx - 1].focus();
                        if (allUnitInputs[currentIdx - 1].select) allUnitInputs[currentIdx - 1].select();
                    } else {
                        const searchInput = row.querySelector('.search-input');
                        if (searchInput) searchInput.focus();
                    }
                }
            });

            container.appendChild(input);
            cell.appendChild(container);
        } else {
            // Product doesn't have a unit at this position - show disabled indicator
            cell.style.textAlign = 'center';
            cell.style.color = '#dee2e6';
            cell.style.background = '#f8f9fa';
            cell.style.fontSize = '11px';
            cell.innerHTML = '<span style="opacity: 0.3;">-</span>';
            cell.title = 'Not applicable for this product';
        }
    }
}

// Calculate row amounts
function calculateRowAmounts(row, totalQty, price, source) {
    totalQty = Number(totalQty) || 0;
    price = Number(price) || 0;
    
    const discPercentInput = row.querySelector('.disc-percent-cell input');
    const discAmountInput = row.querySelector('.disc-amount-cell input');
    const taxPercent = parseFloat(row.querySelector('.tax-percent-cell input')?.value) || 0;
    
    const gross = Number(totalQty) * Number(price);
    const grossInput = row.querySelector('.gross-cell input');
    if (grossInput) {
        grossInput.value = gross.toFixed(3);
    }
    
    // Discount: if triggered from amount field, back-calculate percent; otherwise calc amount from percent
    let discountAmt, discountPercent;
    if (source === 'amount') {
        discountAmt = parseFloat(discAmountInput?.value) || 0;
        discountPercent = gross > 0 ? (discountAmt / gross) * 100 : 0;
        if (discPercentInput) discPercentInput.value = discountPercent.toFixed(3);
    } else {
        discountPercent = parseFloat(discPercentInput?.value) || 0;
        discountAmt = gross * (discountPercent / 100);
        if (discAmountInput) discAmountInput.value = discountAmt.toFixed(3);
    }
    
    // After discount
    const afterDiscount = gross - discountAmt;
    
    // Trade offer amount - read from input (NOT percentage-based)
    const toAmountInput = row.querySelector('.to-amount-cell input');
    let tradeOfferAmt = 0;
    if (toAmountInput) {
        tradeOfferAmt = parseFloat(toAmountInput.value) || 0;
    }
    
    // After trade offer
    const afterTradeOffer = afterDiscount - tradeOfferAmt;
    
    // Check application level - only apply tax at item level if application_level is 'item'
    const applicationLevel = row.dataset.applicationLevel || 'item';
    let taxAmt = 0;
    
    if (applicationLevel === 'item') {
        // Tax amount - use formula from backend if available, otherwise use simple percentage
        const formulaTemplate = row.dataset.formulaTemplate;
        const basePrice = parseFloat(row.dataset.basePrice);
        const taxBase = row.dataset.taxBase;
        
        if (formulaTemplate && !isNaN(basePrice) && basePrice > 0) {
            // For MRP-based formulas (Third Schedule), formula calculates base price
            // Tax = MRP - base_price
            if (taxBase === 'mrp') {
                const totalMrp = basePrice * totalQty;
                const basePriceTotal = evaluateFormula(formulaTemplate, basePrice, taxPercent) * totalQty;
                taxAmt = totalMrp - basePriceTotal;
            } else {
                // For trade_price formulas (Standard GST), formula calculates tax directly
                const taxPerUnit = evaluateFormula(formulaTemplate, basePrice, taxPercent);
                taxAmt = taxPerUnit * totalQty;
            }
        } else {
            taxAmt = afterTradeOffer * (taxPercent / 100);
        }
    }
    // If application_level is 'invoice', tax is NOT applied here (will be applied at invoice level)
    
    row.querySelector('.tax-amount-cell input').value = taxAmt.toFixed(3);
    
    // Net amount (tax excluded)
    const net = afterTradeOffer;
    row.querySelector('.net-cell input').value = net.toFixed(3);
    
    // Update summary
    updateInvoiceSummaryDynamic();
}

// Update footer totals
function updateFooterTotals() {
    const itemsTable = document.getElementById('itemsTable');
    const tfoot = itemsTable.getElementsByTagName('tfoot')[0];
    if (!tfoot || !tfoot.rows[0]) return;
    
    const footerRow = tfoot.rows[0];
    
    // Remove old unit total cells that were dynamically inserted
    // Keep removing cells at index 5 until we reach the known fixed column IDs
    // (index 0=Totals label, 1=Product, 2=Scheme, 3=Batch No, 4=Expiry Date)
    let safetyCounter = 0;
    const knownIds = ['totalSalePrice', 'totalGrossAmount', 'totalDiscountAmountItems', 
                      'totalTradeOfferAmount', 'totalGstAmount', 'totalFocQty', 'totalNetAmountItems'];
    
    while (footerRow.cells.length > 5 && safetyCounter < 20) {
        const cell = footerRow.cells[5];
        // Stop if we reach a cell with a known fixed id
        if (cell && cell.id && knownIds.includes(cell.id)) {
            break;
        }
        footerRow.deleteCell(5);
        safetyCounter++;
    }
    
    // Insert empty cells for dynamic unit columns at index 5
    for (let i = 0; i < maxUnitColumns; i++) {
        const cell = footerRow.insertCell(5 + i);
        cell.textContent = '';
        cell.style.textAlign = 'center';
        cell.style.padding = '8px 4px';
        cell.style.borderTop = '2px solid var(--border-strong, #c9cfda)';
    }
}

// Update invoice summary
function updateInvoiceSummaryDynamic() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    const rows = tbody.rows;
    
    let totalBill = 0;
    let totalPurchasePrice = 0;
    let totalGrossAmount = 0;
    let totalDiscountAmountItems = 0;
    let totalTradeOfferAmountItems = 0;
    let totalTaxAmountItems = 0;
    let totalFOCQty = 0;
    let totalNetAmountItems = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        
        const priceInput = row.querySelector('.price-cell input');
        const grossInput = row.querySelector('.gross-cell input');
        const discAmtInput = row.querySelector('.disc-amount-cell input');
        const toAmtInput = row.querySelector('.to-amount-cell input');
        const taxAmtInput = row.querySelector('.tax-amount-cell input');
        const focInput = row.querySelector('.foc-cell input');
        const netInput = row.querySelector('.net-cell input');
        
        if (netInput) {
            totalPurchasePrice += parseFloat(priceInput?.value) || 0;
            totalGrossAmount += parseFloat(grossInput?.value) || 0;
            totalDiscountAmountItems += parseFloat(discAmtInput?.value) || 0;
            totalTradeOfferAmountItems += parseFloat(toAmtInput?.value) || 0;
            totalTaxAmountItems += parseFloat(taxAmtInput?.value) || 0;
            totalFOCQty += parseFloat(focInput?.value) || 0;
            totalNetAmountItems += parseFloat(netInput?.value) || 0;
            totalBill += parseFloat(netInput?.value) || 0;
        }
    }
    
    // Update totals
    const totalSalePriceEl = document.getElementById('totalSalePrice');
    const totalGrossAmountEl = document.getElementById('totalGrossAmount');
    const totalDiscountAmountItemsEl = document.getElementById('totalDiscountAmountItems');
    const totalTradeOfferAmountEl = document.getElementById('totalTradeOfferAmount');
    const totalTaxAmountEl = document.getElementById('totalTaxAmount');
    const totalFocQtyEl = document.getElementById('totalFocQty');
    const totalNetAmountItemsEl = document.getElementById('totalNetAmountItems');
    
    if (totalSalePriceEl) totalSalePriceEl.textContent = totalPurchasePrice.toFixed(3);
    if (totalGrossAmountEl) totalGrossAmountEl.textContent = totalGrossAmount.toFixed(3);
    if (totalDiscountAmountItemsEl) totalDiscountAmountItemsEl.textContent = totalDiscountAmountItems.toFixed(3);
    if (totalTradeOfferAmountEl) totalTradeOfferAmountEl.textContent = totalTradeOfferAmountItems.toFixed(3);
    if (totalTaxAmountEl) totalTaxAmountEl.textContent = totalTaxAmountItems.toFixed(3);
    if (totalFocQtyEl) totalFocQtyEl.textContent = totalFOCQty.toFixed(3);
    if (totalNetAmountItemsEl) totalNetAmountItemsEl.textContent = totalNetAmountItems.toFixed(3);
    
    const totalBillEl = document.getElementById('totalBill');
    if (totalBillEl) totalBillEl.textContent = totalBill.toFixed(3);
    
    // Calculate invoice-level discount
    const discountPercentEl = document.getElementById('totalDiscountPercent');
    const discountPercent = parseFloat(discountPercentEl?.value) || 0;
    const discountAmount = totalBill * (discountPercent / 100);
    const afterDiscount = totalBill - discountAmount;
    
    const shippingFeesEl = document.getElementById('shippingFees');
    const shippingFees = parseFloat(shippingFeesEl?.value) || 0;
    const netAmount = afterDiscount + shippingFees;
    
    const totalDiscountAmountEl = document.getElementById('totalDiscountAmount');
    const netAmountEl = document.getElementById('netAmount');
    
    if (totalDiscountAmountEl) totalDiscountAmountEl.value = discountAmount.toFixed(3);
    if (netAmountEl) netAmountEl.textContent = netAmount.toFixed(3);
    
    // Calculate withholding tax
    const withholdingTaxPercentEl = document.getElementById('withholdingTaxPercent');
    const withholdingTaxPercent = parseFloat(withholdingTaxPercentEl?.value) || 0;
    const withholdingTaxAmount = netAmount * (withholdingTaxPercent / 100);
    const netReceivable = netAmount - withholdingTaxAmount;
    
    const withholdingTaxAmountEl = document.getElementById('withholdingTaxAmount');
    const netReceivableEl = document.getElementById('netReceivable');
    
    if (withholdingTaxAmountEl) {
        withholdingTaxAmountEl.textContent = withholdingTaxAmount.toFixed(3);
    }
    if (netReceivableEl) {
        netReceivableEl.textContent = netReceivable.toFixed(3);
    }
    
    // Auto-fill Amount Paid when Invoice Type is Cash
    const amountPaidEl = document.getElementById('amountPaid');
    const invoiceTypeEl = document.getElementById('invoiceType');
    if (amountPaidEl && invoiceTypeEl && invoiceTypeEl.value === 'Cash') {
        amountPaidEl.value = netReceivable.toFixed(3);
    }

    // Update remaining balance
    const amountPaid = parseFloat(amountPaidEl?.value) || 0;
    const remainingBalance = netReceivable - amountPaid;

    const remainingBalanceEl = document.getElementById('remainingBalance');
    if (remainingBalanceEl) {
        remainingBalanceEl.value = remainingBalance.toFixed(3);
    }

    // Calculate invoice-level taxes
    if (typeof calculateInvoiceLevelTaxes === 'function') {
        calculateInvoiceLevelTaxes();
    }
}

// Auto-fill the Batch No / Expiry Date columns for a product row.
// No dropdown, no manual selection — the system reads the available
// batches for this product (remaining qty > 0), sorted oldest-first
// (FIFO), and fills in the first one automatically. Once that batch
// (e.g. P1) is fully consumed it stops being returned by the API, so
// the very next invoice for this product will auto-fill the next one
// (e.g. P2) with zero manual work.
async function loadProductBatches(row, productId) {
    const batchNoInput = row.querySelector('.batch-no-input');
    const batchExpiryInput = row.querySelector('.batch-expiry-input');
    if (!batchNoInput || !batchExpiryInput || !productId) return;

    const branchId = document.getElementById('branch')?.value || '';

    batchNoInput.value = '...';
    batchExpiryInput.value = '...';
    row.dataset.batchQtyAvailable = '';

    try {
        let url = `../../../../server/api/sale/pos_invoice/get-product-batches.php?product_id=${productId}`;
        if (branchId) url += `&branch_id=${branchId}`;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.batches && data.batches.length > 0) {
            // First batch = oldest available (FIFO) - auto-filled, read-only
            const batch = data.batches[0];
            batchNoInput.value = batch.batch_no;
            batchExpiryInput.value = batch.batch_expiry_date || '';
            row.dataset.batchQtyAvailable = batch.remaining_qty;
            batchNoInput.title = `Available: ${batch.remaining_qty}`;
        } else {
            batchNoInput.value = '';
            batchExpiryInput.value = '';
            batchNoInput.title = 'No batch available for this product';
        }
    } catch (error) {
        console.error('Error loading product batches:', error);
        batchNoInput.value = '';
        batchExpiryInput.value = '';
    }
}

// Load UOM group units
async function loadUOMGroupUnits() {
    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/get-uom-group-units.php');
        const data = await response.json();
        if (data.success) {
            window.uomGroupUnits = data.groupUnits;
        }
    } catch (error) {
        console.error('Error loading UOM group units:', error);
    }
}