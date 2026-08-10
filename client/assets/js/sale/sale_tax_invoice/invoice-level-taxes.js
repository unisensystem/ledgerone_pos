// Invoice-Level Tax System - Proper Implementation
let invoiceLevelTaxRegimes = [];

/**
 * Load invoice-level tax regimes for a customer
 * Fetches from tax_regimes where application_level = 'invoice'
 * Filters tax_rates based on customer_type_id and is_filer
 */
async function loadInvoiceLevelTaxRegimes(customerId) {
    if (!customerId) {
        clearInvoiceLevelTaxes();
        return;
    }

    try {
        const response = await fetch(`../../../../server/api/sale/sale_tax_invoice/get-invoice-level-taxes.php?customer_id=${customerId}`);
        const data = await response.json();

        if (data.success && data.data && data.data.length > 0) {
            invoiceLevelTaxRegimes = data.data;
            renderInvoiceLevelTaxColumns();
            calculateInvoiceLevelTaxes();
        } else {
            clearInvoiceLevelTaxes();
        }
    } catch (error) {
        console.error('Error loading invoice-level taxes:', error);
        clearInvoiceLevelTaxes();
    }
}

/**
 * Render dynamic tax columns in Invoice Summary
 * Creates two columns per regime: Tax % and Tax Amount
 */
function renderInvoiceLevelTaxColumns() {
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (!container) return;

    container.innerHTML = '';

    invoiceLevelTaxRegimes.forEach(regime => {
        // Tax % Column
        const percentItem = document.createElement('div');
        percentItem.className = 'summary-item invoice-tax-item';
        percentItem.dataset.taxRegimeId = regime.id;
        percentItem.innerHTML = `
            <span class="summary-label">${regime.regime_name} %</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_percent">0.00</span>
        `;
        container.appendChild(percentItem);

        // Tax Amount Column
        const amountItem = document.createElement('div');
        amountItem.className = 'summary-item invoice-tax-item';
        amountItem.dataset.taxRegimeId = regime.id;
        amountItem.innerHTML = `
            <span class="summary-label">${regime.regime_name} Amount</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_amount">0.00</span>
        `;
        container.appendChild(amountItem);
    });
}

/**
 * Calculate invoice-level taxes
 * Formula: Net Amount × (Rate % / 100) = Tax Amount
 */
function calculateInvoiceLevelTaxes() {
    const netAmountEl = document.getElementById('netAmount');
    const netAmount = parseFloat(netAmountEl?.textContent) || 0;

    let totalInvoiceTax = 0;

    invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercent = parseFloat(regime.rate_percentage) || 0;
        const taxAmount = netAmount * (ratePercent / 100);

        // Update display
        const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
        const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);

        if (percentEl) percentEl.textContent = ratePercent.toFixed(2);
        if (amountEl) amountEl.textContent = taxAmount.toFixed(2);

        totalInvoiceTax += taxAmount;
    });

    // Update Net Receivable: Net Amount - Total Invoice-Level Taxes
    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) {
        const netReceivable = netAmount - totalInvoiceTax;
        netReceivableEl.textContent = netReceivable.toFixed(2);
    }

    return totalInvoiceTax;
}

/**
 * Clear all invoice-level tax columns
 */
function clearInvoiceLevelTaxes() {
    invoiceLevelTaxRegimes = [];
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (container) container.innerHTML = '';

    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) netReceivableEl.textContent = '0.00';
}

// Export functions
window.loadInvoiceLevelTaxRegimes = loadInvoiceLevelTaxRegimes;
window.calculateInvoiceLevelTaxes = calculateInvoiceLevelTaxes;
window.clearInvoiceLevelTaxes = clearInvoiceLevelTaxes;
