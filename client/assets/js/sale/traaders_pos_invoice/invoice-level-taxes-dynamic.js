// Dynamic Invoice-Level Tax System
window.invoiceLevelTaxRegimes = [];

function escTaxHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

// Taxation is opt-in per page (see pos-add.js): ON by default on med_pos_invoice,
// OFF by default on the regular POS invoice. Falls back to a self-contained copy
// of the same check in case this file loads before pos-add.js defines it.
function isTaxationEnabledForInvoiceTax() {
    if (typeof isTaxationEnabled === 'function') return isTaxationEnabled();
    const stored = localStorage.getItem('enableTaxation');
    if (stored === 'true') return true;
    if (stored === 'false') return false;
    return window.location.pathname.includes('med_pos_invoice');
}

/**
 * Load invoice-level tax regimes for a customer
 * @param {number} customerId - Customer ID
 * @param {number} companyId - Company ID (optional, for country filtering)
 */
async function loadInvoiceLevelTaxRegimes(customerId, companyId = null) {
    if (!isTaxationEnabledForInvoiceTax()) {
        clearInvoiceLevelTaxes();
        return;
    }

    if (!customerId) {
        clearInvoiceLevelTaxes();
        return;
    }

    try {
        let url = `../../../../server/api/sale/traaders_pos_invoice/get-invoice-level-taxes.php?customer_id=${customerId}`;
        if (companyId) {
            url += `&company_id=${companyId}`;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.data && data.data.length > 0) {
            window.invoiceLevelTaxRegimes = data.data;
            renderInvoiceLevelTaxColumns();

            // Regimes just arrived asynchronously — any earlier netAmount/remainingBalance
            // calculation (triggered before this fetch resolved) ran without them. Re-run the
            // summary calculation now so the invoice reflects tax immediately, not only on the
            // next keystroke. Deferred via setTimeout(0) so any in-flight calculation settles first.
            const itemsTableElement = document.getElementById('itemsTable');
            const tbody = itemsTableElement?.getElementsByTagName('tbody')[0];
            if (tbody && tbody.rows && tbody.rows.length > 0) {
                setTimeout(() => {
                    if (typeof updateInvoiceSummaryDynamic === 'function') {
                        updateInvoiceSummaryDynamic();
                    } else if (typeof updateInvoiceSummary === 'function') {
                        updateInvoiceSummary();
                    }
                }, 0);
            }
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

        const safeRegimeName = escTaxHtml(regime.regime_name);

        // Tax % Column
        const percentItem = document.createElement('div');
        percentItem.className = 'summary-item invoice-tax-item';
        percentItem.dataset.taxRegimeId = regime.id;
        percentItem.innerHTML = `
            <span class="summary-label">${safeRegimeName} %</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_percent">${ratePercent.toFixed(3)}</span>
        `;
        container.appendChild(percentItem);

        // Tax Amount Column
        const amountItem = document.createElement('div');
        amountItem.className = 'summary-item invoice-tax-item';
        amountItem.dataset.taxRegimeId = regime.id;
        amountItem.innerHTML = `
            <span class="summary-label">${safeRegimeName} Amount</span>
            <span class="summary-value" id="invoiceTax_${regime.id}_amount">0.000</span>
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
 *   remaining_balance    → interim net receivable minus amount paid (set during phase 2)
 */
function resolveInvoiceBase(regime) {
    switch (regime.tax_base) {
        case 'total_bill':
            return window.currentTotalBill ?? 0;
        case 'value_excl_sales_tax':
            return window.currentValueExclSalesTax ?? 0;
        case 'remaining_balance':
            return window.currentRemainingBalance ?? 0;
        case 'net_amount':
        default:
            return window.currentNetAmount
                ?? parseFloat(document.getElementById('netAmount')?.textContent)
                ?? 0;
    }
}

/**
 * Evaluate a formula_template string with invoice variables substituted.
 * Supported tokens: {total_bill}, {value_excl_sales_tax}, {net_amount}, {remaining_balance}, {rate}
 * Falls back to simple base × rate / 100 when template is absent.
 *
 * @param {string}  formulaTemplate
 * @param {number}  base            - resolved base amount (also substituted for item-level tokens)
 * @param {number}  ratePercent
 * @param {boolean} isTaxInclusive
 * @param {number|null} overrideBase - when set, ALL invoice-total tokens resolve to this value
 *                                     (used by phase-2 so remaining_balance tax_base wins over
 *                                      whatever token the formula happens to reference)
 */
function applyInvoiceFormula(formulaTemplate, base, ratePercent, isTaxInclusive, overrideBase = null) {
    if (!formulaTemplate || formulaTemplate.trim() === '' || formulaTemplate === '0') {
        return isTaxInclusive
            ? (ratePercent > 0 ? base * ratePercent / (100 + ratePercent) : 0)
            : base * (ratePercent / 100);
    }

    const totalBillVal = overrideBase !== null ? overrideBase : (window.currentTotalBill || 0);
    const valueExclVal = overrideBase !== null ? overrideBase : (window.currentValueExclSalesTax || 0);
    const netAmountVal = overrideBase !== null ? overrideBase : (window.currentNetAmount || 0);
    const remainingVal = overrideBase !== null ? overrideBase : (window.currentRemainingBalance || 0);

    let expr = formulaTemplate
        .replace(/{total_bill}/g,           totalBillVal)
        .replace(/{value_excl_sales_tax}/g,  valueExclVal)
        .replace(/{net_amount}/g,            netAmountVal)
        .replace(/{remaining_balance}/g,     remainingVal)
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
 *
 * Two-phase approach for remaining_balance:
 *   Phase 1 — all regimes except remaining_balance → establishes interim net receivable
 *   Phase 2 — remaining_balance regimes → tax base = interim_netReceivable - amountPaid
 */
function calculateInvoiceLevelTaxes() {
    if (!isTaxationEnabledForInvoiceTax()) {
        // Taxation toggled off — behave as if no regimes are configured, so
        // unchecking the setting immediately stops invoice-level tax from
        // being folded into Net Receivable (no stale tax lingering).
        clearInvoiceLevelTaxes();
        return 0;
    }

    if (!window.invoiceLevelTaxRegimes || window.invoiceLevelTaxRegimes.length === 0) {
        const netReceivableEl = document.getElementById('netReceivable');
        if (netReceivableEl) {
            const baseNetAmount = window.currentNetAmount
                ?? parseFloat(document.getElementById('netAmount')?.textContent)
                ?? 0;
            netReceivableEl.textContent = baseNetAmount.toFixed(3);
        }
        return 0;
    }

    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (container && container.children.length === 0) {
        renderInvoiceLevelTaxColumns();
    }

    // Track the base amount used per regime so it can be saved correctly
    window.invoiceTaxBaseAmounts = {};

    const baseNetAmount = window.currentNetAmount
        ?? parseFloat(document.getElementById('netAmount')?.textContent)
        ?? 0;

    // ── Phase 1: all regimes except remaining_balance ──────────────────────
    let phase1ExclusiveTax = 0;

    window.invoiceLevelTaxRegimes
        .filter(r => r.tax_base !== 'remaining_balance')
        .forEach(regime => {
            const ratePercent    = parseFloat(regime.rate_percentage) || 0;
            const isTaxInclusive = regime.is_tax_inclusive == 1;
            const base           = resolveInvoiceBase(regime);

            window.invoiceTaxBaseAmounts[regime.id] = base;

            const taxAmount = applyInvoiceFormula(
                regime.formula_template || '',
                base,
                ratePercent,
                isTaxInclusive
            );

            const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
            const amountEl  = document.getElementById(`invoiceTax_${regime.id}_amount`);
            if (percentEl) percentEl.textContent = ratePercent.toFixed(3);
            if (amountEl)  amountEl.textContent  = taxAmount.toFixed(3);

            if (!isTaxInclusive) phase1ExclusiveTax += taxAmount;
        });

    // Derive remaining balance from interim net receivable so phase 2 can use it.
    const interimNetReceivable = baseNetAmount + phase1ExclusiveTax;
    window.invoiceLevelInterimNetReceivable = interimNetReceivable;
    const amountPaid = parseFloat(document.getElementById('amountPaid')?.value) || 0;
    window.currentRemainingBalance = Math.max(0, interimNetReceivable - amountPaid);

    // ── Phase 2: remaining_balance regimes ────────────────────────────────
    let phase2ExclusiveTax = 0;

    window.invoiceLevelTaxRegimes
        .filter(r => r.tax_base === 'remaining_balance')
        .forEach(regime => {
            const ratePercent    = parseFloat(regime.rate_percentage) || 0;
            const isTaxInclusive = regime.is_tax_inclusive == 1;
            const base           = window.currentRemainingBalance;

            window.invoiceTaxBaseAmounts[regime.id] = base;

            const taxAmount = applyInvoiceFormula(
                regime.formula_template || '',
                base,
                ratePercent,
                isTaxInclusive,
                base  // overrideBase: all invoice-total tokens resolve to remaining_balance
            );

            const percentEl = document.getElementById(`invoiceTax_${regime.id}_percent`);
            const amountEl  = document.getElementById(`invoiceTax_${regime.id}_amount`);
            if (percentEl) percentEl.textContent = ratePercent.toFixed(3);
            if (amountEl)  amountEl.textContent  = taxAmount.toFixed(3);

            if (!isTaxInclusive) phase2ExclusiveTax += taxAmount;
        });

    // Update Net Amount and Net Receivable with all exclusive taxes added
    const totalExclusiveTax = phase1ExclusiveTax + phase2ExclusiveTax;
    const finalAmount = (baseNetAmount + totalExclusiveTax).toFixed(3);

    const netAmountEl = document.getElementById('netAmount');
    if (netAmountEl) netAmountEl.textContent = finalAmount;

    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) netReceivableEl.textContent = finalAmount;

    return totalExclusiveTax;
}

/**
 * Clear all invoice-level tax columns
 */
function clearInvoiceLevelTaxes() {
    window.invoiceLevelTaxRegimes = [];
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (container) container.innerHTML = '';

    const netReceivableEl = document.getElementById('netReceivable');
    if (netReceivableEl) {
        const baseNetAmount = window.currentNetAmount
            ?? parseFloat(document.getElementById('netAmount')?.textContent)
            ?? 0;
        netReceivableEl.textContent = baseNetAmount.toFixed(3);
    }
}

// Export functions
window.loadInvoiceLevelTaxRegimes = loadInvoiceLevelTaxRegimes;
window.calculateInvoiceLevelTaxes = calculateInvoiceLevelTaxes;
window.clearInvoiceLevelTaxes = clearInvoiceLevelTaxes;
