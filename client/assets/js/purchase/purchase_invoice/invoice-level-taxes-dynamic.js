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
        return Promise.resolve();
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
            const baseNetAmount = parseFloat(this.closest('.summary-grid')?.dataset.baseNet) || 0;
            const pct = parseFloat(this.value) || 0;
            const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);
            if (amountEl) amountEl.value = (baseNetAmount * pct / 100).toFixed(3);
            recalcNetAmount();
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
        amountInput.value = '0.000';
        amountInput.id = `invoiceTax_${regime.id}_amount`;
        amountInput.addEventListener('input', function() {
            const baseNetAmount = parseFloat(this.closest('.summary-grid')?.dataset.baseNet) || 0;
            const amt = parseFloat(this.value) || 0;
            const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
            if (percentEl) percentEl.value = baseNetAmount > 0 ? (amt / baseNetAmount * 100).toFixed(3) : '0.000';
            recalcNetAmount();
        });
        amountItem.innerHTML = `<span class="summary-label">${regime.regime_name} Amt</span>`;
        amountItem.appendChild(amountInput);
        container.appendChild(amountItem);
    });

    // calculation is triggered by loadInvoiceLevelTaxRegimes after render
}

/**
 * Calculate invoice-level taxes
 * Formula: Net Amount × (Rate % / 100) = Tax Amount
 */
function calculateInvoiceLevelTaxes() {
    const netAmountEl = document.getElementById('netAmount');
    if (!netAmountEl) return 0;

    const summaryGrid = netAmountEl.closest('.summary-grid');
    const baseNet = parseFloat(summaryGrid?.dataset.baseNet) || parseFloat(netAmountEl.textContent) || 0;

    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) {
        netAmountEl.textContent = baseNet.toFixed(3);
        return 0;
    }

    let totalInvoiceTax = 0;

    window.invoiceLevelTaxRegimes.forEach(regime => {
        const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
        const amountEl  = document.getElementById(`invoiceTax_${regime.id}_amount`);
        const ratePercent = parseFloat(percentEl?.value ?? regime.rate_percentage) || 0;
        const taxAmount = baseNet * (ratePercent / 100);
        if (amountEl) amountEl.value = taxAmount.toFixed(3);
        totalInvoiceTax += taxAmount;
    });

    netAmountEl.textContent = (baseNet + totalInvoiceTax).toFixed(3);
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
    const summaryGrid = netAmountEl?.closest('.summary-grid');
    const baseNet = parseFloat(summaryGrid?.dataset.baseNet) || parseFloat(netAmountEl?.textContent) || 0;
    if (netAmountEl) netAmountEl.textContent = baseNet.toFixed(3);
}

/**
 * Recalculate Net Amount from baseNet + all current tax amount inputs
 * Called when user manually edits a tax % or amount field
 */
function recalcNetAmount() {
    const netAmountEl = document.getElementById('netAmount');
    if (!netAmountEl) return;
    const summaryGrid = netAmountEl.closest('.summary-grid');
    const baseNet = parseFloat(summaryGrid?.dataset.baseNet) || 0;

    let totalTax = 0;
    window.invoiceLevelTaxRegimes.forEach(regime => {
        const amountEl = document.getElementById(`invoiceTax_${regime.id}_amount`);
        totalTax += parseFloat(amountEl?.value) || 0;
    });

    netAmountEl.textContent = (baseNet + totalTax).toFixed(3);
}

// Export functions
window.loadInvoiceLevelTaxRegimes = loadInvoiceLevelTaxRegimes;
window.calculateInvoiceLevelTaxes = calculateInvoiceLevelTaxes;
window.clearInvoiceLevelTaxes = clearInvoiceLevelTaxes;

/**
 * Collect current invoice-level tax data for saving
 * Returns array of { taxRegimeId, taxName, ratePercentage, baseAmount, taxAmount }
 */
window.getInvoiceTaxData = function() {
    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) return [];
    const summaryGrid = document.getElementById('netAmount')?.closest('.summary-grid');
    const baseNet = parseFloat(summaryGrid?.dataset.baseNet) || 0;
    return window.invoiceLevelTaxRegimes.map(regime => {
        const pct = parseFloat(document.getElementById(`invoiceTax_${regime.id}_percent`)?.value) || 0;
        const amt = parseFloat(document.getElementById(`invoiceTax_${regime.id}_amount`)?.value) || 0;
        return {
            taxRegimeId:    regime.id,
            taxName:        regime.regime_name,
            ratePercentage: pct,
            baseAmount:     baseNet,
            taxAmount:      amt
        };
    }).filter(t => t.taxAmount > 0 || t.ratePercentage > 0);
};

/**
 * Restore saved invoice taxes in edit mode
 * savedTaxes: array from DB { tax_regime_id, tax_name, rate_percentage, base_amount, tax_amount }
 */
window.restoreInvoiceTaxes = function(savedTaxes) {
    savedTaxes = savedTaxes || [];
    const savedByRegime = {};
    savedTaxes.forEach(tax => { savedByRegime[tax.tax_regime_id] = tax; });

    // For every currently-rendered regime column (loaded from the supplier's default
    // tax config), set it to the saved value, or explicitly 0 if this invoice has no
    // saved tax for it — an empty savedTaxes means the user removed the tax, not that
    // the supplier default should be left in place.
    (window.invoiceLevelTaxRegimes || []).forEach(regime => {
        const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
        if (!percentEl) return;
        const saved = savedByRegime[regime.id];
        percentEl.value = saved ? parseFloat(saved.rate_percentage).toFixed(3) : '0.000';
    });
    calculateInvoiceLevelTaxes();
};
