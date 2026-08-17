document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const summaryLedgerBtn = document.getElementById('summary-ledger-btn');
    const detailedLedgerBtn = document.getElementById('detailed-ledger-btn');
    const customerCode = document.getElementById('customer-code');
    const customerSearch = document.getElementById('customer-search');
    const customerDropdown = document.getElementById('customer-dropdown');
    const fromDate = document.getElementById('from-date');
    const toDate = document.getElementById('to-date');
    const fromDateError = document.getElementById('from-date-error');
    const toDateError = document.getElementById('to-date-error');
    const loadLedgerBtn = document.getElementById('load-ledger-btn');
    const resetFiltersBtn = document.getElementById('reset-filters-btn');
    const shareLedgerBtn = document.getElementById('share-ledger-btn');
    const exportDropdown = document.getElementById('export-dropdown');
    const exportMenu = document.getElementById('export-menu');
    const summaryLedgerTable = document.getElementById('summary-ledger-table');
    const detailedLedgerTable = document.getElementById('detailed-ledger-table');
    const ledgerTitle = document.getElementById('ledger-title');
    const shareModal = document.getElementById('share-modal');
    const closeShareModal = document.getElementById('close-share-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');

    const copyUrlBtn = document.getElementById('copy-url-btn');
    const pagination = document.getElementById('pagination');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageInfo = document.getElementById('page-info');
    const distribution = document.getElementById('distribution');
    const subAccount = document.getElementById('sub-account');
    const subAccountGroup = document.getElementById('sub-account-group');
    const currencyFilter = document.getElementById('currency-filter');
    const symbolOverride = document.getElementById('symbol-override');

    let currentPage = 1;
    let totalPages = 1;
    let allData = [];
    let allDetailedData = [];       // add this
