// Fix for updateInvoiceSummary - properly calculate net amounts from rows

function updateInvoiceSummaryFixed() {
    const itemsTable = document.getElementById('itemsTable');
    if (!itemsTable) return;
    
    const tbody = itemsTable.getElementsByTagName('tbody')[0];
    if (!tbody) return;
    
    const rows = tbody.rows;
    let totalBill = 0;
    let totalQty = 0;
    let totalGrossAmount = 0;
    let totalDiscountAmountItems = 0;
    let totalTradeOfferAmount = 0;
    let totalTaxAmount = 0;
    let totalFocQty = 0;
    let totalNetAmountItems = 0;
    
    console.log('=== updateInvoiceSummaryFixed START ===');
    console.log('Items table has', rows.length, 'rows');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        
        // Skip child rows
        if (row.classList.contains('child-row')) {
            continue;
        }
        
        // Get all inputs in the row
        const inputs = row.querySelectorAll('input[type="number"]');
        
        // Find the net amount input - it's the last numeric input before actions
        let netAmountInput = null;
        let grossAmountInput = null;
        let discountAmountInput = null;
        let tradeOfferAmountInput = null;
        let taxAmountInput = null;
        let focQtyInput = null;
        
        // Iterate through cells to find the correct inputs
        for (let j = 0; j < row.cells.length; j++) {
            const cell = row.cells[j];
            const input = cell.querySelector('input[type="number"]');
            
            if (!input) continue;
            
            // Identify by cell content or position
            const cellText = cell.textContent.toLowerCase();
            
            // Check for specific cell types by their position or class
            if (cell.classList.contains('price-cell')) {
                // Sale price
            } else if (cell.classList.contains('gross-cell')) {
                grossAmountInput = input;
            } else if (cell.classList.contains('disc-amount-cell')) {
                discountAmountInput = input;
            } else if (cell.classList.contains('to-amount-cell')) {
                tradeOfferAmountInput = input;
            } else if (cell.classList.contains('tax-amount-cell')) {
                taxAmountInput = input;
            } else if (cell.classList.contains('foc-cell')) {
                focQtyInput = input;
            } else if (cell.classList.contains('net-cell')) {
                netAmountInput = input;
            }
        }
        
        // If we couldn't find by class, find by position (net amount is typically the second-to-last numeric cell)
        if (!netAmountInput) {
            const numericInputs = row.querySelectorAll('input[type="number"]:not([readonly])');
            if (numericInputs.length > 0) {
                // Net amount is usually near the end
                for (let k = numericInputs.length - 1; k >= 0; k--) {
                    const inp = numericInputs[k];
                    if (inp.closest('td') && !inp.closest('td').classList.contains('actions')) {
                        netAmountInput = inp;
                        break;
                    }
                }
            }
        }
        
        if (netAmountInput) {
            const netValue = parseFloat(netAmountInput.value) || 0;
            console.log(`Row ${i}: netAmount = ${netValue}`);
            
            totalQty += 1;
            totalGrossAmount += parseFloat(grossAmountInput?.value) || 0;
            totalDiscountAmountItems += parseFloat(discountAmountInput?.value) || 0;
            totalTradeOfferAmount += parseFloat(tradeOfferAmountInput?.value) || 0;
            totalTaxAmount += parseFloat(taxAmountInput?.value) || 0;
            totalFocQty += parseFloat(focQtyInput?.value) || 0;
            totalNetAmountItems += netValue;
            totalBill += netValue;
        }
    }
    
    console.log('Summary: totalNetAmountItems =', totalNetAmountItems, 'totalBill =', totalBill);
    
    // Update DOM elements
    const totalBillEl = document.getElementById('totalBill');
    const totalGrossAmountEl = document.getElementById('totalGrossAmount');
    const totalDiscountAmountItemsEl = document.getElementById('totalDiscountAmountItems');
    const totalTradeOfferAmountEl = document.getElementById('totalTradeOfferAmount');
    const totalTaxAmountEl = document.getElementById('totalTaxAmount');
    const totalFocQtyEl = document.getElementById('totalFocQty');
    const totalNetAmountItemsEl = document.getElementById('totalNetAmountItems');
    
    if (totalBillEl) totalBillEl.textContent = totalBill.toFixed(2);
    if (totalGrossAmountEl) totalGrossAmountEl.textContent = totalGrossAmount.toFixed(2);
    if (totalDiscountAmountItemsEl) totalDiscountAmountItemsEl.textContent = totalDiscountAmountItems.toFixed(2);
    if (totalTradeOfferAmountEl) totalTradeOfferAmountEl.textContent = totalTradeOfferAmount.toFixed(2);
    if (totalTaxAmountEl) totalTaxAmountEl.textContent = totalTaxAmount.toFixed(2);
    if (totalFocQtyEl) totalFocQtyEl.textContent = totalFocQty.toFixed(2);
    if (totalNetAmountItemsEl) totalNetAmountItemsEl.textContent = totalNetAmountItems.toFixed(2);
    
    // Calculate invoice-level discount
    const discountPercentEl = document.getElementById('totalDiscountPercent');
    const discountPercent = parseFloat(discountPercentEl?.value) || 0;
    const invoiceDiscountAmount = totalBill * (discountPercent / 100);
    const afterDiscount = totalBill - invoiceDiscountAmount;
    
    const shippingFeesEl = document.getElementById('shippingFees');
    const shippingFees = parseFloat(shippingFeesEl?.value) || 0;
    const netAmount = afterDiscount + shippingFees;
    
    const totalDiscountAmountEl = document.getElementById('totalDiscountAmount');
    const netAmountEl = document.getElementById('netAmount');
    
    if (totalDiscountAmountEl) totalDiscountAmountEl.value = invoiceDiscountAmount.toFixed(2);
    if (netAmountEl) netAmountEl.textContent = netAmount.toFixed(2);
    
    console.log('DOM netAmount element now contains:', netAmountEl?.textContent);
    
    // Store net amount for tax calculation
    window.currentNetAmount = netAmount;
    
    // Calculate invoice-level taxes
    if (typeof calculateInvoiceLevelTaxes === 'function') {
        calculateInvoiceLevelTaxes();
    }
    
    console.log('=== updateInvoiceSummaryFixed END ===\n');
    
    // Update remaining balance
    const amountPaidEl = document.getElementById('amountPaid');
    const amountPaid = parseFloat(amountPaidEl?.value) || 0;
    const netReceivableEl = document.getElementById('netReceivable');
    const netReceivable = parseFloat(netReceivableEl?.textContent) || netAmount;
    const remainingBalance = netReceivable - amountPaid;
    
    const remainingBalanceEl = document.getElementById('remainingBalance');
    if (remainingBalanceEl) {
        remainingBalanceEl.value = remainingBalance.toFixed(2);
    }
}

// Override the original updateInvoiceSummary with the fixed version
window.updateInvoiceSummary = updateInvoiceSummaryFixed;
