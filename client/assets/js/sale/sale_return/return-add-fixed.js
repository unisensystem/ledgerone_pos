// Fixed version - replace the original file with this content
// The main fixes:
// 1. Changed API endpoint from return-edit.php to pos-edit.php for loading sale invoice data
// 2. Added null checks for DOM elements before accessing properties
// 3. Fixed loadPriceHistoryForAllRows to properly reference itemsTable

// Copy all content from return-add.js and apply these specific fixes:

// Line ~290: Fix API endpoint
// FROM: const response = await fetch(`../../../../server/api/sale/sale_return/return-edit.php?id=${invoiceId}`);
// TO: const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${invoiceId}`);

// Line ~527: Add null checks
// FROM:
//     document.getElementById('previousBalance').value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
//     const address = e.target.getAttribute('data-address');
//     document.getElementById('customerAddress').textContent = address || 'No address available';
//     loadPriceHistoryForAllRows();
// TO:
//     const prevBalanceEl = document.getElementById('previousBalance');
//     if (prevBalanceEl) {
//         prevBalanceEl.value = `${balance >= 0 ? 'Dr' : 'Cr'} ${Math.abs(balance).toFixed(2)}`;
//     }
//     const addressEl = document.getElementById('customerAddress');
//     if (addressEl) {
//         const address = e.target.getAttribute('data-address');
//         addressEl.textContent = address || 'No address available';
//     }
//     if (typeof loadPriceHistoryForAllRows === 'function') {
//         loadPriceHistoryForAllRows();
//     }

// Line ~1846: Fix loadPriceHistoryForAllRows function
// FROM:
// function loadPriceHistoryForAllRows() {
//     const rows = itemsTable.rows;
//     for (let i = 0; i < rows.length; i++) {
//         const productId = rows[i].cells[1].querySelector('.item-code').value;
//         if (productId) {
//             loadPriceHistory(productId);
//             break;
//         }
//     }
// }
// TO:
// function loadPriceHistoryForAllRows() {
//     const itemsTable = document.getElementById('itemsTable');
//     if (!itemsTable) return;
//     const tbody = itemsTable.getElementsByTagName('tbody')[0];
//     if (!tbody) return;
//     const rows = tbody.rows;
//     for (let i = 0; i < rows.length; i++) {
//         const codeInput = rows[i].cells[1]?.querySelector('.item-code');
//         if (codeInput && codeInput.value) {
//             loadPriceHistory(codeInput.value);
//             break;
//         }
//     }
// }

// Line ~1921: Add null checks for salesOfficer
// FROM:
// document.getElementById('salesOfficer').addEventListener('change', function() {
//     if (this.value) {
//         localStorage.setItem('lastSelectedSalesOfficer', this.value);
//     }
// });
// TO:
// const salesOfficerEl = document.getElementById('salesOfficer');
// if (salesOfficerEl) {
//     salesOfficerEl.addEventListener('change', function() {
//         if (this.value) {
//             localStorage.setItem('lastSelectedSalesOfficer', this.value);
//         }
//     });
// }

// Line ~1927+: Add null checks for field settings buttons
// Wrap all field settings event listeners with null checks

// Line ~1990+: Add null checks for mode change listeners
// Wrap all mode change event listeners with null checks