let detailedOpeningBalance = 0; // add this
    const itemsPerPage = 10;
    let expandedRows = new Set();
    let allCustomers = [];
    let currentCurrencySymbol = window.currencySymbol;

    // Set default dates
    const today = new Date();
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(today.getMonth() - 1);

    fromDate.value = '';
    toDate.value = '';

    // Load customers on page load
    loadCompanies();
    loadCurrencies();
    loadCustomers();
    loadDistributions();

    function loadCompanies() {
        fetch('../../../../server/api/financial_reports/customer_ledger/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const companySelect = document.getElementById('company');
                    companySelect.innerHTML = '<option value="">All Companies</option>';
                    data.companies.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.company_name;
                        companySelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading companies:', error));
    }
    
    // Load currencies
    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=currencies');
            const result = await response.json();
            
            if (result.success) {
                currencyFilter.innerHTML = '';
                result.data.forEach(currency => {
                    const isBase = currency.is_base_currency == 1;
                    currencyFilter.innerHTML += `<option value="${currency.id}" ${isBase ? 'selected' : ''}>${currency.name} (${currency.symbol})${isBase ? ' - Base' : ''}</option>`;
                    if (isBase) {
                        currentCurrencySymbol = currency.symbol;
                        symbolOverride.value = currency.symbol;
                    }
                });
            }
        } catch (error) {
            console.error('Error loading currencies:', error);
        }
    }
    
    // Update currency symbol when currency changes
    currencyFilter.addEventListener('change', async function() {
        const selectedOption = currencyFilter.options[currencyFilter.selectedIndex];
        const symbolMatch = selectedOption.text.match(/\((.+?)\)/);
        if (symbolMatch) {
            currentCurrencySymbol = symbolMatch[1];
            symbolOverride.value = symbolMatch[1];
        }
        await loadLedgerData();
    });

    // Symbol override — only changes display symbol, no conversion
    symbolOverride.addEventListener('change', function() {
        currentCurrencySymbol = this.value;
        if (allData.length > 0) renderPage();
    });
    
    // Reload customers when company changes
    document.getElementById('company').addEventListener('change', function() {
        customerCode.value = '';
        customerSearch.value = '';
        showSummaryLedger();
        loadLedgerData();
    });

    // Searchable dropdown functionality
    let selectedIndex = -1;
    
    customerSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        selectedIndex = -1;
        
        const filtered = searchTerm.length === 0 || searchTerm === ' '
            ? allCustomers
            : allCustomers.filter(customer => 
                customer.customer_name.toLowerCase().includes(searchTerm) ||
                customer.customer_code.toLowerCase().includes(searchTerm)
            );
        
        if (filtered.length > 0) {
            customerDropdown.innerHTML = filtered.map(customer => 
                `<div class="customer-option" data-id="${customer.id}">${customer.customer_code} - ${customer.customer_name}</div>`
            ).join('');
            customerDropdown.style.display = 'block';
        } else {
            customerDropdown.style.display = 'none';
        }
    });

    // Arrow key navigation
    customerSearch.addEventListener('keydown', function(e) {
        const options = customerDropdown.querySelectorAll('.customer-option');
        if (options.length === 0) return;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, options.length - 1);
            updateSelection(options);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(options);
        } else if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            options[selectedIndex].click();
        }
    });

    function updateSelection(options) {
        options.forEach((option, index) => {
            option.classList.toggle('selected', index === selectedIndex);
        });
    }

    // Handle customer selection
    customerDropdown.addEventListener('click', function(e) {
        if (e.target.classList.contains('customer-option')) {
            const customerId = e.target.dataset.id;
            const customerText = e.target.textContent;
            
            customerSearch.value = customerText;
            customerCode.value = customerId;
            customerDropdown.style.display = 'none';
            
            if (customerId) {
                loadSubAccounts(customerId);
                showDetailedLedger();
            } else {
                subAccountGroup.style.display = 'none';
                subAccount.value = '';
            }
        }
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!customerSearch.contains(e.target) && !customerDropdown.contains(e.target)) {
            customerDropdown.style.display = 'none';
        }
    });

    // Toggle ledger type
    function showSummaryLedger() {
        hideCustomerError();
        summaryLedgerBtn.classList.add('active');
        detailedLedgerBtn.classList.remove('active');
        summaryLedgerTable.style.display = 'table';
        detailedLedgerTable.style.display = 'none';
        ledgerTitle.textContent = 'Summary Ledger';
        subAccountGroup.style.display = 'none';
    }

    function showDetailedLedger() {
        if (!customerCode.value) {
            showCustomerError();
            return;
        }
        hideCustomerError();
        detailedLedgerBtn.classList.add('active');
        summaryLedgerBtn.classList.remove('active');
        detailedLedgerTable.style.display = 'table';
        summaryLedgerTable.style.display = 'none';
        ledgerTitle.textContent = 'Detailed Ledger';
        subAccountGroup.style.display = 'block';
    }

    summaryLedgerBtn.addEventListener('click', showSummaryLedger);
    detailedLedgerBtn.addEventListener('click', showDetailedLedger);

    // Show/hide customer error
    function showCustomerError() {
        let errorCard = document.getElementById('customer-error-card');
        if (!errorCard) {
            errorCard = document.createElement('div');
            errorCard.id = 'customer-error-card';
            errorCard.className = 'card';
            errorCard.innerHTML = '<p style="color: var(--error); text-align: center; margin: 0;">Please select a Customer Code first to view detailed ledger.</p>';
            document.querySelector('.card:last-of-type').insertAdjacentElement('afterend', errorCard);
        }
        errorCard.style.display = 'block';
        const tableCard = document.querySelector('.card:nth-last-child(2)');
        if (tableCard) {
            tableCard.style.display = 'none';
        }
    }

    function hideCustomerError() {
        const errorCard = document.getElementById('customer-error-card');
        if (errorCard) {
            errorCard.style.display = 'none';
        }
        const tableCard = document.querySelector('.card:nth-last-child(2)');
        if (tableCard) {
            tableCard.style.display = 'block';
        }
    }

    // Customer code change handler
    customerCode.addEventListener('change', function () {
        if (customerCode.value) {
            loadSubAccounts(customerCode.value);
            showDetailedLedger();
        } else {
            subAccountGroup.style.display = 'none';
            subAccount.value = '';
            showSummaryLedger();
        }
    });

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

    // Load customers
    async function loadCustomers() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=customers');
            const result = await response.json();
            
            if (result.success) {
                allCustomers = result.data;
                customerCode.innerHTML = '<option value="">All Customers</option>';
                result.data.forEach(customer => {
                    customerCode.innerHTML += `<option value="${customer.id}">${customer.customer_code} - ${customer.customer_name}</option>`;
                });
                customerSearch.value = '';
            }
        } catch (error) {
            console.error('Error loading customers:', error);
        }
    }

    // Load distributions
    async function loadDistributions() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=distributions');
            const result = await response.json();
            
            if (result.success) {
                distribution.innerHTML = '<option value="">All Distributions</option>';
                result.data.forEach(dist => {
                    distribution.innerHTML += `<option value="${dist.id}">${dist.supplier_code} - ${dist.supplier_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading distributions:', error);
        }
    }

    // Load sub accounts
    async function loadSubAccounts(customerId) {
        try {
            const response = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=sub_accounts&customer_id=${customerId}`);
            const result = await response.json();
            
            if (result.success) {
                subAccount.innerHTML = '<option value="">All Sub Accounts</option>';
                result.data.forEach(sub => {
                    subAccount.innerHTML += `<option value="${sub.id}">${sub.sub_account_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading sub accounts:', error);
        }
    }

    // Load ledger data
    async function loadLedgerData() {
        if (!validateDates()) return;

        const isDetailed = detailedLedgerBtn.classList.contains('active');
        const params = new URLSearchParams({
            type: isDetailed ? 'detailed' : 'summary',
            from_date: fromDate.value,
            to_date: toDate.value
        });

        if (isDetailed && customerCode.value) {
            params.append('customer_id', customerCode.value);
        }
        
        if (distribution.value) {
            params.append('distribution_id', distribution.value);
        }
        
        if (isDetailed && subAccount.value) {
            params.append('sub_account_id', subAccount.value);
        }
        
        if (company.value) {
            params.append('company_id', company.value);
        }
        
        if (currencyFilter.value) {
            params.append('currency_id', currencyFilter.value);
        }

        try {
            const response = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?${params}`);
            const result = await response.json();
            
            if (result.success) {
                if (isDetailed) {
                    renderDetailedLedger(result.data, result.opening_balance);
                } else {
                    renderSummaryLedger(result.data);
                }
            } else {
                showNotification(result.message || 'Error loading data', 'error');
            }
        } catch (error) {
            showNotification('Network error occurred', 'error');
        }
    }

    // Render summary ledger
    function renderSummaryLedger(data) {
        allData = data;
        totalPages = Math.ceil(data.length / itemsPerPage);
        currentPage = 1;
        renderPage();
        updatePagination();
    }

    function renderPage() {
        const tbody = summaryLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';
        
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageData = allData.slice(startIndex, endIndex);
        
        let totalOpening = 0, totalDebit = 0, totalCredit = 0, totalClosing = 0;
        
        pageData.forEach(row => {
            const opening = parseFloat(row.opening_balance) || 0;
            const debit = parseFloat(row.total_debit) || 0;
            const credit = parseFloat(row.total_credit) || 0;
            const closing = parseFloat(row.closing_balance) || 0;
            
            totalOpening += opening;
            totalDebit += debit;
            totalCredit += credit;
            totalClosing += closing;
            
            tbody.innerHTML += `
                <tr>
                    <td>${row.customer_name}</td>
                    <td class="${opening >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(opening).toFixed(2)} ${opening >= 0 ? 'Dr' : 'Cr'}</td>
                    <td>${currentCurrencySymbol}${debit.toFixed(2)}</td>
                    <td>${currentCurrencySymbol}${credit.toFixed(2)}</td>
                    <td class="${closing >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(closing).toFixed(2)} ${closing >= 0 ? 'Dr' : 'Cr'}</td>
                </tr>
            `;
        });
        
        // Calculate totals for all data
        let grandTotalOpening = 0, grandTotalDebit = 0, grandTotalCredit = 0, grandTotalClosing = 0;
        allData.forEach(row => {
            grandTotalOpening += parseFloat(row.opening_balance) || 0;
            grandTotalDebit += parseFloat(row.total_debit) || 0;
            grandTotalCredit += parseFloat(row.total_credit) || 0;
            grandTotalClosing += parseFloat(row.closing_balance) || 0;
        });
        
        tbody.innerHTML += `
            <tr class="totals-row">
                <td><strong>Totals</strong></td>
                <td><strong class="${grandTotalOpening >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(grandTotalOpening).toFixed(2)} ${grandTotalOpening >= 0 ? 'Dr' : 'Cr'}</strong></td>
                <td><strong>${currentCurrencySymbol}${grandTotalDebit.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${grandTotalCredit.toFixed(2)}</strong></td>
                <td><strong class="${grandTotalClosing >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(grandTotalClosing).toFixed(2)} ${grandTotalClosing >= 0 ? 'Dr' : 'Cr'}</strong></td>
            </tr>
        `;
    }

    function updatePagination() {
        pagination.style.display = 'flex';
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    }

    // Render detailed ledger
    function renderDetailedLedger(data, openingBalance) {
         allDetailedData = data;
    detailedOpeningBalance = openingBalance;
        const tbody = detailedLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';
        
        let totalDebit = 0, totalCredit = 0;
        let rowIndex = 0;
        
        data.forEach((row) => {
            if (row.type === 'opening_balance') {
                const balance = parseFloat(row.running_balance) || 0;
                const debit = parseFloat(row.debit) || 0;
                totalDebit += debit;
                tbody.innerHTML += `
                    <tr style="background: rgba(31,123,255,0.04);">
                        <td>${row.date}</td>
                        <td>${row.description}</td>
                        <td>${row.reference}</td>
                        <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                        <td></td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                    </tr>
                `;
            } else if (row.type === 'sub_account_header') {
                // Render sub account header
                tbody.innerHTML += `
                    <tr style="background: var(--surface-2); font-weight: 600;">
                        <td colspan="6" style="padding: 12px 16px;">
                            <i class="fas fa-folder" style="margin-right: 8px; color: var(--primary);"></i>
                            ${row.sub_account_name}
                        </td>
                    </tr>
                `;
            } else if (row.type === 'sub_account_total') {
                // Render sub account total
                const balance = parseFloat(row.running_balance) || 0;
                tbody.innerHTML += `
                    <tr style="background: var(--surface-1); font-weight: 600; border-top: 2px solid var(--border-strong);">
                        <td colspan="3" style="text-align: right; padding: 12px 16px;">
                            <strong>${row.sub_account_name} Total:</strong>
                        </td>
                        <td><strong>${currentCurrencySymbol}${parseFloat(row.total_debit).toFixed(2)}</strong></td>
                        <td><strong>${currentCurrencySymbol}${parseFloat(row.total_credit).toFixed(2)}</strong></td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}"><strong>${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</strong></td>
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
                
                const creditDisplay = credit > 0 ? currentCurrencySymbol + credit.toFixed(2) : 
                                      (pdcDisplay > 0 ? `(${currentCurrencySymbol}${pdcDisplay.toFixed(2)})` : '');
                
                tbody.innerHTML += `
                    <tr class="ledger-row" data-row-id="${rowIndex}">
                        <td>${row.date}</td>
                        <td>
                            ${hasItems ? `<button class="expand-btn" data-row-id="${rowIndex}"><i class="fas fa-chevron-right"></i></button>` : ''}
                            ${row.description}
                            ${row.type === 'adjustment' && row.contra_account_name ? `<br><small style="color:var(--text-muted);font-size:11px;"><i class="fas fa-exchange-alt" style="margin-right:4px;"></i>${row.contra_account_name}</small>` : ''}
                            ${row.type === 'adjustment' && row.adj_note ? `<br><small style="color:var(--text-muted);font-size:11px;font-style:italic;">${row.adj_note}</small>` : ''}
                        </td>
                        <td>${row.reference}</td>
                        <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                        <td>${creditDisplay}</td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
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
                                                    <td>${currentCurrencySymbol}${parseFloat(item.sale_price).toFixed(2)}</td>
                                                    <td>${currentCurrencySymbol}${parseFloat(item.net_amount).toFixed(2)}</td>
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
        
        let lastRunningBalance = openingBalance;
        data.forEach((row) => {
            if (row.running_balance !== undefined) {
                lastRunningBalance = parseFloat(row.running_balance) || 0;
            }
        });

        tbody.innerHTML += `
            <tr class="totals-row">
                <td><strong>Totals</strong></td>
                <td></td>
                <td></td>
                <td><strong>${currentCurrencySymbol}${totalDebit.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totalCredit.toFixed(2)}</strong></td>
                <td><strong class="${lastRunningBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(lastRunningBalance).toFixed(2)} ${lastRunningBalance >= 0 ? 'Dr' : 'Cr'}</strong></td>
            </tr>
        `;
        
        // Add expand/collapse event listeners
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
                    expandedRows.add(rowId);
                } else {
                    itemsRow.style.display = 'none';
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                    expandedRows.delete(rowId);
                }
            });
        });
    }

    // Show notification
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

    // Reset filters
    resetFiltersBtn.addEventListener('click', function () {
        company.value = '';
        customerCode.value = '';
        customerSearch.value = '';
        customerDropdown.style.display = 'none';
        fromDate.value = '';
        toDate.value = '';
        distribution.value = '';
        subAccount.value = '';
        subAccountGroup.style.display = 'none';
        showSummaryLedger();
        validateDates();
    });

    // Export dropdown
    exportDropdown.addEventListener('click', function () {
        exportMenu.classList.toggle('show');
    });

    // Print ledger
    document.getElementById('print-ledger').addEventListener('click', function(e) {
        e.preventDefault();
        const isDetailed = detailedLedgerBtn.classList.contains('active');
        const params = new URLSearchParams({
            ledger_type: isDetailed ? 'detailed' : 'summary',
            date_from: fromDate.value,
            date_to: toDate.value
        });
        
        if (customerCode.value) {
            const encryptedCode = encryptCustomerCode(customerCode.value);
            params.append('customer_code', encryptedCode);
            params.append('customer_id', customerCode.value);
        }
        
        if (distribution.value) {
            params.append('distribution_id', distribution.value);
        }
        
        if (subAccount.value) {
            params.append('sub_account_id', subAccount.value);
        }
        
        if (isDetailed && expandedRows.size > 0) {
            params.append('expanded_rows', Array.from(expandedRows).join(','));
        }
        
        if (company.value) {
            params.append('company_id', company.value);
        }
        
        if (currencyFilter.value) {
            params.append('currency_id', currencyFilter.value);
        }
        
        window.open(`print.php?${params}`, '_blank');
    });

    document.getElementById('excel-ledger').addEventListener('click', function (e) {
    e.preventDefault();
    exportLedgerToExcel();
    exportMenu.classList.remove('show');
});

