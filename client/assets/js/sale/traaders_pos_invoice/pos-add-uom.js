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
                const response = await fetch(`../../../../server/api/sale/traaders_pos_invoice/get-product-uom-conversions.php?product_id=${product.id}`);
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
        const response = await fetch(`../../../../server/api/sale/traaders_pos_invoice/get-product-uom-conversions.php?product_id=${product.id}`);
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
    while (headerRow.cells.length > 3 && safetyCounter < 20) {
        const cell = headerRow.cells[3];
        const cellText = cell.textContent.trim();
        // Stop if we reach Weight/Packing/Total Weight, Price, or any of the fixed columns
        if (cellText.includes('Weight') || cellText.includes('Packing') ||
            cellText.includes('Sale Price') || cellText.includes('Gross Amount') ||
            cellText.includes('Disc') || cellText.includes('T.O') ||
            cellText.includes('GST') || cellText.includes('FOC') ||
            cellText.includes('Net Amount') || cellText.includes('Actions')) {
            break;
        }
        headerRow.deleteCell(3);
        safetyCounter++;
    }

    // Insert generic unit headers (Unit 1, Unit 2, Unit 3, etc.) starting at index 3
    // (index 0=S#, 1=Product, 2=Scheme; this page has no Batch No/Expiry Date columns)
    for (let i = 0; i < maxUnitColumns; i++) {
        const th = headerRow.insertCell(3 + i);
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
    
    // Remove old unit cells (between Scheme and Packing/Weight/Total Weight)
    // Keep removing cells at index 3 until we find the packing-cell (or,
    // as a fallback, price-cell) — the fixed columns that follow the dynamic
    // unit block (index 0=S#, 1=Product, 2=Scheme; this page has no Batch No/Expiry Date columns)
    while (row.cells.length > 3 && !row.cells[3].classList.contains('packing-cell') && !row.cells[3].classList.contains('price-cell')) {
        row.deleteCell(3);
    }

    // Insert unit cells starting at index 3 (after Scheme cell)
    for (let i = 0; i < maxUnitColumns; i++) {
        const cell = row.insertCell(3 + i);
        
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

    // Extra Discount (med_pos_invoice only — column doesn't exist elsewhere,
    // so the selector simply finds nothing and extraDiscAmt stays 0).
    // The field holds a PERCENTAGE (e.g. "2" = 2%), applied on the amount
    // after the main discount; the computed amount is cached on the row's
    // dataset so save/footer-total code can read it without re-deriving it.
    const extraDiscInput = row.querySelector('.extra-disc-cell input');
    const extraDiscPercent = extraDiscInput ? (parseFloat(extraDiscInput.value) || 0) : 0;
    const extraDiscAmt = afterDiscount * (extraDiscPercent / 100);
    row.dataset.extraDiscountAmount = extraDiscAmt.toFixed(3);
    const afterExtraDiscount = afterDiscount - extraDiscAmt;

    // Trade offer amount - read from input (NOT percentage-based)
    const toAmountInput = row.querySelector('.to-amount-cell input');
    let tradeOfferAmt = 0;
    if (toAmountInput) {
        tradeOfferAmt = parseFloat(toAmountInput.value) || 0;
    }
    
    // After trade offer
    const afterTradeOffer = afterExtraDiscount - tradeOfferAmt;
    
    // Check application level - only apply tax at item level if application_level is 'item'
    const applicationLevel = row.dataset.applicationLevel || 'item';
    let taxAmt = 0;
    
    if (applicationLevel === 'item') {
        // Tax amount - use formula from backend if available, otherwise use simple percentage
        const formulaTemplate = row.dataset.formulaTemplate;
        const basePrice = parseFloat(row.dataset.basePrice);
        const taxBase = row.dataset.taxBase;
        const isTaxInclusive = row.dataset.isTaxInclusive === '1';

        if (isTaxInclusive) {
            // Tax is embedded in the price — extract it via back-calculation per unit,
            // same formula used on initial product selection (loadTaxForNewProduct).
            // Re-derived here too since this function re-runs on every qty/price edit.
            const taxPerUnit = taxPercent > 0 ? basePrice * taxPercent / (100 + taxPercent) : 0;
            taxAmt = taxPerUnit * totalQty;
        } else if (formulaTemplate && !isNaN(basePrice) && basePrice > 0) {
            // For MRP-based formulas (Third Schedule), formula calculates base price
            // Tax = MRP - base_price
            if (taxBase === 'mrp') {
                const totalMrp = basePrice * totalQty;
                const basePriceTotal = evaluateFormula(formulaTemplate, basePrice, taxPercent) * totalQty;
                taxAmt = totalMrp - basePriceTotal;
            } else {
                // For trade_price formulas (Standard GST), formula calculates tax directly.
                // med_pos_invoice: tax is charged on the amount left after ALL discounts
                // (main + extra + trade offer), not the raw statutory trade_price — swap
                // in the discounted per-unit price before running the formula. Regular
                // traaders_pos_invoice keeps the original statutory-value behavior unchanged since
                // isTaxationEnabled() is always false there.
                const taxEnabled = typeof isTaxationEnabled === 'function' ? isTaxationEnabled() : false;
                const effectivePrice = (taxEnabled && totalQty > 0) ? (afterTradeOffer / totalQty) : basePrice;
                const taxPerUnit = evaluateFormula(formulaTemplate, effectivePrice, taxPercent);
                taxAmt = taxPerUnit * totalQty;
            }
        } else {
            taxAmt = afterTradeOffer * (taxPercent / 100);
        }
    }
    // If application_level is 'invoice', tax is NOT applied here (will be applied at invoice level)
    
    row.querySelector('.tax-amount-cell input').value = taxAmt.toFixed(3);

    // Net amount: exclusive item-level tax must be added on top (it isn't embedded
    // in salePrice); inclusive tax is already embedded in salePrice so stays
    // excluded here to avoid double-counting. Gated to med_pos_invoice via
    // isTaxationEnabled() — the regular POS invoice keeps its historical "tax
    // computed but excluded from net" behavior unchanged (enableTaxation is
    // always false there, so this stays off regardless of any tax data present).
    const includeTaxInNet = applicationLevel === 'item'
        && row.dataset.isTaxInclusive !== '1'
        && (typeof isTaxationEnabled === 'function' ? isTaxationEnabled() : false);
    const net = includeTaxInNet ? (afterTradeOffer + taxAmt) : afterTradeOffer;
    row.querySelector('.net-cell input').value = net.toFixed(3);
    
    // Update summary
    updateInvoiceSummaryDynamic();
}

function updateFooterTotals() {
    const itemsTable = document.getElementById('itemsTable');
    const tfoot = itemsTable.getElementsByTagName('tfoot')[0];
    if (!tfoot || !tfoot.rows[0]) return;
    
    const footerRow = tfoot.rows[0];
    
    // Remove old unit total cells that were dynamically inserted
    // Fixed columns: 0=Totals label, 1=Product, 2=Scheme — dynamic Unit columns
    // start at index 3, same anchor as the header/row (this page has no Batch
    // No/Expiry Date footer columns, unlike med_pos_invoice).
    let safetyCounter = 0;
    const knownIds = ['totalSalePrice', 'totalGrossAmount', 'totalDiscountAmountItems',
                      'totalTradeOfferAmount', 'totalGstAmount', 'totalFocQty', 'totalNetAmountItems'];

    while (footerRow.cells.length > 3 && safetyCounter < 20) {
        const cell = footerRow.cells[3];
        // Stop if we reach a cell with a known fixed id, or the Weight column
        if (cell && ((cell.id && knownIds.includes(cell.id)) || cell.classList.contains('packing-cell'))) {
            break;
        }
        footerRow.deleteCell(3);
        safetyCounter++;
    }

    // Insert empty cells for dynamic unit columns at index 3
    for (let i = 0; i < maxUnitColumns; i++) {
        const cell = footerRow.insertCell(3 + i);
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
    let totalExtraDiscountAmountItems = 0;
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
            totalExtraDiscountAmountItems += parseFloat(row.dataset.extraDiscountAmount) || 0;
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
    const totalExtraDiscountAmountEl = document.getElementById('totalExtraDiscountAmount');
    const totalTradeOfferAmountEl = document.getElementById('totalTradeOfferAmount');
    const totalTaxAmountEl = document.getElementById('totalTaxAmount');
    const itemTaxAmountSummaryEl = document.getElementById('itemTaxAmountSummary'); // NEW — med_pos_invoice only
    const totalFocQtyEl = document.getElementById('totalFocQty');
    const totalNetAmountItemsEl = document.getElementById('totalNetAmountItems');
    
    if (totalSalePriceEl) totalSalePriceEl.textContent = totalPurchasePrice.toFixed(3);
    if (totalGrossAmountEl) totalGrossAmountEl.textContent = totalGrossAmount.toFixed(3);
    if (totalDiscountAmountItemsEl) totalDiscountAmountItemsEl.textContent = totalDiscountAmountItems.toFixed(3);
    if (totalExtraDiscountAmountEl) totalExtraDiscountAmountEl.textContent = totalExtraDiscountAmountItems.toFixed(3);
    if (totalTradeOfferAmountEl) totalTradeOfferAmountEl.textContent = totalTradeOfferAmountItems.toFixed(3);
    if (totalTaxAmountEl) totalTaxAmountEl.textContent = totalTaxAmountItems.toFixed(3);
    if (itemTaxAmountSummaryEl) itemTaxAmountSummaryEl.textContent = totalTaxAmountItems.toFixed(3); // NEW
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

    // Expose totals for invoice-level tax base resolution
    window.currentTotalBill = totalBill;
    window.currentValueExclSalesTax = afterDiscount;
    window.currentNetAmount = netAmount;

    // Calculate withholding tax
    const withholdingTaxPercentEl = document.getElementById('withholdingTaxPercent');
    const withholdingTaxPercent = parseFloat(withholdingTaxPercentEl?.value) || 0;
    const withholdingTaxAmount = netAmount * (withholdingTaxPercent / 100);
    let netReceivable = netAmount - withholdingTaxAmount;

    const withholdingTaxAmountEl = document.getElementById('withholdingTaxAmount');
    const netReceivableEl = document.getElementById('netReceivable');

    if (withholdingTaxAmountEl) {
        withholdingTaxAmountEl.textContent = withholdingTaxAmount.toFixed(3);
    }

    // Apply invoice-level taxes and factor exclusive tax into net receivable
    // BEFORE computing remaining balance — otherwise remainingBalance would be
    // derived from the pre-tax netReceivable while the displayed netReceivable
    // (overwritten inside calculateInvoiceLevelTaxes) already includes tax,
    // leaving the two fields inconsistent with each other.
    if (typeof calculateInvoiceLevelTaxes === 'function') {
        const exclusiveTax = calculateInvoiceLevelTaxes() || 0;
        netReceivable = netAmount - withholdingTaxAmount + exclusiveTax;
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
}

function escBatchHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

// Load the Batch No dropdown / Expiry Date column for a product row.
// Batches are fetched sorted soonest-expiry-first (FEFO) and the dropdown
// auto-selects the first (soonest-expiry) one, but the user can pick a
// different batch manually (e.g. if the FEFO batch doesn't have enough
// remaining qty for this sale) — see applySelectedBatch().
async function loadProductBatches(row, productId) {
    const batchNoSelect = row.querySelector('.batch-no-input');
    const batchExpiryInput = row.querySelector('.batch-expiry-input');
    if (!batchNoSelect || !batchExpiryInput || !productId) return;

    const branchId = document.getElementById('branch')?.value || '';

    batchNoSelect.innerHTML = '<option value="">Loading...</option>';
    batchExpiryInput.value = '...';
    row.dataset.batchQtyAvailable = '';
    row.dataset.productBatches = '[]';

    try {
        let url = `../../../../server/api/sale/traaders_pos_invoice/get-product-batches.php?product_id=${productId}`;
        if (branchId) url += `&branch_id=${branchId}`;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.batches && data.batches.length > 0) {
            row.dataset.productBatches = JSON.stringify(data.batches);

            batchNoSelect.innerHTML = data.batches.map(function (b) {
                const expiryText = b.batch_expiry_date ? ` - Exp: ${escBatchHtml(b.batch_expiry_date)}` : '';
                return `<option value="${escBatchHtml(b.batch_no)}">${escBatchHtml(b.batch_no)}${expiryText} (Qty: ${escBatchHtml(b.remaining_qty)})</option>`;
            }).join('');

            batchNoSelect.selectedIndex = 0;
            applySelectedBatch(row, data.batches[0]);
        } else {
            batchNoSelect.innerHTML = '<option value="">No Batch</option>';
            batchExpiryInput.value = '';
            batchNoSelect.title = 'No batch available for this product';
            row.dataset.batchQtyAvailable = '';
        }
    } catch (error) {
        console.error('Error loading product batches:', error);
        batchNoSelect.innerHTML = '<option value="">No Batch</option>';
        batchExpiryInput.value = '';
        row.dataset.batchQtyAvailable = '';
    }

    // Wire the manual-selection handler once per row
    if (!batchNoSelect.dataset.changeWired) {
        batchNoSelect.dataset.changeWired = '1';
        batchNoSelect.addEventListener('change', function () {
            let batches = [];
            try { batches = JSON.parse(row.dataset.productBatches || '[]'); } catch (e) { batches = []; }
            const selected = batches.find(function (b) { return b.batch_no === batchNoSelect.value; });
            applySelectedBatch(row, selected || null);
        });
    }
}

// Apply a chosen batch (from the FEFO auto-select or a manual dropdown change):
// fills the Expiry Date field, stores the batch's remaining qty for stock
// validation, and immediately re-runs stock validation for this row.
function applySelectedBatch(row, batch) {
    const batchExpiryInput = row.querySelector('.batch-expiry-input');
    if (batch) {
        if (batchExpiryInput) batchExpiryInput.value = batch.batch_expiry_date || '';
        row.dataset.batchQtyAvailable = batch.remaining_qty;
        row.dataset.batchNo = batch.batch_no;
    } else {
        if (batchExpiryInput) batchExpiryInput.value = '';
        row.dataset.batchQtyAvailable = '';
        row.dataset.batchNo = '';
    }
    if (typeof validateStockForRow === 'function') {
        validateStockForRow(row);
    }
}

// Load UOM group units
async function loadUOMGroupUnits() {
    try {
        const response = await fetch('../../../../server/api/sale/traaders_pos_invoice/get-uom-group-units.php');
        const data = await response.json();
        if (data.success) {
            window.uomGroupUnits = data.groupUnits;
        }
    } catch (error) {
        console.error('Error loading UOM group units:', error);
    }
}