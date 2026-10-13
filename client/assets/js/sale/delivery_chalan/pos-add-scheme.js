// Scheme Management for POS Invoice
// Handles "Sale On TP", "Less", and "Given" schemes with auto-calculation

// Store scheme data for each row
const rowSchemeData = new Map();

// Constants for scheme types
const SCHEME_TYPES = {
    SALE_ON_TP: 'sale_on_tp',
    LESS: 'less',                  // Less (Original): FOC Qty & T.O Amt both work (qty-based)
    LESS_SPECIAL: 'less_special',  // Less Special: T.O Amt formula-based, FOC readonly
    GIVEN: 'given'                 // Given: FOC Qty works, T.O Amt readonly
};

// Default scheme options
const SCHEME_OPTIONS = [
    { value: SCHEME_TYPES.SALE_ON_TP, label: 'Sale On TP', disabled: false },
    { value: SCHEME_TYPES.LESS, label: 'Less', disabled: false },
    { value: SCHEME_TYPES.LESS_SPECIAL, label: 'Less Special', disabled: false },
    { value: SCHEME_TYPES.GIVEN, label: 'Given', disabled: false }
];

/**
 * Create scheme dropdown cell for a row
 * @param {HTMLTableRowElement} row - The table row
 * @returns {HTMLTableCellElement} - The scheme cell
 */
function createSchemeCell(row) {
    const cell = document.createElement('div');
    cell.className = 'searchable-dropdown scheme-dropdown';
    
    const select = document.createElement('select');
    select.className = 'table-input scheme-select';
    select.tabIndex = -1;
    select.required = true;
    
    // Add default option
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Select Scheme';
    select.appendChild(defaultOption);
    
    // Add scheme options
    SCHEME_OPTIONS.forEach(scheme => {
        const option = document.createElement('option');
        option.value = scheme.value;
        option.textContent = scheme.label;
        option.disabled = scheme.disabled;
        select.appendChild(option);
    });
    
    // Set default to saved preference from localStorage
    const savedScheme = localStorage.getItem('defaultScheme') || SCHEME_TYPES.SALE_ON_TP;
    select.value = savedScheme;
    
    // Add change event listener
    select.addEventListener('change', async function() {
        await onSchemeChange(row, this.value);
    });
    
    cell.appendChild(select);
    return cell;
}

/**
 * Handle scheme change event
 * @param {HTMLTableRowElement} row - The table row
 * @param {string} schemeType - The selected scheme type
 */
async function onSchemeChange(row, schemeType) {
    const productId = row.dataset.productId;
    
    if (!productId) {
        console.warn('Product ID not found for row');
        return;
    }
    
    // Store scheme data for the row
    rowSchemeData.set(row, { schemeType, productId });
    
    // Get product data
    const product = productsData.find(p => p.id == productId);
    if (!product) {
        console.warn('Product not found');
        return;
    }
    
    // Apply scheme logic
    switch(schemeType) {
        case SCHEME_TYPES.SALE_ON_TP:
            applyDefaultScheme(row, product);
            break;
        case SCHEME_TYPES.LESS:
            await applyLessScheme(row, product);
            break;
        case SCHEME_TYPES.LESS_SPECIAL:
            await applyLessSpecialScheme(row, product);
            break;
        case SCHEME_TYPES.GIVEN:
            await applyGivenScheme(row, product);
            break;
    }
}

/**
 * Apply default "Sale On TP" scheme
 * Makes T.O Amt and FOC Qty readonly and non-functional
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object
 */
function applyDefaultScheme(row, product) {
    // Clear scheme flags - use normal percentage calculation
    row.dataset.useSchemeTO = 'false';
    row.dataset.schemeToAmount = '0';
    
    const toAmountInput = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    
    if (toAmountInput) {
        toAmountInput.readOnly = true;
        toAmountInput.style.backgroundColor = '#f0f0f0';
        toAmountInput.value = '0.00';
        toAmountInput.title = 'Read-only in Sale On TP scheme';
    }
    
    if (focInput) {
        focInput.readOnly = true;
        focInput.style.backgroundColor = '#f0f0f0';
        focInput.value = '0';
        focInput.title = 'Read-only in Sale On TP scheme';
    }
    
    // Recalculate amounts using normal percentage-based calculation
    const unitInputs = row.querySelectorAll('.unit-input');
    let totalQty = 0;
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const cf = parseFloat(input.dataset.conversionFactor) || 1;
        totalQty += qty * cf;
    });
    
    const priceInput = row.querySelector('.price-cell input');
    const price = priceInput ? (parseFloat(priceInput.value) || 0) : 0;
    
    calculateRowAmounts(row, totalQty, price);
}

