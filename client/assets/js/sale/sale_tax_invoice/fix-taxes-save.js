// Override collectInvoiceTaxes to read from correct DOM elements
const originalCollectInvoiceTaxes = window.collectInvoiceTaxes || function() { return []; };

window.collectInvoiceTaxes = function() {
    const taxes = [];
    
    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) {
        console.log('collectInvoiceTaxes: No tax regimes found');
        return taxes;
    }
    
    // Recalculate taxes first
    if (typeof calculateInvoiceLevelTaxes === 'function') {
        calculateInvoiceLevelTaxes();
    }
    
    // Get net amount from DOM
    const netAmountEl = document.getElementById('netAmount');
    const netAmount = parseFloat(netAmountEl?.textContent) || 0;
    
    console.log('collectInvoiceTaxes: Net Amount =', netAmount);
    
    // Collect each tax regime
    window.invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercentage = parseFloat(regime.rate_percentage) || 0;
        const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);
        const taxAmount = parseFloat(amountEl?.textContent) || (netAmount * (ratePercentage / 100));
        
        console.log(`Tax: ${regime.regime_name} = ${taxAmount}`);
        
        if (taxAmount > 0 || ratePercentage > 0) {
            taxes.push({
                taxRegimeId: regime.id,
                taxRateId: null,
                taxName: regime.regime_name,
                ratePercentage: ratePercentage,
                baseAmount: netAmount,
                taxAmount: taxAmount
            });
        }
    });
    
    console.log('collectInvoiceTaxes: Collected', taxes.length, 'taxes');
    return taxes;
};
