document.addEventListener('DOMContentLoaded', function () {
    const fromDate = document.getElementById('from-date');
    const toDate = document.getElementById('to-date');
    const fromDateError = document.getElementById('from-date-error');
    const toDateError = document.getElementById('to-date-error');
    const loadLedgerBtn = document.getElementById('load-ledger-btn');
    const resetFiltersBtn = document.getElementById('reset-filters-btn');
    const exportDropdown = document.getElementById('export-dropdown');
    const exportMenu = document.getElementById('export-menu');
    const detailedLedgerTable = document.getElementById('detailed-ledger-table');

    fromDate.value = '';
    toDate.value = '';

    // Date validation
    function validateDates() {
        if (!fromDate.value || !toDate.value) return true;
        
        const from = new Date(fromDate.value);
        const to = new Date(toDate.value);

        if (isNaN(from.getTime()) || isNaN(to.getTime())) {
            return false;
        }

        if (from > to) {
            fromDate.classList.add('error');
            toDate.classList.add('error');
            fromDateError.style.display = 'block';
            toDateError.style.display = 'block';
            return false;
        } else {
            fromDate.classList.remove('error');
            toDate.classList.remove('error');
            fromDateError.style.display = 'none';
            toDateError.style.display = 'none';
            return true;
        }
    }

    fromDate.addEventListener('change', validateDates);
    toDate.addEventListener('change', validateDates);

    // Load ledger data
    async function loadLedgerData() {
        if (!validateDates()) return;

        const params = new URLSearchParams({
            type: 'detailed',
            customer_id: window.customerId,
            from_date: fromDate.value,
            to_date: toDate.value
        });

        try {
            const response = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?${params}`);
            const result = await response.json();
            
            if (result.success) {
                renderDetailedLedger(result.data, result.opening_balance);
            } else {
                showNotification(result.message || 'Error loading data', 'error');
            }
        } catch (error) {
            showNotification('Network error occurred', 'error');
        }
    }

    // Render detailed ledger
    function renderDetailedLedger(data, openingBalance) {
        const tbody = detailedLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';
        
        let totalDebit = 0, totalCredit = 0;
        let rowIndex = 0;
        
        data.forEach((row) => {
            if (row.type === 'opening_balance') {
                const balance = parseFloat(row.running_balance) || 0;
                tbody.innerHTML += `
                    <tr>
                        <td>${row.date}</td>
                        <td>${row.description}</td>
                        <td>${row.reference}</td>
                        <td></td>
                        <td></td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${window.currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                    </tr>
                `;
            } else if (row.type === 'sub_account_header') {
                tbody.innerHTML += `
                    <tr style="background: var(--surface-2); font-weight: 600;">
                        <td colspan="6" style="padding: 12px 16px;">
                            <i class="fas fa-folder" style="margin-right: 8px; color: var(--primary);"></i>
                            ${row.sub_account_name}
                        </td>
                    </tr>
                `;
            } else if (row.type === 'sub_account_total') {
                const balance = parseFloat(row.running_balance) || 0;
                tbody.innerHTML += `
                    <tr style="background: var(--surface-1); font-weight: 600; border-top: 2px solid var(--border-strong);">
                        <td colspan="3" style="text-align: right; padding: 12px 16px;">
                            <strong>${row.sub_account_name} Total:</strong>
                        </td>
                        <td><strong>${window.currencySymbol}${parseFloat(row.total_debit).toFixed(2)}</strong></td>
                        <td><strong>${window.currencySymbol}${parseFloat(row.total_credit).toFixed(2)}</strong></td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}"><strong>${window.currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</strong></td>
                    </tr>
                `;
            } else {
                const debit = parseFloat(row.debit) || 0;
                const credit = parseFloat(row.credit) || 0;
                const balance = parseFloat(row.running_balance) || 0;
                const pdcDisplay = parseFloat(row.pdc_display) || 0;
                const hasItems = (row.type === 'invoice' || row.type === 'return') && row.items && row.items.length > 0;
                
                if (row.type !== 'opening_balance' && row.type !== 'sub_account_header' && row.type !== 'sub_account_total') {
                    totalDebit += debit;
                    totalCredit += credit;
                }
                
                const creditDisplay = credit > 0 ? window.currencySymbol + credit.toFixed(2) : 
                                      (pdcDisplay > 0 ? `(${window.currencySymbol}${pdcDisplay.toFixed(2)})` : '');
                
                tbody.innerHTML += `
                    <tr class="ledger-row" data-row-id="${rowIndex}">
                        <td>${row.date}</td>
                        <td>
                            ${hasItems ? `<button class="expand-btn" data-row-id="${rowIndex}"><i class="fas fa-chevron-right"></i></button>` : ''}
                            ${row.description}
                        </td>
                        <td>${row.reference}</td>
                        <td>${debit > 0 ? window.currencySymbol + debit.toFixed(2) : ''}</td>
                        <td>${creditDisplay}</td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${window.currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                    </tr>
                `;
                
                if (hasItems) {
                    tbody.innerHTML += `
                        <tr class="items-row" id="items-${rowIndex}" style="display: none;">
                            <td colspan="6">
                                <div class="items-container">
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${row.items.map(item => `
                                                <tr>
                                                    <td>${item.product_name}</td>
                                                    <td>${parseFloat(item.quantity).toFixed(2)} ${item.uom_name || ''}</td>
                                                    <td>${window.currencySymbol}${parseFloat(item.sale_price).toFixed(2)}</td>
                                                    <td>${window.currencySymbol}${parseFloat(item.net_amount).toFixed(2)}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                
                rowIndex++;
            }
        });
        
        const finalBalance = openingBalance + totalDebit - totalCredit;
        tbody.innerHTML += `
            <tr class="totals-row">
                <td><strong>Totals</strong></td>
                <td></td>
                <td></td>
                <td><strong>${window.currencySymbol}${totalDebit.toFixed(2)}</strong></td>
                <td><strong>${window.currencySymbol}${totalCredit.toFixed(2)}</strong></td>
                <td><strong class="${finalBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${window.currencySymbol}${Math.abs(finalBalance).toFixed(2)} ${finalBalance >= 0 ? 'Dr' : 'Cr'}</strong></td>
            </tr>
        `;
        
        document.querySelectorAll('.expand-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const rowId = this.dataset.rowId;
                const itemsRow = document.getElementById(`items-${rowId}`);
                const icon = this.querySelector('i');
                
                if (itemsRow.style.display === 'none') {
                    itemsRow.style.display = 'table-row';
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                } else {
                    itemsRow.style.display = 'none';
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                }
            });
        });
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 1001;
            padding: 12px 20px; border-radius: 6px; color: white;
            background: ${type === 'error' ? '#e34f4f' : '#2fbf71'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    }

    loadLedgerBtn.addEventListener('click', loadLedgerData);

    resetFiltersBtn.addEventListener('click', function () {
        fromDate.value = '';
        toDate.value = '';
        validateDates();
        loadLedgerData();
    });

    if (exportDropdown) {
        exportDropdown.addEventListener('click', function () {
            exportMenu.classList.toggle('show');
        });
    }

    if (document.getElementById('print-ledger')) {
        document.getElementById('print-ledger').addEventListener('click', function(e) {
            e.preventDefault();
            const params = new URLSearchParams({
                ledger_type: 'detailed',
                customer_id: window.customerId,
                customer_code: encodeURIComponent(btoa(window.customerCode)),
                date_from: fromDate.value,
                date_to: toDate.value
            });
            
            window.open(`print.php?${params}`, '_blank');
        });
    }

    if (exportDropdown && exportMenu) {
        document.addEventListener('click', function (event) {
            if (!exportDropdown.contains(event.target) && !exportMenu.contains(event.target)) {
                exportMenu.classList.remove('show');
            }
        });
    }

    loadLedgerData();
});
