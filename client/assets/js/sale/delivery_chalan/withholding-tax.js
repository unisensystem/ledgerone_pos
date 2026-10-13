// Withholding Tax (AIT) Handler for POS Invoice

// Update invoice summary with withholding tax
function updateInvoiceSummaryWithAIT() {
    const netAmount = parseFloat(document.getElementById('netAmount').textContent) || 0;
    const withholdingTaxPercent = parseFloat(document.getElementById('withholdingTaxPercent').value) || 0;
    const withholdingTaxAmount = netAmount * (withholdingTaxPercent / 100);
    const netReceivable = netAmount - withholdingTaxAmount;
    
    document.getElementById('withholdingTaxAmount').textContent = withholdingTaxAmount.toFixed(2);
    document.getElementById('netReceivable').textContent = netReceivable.toFixed(2);
}

// Customer selection handler with tax info
function handleCustomerTaxInfo(customer) {
    if (!customer) return;
    
    // Set withholding tax percentage
    const withholdingTaxPercent = parseFloat(customer.advance_income_tax_percentage || 0);
    document.getElementById('withholdingTaxPercent').value = withholdingTaxPercent.toFixed(2);
    
    // Handle non-filer customers (is_filer = 0 means higher tax rate)
    if (customer.is_filer == 0 && withholdingTaxPercent > 0) {
        showNotification('Customer is non-filer - Higher AIT rate applied', 'warning');
    }
    
    // Show tax registration status
    if (customer.is_sales_tax_registered == 1) {
        console.log(`Customer is sales tax registered: NTN ${customer.ntn}, STRN ${customer.strn}`);
    }
    
    updateInvoiceSummaryWithAIT();
}

// Show notification helper
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'warning' ? 'var(--warning)' : 'var(--success)'};
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

// Export functions
window.updateInvoiceSummaryWithAIT = updateInvoiceSummaryWithAIT;
window.handleCustomerTaxInfo = handleCustomerTaxInfo;