/**
 * Apply "Less" scheme (Original)
 * Enables T.O Amt calculation (qty-based), FOC Qty stays readonly
 * T.O Amt = floor(qty / to_qty) × to_rs
 * FOC Qty = 0 (not used in Less scheme)
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object
 */
async function applyLessScheme(row, product) {
    const toAmountInput = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    
    // Make T.O Amount editable (will be auto-calculated)
    if (toAmountInput) {
        toAmountInput.readOnly = false;
        toAmountInput.style.backgroundColor = '';
        toAmountInput.title = 'Auto-calculated based on to_qty and to_rs, can be edited';
    }
    
    // Keep FOC readonly with value 0 (not used in Less scheme)
    if (focInput) {
        focInput.readOnly = true;
        focInput.value = 0;
        focInput.style.backgroundColor = '#e8e8e8';
        focInput.title = 'Not applicable in Less scheme (FOC Qty = 0)';
    }
    
    // Fetch schemes for this product
    try {
        const schemes = await fetchProductSchemes(product.id);
        
        rowSchemeData.set(row, { 
            schemeType: SCHEME_TYPES.LESS, 
            productId: product.id,
            schemes: schemes || [],
            needsRecalc: true
        });
        
        // Calculate initial amounts
        await calculateLessSchemeAmounts(row, product);
    } catch (error) {
        console.error('Error applying Less scheme:', error);
    }
}

/**
 * Apply "Less Special" scheme
 * Enables T.O Amt calculation (formula-based), FOC Qty readonly
 * Formula: (Qty × Sale Price) / (promo_qty + bonus_qty) = T.O Amt
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object
 */
async function applyLessSpecialScheme(row, product) {
    const toAmountInput = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    
    // Make T.O Amount editable (will be auto-calculated)
    if (toAmountInput) {
        toAmountInput.readOnly = false;
        toAmountInput.style.backgroundColor = '';
        toAmountInput.title = 'Auto-calculated using Less Special formula, can be edited';
    }
    
    // Keep FOC readonly (not used in Less Special scheme)
    if (focInput) {
        focInput.readOnly = true;
        focInput.style.backgroundColor = '#f0f0f0';
        focInput.value = '0';
        focInput.title = 'Not applicable in Less Special scheme';
    }
    
    // Fetch schemes for this product
    try {
        const schemes = await fetchProductSchemes(product.id);
        
        rowSchemeData.set(row, { 
            schemeType: SCHEME_TYPES.LESS_SPECIAL, 
            productId: product.id,
            schemes: schemes || [],
            needsRecalc: true
        });
        
        // Calculate initial amounts
        await calculateLessSpecialSchemeAmounts(row, product);
    } catch (error) {
        console.error('Error applying Less Special scheme:', error);
    }
}

/**
 * Calculate amounts for "Less" scheme (Original)
 * T.O Amt = floor(qty / to_qty) × to_rs
 * FOC Qty = 0 (not used in Less scheme)
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object (optional)
 */
