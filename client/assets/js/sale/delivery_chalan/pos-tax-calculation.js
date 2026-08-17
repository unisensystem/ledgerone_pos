// Tax Calculation Module for POS Invoice
// Handles dynamic tax calculation with generic formula parser

/**
 * Fetch and calculate tax for a product based on customer and tax regime
 * @param {number} customerId - Selected customer ID
 * @param {number} productId - Selected product ID
 * @param {string} salePriceSetting - 'trade_price' or 'mrp'
 * @returns {Promise} Tax calculation result
 */
async function calculateProductTax(customerId, productId, salePriceSetting = 'trade_price') {
    if (!customerId || !productId) {
        return {
            success: false,
            tax_rate: 0,
            tax_amount: 0,
            message: 'Missing customer or product'
        };
    }

    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/calculate-tax.php?customer_id=${customerId}&product_id=${productId}&sale_price_setting=${salePriceSetting}`);
        const data = await response.json();
        
        if (!data.success && data.mismatch) {
            showNotification(`Tax Mismatch: ${data.message}`, 'warning');
        }
        
        return data;
    } catch (error) {
        console.error('Error calculating tax:', error);
        return {
            success: false,
            tax_rate: 0,
            tax_amount: 0,
            message: 'Error calculating tax'
        };
    }
}

/**
 * Generic formula evaluator for frontend
 * @param {string} formula - Formula template with tokens
 * @param {number} basePrice - Base price value
 * @param {number} rate - Tax rate percentage
 * @returns {number} Calculated result
 */
function evaluateFormula(formula, basePrice, rate) {
    if (!formula || formula === '0') {
        return 0;
    }
    
    // Replace tokens with actual values
    let expr = formula;
    expr = expr.replace(/{trade_price}/g, basePrice);
    expr = expr.replace(/{mrp}/g, basePrice);
    expr = expr.replace(/{rate}/g, rate);
    
    // Remove whitespace
    expr = expr.replace(/\s/g, '');
    
    // Validate and evaluate
    if (!/^[0-9+\-*\/().]+$/.test(expr)) {
        return 0;
    }
    
    try {
        return Function('"use strict"; return (' + expr + ')')();
    } catch (e) {
        console.error('Formula evaluation error:', e);
        return 0;
    }
}

/**
 * Apply tax to a row based on formula and base price
 * @param {HTMLElement} row - Table row element
 * @param {number} taxRate - Tax rate percentage
 * @param {string} formula - Formula template for tax calculation
 * @param {number} basePrice - Trade price or MRP value
 * @param {string} applicationLevel - 'item' or 'invoice'
 * @param {number} isTaxInclusive - 1 if tax is embedded in price, 0 if added on top
 */
function applyTaxToRow(row, taxRate, formula, basePrice, applicationLevel = 'item', isTaxInclusive = 0) {
    let taxAmount;
    if (isTaxInclusive) {
        taxAmount = taxRate > 0 ? basePrice * taxRate / (100 + taxRate) : 0;
    } else {
        taxAmount = evaluateFormula(formula, basePrice, taxRate);
    }

    row.dataset.isTaxInclusive = isTaxInclusive ? '1' : '0';

    const taxPercentInput = row.querySelector('.tax-percent-cell input') || row.querySelector('[data-tax-percent]');
    const taxAmountInput = row.querySelector('.tax-amount-cell input') || row.querySelector('[data-tax-amount]');

    // Apply tax based on application_level
    if (applicationLevel === 'invoice') {
        if (taxPercentInput) {
            taxPercentInput.value = '0.00';
            taxPercentInput.readOnly = true;
            taxPercentInput.disabled = false;
            taxPercentInput.style.backgroundColor = '#f0f0f0';
            taxPercentInput.style.cursor = 'not-allowed';
            taxPercentInput.style.opacity = '0.7';
            taxPercentInput.title = 'Tax applied at invoice level, not at item level';
        }
        if (taxAmountInput) {
            taxAmountInput.value = '0.00';
        }
    } else {
        if (taxPercentInput) {
            taxPercentInput.value = parseFloat(taxRate).toFixed(2);
            taxPercentInput.readOnly = false;
            taxPercentInput.disabled = false;
            taxPercentInput.style.backgroundColor = '';
            taxPercentInput.style.cursor = 'auto';
            taxPercentInput.style.opacity = '1';
            taxPercentInput.title = isTaxInclusive
                ? `${parseFloat(taxRate).toFixed(2)}% (Tax Inclusive — extracted from price)`
                : `${parseFloat(taxRate).toFixed(2)}% (Tax Exclusive — added to price)`;
        }
        if (taxAmountInput) {
            taxAmountInput.value = parseFloat(taxAmount).toFixed(2);
        }
    }

    return taxAmount;
}

/**
 * Load tax rate when customer is changed
 * Updates all rows with new tax calculation
 */
async function loadTaxRatesForCustomer(customerId) {
    const itemsTable = document.getElementById('itemsTable');
    if (!itemsTable) return;
    
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    const salePriceSetting = localStorage.getItem('salePriceSetting') || 'trade_price';
    
    // Calculate tax for each row
    for (let row of tbody.rows) {
        const productIdInput = row.querySelector('.item-code');
        if (!productIdInput || !productIdInput.value) continue;
        
        const productId = productIdInput.value;
        
        // Fetch tax calculation
        const taxResult = await calculateProductTax(customerId, productId, salePriceSetting);
        
        if (taxResult.success) {
            const applicationLevel = taxResult.application_level || 'item';
            const isTaxInclusive = taxResult.is_tax_inclusive || 0;
            row.dataset.applicationLevel = applicationLevel;

            applyTaxToRow(row, taxResult.tax_rate, taxResult.formula_template, taxResult.base_price, applicationLevel, isTaxInclusive);
            
            // Recalculate row amounts
            if (typeof calculateRowAmounts === 'function') {
                const totalQty = Number(row.querySelector('.qty-cell input')?.value) || 0;
                const price = Number(row.querySelector('.price-cell input')?.value) || 0;
                calculateRowAmounts(row, totalQty, price);
            }
        }
    }

    // Load invoice-level tax regimes for this customer
    if (typeof loadInvoiceLevelTaxRegimes === 'function') {
        const companyId = document.getElementById('company')?.value;
        await loadInvoiceLevelTaxRegimes(customerId, companyId);
    }
}

/**
 * Load initial tax for a new row when product is selected
 */
async function loadTaxForNewProduct(row, customerId, productId) {
    const salePriceSetting = localStorage.getItem('salePriceSetting') || 'trade_price';
    const taxResult = await calculateProductTax(customerId, productId, salePriceSetting);
    
    if (taxResult.success) {
        const taxPercentInput = row.querySelector('.tax-percent-cell input') || row.querySelector('[data-tax-percent]');
        const applicationLevel = taxResult.application_level || 'item';
        const isTaxInclusive = taxResult.is_tax_inclusive || 0;

        // Store tax data in row dataset for formula-based calculation
        row.dataset.taxRate = taxResult.tax_rate;
        row.dataset.taxBase = taxResult.tax_base;
        row.dataset.basePrice = taxResult.base_price;
        row.dataset.formulaTemplate = taxResult.formula_template;
        row.dataset.applicationLevel = applicationLevel;
        row.dataset.isTaxInclusive = isTaxInclusive ? '1' : '0';

        // Set Tax % based on application_level
        if (taxPercentInput) {
            if (applicationLevel === 'invoice') {
                taxPercentInput.value = '0.00';
                taxPercentInput.readOnly = true;
                taxPercentInput.disabled = false;
                taxPercentInput.style.backgroundColor = '#f0f0f0';
                taxPercentInput.style.cursor = 'not-allowed';
                taxPercentInput.style.opacity = '0.7';
                taxPercentInput.title = 'Tax applied at invoice level, not at item level';
            } else {
                taxPercentInput.value = parseFloat(taxResult.tax_rate).toFixed(2);
                taxPercentInput.readOnly = false;
                taxPercentInput.disabled = false;
                taxPercentInput.style.backgroundColor = '';
                taxPercentInput.style.cursor = 'auto';
                taxPercentInput.style.opacity = '1';
                taxPercentInput.title = isTaxInclusive
                    ? `${parseFloat(taxResult.tax_rate).toFixed(2)}% (Tax Inclusive — extracted from price)`
                    : `${parseFloat(taxResult.tax_rate).toFixed(2)}% (Tax Exclusive — added to price)`;
            }
        }
        
        // Trigger row recalculation to apply formula
        if (typeof calculateRowAmounts === 'function') {
            const unitInputs = row.querySelectorAll('.unit-input');
            let totalQty = 0;
            unitInputs.forEach(input => {
                totalQty += parseFloat(input.value) || 0;
            });
            const price = parseFloat(row.querySelector('.price-cell input')?.value) || 0;
            calculateRowAmounts(row, totalQty, price);
        }
    } else if (taxResult.mismatch) {
        const taxPercentInput = row.querySelector('.tax-percent-cell input') || row.querySelector('[data-tax-percent]');
        if (taxPercentInput) {
            taxPercentInput.value = '0';
            taxPercentInput.disabled = true;
            taxPercentInput.title = taxResult.message;
        }
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'warning' ? 'var(--warning, #e8b23f)' : 'var(--success, #2fbf71)'};
        color: white;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-size: 14px;
        font-weight: 500;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.remove(), 3000);
}

window.showNotification = showNotification;
