// PATCH: Add this code after line where FOC Qty cell (cell12) is created in addRow() function
// Insert after: cell12.appendChild(focQty);

// Inventory Status (dropdown) - NEW
const cell13Status = row.insertCell(13);
const statusSelect = document.createElement('select');
statusSelect.className = 'table-input';
statusSelect.innerHTML = `
    <option value="sellable" selected>Sellable</option>
    <option value="damaged">Damaged</option>
`;
statusSelect.tabIndex = -1;
cell13Status.appendChild(statusSelect);

// IMPORTANT: Update all subsequent cell indices by +1
// Old cell13 (Net Amount) becomes cell14
// Old cell14 (Actions) becomes cell15

// Also update in loadSaleInvoiceData() and loadInvoiceData() functions:
// Add after line: lastRow.cells[12].querySelector('input').value = item.foc_quantity || 0;
// lastRow.cells[13].querySelector('select').value = item.stock_status || 'sellable';

// Update in saveInvoice() function - add to item object:
// stockStatus: row.cells[13].querySelector('select').value,
