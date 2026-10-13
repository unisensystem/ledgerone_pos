// Purchase Tax Calculation Module
async function calculateProductTax(supplierId, productId) {
    if (!supplierId || !productId) {
        return { success: false, tax_rate: 0, tax_amount: 0, message: 'Missing supplier or product' };
    }

    try {
        const response = await fetch(`../../../../server/api/purchase/grocery_purchase_invoice/calculate-tax.php?supplier_id=${supplierId}&product_id=${productId}`);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error calculating tax:', error);
        return { success: false, tax_rate: 0, tax_amount: 0, message: 'Error calculating tax' };
    }
}

function evaluateTaxFormula(formula, basePrice, rate) {
    if (!formula || formula === '0') return 0;
    
    let expr = formula;
    expr = expr.replace(/{trade_price}/g, basePrice);
    expr = expr.replace(/{cost_price}/g, basePrice);
    expr = expr.replace(/{rate}/g, rate);
    expr = expr.replace(/\s/g, '');
    
    if (!/^[0-9+\-*\/().]+$/.test(expr)) return 0;
    
    try {
        return Function('"use strict"; return (' + expr + ')')();
    } catch (e) {
        console.error('Formula evaluation error:', e);
        return 0;
    }
}

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
    
    // Calculate tax
    const taxPercent = parseFloat(taxPercentCell.querySelector('input').value) || 0;
    const taxAmount = afterTO * (taxPercent / 100);
    taxAmountCell.querySelector('input').value = taxAmount.toFixed(3);
    
    const net = afterTO + taxAmount;
    netCell.querySelector('input').value = net.toFixed(3);
    
    updateInvoiceSummaryDynamic();
}

window.calculateProductTax = calculateProductTax;
window.evaluateTaxFormula = evaluateTaxFormula;
window.calculateRowAmounts = calculateRowAmounts;