async function calculateLessSchemeAmounts(row, product) {
    const schemeData = rowSchemeData.get(row) || {};
    
    // If product not provided, try to get it from productsData using row's product ID
    if (!product || !product.id) {
        const productId = row.dataset.productId;
        product = productsData.find(p => p.id == productId) || {};
    }
    
    let schemes = schemeData.schemes;
    
    // If schemes not loaded yet, fetch them
    if (!schemes) {
        const productId = product.id || row.dataset.productId;
        if (productId) {
            try {
                schemes = await fetchProductSchemes(productId);
                rowSchemeData.set(row, {
                    ...schemeData,
                    schemes: schemes || []
                });
            } catch (error) {
                console.error('Error fetching schemes in calculation:', error);
                schemes = [];
            }
        }
    }
    
    let totalTOAmount = 0;
    
    // Get all unit inputs
    const unitInputs = row.querySelectorAll('.unit-input');
    
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const unitId = input.dataset.unitId;
        
        // Find scheme for this unit
        const scheme = schemes.find(s => parseFloat(s.unit_id) == parseFloat(unitId));
        
        if (scheme && qty > 0) {
            // Calculate T.O Amt: floor(qty / to_qty) * to_rs
            if (scheme.to_qty > 0) {
                const toPerUnit = Math.floor(qty / scheme.to_qty) * scheme.to_rs;
                totalTOAmount += toPerUnit;
            }
        }
    });
    
    // Update T.O Amount field and keep FOC at 0
    const focInput = row.querySelector('.foc-cell input');
    const toAmountInput = row.querySelector('.to-amount-cell input');
    
    if (focInput) {
        focInput.value = 0;
    }
    
    if (toAmountInput) {
        toAmountInput.value = totalTOAmount.toFixed(2);
    }
    
    // Recalculate row amounts with updated FOC and T.O
    // Mark row to skip percentage-based T.O calculation
    row.dataset.useSchemeTO = 'true';
    row.dataset.schemeToAmount = totalTOAmount.toFixed(2);
    
    let totalQty = 0;
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const cf = parseFloat(input.dataset.conversionFactor) || 1;
        totalQty += qty * cf;
    });
    
    const priceInput = row.querySelector('.price-cell input');
    const price = priceInput ? (parseFloat(priceInput.value) || 0) : 0;
    
    calculateRowAmountsWithScheme(row, totalQty, price);
}

/**
 * Calculate amounts for "Less Special" scheme
 * Formula: (Qty × Sale Price) / (promo_qty + bonus_qty) = T.O Amt
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object (optional)
 */
async function calculateLessSpecialSchemeAmounts(row, product) {
    const schemeData = rowSchemeData.get(row) || {};
    
    // If product not provided, try to get it from productsData using row's product ID
    if (!product || !product.id) {
        const productId = row.dataset.productId;
        product = productsData.find(p => p.id == productId) || {};
    }
    
    let schemes = schemeData.schemes;
    
    // If schemes not loaded yet, fetch them
    if (!schemes) {
        const productId = product.id || row.dataset.productId;
        if (productId) {
            try {
                schemes = await fetchProductSchemes(productId);
                rowSchemeData.set(row, {
                    ...schemeData,
                    schemes: schemes || []
                });
            } catch (error) {
                console.error('Error fetching schemes in calculation:', error);
                schemes = [];
            }
        }
    }
    
    let totalTOAmount = 0;
    
    // Get all unit inputs and sale price
    const unitInputs = row.querySelectorAll('.unit-input');
    const priceInput = row.querySelector('.price-cell input');
    const salePrice = parseFloat(priceInput?.value) || 0;
    
    // Calculate gross quantity
    let totalQty = 0;
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const cf = parseFloat(input.dataset.conversionFactor) || 1;
        totalQty += qty * cf;
    });
    
    // Calculate T.O Amount using Less Special formula
    // Formula: (Total Qty × Sale Price) / (promo_qty + bonus_qty) = T.O Amt
    // Example: (12 × 200) / (12 + 1) = 2400 / 13 = 184.61
    if (schemes && schemes.length > 0 && totalQty > 0 && salePrice > 0) {
        const scheme = schemes[0]; // Use first scheme for calculation
        
        if (scheme.promo_qty && scheme.bonus_qty !== undefined) {
            const denominator = parseFloat(scheme.promo_qty) + parseFloat(scheme.bonus_qty);
            if (denominator > 0) {
                // T.O Amount = (Total Qty × Sale Price) / (promo_qty + bonus_qty)
                totalTOAmount = (totalQty * salePrice) / denominator;
            }
        }
    }
    
    // Update T.O Amount field
    const toAmountInput = row.querySelector('.to-amount-cell input');
    if (toAmountInput) {
        toAmountInput.value = totalTOAmount.toFixed(2);
    }
    
    // Keep FOC at 0
    const focInput = row.querySelector('.foc-cell input');
    if (focInput) {
        focInput.value = '0';
    }
    
    // Mark row to use scheme T.O calculation
    row.dataset.useSchemeTO = 'true';
    row.dataset.schemeToAmount = totalTOAmount.toFixed(2);
    
    // Recalculate row amounts with updated T.O
    calculateRowAmountsWithScheme(row, totalQty, salePrice);
}

