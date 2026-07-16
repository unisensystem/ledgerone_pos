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

        // Tax % Column (editable)
        const percentItem = document.createElement('div');
        percentItem.className = 'summary-item';
        const percentInput = document.createElement('input');
        percentInput.type = 'number';
        percentInput.className = 'table-input';
        percentInput.min = '0';
        percentInput.step = '0.001';
        percentInput.value = ratePercent.toFixed(3);
        percentInput.id = `invoiceTax_${regime.id}_percent`;
        percentInput.addEventListener('input', function() {
            const netAmount = parseFloat(document.getElementById('netAmount')?.textContent) || 0;
            const pct = parseFloat(this.value) || 0;
            const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);
            if (amountEl) amountEl.value = (netAmount * pct / 100).toFixed(3);
            recalcNetReceivable();
        });
        percentItem.innerHTML = `<span class="summary-label">${regime.regime_name} %</span>`;
        percentItem.appendChild(percentInput);
        container.appendChild(percentItem);

        // Tax Amount Column (editable)
        const amountItem = document.createElement('div');
        amountItem.className = 'summary-item';
        const amountInput = document.createElement('input');
        amountInput.type = 'number';
        amountInput.className = 'table-input';
        amountInput.min = '0';
        amountInput.step = '0.001';
        amountInput.value = '0.00';
        amountInput.id = `invoiceTax_${regime.id}_amount`;
        amountInput.addEventListener('input', function() {
            const netAmount = parseFloat(document.getElementById('netAmount')?.textContent) || 0;
            const amt = parseFloat(this.value) || 0;
            const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
            if (percentEl) percentEl.value = netAmount > 0 ? (amt / netAmount * 100).toFixed(3) : '0.000';
            recalcNetReceivable();
        });
        amountItem.innerHTML = `<span class="summary-label">${regime.regime_name} Amt</span>`;
        amountItem.appendChild(amountInput);
        container.appendChild(amountItem);
    });

    function recalcNetReceivable() {
        const netAmount = parseFloat(document.getElementById('netAmount')?.textContent) || 0;
        let totalTax = 0;
        window.invoiceLevelTaxRegimes.forEach(r => {
            totalTax += parseFloat(document.getElementById(`invoiceTax_${r.id}_amount`)?.value) || 0;
        });
        const netReceivableEl = document.getElementById('netReceivable');
        if (netReceivableEl) netReceivableEl.textContent = (netAmount + totalTax).toFixed(3);
    }
    
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
            amountEl.value = taxAmount.toFixed(3);
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
