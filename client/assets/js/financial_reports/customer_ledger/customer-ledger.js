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
    const toggleAllRowsBtn = document.getElementById('toggle-all-rows-btn');
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
    const sortOrder = document.getElementById('sort-order');
    const roundOffToggle = document.getElementById('round-off-toggle');

    // City / Zone / Area / Salesman filters (Summary Ledger only) — city -> zone -> area
    // cascade, and an independent Salesman (employee) filter. All optional; narrow which
    // customers are included in the Summary Ledger list.
    const cityFilter = document.getElementById('city-filter');
    const zoneFilter = document.getElementById('zone-filter');
    const areaFilter = document.getElementById('area-filter');
    const salesmanFilter = document.getElementById('salesman-filter');

    // Sort By field (Summary Ledger only) — combines with Sort Order (Less to Greater /
    // Greater to Less) to do a real numeric sort on the selected column instead of just
    // reversing the list.
    const sortField = document.getElementById('sort-field');
    const sortFieldGroup = document.getElementById('sort-field-group');

    let currentPage = 1;
    let totalPages = 1;
    let allData = [];
    let allDetailedData = [];
    let detailedOpeningBalance = 0;
    let rawSummaryData = [];        // raw, unsorted data as received from server
    let rawDetailedData = [];       // raw, unsorted data as received from server
    let rawDetailedOpeningBalance = 0;
    const itemsPerPage = 10;
    let expandedRows = new Set();
    let allRowsExpanded = false; // drives the Expand All / Collapse All button's label + behavior
    let allCustomers = [];
    let currentCurrencySymbol = window.currencySymbol;

    function formatAmount(value) {
        const amount = Number(value) || 0;
        return roundOffToggle.checked
            ? Math.round(amount).toLocaleString()
            : amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatMoney(value) {
        return currentCurrencySymbol + formatAmount(value);
    }

    function formatBalance(value) {
        const amount = Number(value) || 0;
        return formatMoney(Math.abs(amount)) + (amount >= 0 ? ' Dr' : ' Cr');
    }

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
    loadCities();
    loadZones();
    loadAreas();
    loadSalesmen();

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

    // Sort order — re-sorts already-fetched data locally, no refetch needed
    sortOrder.addEventListener('change', function() {
        if (detailedLedgerBtn.classList.contains('active')) {
            if (rawDetailedData.length > 0) applyDetailedSort();
        } else {
            if (rawSummaryData.length > 0) applySummarySort();
        }
    });

    roundOffToggle.addEventListener('change', function() {
        if (rawSummaryData.length > 0) applySummarySort();
        if (rawDetailedData.length > 0) applyDetailedSort();
    });

    // Re-sort when the Sort By field changes (Summary Ledger only)
    sortField.addEventListener('change', function () {
        if (rawSummaryData.length > 0) applySummarySort();
    });
    
    // Reload customers when company changes
    document.getElementById('company').addEventListener('change', function() {
        customerCode.value = '';
        customerSearch.value = '';
        showSummaryLedger();
        loadLedgerData();
    });

    // Load cities (Summary Ledger filter)
    async function loadCities() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=cities');
            const result = await response.json();

            if (result.success) {
                cityFilter.innerHTML = '<option value="">All Cities</option>';
                result.data.forEach(city => {
                    cityFilter.innerHTML += `<option value="${city.id}">${city.city_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading cities:', error);
        }
    }

    // Load zones, optionally scoped to a city (Summary Ledger filter)
    async function loadZones(cityId = '') {
        try {
            const url = '../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=city_zones' + (cityId ? `&city_id=${cityId}` : '');
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                zoneFilter.innerHTML = '<option value="">All Zones</option>';
                result.data.forEach(zone => {
                    zoneFilter.innerHTML += `<option value="${zone.id}">${zone.city_zone_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading zones:', error);
        }
    }

    // Load areas, optionally scoped to a zone (Summary Ledger filter)
    async function loadAreas(zoneId = '') {
        try {
            const url = '../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=areas' + (zoneId ? `&city_zone_id=${zoneId}` : '');
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                areaFilter.innerHTML = '<option value="">All Areas</option>';
                result.data.forEach(area => {
                    areaFilter.innerHTML += `<option value="${area.id}">${area.area_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading areas:', error);
        }
    }

    // Load salesmen (employees) for the Salesman filter (Summary Ledger)
    async function loadSalesmen() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=salesmen');
            const result = await response.json();

            if (result.success) {
                salesmanFilter.innerHTML = '<option value="">All Salesmen</option>';
                result.data.forEach(s => {
                    salesmanFilter.innerHTML += `<option value="${s.id}">${s.full_name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading salesmen:', error);
        }
    }

    // City -> Zone -> Area cascade
    cityFilter.addEventListener('change', function () {
        areaFilter.innerHTML = '<option value="">All Areas</option>';
        loadZones(this.value);
    });

    zoneFilter.addEventListener('change', function () {
        loadAreas(this.value);
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

    // Click/focus pe bhi dropdown khulay, chahay kuch type na kiya ho — sab customers show hon
    function openCustomerDropdown() {
        if (allCustomers.length === 0) return;
        selectedIndex = -1;

        const searchTerm = customerSearch.value.toLowerCase().trim();
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
        }
    }

    customerSearch.addEventListener('focus', openCustomerDropdown);
    customerSearch.addEventListener('click', openCustomerDropdown);

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

        // Sort By field is only relevant for Summary Ledger
        sortFieldGroup.style.display = 'block';
        if (rawSummaryData.length > 0) applySummarySort();

        // Expand All / Collapse All only applies to Detailed Ledger's invoice rows.
        toggleAllRowsBtn.style.display = 'none';
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

        // Hide Sort By field in Detailed Ledger view
        sortFieldGroup.style.display = 'none';

        updateToggleAllRowsButton();
    }

    // Shows/hides the Expand All / Collapse All button (only worth showing when the
    // currently-rendered Detailed Ledger actually has at least one expandable invoice row)
    // and keeps its label in sync with allRowsExpanded.
    function updateToggleAllRowsButton() {
        const hasExpandableRows = detailedLedgerTable.querySelectorAll('.expand-btn').length > 0;
        toggleAllRowsBtn.style.display = hasExpandableRows ? 'inline-flex' : 'none';
        toggleAllRowsBtn.innerHTML = allRowsExpanded
            ? '<i class="fas fa-compress-alt"></i> Collapse All'
            : '<i class="fas fa-expand-alt"></i> Expand All';
    }

    toggleAllRowsBtn.addEventListener('click', function () {
        const expandBtns = detailedLedgerTable.querySelectorAll('.expand-btn');
        const shouldExpand = !allRowsExpanded;

        expandBtns.forEach(btn => {
            const rowId = btn.dataset.rowId;
            const itemsRow = document.getElementById(`items-${rowId}`);
            const icon = btn.querySelector('i');
            if (!itemsRow) return;

            itemsRow.style.display = shouldExpand ? 'table-row' : 'none';
            icon.classList.toggle('fa-chevron-down', shouldExpand);
            icon.classList.toggle('fa-chevron-right', !shouldExpand);

            if (shouldExpand) {
                expandedRows.add(rowId);
            } else {
                expandedRows.delete(rowId);
            }
        });

        allRowsExpanded = shouldExpand;
        updateToggleAllRowsButton();
    });

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
    // Filters shared by both the Summary and Detailed requests below.
    function sharedLedgerParams() {
        const params = new URLSearchParams({
            from_date: fromDate.value,
            to_date: toDate.value
        });
        if (distribution.value) params.append('distribution_id', distribution.value);
        if (company.value) params.append('company_id', company.value);
        if (currencyFilter.value) params.append('currency_id', currencyFilter.value);
        if (cityFilter.value) params.append('city_id', cityFilter.value);
        if (zoneFilter.value) params.append('zone_id', zoneFilter.value);
        if (areaFilter.value) params.append('area_id', areaFilter.value);
        if (salesmanFilter.value) params.append('salesman_id', salesmanFilter.value);
        return params;
    }

    // Loads Summary Ledger, and Detailed Ledger too whenever a customer is selected -
    // regardless of which tab happens to be active when "Load Ledger" is clicked. That way
    // switching between Summary/Detailed afterward always shows current data for both, not
    // just whichever view was active at load time (previously only the active tab's data
    // actually refreshed, so the other tab kept showing stale/empty data until you switched
    // to it AND clicked Load Ledger again).
    async function loadLedgerData() {
        if (!validateDates()) return;

        const requests = [];

        const summaryParams = sharedLedgerParams();
        summaryParams.set('type', 'summary');
        requests.push(
            fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?${summaryParams}`)
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        renderSummaryLedger(result.data);
                    } else {
                        showNotification(result.message || 'Error loading Summary Ledger', 'error');
                    }
                })
                .catch(() => showNotification('Network error occurred while loading Summary Ledger', 'error'))
        );

        if (customerCode.value) {
            const detailedParams = sharedLedgerParams();
            detailedParams.set('type', 'detailed');
            detailedParams.append('customer_id', customerCode.value);
            if (subAccount.value) detailedParams.append('sub_account_id', subAccount.value);
            requests.push(
                fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?${detailedParams}`)
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            renderDetailedLedger(result.data, result.opening_balance);
                        } else {
                            showNotification(result.message || 'Error loading Detailed Ledger', 'error');
                        }
                    })
                    .catch(() => showNotification('Network error occurred while loading Detailed Ledger', 'error'))
            );
        } else if (detailedLedgerBtn.classList.contains('active')) {
            // Detailed tab is active but no customer picked - same guard showDetailedLedger() uses.
            showCustomerError();
        }

        await Promise.all(requests);
    }

    // Render summary ledger
    function renderSummaryLedger(data) {
        rawSummaryData = data;
        currentPage = 1;
        applySummarySort();
    }

    // Sorts the raw summary data by the selected Sort By field (customer name, opening
    // balance, debit, credit, or closing balance) and applies the Sort Order (Less to
    // Greater / Greater to Less) as an actual numeric/alphabetical sort — not just a
    // reverse of the server order.
    function applySummarySort() {
        const field = sortField.value || 'closing_balance';
        const direction = sortOrder.value === 'desc' ? -1 : 1;

        allData = rawSummaryData.slice().sort((a, b) => {
            const valA = parseFloat(a[field]) || 0;
            const valB = parseFloat(b[field]) || 0;
            return (valA - valB) * direction;
        });

        currentPage = 1;
        totalPages = Math.ceil(allData.length / itemsPerPage) || 1;
        if (currentPage > totalPages) currentPage = 1;
        renderPage();
        updatePagination();
    }

    function renderPage() {
        const tbody = summaryLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';

        if (allData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align:center; padding: 24px; color: var(--text-muted, #888);">
                        No records match the selected filter.
                    </td>
                </tr>
            `;
            return;
        }
        
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
                    <td class="${opening >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(opening)}</td>
                    <td>${formatMoney(debit)}</td>
                    <td>${formatMoney(credit)}</td>
                    <td class="${closing >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(closing)}</td>
                </tr>
            `;
        });
        
        // Calculate totals for all (filtered) data
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
                <td><strong class="${grandTotalOpening >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(grandTotalOpening)}</strong></td>
                <td><strong>${formatMoney(grandTotalDebit)}</strong></td>
                <td><strong>${formatMoney(grandTotalCredit)}</strong></td>
                <td><strong class="${grandTotalClosing >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(grandTotalClosing)}</strong></td>
            </tr>
        `;
    }

    function updatePagination() {
        pagination.style.display = 'flex';
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    }

    // Reorders a detailed-ledger row array for the "Greater to Less" sort option.
    // 'opening_balance', 'sub_account_header' and 'sub_account_total' rows are
    // structural anchors and stay where they are; only the transaction rows
    // between them get reversed, so sub-account grouping / totals still line up.
    // Each row already carries its own correct running_balance, computed
    // chronologically on the backend, so reordering for display is safe.
    function reverseTransactionRuns(data) {
        const specialTypes = ['opening_balance', 'sub_account_header', 'sub_account_total'];
        const result = [];
        let buffer = [];
        const flush = () => {
            if (buffer.length) {
                result.push(...buffer.reverse());
                buffer = [];
            }
        };
        data.forEach(row => {
            if (specialTypes.includes(row.type)) {
                flush();
                result.push(row);
            } else {
                buffer.push(row);
            }
        });
        flush();
        return result;
    }

    // Render detailed ledger
    function renderDetailedLedger(data, openingBalance) {
        rawDetailedData = data;
        rawDetailedOpeningBalance = openingBalance;
        applyDetailedSort();
    }

    function applyDetailedSort() {
        const sorted = sortOrder.value === 'desc' ? reverseTransactionRuns(rawDetailedData) : rawDetailedData;
        renderDetailedLedgerRows(sorted, rawDetailedOpeningBalance);
    }

    function renderDetailedLedgerRows(data, openingBalance) {
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
                        <td></td>
                        <td>${row.description}</td>
                        <td>${row.reference}</td>
                        <td>${debit > 0 ? formatMoney(debit) : ''}</td>
                        <td></td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(balance)}</td>
                    </tr>
                `;
            } else if (row.type === 'sub_account_header') {
                tbody.innerHTML += `
                    <tr style="background: var(--surface-2); font-weight: 600;">
                        <td colspan="7" style="padding: 12px 16px;">
                            <i class="fas fa-folder" style="margin-right: 8px; color: var(--primary);"></i>
                            ${row.sub_account_name}
                        </td>
                    </tr>
                `;
            } else if (row.type === 'sub_account_total') {
                const balance = parseFloat(row.running_balance) || 0;
                tbody.innerHTML += `
                    <tr style="background: var(--surface-1); font-weight: 600; border-top: 2px solid var(--border-strong);">
                        <td colspan="4" style="text-align: right; padding: 12px 16px;">
                            <strong>${row.sub_account_name} Total:</strong>
                        </td>
                        <td><strong>${formatMoney(row.total_debit)}</strong></td>
                        <td><strong>${formatMoney(row.total_credit)}</strong></td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}"><strong>${formatBalance(balance)}</strong></td>
                    </tr>
                `;
            } else {
                const debit = parseFloat(row.debit) || 0;
                const credit = parseFloat(row.credit) || 0;
                const balance = parseFloat(row.running_balance) || 0;
                const pdcDisplay = parseFloat(row.pdc_display) || 0;
                const coveredAmount = parseFloat(row.covered_amount) || 0;
                const hasItems = (row.type === 'invoice' || row.type === 'return') && row.items && row.items.length > 0;

                totalDebit += debit;
                totalCredit += credit;

                // Bracketed = shown for reference only, not counted in the balance
                const creditDisplay = credit > 0 ? formatMoney(credit) :
                                      (pdcDisplay > 0 ? `(${formatMoney(pdcDisplay)})` :
                                      (coveredAmount > 0 ? `(${formatMoney(coveredAmount)})` : ''));

                const receivedDate = row.type === 'pdc' ? (row.received_date || '') : '';

                tbody.innerHTML += `
                    <tr class="ledger-row" data-row-id="${rowIndex}">
                        <td>${row.date}</td>
                        <td>${receivedDate}</td>
                        <td>
                            ${hasItems ? `<button class="expand-btn" data-row-id="${rowIndex}"><i class="fas fa-chevron-right"></i></button>` : ''}
                            ${row.description}
                            ${row.type === 'pdc' && row.against_rvs ? `<br><small style="color:var(--text-muted);font-size:11px;"><i class="fas fa-receipt" style="margin-right:4px;"></i>Against ${row.against_rvs}</small>` : ''}
                            ${coveredAmount > 0 ? `<br><small style="color:var(--text-muted);font-size:11px;"><i class="fas fa-link" style="margin-right:4px;"></i>Covered by PDC</small>` : ''}
                            ${row.type === 'adjustment' && row.contra_account_name ? `<br><small style="color:var(--text-muted);font-size:11px;"><i class="fas fa-exchange-alt" style="margin-right:4px;"></i>${row.contra_account_name}</small>` : ''}
                            ${row.type === 'adjustment' && row.adj_note ? `<br><small style="color:var(--text-muted);font-size:11px;font-style:italic;">${row.adj_note}</small>` : ''}
                        </td>
                        <td>
                            ${row.reference}
                            ${row.type === 'payment' && row.against_cheque_no ? `<br><small style="color:var(--text-muted);font-size:11px;"><i class="fas fa-money-check-alt" style="margin-right:4px;"></i>Against Cheque ${row.against_cheque_no}</small>` : ''}
                        </td>
                        <td>${debit > 0 ? formatMoney(debit) : ''}</td>
                        <td>${creditDisplay}</td>
                        <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(balance)}</td>
                    </tr>
                `;

                if (hasItems) {
                    tbody.innerHTML += `
                        <tr class="items-row" id="items-${rowIndex}" style="display: none;">
                            <td colspan="7">
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
                <td></td>
                <td><strong>${formatMoney(totalDebit)}</strong></td>
                <td><strong>${formatMoney(totalCredit)}</strong></td>
                <td><strong class="${lastRunningBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${formatBalance(lastRunningBalance)}</strong></td>
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
                    expandedRows.add(rowId);
                } else {
                    itemsRow.style.display = 'none';
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                    expandedRows.delete(rowId);
                }

                // Keep the Expand All / Collapse All button's label accurate even after a
                // manual per-row toggle.
                const allExpandBtns = detailedLedgerTable.querySelectorAll('.expand-btn');
                allRowsExpanded = allExpandBtns.length > 0 && Array.from(allExpandBtns).every(b => {
                    const r = document.getElementById(`items-${b.dataset.rowId}`);
                    return r && r.style.display !== 'none';
                });
                updateToggleAllRowsButton();
            });
        });

        // Freshly rendered data - nothing is expanded yet.
        expandedRows.clear();
        allRowsExpanded = false;
        updateToggleAllRowsButton();
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
        sortOrder.value = 'asc';

        // reset Sort By field
        sortField.value = 'closing_balance';

        // reset City / Zone / Area / Salesman filters
        cityFilter.value = '';
        zoneFilter.value = '';
        areaFilter.value = '';
        salesmanFilter.value = '';
        loadZones();
        loadAreas();

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
            date_to: toDate.value,
            round_off: roundOffToggle.checked ? '1' : '0'
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

        if (sortOrder.value) {
            params.append('sort_order', sortOrder.value);
        }

        if (cityFilter.value) {
            params.append('city_id', cityFilter.value);
        }

        if (zoneFilter.value) {
            params.append('zone_id', zoneFilter.value);
        }

        if (areaFilter.value) {
            params.append('area_id', areaFilter.value);
        }

        if (salesmanFilter.value) {
            params.append('salesman_id', salesmanFilter.value);
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

            allDetailedData.forEach(row => {
                if (row.type === 'sub_account_header' || row.type === 'sub_account_total') {
                    return; // skip structural rows, keep the export flat
                }
                const debit = parseFloat(row.debit) || 0;
                const credit = parseFloat(row.credit) || 0;
                const covered = parseFloat(row.covered_amount) || 0;
                const balance = parseFloat(row.running_balance) || 0;

                rows.push({
                    'Date': row.date,
                    'Received Date': row.type === 'pdc' ? (row.received_date || '') : '',
                    'Description': row.description || '',
                    'Against RV': row.type === 'pdc' ? (row.against_rvs || '') : '',
                    'Reference': (row.reference || '') + (row.against_cheque_no ? ` (Against Cheque ${row.against_cheque_no})` : ''),
                    'Debit': debit || '',
                    'Credit': credit || (covered ? `(${covered.toFixed(2)})` : ''),
                    'Balance': Math.abs(balance).toFixed(2),
                    'Dr/Cr': balance >= 0 ? 'Dr' : 'Cr'
                });
            });

            const worksheet = XLSX.utils.json_to_sheet(rows);
            worksheet['!cols'] = [
                { wch: 12 }, { wch: 14 }, { wch: 30 }, { wch: 22 }, { wch: 14 },
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
            pdf: '1',
            round_off: roundOffToggle.checked ? '1' : '0'
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
        if (sortOrder.value) params.append('sort_order', sortOrder.value);
        if (cityFilter.value) params.append('city_id', cityFilter.value);
        if (zoneFilter.value) params.append('zone_id', zoneFilter.value);
        if (areaFilter.value) params.append('area_id', areaFilter.value);
        if (salesmanFilter.value) params.append('salesman_id', salesmanFilter.value);
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