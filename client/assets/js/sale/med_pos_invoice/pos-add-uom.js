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
    updateTableFooter();  // 🔴 NEW - Update footer too!
    
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

// 🔴 NEW FUNCTION - Update table footer to match header columns
function updateTableFooter() {
    const itemsTable = document.getElementById('itemsTable');
    const tfoot = itemsTable.getElementsByTagName('tfoot')[0];
    if (!tfoot || tfoot.rows.length === 0) return;
    
    const footerRow = tfoot.rows[0];
    
    // Remove old unit cells from footer (between Expiry Date 2 and Price)
    // Keep removing cells at index 5 until we find a cell with price-cell class
    let safetyCounter = 0;
    while (footerRow.cells.length > 5 && safetyCounter < 20 && !footerRow.cells[5].classList.contains('price-cell')) {
        footerRow.deleteCell(5);
        safetyCounter++;
    }
    
    // Insert empty unit cells in footer starting at index 5 (after Batch No 2 / Expiry Date 2)
    for (let i = 0; i < maxUnitColumns; i++) {
        const th = footerRow.insertCell(5 + i);
        th.width = '8%';
        th.style.textAlign = 'center';
        th.textContent = '';  // Empty footer cells for units
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
            
            // Label
            const label = document.createElement('span');
            label.style.fontSize = '10px';
            label.style.fontWeight = '500';
            label.style.color = 'var(--subtext, #7a7a7a)';
            label.style.whiteSpace = 'nowrap';
            label.textContent = uomDetail.name;
            
            // Input
            const input = document.createElement('input');
            input.type = 'number';
            input.step = '0.001';
            input.min = '0';
            input.className = 'unit-input';
            input.dataset.unitId = uomDetail.id;
            input.style.fontSize = '12px';
            input.style.padding = '4px 6px';
            input.style.border = '1px solid var(--border-default)';
            input.style.borderRadius = '4px';
            input.style.fontFamily = 'monospace';
            input.value = existingValues[uomDetail.id] || '0';
            
            // Calculate total quantity if this is base unit
            const baseUnitConversion = uomDetail.isBase ? 1 : uomDetail.conversionFactor;
            
            // Wire change event
            input.addEventListener('change', function() {
                calculateRowAmounts(row);
            });
            input.addEventListener('input', function() {
                // Show preview while typing (no calculation yet)
            });
            
            container.appendChild(label);
            container.appendChild(input);
            cell.appendChild(container);
        } else {
            // Empty cell for products with fewer units
            cell.textContent = '';
        }
    }
}

// Calculate row amounts (triggered on unit input change)
function calculateRowAmounts(row) {
    // Collect all units for this row
    const unitInputs = row.querySelectorAll('.unit-input');
    let totalQuantity = 0;
    
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        totalQuantity += qty;
    });
    
    // Update gross amount (simplified - your app has more complex logic)
    const priceCell = row.querySelector('.price-cell');
    const grossCell = row.querySelector('.gross-cell');
    if (priceCell && grossCell && totalQuantity > 0) {
        const price = parseFloat(priceCell.textContent) || 0;
        const grossAmount = totalQuantity * price;
        grossCell.textContent = grossAmount.toFixed(3);
    }
    
    // Trigger the main calculation function
    if (typeof updateInvoiceSummary === 'function') {
        updateInvoiceSummary();
    }
}

// Combined total calculation
function updateFooterTotals() {
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    const rows = tbody.rows;
    
    let totalUnitQties = new Array(maxUnitColumns).fill(0);
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const unitInputs = row.querySelectorAll('.unit-input');
        unitInputs.forEach((input, idx) => {
            if (idx < maxUnitColumns) {
                totalUnitQties[idx] += parseFloat(input.value) || 0;
            }
        });
    }
    
    // Update footer with unit totals
    const tfoot = itemsTable.getElementsByTagName('tfoot')[0];
    if (tfoot && tfoot.rows.length > 0) {
        const footerRow = tfoot.rows[0];
        for (let i = 0; i < maxUnitColumns; i++) {
            if (footerRow.cells[5 + i]) {
                footerRow.cells[5 + i].textContent = totalUnitQties[i].toFixed(3);
                footerRow.cells[5 + i].style.textAlign = 'center';
                footerRow.cells[5 + i].style.fontWeight = '600';
            }
        }
    }
    
    // Update other totals
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
        let url = `../../../../server/api/sale/pos_invoice/get-product-batches.php?product_id=${productId}`;
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
        const response = await fetch('../../../../server/api/sale/pos_invoice/get-uom-group-units.php');
        const data = await response.json();
        if (data.success) {
            window.uomGroupUnits = data.groupUnits;
        }
    } catch (error) {
        console.error('Error loading UOM group units:', error);
    }
}


// commit for git