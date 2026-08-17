// Collect invoice-level taxes from DOM
function collectInvoiceTaxes() {
    const taxes = [];
    
    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) {
        console.log('collectInvoiceTaxes: No tax regimes found');
        return taxes;
    }
    
    // Ensure taxes are calculated before collecting
    if (typeof calculateInvoiceLevelTaxes === 'function') {
        console.log('collectInvoiceTaxes: Recalculating taxes before collection');
        calculateInvoiceLevelTaxes();
    }
    
    const finalNetAmount = parseFloat(document.getElementById('netAmount')?.textContent) || 0;
    const baseAmounts = window.invoiceTaxBaseAmounts || {};

    console.log('collectInvoiceTaxes: netAmount =', finalNetAmount);

    window.invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercentage = parseFloat(regime.rate_percentage) || 0;
        const baseAmount = baseAmounts[regime.id] ?? finalNetAmount;

        const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);
        let taxAmount = 0;

        if (amountEl) {
            taxAmount = parseFloat(amountEl.textContent) || 0;
            console.log(`collectInvoiceTaxes: regime ${regime.id} (${regime.regime_name}): amount=${taxAmount} base=${baseAmount}`);
        } else {
            taxAmount = baseAmount * (ratePercentage / 100);
            console.log(`collectInvoiceTaxes: regime ${regime.id} (${regime.regime_name}): calculated=${taxAmount} base=${baseAmount}`);
        }

        if (taxAmount > 0 || ratePercentage > 0) {
            taxes.push({
                taxRegimeId: regime.id,
                taxRateId: null,
                taxName: regime.regime_name,
                ratePercentage: ratePercentage,
                baseAmount: baseAmount,
                taxAmount: taxAmount
            });
        }
    });
    
    console.log('collectInvoiceTaxes: Final collected taxes:', taxes);
    return taxes;
}