/**
 * Calculate row amounts with scheme T.O support
 * Preserves scheme-calculated T.O Amt instead of overwriting with percentage
 * @param {HTMLTableRowElement} row - The table row
 * @param {number} totalQty - Total quantity
 * @param {number} price - Sale price
 */
function calculateRowAmountsWithScheme(row, totalQty, price) {
    totalQty = Number(totalQty) || 0;
    price = Number(price) || 0;
    
    const discountPercentInput = row.querySelector('.disc-percent-cell input');
    const gstPercentInput = row.querySelector('.tax-percent-cell input');
    
    if (!discountPercentInput || !gstPercentInput) {
        console.warn('Missing discount or tax input elements in row');
        return;
    }
    
    const discountPercent = parseFloat(discountPercentInput.value) || 0;
    const gstPercent = parseFloat(gstPercentInput.value) || 0;
    
    const gross = Number(totalQty) * Number(price);
    const grossInput = row.querySelector('.gross-cell input');
    if (grossInput) {
        grossInput.value = gross.toFixed(2);
    }
    
    // Discount amount (percentage-based) - only if enabled
    const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
    let discountAmt = 0;
    if (enableCashDiscountAmount) {
        discountAmt = gross * (discountPercent / 100);
    }
    const discAmountInput = row.querySelector('.disc-amount-cell input');
    if (discAmountInput) {
        discAmountInput.value = discountAmt.toFixed(2);
    }
    
    // After discount
    const afterDiscount = gross - discountAmt;
    
    // Trade offer amount (from scheme, not percentage) - only if enabled
    const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
    const toAmountInput = row.querySelector('.to-amount-cell input');
    let schemeToAmount = 0;
    if (enableTradeOfferAmount && toAmountInput) {
        schemeToAmount = parseFloat(toAmountInput.value) || 0;
    } else if (toAmountInput) {
        toAmountInput.value = '0.00';
    }
    
    // After trade offer
    const afterTradeOffer = afterDiscount - schemeToAmount;
    
    // GST amount - only if enabled
    const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
    let gstAmt = 0;
    if (enableTaxation) {
        gstAmt = afterTradeOffer * (gstPercent / 100);
    }
    const gstAmountInput = row.querySelector('.tax-amount-cell input');
    if (gstAmountInput) {
        gstAmountInput.value = gstAmt.toFixed(2);
    }
    
    // Net amount
    const net = afterTradeOffer + gstAmt;
    const netInput = row.querySelector('.net-cell input');
    if (netInput) {
        netInput.value = net.toFixed(2);
    }
    
    // Update summary
    updateInvoiceSummaryDynamic();
}

/**
 * Apply "Given" scheme
 * Enables FOC Qty calculation based on product_schemes, T.O Amt readonly
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object
 */
async function applyGivenScheme(row, product) {
    const toAmountInput = row.querySelector('.to-amount-cell input');
    const focInput = row.querySelector('.foc-cell input');
    
    // Keep T.O Amount readonly (not used in Given scheme)
    if (toAmountInput) {
        toAmountInput.readOnly = true;
        toAmountInput.style.backgroundColor = '#f0f0f0';
        toAmountInput.value = '0.00';
        toAmountInput.title = 'Not applicable in Given scheme';
    }
    
    // Make FOC Qty editable (will be auto-calculated from promo_qty/bonus_qty)
    if (focInput) {
        focInput.readOnly = false;
        focInput.style.backgroundColor = '';
        focInput.title = 'Auto-calculated using promo_qty (free gift), can be edited';
    }
    
    // Fetch schemes for this product
    try {
        const schemes = await fetchProductSchemes(product.id);
        
        rowSchemeData.set(row, { 
            schemeType: SCHEME_TYPES.GIVEN, 
            productId: product.id,
            schemes: schemes || [],
            needsRecalc: true
        });
        
        // Calculate initial amounts
        await calculateGivenSchemeAmounts(row, product);
    } catch (error) {
        console.error('Error applying Given scheme:', error);
    }
}

