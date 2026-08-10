// Dynamic Invoice-Level Tax System
window.invoiceLevelTaxRegimes = [];

/**
 * Load invoice-level tax regimes for a customer
 * @param {number} customerId - Customer ID
 * @param {number} companyId - Company ID (optional, for country filtering)
 */
async function loadInvoiceLevelTaxRegimes(customerId, companyId = null) {
    console.log('loadInvoiceLevelTaxRegimes called with customerId:', customerId, 'companyId:', companyId);
    
    if (!customerId) {
        console.log('No customerId provided, clearing taxes');
        clearInvoiceLevelTaxes();
        return;
    }

    try {
        let url = `../../../../server/api/sale/pos_invoice/get-invoice-level-taxes.php?customer_id=${customerId}`;
        if (companyId) {
            url += `&company_id=${companyId}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('Invoice-level taxes API response:', data);

        if (data.success && data.data && data.data.length > 0) {
            window.invoiceLevelTaxRegimes = data.data;
            console.log('Invoice-level tax regimes loaded:', window.invoiceLevelTaxRegimes);
            renderInvoiceLevelTaxColumns();
        } else {
            console.log('No invoice-level tax regimes found for customer');
            clearInvoiceLevelTaxes();
        }
    } catch (error) {
        console.error('Error loading invoice-level taxes:', error);
        clearInvoiceLevelTaxes();
    }
}

/**
 * Render dynamic tax columns in Invoice Summary
 */
function renderInvoiceLevelTaxColumns() {
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (!container) return;

    container.innerHTML = '';

    window.invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercent = parseFloat(regime.rate_percentage) || 0;
        
        // Tax % Column
        const percentItem = document.createElement('div');
        percentItem.className = 'summary-item invoice-tax-item';
        percentItem.dataset.taxRegimeId = regime.id;
        percentItem.innerHTML = `
            <span class="summary-label">${regime.regime_name} %</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_percent">${ratePercent.toFixed(2)}</span>
        `;
        container.appendChild(percentItem);

        // Tax Amount Column
        const amountItem = document.createElement('div');
        amountItem.className = 'summary-item invoice-tax-item';
        amountItem.dataset.taxRegimeId = regime.id;
        amountItem.innerHTML = `
            <span class="summary-label">${regime.regime_name} Amt</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_amount">0.00</span>
        `;
        container.appendChild(amountItem);
    });
}

/**
 * Resolve the correct base amount for an invoice-level tax regime.
 * tax_base drives which invoice total is used:
 *   total_bill           → sum of all row net amounts before any invoice discount
 *   value_excl_sales_tax → total_bill minus invoice discount (does NOT include shipping)
 *   net_amount (default) → value_excl_sales_tax plus shipping fees
 */
function resolveInvoiceBase(regime) {
    switch (regime.tax_base) {
        case 'total_bill':
            return window.currentTotalBill || 0;
        case 'value_excl_sales_tax':
            return window.currentValueExclSalesTax || 0;
        case 'net_amount':
        default:
            return window.currentNetAmount
                || parseFloat(document.getElementById('netAmount')?.textContent)
                || 0;
    }
}

/**
 * Evaluate a formula_template string with invoice variables substituted.
 * Supported tokens: {total_bill}, {value_excl_sales_tax}, {net_amount}, {rate}
 * Falls back to simple base × rate / 100 when template is absent.
 */
function applyInvoiceFormula(formulaTemplate, base, ratePercent, isTaxInclusive) {
    if (!formulaTemplate || formulaTemplate.trim() === '' || formulaTemplate === '0') {
        return isTaxInclusive
            ? (ratePercent > 0 ? base * ratePercent / (100 + ratePercent) : 0)
            : base * (ratePercent / 100);
    }

    let expr = formulaTemplate
        .replace(/{total_bill}/g,           window.currentTotalBill          || 0)
        .replace(/{value_excl_sales_tax}/g,  window.currentValueExclSalesTax  || 0)
        .replace(/{net_amount}/g,            window.currentNetAmount          || 0)
        .replace(/{trade_price}/g,           base)
        .replace(/{mrp}/g,                   base)
        .replace(/{import_value}/g,          base)
        .replace(/{rate}/g,                  ratePercent);

    try {
        // eslint-disable-next-line no-new-func
        const result = Function('"use strict"; return (' + expr + ')')();
        return isFinite(result) ? Math.max(0, result) : 0;
    } catch (e) {
        console.warn('Invoice formula eval failed:', expr, e);
        return 0;
    }
}

/**
 * Calculate invoice-level taxes using each regime's tax_base and formula_template.
 */
function calculateInvoiceLevelTaxes() {
    console.log('calculateInvoiceLevelTaxes called. Regimes:', window.invoiceLevelTaxRegimes);

    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) {
        console.log('No invoice-level tax regimes found');
        return 0;
    }

    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (container && container.children.length === 0) {
        renderInvoiceLevelTaxColumns();
    }

    // Track the base amount used per regime so it can be saved correctly
    window.invoiceTaxBaseAmounts = {};

    let totalInvoiceTax = 0;
    let totalExclusiveTax = 0;

    window.invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercent   = parseFloat(regime.rate_percentage) || 0;
        const isTaxInclusive = regime.is_tax_inclusive == 1;
        const base          = resolveInvoiceBase(regime);

        window.invoiceTaxBaseAmounts[regime.id] = base;

        const taxAmount = applyInvoiceFormula(
            regime.formula_template || '',
            base,
            ratePercent,
            isTaxInclusive
        );

        console.log(`${regime.regime_name} [tax_base=${regime.tax_base}] base=${base} rate=${ratePercent}% → tax=${taxAmount}`);

        const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
        const amountEl  = document.getElementById(`invoiceTax_${regime.id}_amount`);

        if (percentEl) percentEl.textContent = ratePercent.toFixed(2);
        if (amountEl)  amountEl.textContent  = taxAmount.toFixed(2);

        totalInvoiceTax += taxAmount;
        if (!isTaxInclusive) totalExclusiveTax += taxAmount;
    });

    // Net receivable = net_amount + all exclusive taxes (inclusive taxes are already inside net_amount)
    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) {
        const baseNetAmount = window.currentNetAmount
            || parseFloat(document.getElementById('netAmount')?.textContent)
            || 0;
        const netReceivable = baseNetAmount + totalExclusiveTax;
        netReceivableEl.textContent = netReceivable.toFixed(2);
        console.log('Net Receivable updated:', netReceivable);
    }

    return totalInvoiceTax;
}

/**
 * Clear all invoice-level tax columns
 */
function clearInvoiceLevelTaxes() {
    window.invoiceLevelTaxRegimes = [];
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (container) container.innerHTML = '';

    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) netReceivableEl.textContent = '0.00';
}

// Export functions
window.loadInvoiceLevelTaxRegimes = loadInvoiceLevelTaxRegimes;
window.calculateInvoiceLevelTaxes = calculateInvoiceLevelTaxes;
window.clearInvoiceLevelTaxes = clearInvoiceLevelTaxes;
