// Invoice Settings with Product Filtering
// Handles loading, saving, and applying product filtering settings

function loadInvoiceSettingsWithFiltering() {
    // Load scheme preference
    const defaultScheme = localStorage.getItem('defaultScheme') || 'sale_on_tp';
    const schemeRadio = document.querySelector(`input[name="defaultScheme"][value="${defaultScheme}"]`);
    if (schemeRadio) schemeRadio.checked = true;
    
    // Load sale price preference
    const salePriceSetting = localStorage.getItem('salePriceSetting') || 'trade_price';
    const salePriceRadio = document.querySelector(`input[name="salePriceSetting"][value="${salePriceSetting}"]`);
    if (salePriceRadio) salePriceRadio.checked = true;
    
    // Load product filtering mode
    const productFilteringMode = localStorage.getItem('productFilteringMode') || 'showAll';
    const filterRadio = document.querySelector(`input[name="productFilteringMode"][value="${productFilteringMode}"]`);
    if (filterRadio) filterRadio.checked = true;
    
    // Load other settings
    document.getElementById('enableTradeOfferAmount').checked = localStorage.getItem('enableTradeOfferAmount') === 'true';
    document.getElementById('enableFOC').checked = localStorage.getItem('enableFOC') === 'true';
    document.getElementById('enableCashDiscountPercent').checked = localStorage.getItem('enableCashDiscountPercent') === 'true';
    document.getElementById('enableCashDiscountAmount').checked = localStorage.getItem('enableCashDiscountAmount') === 'true';
    document.getElementById('enableInvoiceCashDiscountPercent').checked = localStorage.getItem('enableInvoiceCashDiscountPercent') === 'true';
    document.getElementById('enableInvoiceCashDiscountAmount').checked = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';
    document.getElementById('enableShippingFees').checked = localStorage.getItem('enableShippingFees') === 'true';
    document.getElementById('enablePrintQRCode').checked = localStorage.getItem('enablePrintQRCode') === 'true';
    document.getElementById('enableAmountPaidPaymentMethod').checked = localStorage.getItem('enableAmountPaidPaymentMethod') === 'true';
}

function saveInvoiceSettingsWithFiltering() {
    // Save scheme preference
    const selectedScheme = document.querySelector('input[name="defaultScheme"]:checked')?.value || 'sale_on_tp';
    localStorage.setItem('defaultScheme', selectedScheme);
    
    // Save sale price preference
    const selectedSalePrice = document.querySelector('input[name="salePriceSetting"]:checked')?.value || 'trade_price';
    localStorage.setItem('salePriceSetting', selectedSalePrice);
    
    // Save product filtering mode
    const selectedFilteringMode = document.querySelector('input[name="productFilteringMode"]:checked')?.value || 'showAll';
    localStorage.setItem('productFilteringMode', selectedFilteringMode);
    
    // Update the global filtering mode
    if (typeof setProductFilteringMode === 'function') {
        setProductFilteringMode(selectedFilteringMode);
    }
    
    // Save other settings
    localStorage.setItem('enableTradeOfferAmount', document.getElementById('enableTradeOfferAmount').checked);
    localStorage.setItem('enableFOC', document.getElementById('enableFOC').checked);
    localStorage.setItem('enableCashDiscountPercent', document.getElementById('enableCashDiscountPercent').checked);
    localStorage.setItem('enableCashDiscountAmount', document.getElementById('enableCashDiscountAmount').checked);
    localStorage.setItem('enableInvoiceCashDiscountPercent', document.getElementById('enableInvoiceCashDiscountPercent').checked);
    localStorage.setItem('enableInvoiceCashDiscountAmount', document.getElementById('enableInvoiceCashDiscountAmount').checked);
    localStorage.setItem('enableShippingFees', document.getElementById('enableShippingFees').checked);
    localStorage.setItem('enablePrintQRCode', document.getElementById('enablePrintQRCode').checked);
    localStorage.setItem('enableAmountPaidPaymentMethod', document.getElementById('enableAmountPaidPaymentMethod').checked);
    
    alert('Invoice settings saved successfully!');
}

// Override the existing loadInvoiceSettings function
const originalLoadInvoiceSettings = window.loadInvoiceSettings;
window.loadInvoiceSettings = function() {
    loadInvoiceSettingsWithFiltering();
};

// Override the existing saveInvoiceSettings function
const originalSaveInvoiceSettings = window.saveInvoiceSettings;
window.saveInvoiceSettings = function() {
    saveInvoiceSettingsWithFiltering();
};
