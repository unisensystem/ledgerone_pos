// COMPLETE MODIFICATIONS FOR INVENTORY STATUS DROPDOWN
// Add these changes to return-add.js

// ============================================
// MODIFICATION 1: In addRow() function
// ============================================
// Find the section after FOC Qty (cell12) and BEFORE Net Amount
// Replace the Net Amount section with this:

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

        // Net Amount (readonly) - NOW cell14 instead of cell13
        const cell14 = row.insertCell(14);
        const netAmount = document.createElement('input');
        netAmount.type = 'text';
        netAmount.className = 'table-input';
        netAmount.readOnly = true;
        netAmount.value = '0.00';
        netAmount.tabIndex = 0;
        
        // Add tab navigation logic for Net Amount
        netAmount.addEventListener('keydown', function(e) {
            if (e.key === 'Tab' && !e.shiftKey) {
                const codeInput = row.cells[1].querySelector('.item-code');
                if (codeInput.value) {
                    e.preventDefault();
                    const currentRowIndex = row.rowIndex - 1;
                    const nextRowIndex = currentRowIndex + 1;
                    
                    if (nextRowIndex < itemsTable.rows.length) {
                        const nextRow = itemsTable.rows[nextRowIndex];
                        nextRow.cells[1].querySelector('.search-input').focus();
                    } else {
                        addRow();
                        setTimeout(() => {
                            const newRow = itemsTable.rows[itemsTable.rows.length - 1];
                            newRow.cells[1].querySelector('.search-input').focus();
                        }, 10);
                    }
                } else {
                    e.preventDefault();
                    if (itemsTable.rows.length > 1) {
                        const searchInput = row.cells[1].querySelector('.search-input');
                        if (searchInput.dropdownOptions) {
                            searchInput.dropdownOptions.remove();
                        }
                        row.remove();
                        updateSerialNumbers();
                        updateInvoiceSummary();
                    }
                    document.getElementById('totalDiscountPercent').focus();
                }
            }
        });
        
        cell14.appendChild(netAmount);

        // Actions (delete button) - NOW cell15 instead of cell14
        const cell15 = row.insertCell(15);
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-sm';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.title = 'Delete row';
        deleteBtn.tabIndex = -1;
        cell15.appendChild(deleteBtn);

// ============================================
// MODIFICATION 2: In saveInvoice() function
// ============================================
// Find the section where items are collected
// Update the item object to include stockStatus:

            gstAmount: parseFloat(row.cells[11].querySelector('input').value) || 0,
            focQty: parseFloat(row.cells[12].querySelector('input').value) || 0,
            stockStatus: row.cells[13].querySelector('select').value,  // NEW LINE
            netAmount: parseFloat(row.cells[14].querySelector('input').value)  // UPDATED from cells[13]

// ============================================
// MODIFICATION 3: In loadSaleInvoiceData() function
// ============================================
// Add after the line that sets foc_quantity:

                    lastRow.cells[12].querySelector('input').value = item.foc_quantity || 0;
                    lastRow.cells[13].querySelector('select').value = item.stock_status || 'sellable';  // NEW LINE
                    lastRow.cells[14].querySelector('input').value = item.net_amount;  // UPDATED from cells[13]

// ============================================
// MODIFICATION 4: In loadInvoiceData() function
// ============================================
// Add after the line that sets foc_quantity:

                    lastRow.cells[12].querySelector('input').value = item.foc_quantity || 0;
                    lastRow.cells[13].querySelector('select').value = item.stock_status || 'sellable';  // NEW LINE
                    lastRow.cells[14].querySelector('input').value = item.net_amount;  // UPDATED from cells[13]

// ============================================
// MODIFICATION 5: In updateInvoiceSummary() function
// ============================================
// Update all references from cells[13] to cells[14] for netAmountInput:

                const netAmountInput = rows[i].cells[14].querySelector('input');  // UPDATED from cells[13]

// ============================================
// MODIFICATION 6: In applyInvoiceSettings() function
// ============================================
// Update the column indices for hiding/showing columns:
// The Status column (13) should always be visible
// Net Amount is now column 14, Actions is now column 15
