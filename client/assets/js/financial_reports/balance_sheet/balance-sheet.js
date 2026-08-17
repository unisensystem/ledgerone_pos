document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const validateBtn = document.getElementById('validate-btn');
    const printBtn = document.getElementById('print-btn');
    const exportBtn = document.getElementById('export-btn');
    const exportDropdown = document.getElementById('export-dropdown');
    const exportExcelBtn = document.getElementById('export-excel-btn');
    const exportJsonBtn = document.getElementById('export-json-btn');
    const closePeriodBtn = document.getElementById('close-period-btn');
    const validationMessage = document.getElementById('validation-message');
    const reportPeriod = document.getElementById('report-period');
    const dateFrom = document.getElementById('date-from');
    const dateTo = document.getElementById('date-to');

    // Balance sheet totals
    const totalAssetsEl = document.getElementById('total-assets');
    const totalLiabilitiesEquityEl = document.getElementById('total-liabilities-equity');

    // Event Listeners
    validateBtn.addEventListener('click', validateBalanceSheet);
    printBtn.addEventListener('click', printReport);
    exportBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        exportDropdown.style.display = exportDropdown.style.display === 'none' ? 'block' : 'none';
    });
    exportExcelBtn.addEventListener('click', exportToExcel);
    exportJsonBtn.addEventListener('click', exportToJson);
    
    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        exportDropdown.style.display = 'none';
    });
    closePeriodBtn.addEventListener('click', () => {
        if (closePeriodBtn.disabled) return;
        if (confirm('Close the period? This will transfer net income to Retained Earnings.')) {
            closePeriodBtn.disabled = true;
            closePeriodBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Closing...';
            closePeriod().then(() => {
                loadBalanceSheet();
                closePeriodBtn.disabled = false;
                closePeriodBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Close Period';
            }).catch(() => {
                closePeriodBtn.disabled = false;
                closePeriodBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Close Period';
            });
        }
    });
    reportPeriod.addEventListener('change', () => {
        const isCustom = reportPeriod.value === 'custom';
        document.getElementById('date-from-container').style.display = isCustom ? 'block' : 'none';
        document.getElementById('date-to-container').querySelector('label').textContent = isCustom ? 'Date To' : 'As Of Date';
        updateReportDate();
        loadBalanceSheet();
    });
    
    if (dateFrom) {
        dateFrom.addEventListener('change', () => {
            updateReportDate();
            loadBalanceSheet();
        });
    }
    
    if (dateTo) {
        dateTo.addEventListener('change', () => {
            updateReportDate();
            loadBalanceSheet();
        });
    }

    // Load data
    syncInventory().then(() => loadBalanceSheet());

    let balanceSheetData = {};

    // Load balance sheet data from API
    function loadBalanceSheet() {
        const endDate = getEndDateFromPeriod();
        const startDate = getStartDateFromPeriod();
        
        let url = `../../../../server/api/financial_reports/balance_sheet/balance-sheet.php?end_date=${endDate}`;
        if (startDate) {
            url += `&start_date=${startDate}`;
        }
        
        fetch(url)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    balanceSheetData = result.data;
                    renderBalanceSheet(result.data);
                    calculateTotals();
                } else {
                    showNotification('Error loading balance sheet: ' + result.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error loading balance sheet', 'error');
                console.error(error);
            });
    }

    // Get end date from selected period
    function getEndDateFromPeriod() {
        return dateTo?.value || new Date().toISOString().split('T')[0];
    }
    
    // Get start date for custom range (for soft opening)
    function getStartDateFromPeriod() {
        if (reportPeriod.value === 'custom') {
            return dateFrom?.value || null;
        }
        return null;
    }

    // Render balance sheet data
    function renderBalanceSheet(data) {
        console.log('renderBalanceSheet called with data:', data);
        const assetsCard = document.querySelector('.balance-sheet-container .card:first-child');
        const liabilitiesCard = document.querySelector('.balance-sheet-container .card:last-child');
        
        console.log('Assets card:', assetsCard);
        console.log('Liabilities card:', liabilitiesCard);
        
        // Clear old sections
        assetsCard.querySelectorAll('.balance-sheet-section').forEach(s => s.remove());
        liabilitiesCard.querySelectorAll('.balance-sheet-section').forEach(s => s.remove());
        
        // Render Assets (head_id = 1)
        if (data['1']) {
            console.log('Rendering Assets:', data['1']);
            const assetsTotal = assetsCard.querySelector('.account-total');
            for (const [subId, subData] of Object.entries(data['1'])) {
                const section = createSection(subData.name, subData.accounts);
                assetsCard.insertBefore(section, assetsTotal);
            }
        }
        
        // Render Liabilities (head_id = 2)
        if (data['2']) {
            console.log('Rendering Liabilities:', data['2']);
            const liabTotal = liabilitiesCard.querySelector('.account-total');
            console.log('Liab total:', liabTotal);
            for (const [subId, subData] of Object.entries(data['2'])) {
                console.log('Creating liability section:', subData.name, subData.accounts);
                const section = createSection(subData.name, subData.accounts);
                console.log('Inserting before liab total');
                liabilitiesCard.insertBefore(section, liabTotal);
            }
        } else {
            console.log('No liabilities data found');
        }
        
        // Render Equity (head_id = 3)
        if (data['3']) {
            const equitySection = document.createElement('div');
            equitySection.className = 'balance-sheet-section mt-4';
            equitySection.innerHTML = `<h4 class="section-title">Equity</h4>`;
            
            for (const [subId, subData] of Object.entries(data['3'])) {
                subData.accounts.forEach(account => {
                    const row = document.createElement('div');
                    row.className = 'account-row';
                    row.innerHTML = `
                        <div>${account.name}</div>
                        <div class="amount" data-value="${account.balance}">${formatCurrency(account.balance)}</div>
                    `;
                    equitySection.appendChild(row);
                });
            }
            
            equitySection.innerHTML += `
                <div class="account-row account-subtotal">
                    <div>Total Retained Earnings</div>
                    <div class="amount" id="retained-earnings-total">$0.00</div>
                </div>
                <div class="account-row account-total">
                    <div>TOTAL EQUITY</div>
                    <div class="amount" id="equity-total">$0.00</div>
                </div>
            `;
            
            const grandTotal = liabilitiesCard.querySelector('.account-grand-total');
            liabilitiesCard.insertBefore(equitySection, grandTotal);
        }
    }
    
    function createSection(title, accounts) {
        const section = document.createElement('div');
        section.className = 'balance-sheet-section';
        
        let html = `<h4 class="section-title">${title}</h4>`;
        accounts.forEach(account => {
            html += `
                <div class="account-row">
                    <div>${account.name}</div>
                    <div class="amount" data-value="${account.balance}">${formatCurrency(account.balance)}</div>
                </div>
            `;
        });
        
        section.innerHTML = html;
        return section;
    }

    // Function to calculate all totals
    function calculateTotals() {
        // Calculate from data structure
        let totalAssets = 0;
        let totalLiabilities = 0;
        let equityAccountsSum = 0;

        if (balanceSheetData['1']) {
            Object.values(balanceSheetData['1']).forEach(sub => {
                sub.accounts.forEach(acc => totalAssets += parseFloat(acc.balance));
            });
        }

        if (balanceSheetData['2']) {
            Object.values(balanceSheetData['2']).forEach(sub => {
                sub.accounts.forEach(acc => totalLiabilities += parseFloat(acc.balance));
            });
        }

        if (balanceSheetData['3']) {
            Object.values(balanceSheetData['3']).forEach(sub => {
                sub.accounts.forEach(acc => equityAccountsSum += parseFloat(acc.balance));
            });
        }

        // Equity = Assets - Liabilities (fundamental accounting equation).
        // This is negative when liabilities exceed assets, reflecting insolvency.
        const totalEquity = totalAssets - totalLiabilities;
        const totalLiabilitiesEquityValue = totalLiabilities + totalEquity; // always equals totalAssets

        // Update DOM
        const assetsGrandTotal = document.getElementById('assets-grand-total');
        const liabilitiesTotal = document.getElementById('liabilities-total');
        const equityTotal = document.getElementById('equity-total');
        const retainedEarningsTotal = document.getElementById('retained-earnings-total');
        const liabilitiesEquityGrandTotal = document.getElementById('liabilities-equity-grand-total');

        if (assetsGrandTotal) assetsGrandTotal.textContent = formatCurrency(totalAssets);
        if (totalAssetsEl) totalAssetsEl.textContent = formatCurrency(totalAssets);
        if (liabilitiesTotal) liabilitiesTotal.textContent = formatCurrency(totalLiabilities);
        if (retainedEarningsTotal) retainedEarningsTotal.textContent = formatCurrency(equityAccountsSum);
        if (equityTotal) {
            equityTotal.textContent = formatCurrency(totalEquity);
            equityTotal.style.color = totalEquity < 0 ? 'var(--error)' : '';
            equityTotal.style.fontWeight = totalEquity < 0 ? '700' : '';
        }
        if (liabilitiesEquityGrandTotal) liabilitiesEquityGrandTotal.textContent = formatCurrency(totalLiabilitiesEquityValue);
        if (totalLiabilitiesEquityEl) totalLiabilitiesEquityEl.textContent = formatCurrency(totalLiabilitiesEquityValue);

        return {
            assets: totalAssets,
            liabilities: totalLiabilities,
            equity: totalEquity,
            liabilitiesEquity: totalLiabilitiesEquityValue
        };
    }

    // Helper function to sum a card
    function sumCard(card) {
        const amountElements = card.querySelectorAll('.balance-sheet-section .account-row .amount[data-value]');
        let sum = 0;
        amountElements.forEach(element => {
            const value = parseFloat(element.getAttribute('data-value')) || 0;
            sum += value;
        });
        return sum;
    }

    // Helper function to sum a section
    function sumSection2(section) {
        const amountElements = section.querySelectorAll('.account-row:not(.account-subtotal):not(.account-total) .amount[data-value]');
        let sum = 0;
        amountElements.forEach(element => {
            const value = parseFloat(element.getAttribute('data-value')) || 0;
            sum += value;
        });
        return sum;
    }

    // Function to sync inventory
    function syncInventory() {
        const endDate = getEndDateFromPeriod();
        
        return fetch('../../../../server/api/financial_reports/balance_sheet/sync-inventory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ end_date: endDate })
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                console.error('Inventory sync error:', result.message);
            } else {
                console.log('Inventory sync result:', result);
            }
        })
        .catch(error => console.error('Inventory sync error:', error));
    }

    // Function to close period
    function closePeriod() {
        const endDate = getEndDateFromPeriod();
        
        return fetch('../../../../server/api/financial_reports/balance_sheet/close-period.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ end_date: endDate })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Period closed successfully', 'success');
                console.log('Period closed:', result.data);
            } else {
                showNotification('Error: ' + result.message, 'error');
                throw new Error(result.message);
            }
        })
        .catch(error => {
            showNotification('Period close error', 'error');
            console.error('Period close error:', error);
            throw error;
        });
    }

    // Function to validate the balance sheet
    function validateBalanceSheet() {
        const totals = calculateTotals();
        const assets = totals.assets;
        const liabilitiesEquity = totals.liabilitiesEquity;

        // Clear previous validation message
        validationMessage.textContent = '';
        validationMessage.className = 'validation-message';

        // Check if balance sheet balances
        if (Math.abs(assets - liabilitiesEquity) < 0.01) {
            // Balance sheet is balanced
            validationMessage.innerHTML = `
                <div class="d-flex align-center">
                    <i class="fas fa-check-circle" style="color: var(--success); margin-right: 10px; font-size: 20px;"></i>
                    <div>
                        <h4 style="margin-bottom: 4px;">Balance Sheet Validated Successfully!</h4>
                        <p>Assets (${CURRENCY_SYMBOL}${formatNumber(assets)}) = Liabilities & Equity (${CURRENCY_SYMBOL}${formatNumber(liabilitiesEquity)}). The balance sheet is properly balanced.</p>
                    </div>
                </div>
            `;
            validationMessage.classList.add('validation-success');

            // Calculate financial ratios
            let currentAssets = 0;
            let currentLiabilities = 0;
            
            // Sum current assets (sub_account_id 74)
            if (balanceSheetData['1'] && balanceSheetData['1']['74']) {
                balanceSheetData['1']['74'].accounts.forEach(acc => currentAssets += parseFloat(acc.balance));
            }
            
            // Sum current liabilities (sub_account_id 79)
            if (balanceSheetData['2'] && balanceSheetData['2']['79']) {
                balanceSheetData['2']['79'].accounts.forEach(acc => currentLiabilities += parseFloat(acc.balance));
            }

            if (currentAssets > 0 && currentLiabilities > 0) {
                const currentRatio = currentAssets / currentLiabilities;
                const debtToEquityRatio = totals.liabilities / totals.equity;

                setTimeout(() => {
                    const ratioMessage = document.createElement('div');
                    ratioMessage.className = 'help-tip mt-3';
                    ratioMessage.innerHTML = `
                        <strong>Financial Health Check:</strong>
                        <div class="mt-2">
                            <div>Current Ratio: ${currentRatio.toFixed(2)} ${currentRatio >= 1.5 ? '✅' : currentRatio >= 1.0 ? '⚠️' : '❌'}</div>
                            <div>Debt-to-Equity Ratio: ${debtToEquityRatio.toFixed(2)} ${debtToEquityRatio <= 2.0 ? '✅' : debtToEquityRatio <= 3.0 ? '⚠️' : '❌'}</div>
                        </div>
                        <div class="mt-2" style="font-size: 12px;">
                            ✅ = Good  ⚠️ = Monitor  ❌ = Concerning
                        </div>
                    `;
                    validationMessage.appendChild(ratioMessage);
                }, 100);
            }

        } else {
            // Balance sheet doesn't balance
            const difference = Math.abs(assets - liabilitiesEquity);
            validationMessage.innerHTML = `
                <div class="d-flex align-center">
                    <i class="fas fa-exclamation-triangle" style="color: var(--error); margin-right: 10px; font-size: 20px;"></i>
                    <div>
                        <h4 style="margin-bottom: 4px;">Balance Sheet Validation Failed</h4>
                        <p>Assets (${CURRENCY_SYMBOL}${formatNumber(assets)}) do not equal Liabilities & Equity (${CURRENCY_SYMBOL}${formatNumber(liabilitiesEquity)}). Difference: ${CURRENCY_SYMBOL}${formatNumber(difference)}</p>
                        <p class="mt-2">This indicates a possible error in your financial records. Check for missing entries or calculation errors.</p>
                    </div>
                </div>
            `;
            validationMessage.classList.add('validation-error');
        }

        validationMessage.style.display = 'block';

        // Scroll to validation message
        validationMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Function to print the report
    function printReport() {
        window.open('print.php', '_blank');
    }

    // Function to export the report
    function exportToExcel() {
        showNotification('Exporting balance sheet to Excel...', 'info');
        // TODO: Implement Excel export
    }
    
    function exportToJson() {
        const dataStr = JSON.stringify(balanceSheetData, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `balance-sheet-${new Date().toISOString().split('T')[0]}.json`;
        link.click();
        URL.revokeObjectURL(url);
        showNotification('Balance sheet exported as JSON', 'success');
    }

    // Function to update report date based on selection
    function updateReportDate() {
        const reportDateEl = document.getElementById('report-date');
        const isCustom = reportPeriod.value === 'custom';
        const from = dateFrom?.value;
        const to = dateTo?.value;
        
        if (isCustom && from && to) {
            reportDateEl.textContent = `${new Date(from).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})} - ${new Date(to).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})}`;
        } else if (to) {
            reportDateEl.textContent = new Date(to).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'});
        }
    }

    // Helper function to format currency
    function formatCurrency(value) {
        return CURRENCY_SYMBOL + new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }

    // Helper function to format number without currency symbol
    function formatNumber(value) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }

    // Function to show notifications
    function showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease;
        `;

        // Set background color based on type
        if (type === 'success') {
            notification.style.backgroundColor = 'var(--success)';
        } else if (type === 'error') {
            notification.style.backgroundColor = 'var(--error)';
        } else if (type === 'warning') {
            notification.style.backgroundColor = 'var(--warning)';
        } else {
            notification.style.backgroundColor = 'var(--primary)';
        }

        // Add to DOM
        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        @media print {
            .action-buttons, .help-section, .validation-message {
                display: none !important;
            }
            .container {
                max-width: 100%;
                padding: 0;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    `;
    document.head.appendChild(style);
});