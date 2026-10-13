// Add this code to the end of purchase-add.js, after the supplier selection code

// Modify the supplier selection in initSearchableDropdown to trigger invoice-level taxes
// Find this section in the original code and update it:

// OLD CODE (around line 300-320):
// if (hiddenInputId === 'supplierCode') {
//     const balance = parseFloat(e.target.getAttribute('data-balance'));
//     document.getElementById('previousBalance').value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
//     
//     // Load sub accounts for selected supplier
//     loadSubAccounts(value);
// }

// NEW CODE - Add this after loadSubAccounts:
// if (hiddenInputId === 'supplierCode') {
//     const balance = parseFloat(e.target.getAttribute('data-balance'));
//     document.getElementById('previousBalance').value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
//     
//     // Load sub accounts for selected supplier
//     loadSubAccounts(value);
//     
//     // Load invoice-level taxes for selected supplier
//     const companyId = document.getElementById('company').value;
//     if (window.loadInvoiceLevelTaxRegimes) {
//         console.log('Triggering loadInvoiceLevelTaxRegimes for supplier:', value);
//         window.loadInvoiceLevelTaxRegimes(value, companyId);
//     }
// }
