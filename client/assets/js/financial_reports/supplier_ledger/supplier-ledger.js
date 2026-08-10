document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const summaryLedgerBtn = document.getElementById('summary-ledger-btn');
    const detailedLedgerBtn = document.getElementById('detailed-ledger-btn');
    const supplierCode = document.getElementById('supplier-code');
    const supplierSearch = document.getElementById('supplier-search');
    const supplierDropdown = document.getElementById('supplier-dropdown');
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
    const togglePassword = document.getElementById('toggle-password');
    const supplierPassword = document.getElementById('supplier-password');
    const copyUrlBtn = document.getElementById('copy-url-btn');
    const pagination = document.getElementById('pagination');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageInfo = document.getElementById('page-info');
    const subAccountFilter = document.getElementById('sub-account-filter');
    const subAccount = document.getElementById('sub-account');
    const companyFilter = document.getElementById('company-filter');
    const currencyFilter = document.getElementById('currency-filter');
    const symbolOverride = document.getElementById('symbol-override');

    let currentPage = 1;
    let totalPages = 1;
    let allData = [];
    let allDetailedGrouped = null;   // add this
let allDetailedSubAccounts = []; // add this
let detailedOpeningBalance = 0;  // add this
    const itemsPerPage = 10;
    let expandedInvoices = new Set();
    let allSuppliers = [];
    let currentCurrencySymbol = window.currencySymbol;

    // Set default dates
    const today = new Date();
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(today.getMonth() - 1);

    fromDate.value = '';
    toDate.value = '';

    // Load companies on page load
    loadCompanies();
    loadCurrencies();
    loadSuppliers();

    // Searchable dropdown functionality
    let selectedIndex = -1;
    
    supplierSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        selectedIndex = -1;
        
        const filtered = searchTerm.length === 0 
            ? allSuppliers 
            : allSuppliers.filter(supplier => 
                supplier.supplier_name.toLowerCase().includes(searchTerm) ||
                supplier.supplier_code.toLowerCase().includes(searchTerm)
            );
        
        if (filtered.length > 0) {
            supplierDropdown.innerHTML = filtered.map(supplier => 
                `<div class="supplier-option" data-id="${supplier.id}">${supplier.supplier_code} - ${supplier.supplier_name}</div>`
            ).join('');
            supplierDropdown.style.display = 'block';
        } else {
            supplierDropdown.style.display = 'none';
        }
    });
    
    supplierSearch.addEventListener('focus', function() {
        if (allSuppliers.length > 0) {
            const searchTerm = this.value.toLowerCase().trim();
            const filtered = searchTerm.length === 0 
                ? allSuppliers 
                : allSuppliers.filter(supplier => 
                    supplier.supplier_name.toLowerCase().includes(searchTerm) ||
                    supplier.supplier_code.toLowerCase().includes(searchTerm)
                );
            
            if (filtered.length > 0) {
                supplierDropdown.innerHTML = filtered.map(supplier => 
                    `<div class="supplier-option" data-id="${supplier.id}">${supplier.supplier_code} - ${supplier.supplier_name}</div>`
                ).join('');
                supplierDropdown.style.display = 'block';
            }
        }
    });

    // Arrow key navigation
    supplierSearch.addEventListener('keydown', function(e) {
        const options = supplierDropdown.querySelectorAll('.supplier-option');
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

    // Handle supplier selection
    supplierDropdown.addEventListener('click', function(e) {
        if (e.target.classList.contains('supplier-option')) {
            const supplierId = e.target.dataset.id;
            const supplierText = e.target.textContent;
            
            supplierSearch.value = supplierText;
            supplierCode.value = supplierId;
            supplierDropdown.style.display = 'none';
            
            if (supplierId) {
                showDetailedLedger();
            } else {
                showSummaryLedger();
            }
        }
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!supplierSearch.contains(e.target) && !supplierDropdown.contains(e.target)) {
            supplierDropdown.style.display = 'none';
        }
    });

    // Toggle ledger type
    function showSummaryLedger() {
        hideSupplierError();
        summaryLedgerBtn.classList.add('active');
        detailedLedgerBtn.classList.remove('active');
        summaryLedgerTable.style.display = 'table';
        detailedLedgerTable.style.display = 'none';
        ledgerTitle.textContent = 'Summary Ledger';
        subAccountFilter.style.display = 'none';
    }

    function showDetailedLedger() {
        if (!supplierCode.value) {
            showSupplierError();
            return;
        }
        hideSupplierError();
        detailedLedgerBtn.classList.add('active');
        summaryLedgerBtn.classList.remove('active');
        detailedLedgerTable.style.display = 'table';
        summaryLedgerTable.style.display = 'none';
        ledgerTitle.textContent = 'Detailed Ledger';
        subAccountFilter.style.display = 'block';
        loadSubAccounts();
        loadLedgerData();
    }

    summaryLedgerBtn.addEventListener('click', showSummaryLedger);
    detailedLedgerBtn.addEventListener('click', showDetailedLedger);

    // Show/hide supplier error
    function showSupplierError() {
        let errorCard = document.getElementById('supplier-error-card');
        if (!errorCard) {
            errorCard = document.createElement('div');
            errorCard.id = 'supplier-error-card';
            errorCard.className = 'card';
            errorCard.innerHTML = '<p style="color: var(--error); text-align: center; margin: 0;">Please select a Supplier Code first to view detailed ledger.</p>';
            document.querySelector('.card:last-of-type').insertAdjacentElement('afterend', errorCard);
        }
        errorCard.style.display = 'block';
        const tableCard = document.querySelector('.card:nth-last-child(2)');
        if (tableCard) {
            tableCard.style.display = 'none';
        }
    }

    function hideSupplierError() {
        const errorCard = document.getElementById('supplier-error-card');
        if (errorCard) {
            errorCard.style.display = 'none';
        }
        const tableCard = document.querySelector('.card:nth-last-child(2)');
        if (tableCard) {
            tableCard.style.display = 'block';
        }
    }

    // Supplier code change handler
    supplierCode.addEventListener('change', function () {
        if (supplierCode.value) {
            showDetailedLedger();
        } else {
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

    // Load companies
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/supplier_ledger/get-companies.php');
            const result = await response.json();
            
            if (result.success) {
                companyFilter.innerHTML = '<option value="">All Companies</option>';
                result.data.forEach(company => {
                    companyFilter.innerHTML += `<option value="${company.id}">${company.company_name}</option>`;
                });
                
                if (result.data.length === 1) {
                    companyFilter.value = result.data[0].id;
                }
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    // Load currencies
    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=currencies');
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
        if (summaryLedgerBtn.classList.contains('active') || (detailedLedgerBtn.classList.contains('active') && supplierCode.value)) {
            await loadLedgerData();
        }
    });

    // Symbol override — only changes display symbol, no conversion
    symbolOverride.addEventListener('change', function() {
        currentCurrencySymbol = this.value;
        if (allData.length > 0) renderPage();
    });

    // Load suppliers
    async function loadSuppliers() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=suppliers');
            const result = await response.json();
            
            if (result.success) {
                allSuppliers = result.data;
                supplierCode.innerHTML = '<option value="">Select Supplier</option>';
                result.data.forEach(supplier => {
                    supplierCode.innerHTML += `<option value="${supplier.id}">${supplier.supplier_code} - ${supplier.supplier_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading suppliers:', error);
        }
    }

    // Load sub accounts
    async function loadSubAccounts() {
        if (!supplierCode.value) return;
        try {
            const response = await fetch(`../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=sub_accounts&supplier_id=${supplierCode.value}`);
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
            type: isDetailed ? 'detailed' : 'summary'
        });

        if (fromDate.value) {
            params.append('from_date', fromDate.value);
        }
        if (toDate.value) {
            params.append('to_date', toDate.value);
        }

        if (isDetailed && supplierCode.value) {
            params.append('supplier_id', supplierCode.value);
            if (subAccount.value) {
                params.append('sub_account_id', subAccount.value);
            }
        }
        
        if (companyFilter.value) {
            params.append('company_id', companyFilter.value);
        }
        
        if (currencyFilter.value) {
            params.append('currency_id', currencyFilter.value);
        }

        console.log('API URL:', `../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?${params}`);
        console.log('Is Detailed:', isDetailed, 'Supplier ID:', supplierCode.value);

        try {
            const response = await fetch(`../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?${params}`);
            const result = await response.json();
            
            console.log('API Response:', result);
            
            if (result.success) {
                if (isDetailed) {
                    renderDetailedLedger(result.data, result.sub_accounts, result.opening_balance);
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
        pagination.style.display = 'flex';
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
                    <td>${row.supplier_name}</td>
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
    function renderDetailedLedger(groupedData, subAccounts, openingBalance) {
        allDetailedGrouped = groupedData;
    allDetailedSubAccounts = subAccounts;
    detailedOpeningBalance = openingBalance;
        pagination.style.display = 'none';
        const tbody = detailedLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';

        const openingLabel = fromDate.value ? 'Soft Opening Balance' : 'Opening Balance';
        tbody.innerHTML += `
            <tr style="background:#f0f9ff;">
                <td>${fromDate.value || '-'}</td>
                <td><em>${openingLabel}</em></td>
                <td>-</td>
                <td></td>
                <td></td>
                <td class="${openingBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(openingBalance).toFixed(2)} ${openingBalance >= 0 ? 'Dr' : 'Cr'}</td>
            </tr>
        `;

        let totalDebit = 0, totalCredit = 0;
        const renderedSubAccounts = new Set();

        function renderRow(row) {
            const debit = parseFloat(row.debit) || 0;
            const credit = parseFloat(row.credit) || 0;
            const balance = parseFloat(row.running_balance) || 0;
            const isPDC = row.pdc_status !== undefined;
            const isNonApprovedPDC = isPDC && row.pdc_status !== 'Approved';
            const isExpense = row.description && row.description.startsWith('Expense Voucher');

            if (!isNonApprovedPDC) totalDebit += debit;
            totalCredit += credit;

            let rowStyle = '';
            if (isNonApprovedPDC) rowStyle = 'color:#e8b23f; font-style:italic;';
            else if (isExpense) rowStyle = 'background:#fff3e0;';

            const typeTag = isExpense
                ? `<span style="display:inline-block;margin-left:6px;padding:1px 7px;font-size:11px;border-radius:10px;font-weight:600;background:${debit > 0 ? '#fde8e8' : '#e8f5e9'};color:${debit > 0 ? '#c0392b' : '#27ae60'};">${debit > 0 ? 'DR' : 'CR'}</span>`
                : '';

            tbody.innerHTML += `
                <tr style="${rowStyle}">
                    <td>${row.date}</td>
                    <td>
                        ${row.description}${typeTag}
                        ${row.invoice_id || row.return_id ? `<button class="btn-expand" data-type="${row.invoice_id ? 'invoice' : 'return'}" data-id="${row.invoice_id || row.return_id}" style="margin-left:8px;padding:2px 8px;font-size:11px;background:var(--primary);color:white;border:none;border-radius:4px;cursor:pointer;">Expand</button>` : ''}
                        ${row.type === 'adjustment' && row.contra_account_name ? `<br><small style="color:var(--text-muted);font-size:11px;"><i class="fas fa-exchange-alt" style="margin-right:4px;"></i>${row.contra_account_name}</small>` : ''}
                        ${row.type === 'adjustment' && row.adj_note ? `<br><small style="color:var(--text-muted);font-size:11px;font-style:italic;">${row.adj_note}</small>` : ''}
                    </td>
                    <td>${row.reference}</td>
                    <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                    <td>${credit > 0 ? currentCurrencySymbol + credit.toFixed(2) : ''}</td>
                    <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                </tr>
            `;
        }

        // Transactions without sub account
        const noSubTxns = groupedData[null] || groupedData[''] || groupedData[undefined] || [];
        noSubTxns.forEach(renderRow);

        // Known sub accounts
        subAccounts.forEach(sa => {
            renderedSubAccounts.add(sa.id.toString());
            const saOpening = (parseFloat(sa.debit) || 0) - (parseFloat(sa.credit) || 0);
            const saTxns = groupedData[sa.id] || [];

            tbody.innerHTML += `<tr style="background:#e3f2fd;font-weight:600;"><td colspan="6"><strong>Sub Account: ${sa.sub_account_name}</strong></td></tr>`;

            if (saOpening !== 0) {
                tbody.innerHTML += `
                    <tr style="background:#f0f9ff;">
                        <td>${fromDate.value || '-'}</td>
                        <td><em>${openingLabel}</em></td>
                        <td>-</td><td></td><td></td>
                        <td class="${saOpening >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(saOpening).toFixed(2)} ${saOpening >= 0 ? 'Dr' : 'Cr'}</td>
                    </tr>`;
            }
            saTxns.forEach(renderRow);
        });

        // Remaining sub accounts not in sub_accounts array
        Object.keys(groupedData).forEach(key => {
            if (key && key !== 'null' && key !== '' && key !== 'undefined' && !renderedSubAccounts.has(key)) {
                tbody.innerHTML += `<tr style="background:#e3f2fd;font-weight:600;"><td colspan="6"><strong>Sub Account ID: ${key}</strong></td></tr>`;
                groupedData[key].forEach(renderRow);
            }
        });

        const finalBalance = openingBalance - totalCredit + totalDebit;
        tbody.innerHTML += `
            <tr class="totals-row">
                <td><strong>Totals</strong></td>
                <td></td><td></td>
                <td><strong>${currentCurrencySymbol}${totalDebit.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totalCredit.toFixed(2)}</strong></td>
                <td><strong class="${finalBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${currentCurrencySymbol}${Math.abs(finalBalance).toFixed(2)} ${finalBalance >= 0 ? 'Dr' : 'Cr'}</strong></td>
            </tr>
        `;
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
        supplierCode.value = '';
        supplierSearch.value = '';
        supplierDropdown.style.display = 'none';
        fromDate.value = '';
        toDate.value = '';
        subAccount.value = '';
        companyFilter.value = '';
        showSummaryLedger();
        validateDates();
    });

    // Export dropdown
    exportDropdown.addEventListener('click', function () {
        exportMenu.classList.toggle('show');
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
        if (!allDetailedGrouped) {
            showNotification('No ledger data to export', 'error');
            return;
        }

        const rows = [];
        const openingLabel = fromDate.value ? 'Soft Opening Balance' : 'Opening Balance';

        rows.push({
            'Sub Account': '',
            'Date': fromDate.value || '-',
            'Description': openingLabel,
            'Reference': '-',
            'Debit': '',
            'Credit': '',
            'Balance': Math.abs(detailedOpeningBalance).toFixed(2),
            'Dr/Cr': detailedOpeningBalance >= 0 ? 'Dr' : 'Cr'
        });

        const pushTxns = (txns, subAccountLabel) => {
            (txns || []).forEach(row => {
                const debit = parseFloat(row.debit) || 0;
                const credit = parseFloat(row.credit) || 0;
                const balance = parseFloat(row.running_balance) || 0;
                rows.push({
                    'Sub Account': subAccountLabel,
                    'Date': row.date,
                    'Description': row.description || '',
                    'Reference': row.reference || '',
                    'Debit': debit || '',
                    'Credit': credit || '',
                    'Balance': Math.abs(balance).toFixed(2),
                    'Dr/Cr': balance >= 0 ? 'Dr' : 'Cr'
                });
            });
        };

        const noSubTxns = allDetailedGrouped[null] || allDetailedGrouped[''] || allDetailedGrouped[undefined] || [];
        pushTxns(noSubTxns, '');

        const renderedSubAccounts = new Set();
        allDetailedSubAccounts.forEach(sa => {
            renderedSubAccounts.add(sa.id.toString());
            pushTxns(allDetailedGrouped[sa.id], sa.sub_account_name);
        });

        Object.keys(allDetailedGrouped).forEach(key => {
            if (key && key !== 'null' && key !== '' && key !== 'undefined' && !renderedSubAccounts.has(key)) {
                pushTxns(allDetailedGrouped[key], `Sub Account ID: ${key}`);
            }
        });

        const worksheet = XLSX.utils.json_to_sheet(rows);
        worksheet['!cols'] = [
            { wch: 18 }, { wch: 12 }, { wch: 30 }, { wch: 14 },
            { wch: 12 }, { wch: 12 }, { wch: 12 }, { wch: 8 }
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
                'Supplier Name': row.supplier_name,
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
    XLSX.writeFile(workbook, `supplier-ledger-${filenamePart}-${dateStr}.xlsx`);
}
    // Print ledger
    document.getElementById('print-ledger').addEventListener('click', function(e) {
        e.preventDefault();
        const isDetailed = detailedLedgerBtn.classList.contains('active');
        const params = new URLSearchParams({
            ledger_type: isDetailed ? 'detailed' : 'summary',
            date_from: fromDate.value,
            date_to: toDate.value
        });
        
        if (supplierCode.value) {
            params.append('supplier_id', supplierCode.value);
        }
        
        if (companyFilter.value) {
            params.append('company_id', companyFilter.value);
        }
        
        if (currencyFilter.value) {
            params.append('currency_id', currencyFilter.value);
        }
        
        if (expandedInvoices.size > 0) {
            params.append('expanded', Array.from(expandedInvoices).join(','));
        }
        
        window.open(`print.php?${params}`, '_blank');
    });

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
        if (supplierCode.value) params.append('supplier_id', supplierCode.value);
        if (companyFilter.value) params.append('company_id', companyFilter.value);
        if (currencyFilter.value) params.append('currency_id', currencyFilter.value);
        if (expandedInvoices.size > 0) params.append('expanded', Array.from(expandedInvoices).join(','));
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
        shareModal.classList.add('show');
    });

    closeShareModal.addEventListener('click', function () {
        shareModal.classList.remove('show');
    });

    closeModalBtn.addEventListener('click', function () {
        shareModal.classList.remove('show');
    });

    // Toggle password visibility
    togglePassword.addEventListener('click', function () {
        const type = supplierPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        supplierPassword.setAttribute('type', type);
        togglePassword.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
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

    // Load initial data
    loadLedgerData();

    // Handle expand/collapse for invoice items
    detailedLedgerTable.addEventListener('click', async function(e) {
        if (e.target.classList.contains('btn-expand')) {
            const btn = e.target;
            const type = btn.dataset.type;
            const id = btn.dataset.id;
            const row = btn.closest('tr');
            const key = `${type}_${id}`;
            
            if (btn.textContent === 'Expand') {
                try {
                    const response = await fetch(`../../../../server/api/financial_reports/supplier_ledger/get-items.php?type=${type}&id=${id}`);
                    const result = await response.json();
                    
                    if (result.success && result.data.length > 0) {
                        let itemsHtml = '';
                        result.data.forEach(item => {
                            itemsHtml += `
                                <tr class="item-row" style="background: #f9fafb; font-size: 13px;">
                                    <td></td>
                                    <td style="padding-left: 30px;">→ ${item.product_name} (${item.quantity} ${item.uom_name})</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            `;
                        });
                        row.insertAdjacentHTML('afterend', itemsHtml);
                        btn.textContent = 'Collapse';
                        expandedInvoices.add(key);
                    }
                } catch (error) {
                    console.error('Error loading items:', error);
                }
            } else {
                let nextRow = row.nextElementSibling;
                while (nextRow && nextRow.classList.contains('item-row')) {
                    const toRemove = nextRow;
                    nextRow = nextRow.nextElementSibling;
                    toRemove.remove();
                }
                btn.textContent = 'Expand';
                expandedInvoices.delete(key);
            }
        }
    });
});