/**
 * Calculate amounts for "Given" scheme
 * FOC Qty = sum of (floor(qty_per_unit / promo_qty) * bonus_qty) for each unit
 * @param {HTMLTableRowElement} row - The table row
 * @param {Object} product - Product object (optional)
 */
async function calculateGivenSchemeAmounts(row, product) {
    const schemeData = rowSchemeData.get(row) || {};
    
    // If product not provided, try to get it from productsData using row's product ID
    if (!product || !product.id) {
        const productId = row.dataset.productId;
        product = productsData.find(p => p.id == productId) || {};
    }
    
    let schemes = schemeData.schemes;
    
    // If schemes not loaded yet, fetch them
    if (!schemes) {
        const productId = product.id || row.dataset.productId;
        if (productId) {
            try {
                schemes = await fetchProductSchemes(productId);
                rowSchemeData.set(row, {
                    ...schemeData,
                    schemes: schemes || []
                });
            } catch (error) {
                console.error('Error fetching schemes in calculation:', error);
                schemes = [];
            }
        }
    }
    
    let totalFOCQty = 0;
    let totalQty = 0;
    
    // Get all unit inputs
    const unitInputs = row.querySelectorAll('.unit-input');
    
    // Calculate FOC for each unit separately
    unitInputs.forEach(input => {
        const qty = parseFloat(input.value) || 0;
        const unitId = input.dataset.unitId;
        const cf = parseFloat(input.dataset.conversionFactor) || 1;
        
        // Add to total quantity for price calculation
        totalQty += qty * cf;
        
        // Find scheme for this specific unit
        if (schemes && schemes.length > 0 && qty > 0) {
            const scheme = schemes.find(s => parseFloat(s.unit_id) == parseFloat(unitId));
            
            if (scheme && scheme.promo_qty > 0) {
                // FOC Qty for this unit: floor(qty / promo_qty) * bonus_qty
                const unitFOC = Math.floor(qty / scheme.promo_qty) * scheme.bonus_qty;
                totalFOCQty += unitFOC;
            }
        }
    });
    
    // Update FOC Qty field
    const focInput = row.querySelector('.foc-cell input');
    if (focInput) {
        focInput.value = totalFOCQty.toFixed(2);
    }
    
    // Keep T.O Amount at 0
    const toAmountInput = row.querySelector('.to-amount-cell input');
    if (toAmountInput) {
        toAmountInput.value = '0.00';
    }
    
    // Mark row - Given scheme doesn't use T.O
    row.dataset.useSchemeTO = 'false';
    row.dataset.schemeToAmount = '0.00';
    
    // Recalculate row amounts with standard calculation
    const priceInput = row.querySelector('.price-cell input');
    const price = priceInput ? (parseFloat(priceInput.value) || 0) : 0;
    
    console.log('calculateGivenSchemeAmounts: totalQty=', totalQty, 'price=', price, 'totalFOCQty=', totalFOCQty);
    calculateRowAmountsWithScheme(row, totalQty, price);
    console.log('After calculateRowAmountsWithScheme - Net Amount:', row.querySelector('.net-cell input')?.value);
}

/**
 * Fetch product schemes from server
 * @param {number} productId - Product ID
 * @returns {Promise<Array>} - Array of schemes
 */
async function fetchProductSchemes(productId) {
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-schemes.php?product_id=${productId}`);
        const data = await response.json();
        
        if (data.success && data.schemes) {
            return data.schemes;
        }
        return [];
    } catch (error) {
        console.error('Error fetching product schemes:', error);
        return [];
    }
}

/**
 * Get selected scheme for a row
 * @param {HTMLTableRowElement} row - The table row
 * @returns {string} - Selected scheme type
 */
function getSelectedScheme(row) {
    const select = row.querySelector('.scheme-select');
    return select ? select.value : SCHEME_TYPES.SALE_ON_TP;
}

/**
 * Set scheme for a row
 * @param {HTMLTableRowElement} row - The table row
 * @param {string} schemeType - Scheme type to set
 */
async function setRowScheme(row, schemeType) {
    const select = row.querySelector('.scheme-select');
    if (select) {
        select.value = schemeType;
        await onSchemeChange(row, schemeType);
    }
}