function exportLedgerToExcel() {
    const isDetailed = detailedLedgerBtn.classList.contains('active');
    const workbook = XLSX.utils.book_new();

    if (isDetailed) {
        if (!allDetailedData || allDetailedData.length === 0) {
            showNotification('No ledger data to export', 'error');
            return;
        }

        const rows = [];
        let runningBalance = detailedOpeningBalance;

        allDetailedData.forEach(row => {
            if (row.type === 'sub_account_header' || row.type === 'sub_account_total') {
                return; // skip structural rows, keep the export flat
            }
            const debit = parseFloat(row.debit) || 0;
            const credit = parseFloat(row.credit) || 0;
            const balance = parseFloat(row.running_balance) || 0;

            rows.push({
                'Date': row.date,
                'Description': row.description || '',
                'Reference': row.reference || '',
                'Debit': debit || '',
                'Credit': credit || '',
                'Balance': Math.abs(balance).toFixed(2),
                'Dr/Cr': balance >= 0 ? 'Dr' : 'Cr'
            });
        });

        const worksheet = XLSX.utils.json_to_sheet(rows);
        worksheet['!cols'] = [
            { wch: 12 }, { wch: 30 }, { wch: 14 }, { wch: 12 },
            { wch: 12 }, { wch: 12 }, { wch: 8 }
        ];
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Detailed Ledger');
    } else {
        if (!allData || allData.length === 0) {
            showNotification('No ledger data to export', 'error');
            return;
        }

        const rows = allData.map(row => {
            const opening = parseFloat(row.opening_balance) || 0;
            const closing = parseFloat(row.closing_balance) || 0;
            return {
                'Customer Name': row.customer_name,
                'Opening Balance': Math.abs(opening).toFixed(2),
                'Opening Dr/Cr': opening >= 0 ? 'Dr' : 'Cr',
                'Debit': (parseFloat(row.total_debit) || 0).toFixed(2),
                'Credit': (parseFloat(row.total_credit) || 0).toFixed(2),
                'Closing Balance': Math.abs(closing).toFixed(2),
                'Closing Dr/Cr': closing >= 0 ? 'Dr' : 'Cr'
            };
        });

        const worksheet = XLSX.utils.json_to_sheet(rows);
        worksheet['!cols'] = [
            { wch: 24 }, { wch: 14 }, { wch: 10 }, { wch: 12 },
            { wch: 12 }, { wch: 14 }, { wch: 10 }
        ];
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Summary Ledger');
    }

    const dateStr = new Date().toISOString().slice(0, 10);
    const filenamePart = isDetailed ? 'detailed' : 'summary';
    XLSX.writeFile(workbook, `customer-ledger-${filenamePart}-${dateStr}.xlsx`);
}

    // PDF ledger
    document.getElementById('pdf-ledger').addEventListener('click', function(e) {
        e.preventDefault();
        const isDetailed = detailedLedgerBtn.classList.contains('active');
        const params = new URLSearchParams({
            ledger_type: isDetailed ? 'detailed' : 'summary',
            date_from: fromDate.value,
            date_to: toDate.value,
            pdf: '1'
        });
        if (customerCode.value) {
            const encryptedCode = encryptCustomerCode(customerCode.value);
            params.append('customer_code', encryptedCode);
            params.append('customer_id', customerCode.value);
        }
        if (distribution.value) params.append('distribution_id', distribution.value);
        if (subAccount.value) params.append('sub_account_id', subAccount.value);
        if (isDetailed && expandedRows.size > 0) params.append('expanded_rows', Array.from(expandedRows).join(','));
        if (company.value) params.append('company_id', company.value);
        if (currencyFilter.value) params.append('currency_id', currencyFilter.value);
        window.open(`print.php?${params}`, '_blank');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function (event) {
        if (!exportDropdown.contains(event.target) && !exportMenu.contains(event.target)) {
            exportMenu.classList.remove('show');
        }
    });

    // Share ledger modal
    shareLedgerBtn.addEventListener('click', function () {
        const selectedCustomer = customerCode.value;
        const shareableUrl = document.getElementById('shareable-url');
        
        if (selectedCustomer) {
            const encryptedCode = encryptCustomerCode(selectedCustomer);
            shareableUrl.value = `${window.location.origin}/client/pages/financial_reports/customer_ledger/view.php?code=${encryptedCode}`;
        } else {
            shareableUrl.value = 'Please select a customer first';
        }
        
        shareModal.classList.add('show');
    });

    closeShareModal.addEventListener('click', function () {
        shareModal.classList.remove('show');
    });

    closeModalBtn.addEventListener('click', function () {
        shareModal.classList.remove('show');
    });

    // Copy URL to clipboard
    copyUrlBtn.addEventListener('click', async function () {
        const urlInput = document.getElementById('shareable-url');
        
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(urlInput.value);
            } else {
                urlInput.select();
                document.execCommand('copy');
            }
            
            const originalText = copyUrlBtn.innerHTML;
            copyUrlBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                copyUrlBtn.innerHTML = originalText;
            }, 2000);
        } catch (error) {
            showNotification('Failed to copy URL', 'error');
        }
    });

    // Pagination event listeners
    prevBtn.addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            renderPage();
            updatePagination();
        }
    });

    nextBtn.addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            renderPage();
            updatePagination();
        }
    });
});
