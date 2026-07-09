// Dynamic Invoice-Level Tax System for Purchase
window.invoiceLevelTaxRegimes = [];

/**
 * Load invoice-level tax regimes for a supplier
 * @param {number} supplierId - Supplier ID
 * @param {number} companyId - Company ID (optional, for country filtering)
 */
async function loadInvoiceLevelTaxRegimes(supplierId, companyId = null) {
    if (!supplierId) {
        clearInvoiceLevelTaxes();
        return;
    }

    try {
        let url = `../../../../server/api/purchase/purchase_invoice/get-invoice-level-taxes.php?supplier_id=${supplierId}`;
        if (companyId) {
            url += `&company_id=${companyId}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.data && data.data.length > 0) {
            window.invoiceLevelTaxRegimes = data.data;
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
 */
function renderInvoiceLevelTaxColumns() {
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (!container) return;

    container.innerHTML = '';

    window.invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercent = parseFloat(regime.rate_percentage) || 0;
        
        // Tax % Column
        const percentItem = document.createElement('div');
        percentItem.className = 'summary-item';
        percentItem.innerHTML = `
            <span class="summary-label">${regime.regime_name} %</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_percent">${ratePercent.toFixed(3)}</span>
        `;
        container.appendChild(percentItem);

        // Tax Amount Column
        const amountItem = document.createElement('div');
        amountItem.className = 'summary-item';
        amountItem.innerHTML = `
            <span class="summary-label">${regime.regime_name} Amt</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_amount">0.00</span>
        `;
        container.appendChild(amountItem);
    });
    
    // Trigger calculation after rendering
    setTimeout(() => calculateInvoiceLevelTaxes(), 10);
}

/**
 * Calculate invoice-level taxes
 * Formula: Net Amount × (Rate % / 100) = Tax Amount
 */
function calculateInvoiceLevelTaxes() {
    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) {
        return 0;
    }

    const netAmountEl = document.getElementById('netAmount');
    if (!netAmountEl) return 0;
    
    const netAmount = parseFloat(netAmountEl.textContent) || 0;

    let totalInvoiceTax = 0;

    window.invoiceLevelTaxRegimes.forEach(regime => {
        const ratePercent = parseFloat(regime.rate_percentage) || 0;
        const taxAmount = netAmount * (ratePercent / 100);

        const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);
        if (amountEl) {
            amountEl.textContent = taxAmount.toFixed(3);
        }

        totalInvoiceTax += taxAmount;
    });

    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) {
        const netReceivable = netAmount + totalInvoiceTax;
        netReceivableEl.textContent = netReceivable.toFixed(3);
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

    const netAmountEl = document.getElementById('netAmount');
    const netAmount = parseFloat(netAmountEl?.textContent) || 0;
    
    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) netReceivableEl.textContent = netAmount.toFixed(3);
}

// Export functions
window.loadInvoiceLevelTaxRegimes = loadInvoiceLevelTaxRegimes;
window.calculateInvoiceLevelTaxes = calculateInvoiceLevelTaxes;
window.clearInvoiceLevelTaxes = clearInvoiceLevelTaxes;
