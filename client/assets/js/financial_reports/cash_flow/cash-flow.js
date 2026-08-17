// Global variables
let cashFlowData = [];
let currentCurrencySymbol = 'Rs';
let currentPage = 1;
let totalPages = 1;
let cashFlowChart = null;
let isGraphView = false;

// Function to format currency
function formatCurrency(amount) {
    const num = parseFloat(amount) || 0;
    return currentCurrencySymbol + ' ' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Function to fetch cash flow data
async function fetchCashFlowData() {
    try {
        const params = new URLSearchParams({
            date_range: document.getElementById('dateRange').value,
            from_date: document.getElementById('fromDate').value,
            to_date: document.getElementById('toDate').value,
            description: document.getElementById('descriptionFilter').value,
            account: document.getElementById('accountFilter').value,
            method: document.getElementById('methodFilter').value,
            bank_account: document.getElementById('bankAccountFilter').value,
            type: document.getElementById('typeFilter').value,
            page: currentPage,
            limit: 15
        });
        
        const companyFilter = document.getElementById('companyFilter');
        if (companyFilter.value) {
            params.append('company_id', companyFilter.value);
        }
        
        const currencyFilter = document.getElementById('currencyFilter');
        if (currencyFilter.value) {
            params.append('currency_id', currencyFilter.value);
        }
        
        const response = await fetch(`../../../../server/api/financial_reports/cash_flow/cash-flow.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            cashFlowData = result.data;
            currentCurrencySymbol = result.currency_symbol;
            if (result.pagination) {
                currentPage = result.pagination.current_page;
                totalPages = result.pagination.total_pages;
                updatePaginationButtons();
            }
            // Log debug info
            if (result.debug) {
                console.log('=== CASH FLOW DEBUG INFO ===');
                console.log('Bank Opening (raw):', result.debug.bank_opening_raw);
                console.log('Bank Credit (raw):', result.debug.bank_credit_raw);
                console.log('Bank Opening Balance (calculated):', result.debug.bank_opening_balance);
                console.log('Cash Opening:', result.debug.cash_opening);
                console.log('Soft Opening Cash:', result.debug.soft_opening_cash);
                console.log('Soft Opening Bank:', result.debug.soft_opening_bank);
                console.log('Combined Opening Balance:', result.debug.combined_opening);
                console.log('Base Currency ID:', result.debug.base_currency_id);
                console.log('Target Currency ID:', result.debug.target_currency_id);
                console.log('===========================');
            }
            return result;
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error fetching cash flow data:', error);
        showNotification('Error loading data: ' + error.message, 'error');
        return { data: [], totals: { inflow: 0, outflow: 0, cash_balance: 0, bank_balance: 0 }, count: 0 };
    }
}

// Function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Function to populate table with data
function populateTable(data) {
    const tableBody = document.getElementById('tableBody');
    tableBody.innerHTML = '';

    data.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
                    <td>${formatDate(item.date)}</td>
                    <td><strong>${item.description}</strong></td>
                    <td><span class="account-badge">${item.account}</span></td>
                    <td>
                        <span class="method-tag ${item.method.toLowerCase()}">
                            ${item.method}
                        </span>
                    </td>
                    <td>${item.bank_account}</td>
                    <td class="text-right ${item.inflow > 0 ? 'positive' : ''}">
                        ${item.inflow > 0 ? `<strong>${formatCurrency(item.inflow)}</strong>` : '-'}
                    </td>
                    <td class="text-right ${item.outflow > 0 ? 'negative' : ''}">
                        ${item.outflow > 0 ? `<strong>${formatCurrency(item.outflow)}</strong>` : '-'}
                    </td>
                    <td class="text-right"><strong>${formatCurrency(item.cash_balance)}</strong></td>
                    <td class="text-right"><strong>${formatCurrency(item.bank_balance)}</strong></td>
                `;
        tableBody.appendChild(row);
    });

    // Add some CSS for badges and tags
    addInlineStyles();
}

// Add inline styles for badges
function addInlineStyles() {
    if (!document.getElementById('inline-styles')) {
        const style = document.createElement('style');
        style.id = 'inline-styles';
        style.textContent = `
                    .account-badge {
                        background-color: var(--surface-2);
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 12px;
                        color: var(--text-body);
                    }
                    
                    .method-tag {
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 12px;
                        font-weight: 500;
                    }
                    
                    .method-tag.cash {
                        background-color: rgba(47, 191, 113, 0.1);
                        color: var(--success);
                        border: 1px solid rgba(47, 191, 113, 0.2);
                    }
                    
                    .method-tag.card {
                        background-color: rgba(31, 123, 255, 0.1);
                        color: var(--primary);
                        border: 1px solid rgba(31, 123, 255, 0.2);
                    }
                    
                    .method-tag.bank {
                        background-color: rgba(107, 114, 128, 0.1);
                        color: var(--text-subtle);
                        border: 1px solid rgba(107, 114, 128, 0.2);
                    }
                    
                    .method-tag.cheque {
                        background-color: rgba(232, 178, 63, 0.1);
                        color: var(--warning);
                        border: 1px solid rgba(232, 178, 63, 0.2);
                    }
                    
                    code {
                        background-color: var(--surface-2);
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-family: 'Courier New', monospace;
                        font-size: 13px;
                        color: var(--primary);
                    }
                `;
        document.head.appendChild(style);
    }
}

// Function to apply filters
async function applyFilters() {
    const result = await fetchCashFlowData();
    
    if (result.data) {
        populateTable(result.data);
        updateTotals(result.data, result.totals);
        updateBalances(result.balances);
        updateTransactionCount(result.data, result.pagination);
        showNotification(`${result.count} transactions found`);
    }
}

// Function to update totals row
function updateTotals(data, totals = null) {
    let totalInflow, totalOutflow, lastCashBalance, lastBankBalance;
    
    if (totals) {
        totalInflow = totals.inflow;
        totalOutflow = totals.outflow;
        lastCashBalance = totals.cash_balance;
        lastBankBalance = totals.bank_balance;
    } else {
        totalInflow = data.reduce((sum, item) => sum + item.inflow, 0);
        totalOutflow = data.reduce((sum, item) => sum + item.outflow, 0);
        lastCashBalance = data.length > 0 ? data[data.length - 1].cash_balance : 0;
        lastBankBalance = data.length > 0 ? data[data.length - 1].bank_balance : 0;
    }

    const totalsRow = document.querySelector('.totals-row');
    totalsRow.innerHTML = `
        <td colspan="5"><strong>TOTALS</strong></td>
        <td class="text-right positive"><strong>${formatCurrency(totalInflow)}</strong></td>
        <td class="text-right negative"><strong>${formatCurrency(totalOutflow)}</strong></td>
        <td class="text-right"><strong>${formatCurrency(lastCashBalance)}</strong></td>
        <td class="text-right"><strong>${formatCurrency(lastBankBalance)}</strong></td>
    `;
}

// Update transaction count display
function updateTransactionCount(data, pagination) {
    const countElement = document.querySelector('.form-actions .fa-info-circle')?.parentElement;
    if (countElement) {
        if (pagination) {
            const start = ((pagination.current_page - 1) * pagination.limit) + 1;
            const end = Math.min(start + data.length - 1, pagination.total_records);
            countElement.innerHTML = `<i class="fas fa-info-circle"></i> Showing ${start}-${end} of ${pagination.total_records} transactions`;
        } else {
            countElement.innerHTML = `<i class="fas fa-info-circle"></i> Showing ${data.length} transactions`;
        }
    }
}

// Update pagination buttons
function updatePaginationButtons() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    
    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
    
    prevBtn.style.opacity = currentPage <= 1 ? '0.5' : '1';
    nextBtn.style.opacity = currentPage >= totalPages ? '0.5' : '1';
}

// Update balance cards
function updateBalances(balances) {
    if (!balances) return;
    
    const openingBalance = document.querySelector('.opening-balance .balance-value');
    const totalInflow = document.querySelector('.total-inflow .balance-value');
    const totalOutflow = document.querySelector('.total-outflow .balance-value');
    const closingBalance = document.querySelector('.closing-balance .balance-value');
    
    if (openingBalance) openingBalance.textContent = formatCurrency(balances.opening_balance);
    if (totalInflow) totalInflow.textContent = formatCurrency(balances.total_inflow);
    if (totalOutflow) totalOutflow.textContent = formatCurrency(balances.total_outflow);
    if (closingBalance) closingBalance.textContent = formatCurrency(balances.closing_balance);
}

// Show notification
function showNotification(message, type = 'success') {
    // Remove any existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.innerHTML = `
                <div style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background-color: var(--surface-0);
                    border: 1px solid var(--border-default);
                    border-radius: 8px;
                    padding: 12px 16px;
                    box-shadow: var(--shadow-medium);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    z-index: 1000;
                    animation: slideIn 0.3s ease;
                ">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}" style="color: var(--${type === 'error' ? 'error' : 'success'});"></i>
                    <span>${message}</span>
                </div>
            `;

    document.body.appendChild(notification);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);

    // Add animation keyframes
    if (!document.getElementById('notification-animations')) {
        const style = document.createElement('style');
        style.id = 'notification-animations';
        style.textContent = `
                    @keyframes slideIn {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                `;
        document.head.appendChild(style);
    }
}

// Function to toggle custom date inputs
function toggleCustomDateInputs() {
    const dateRange = document.getElementById('dateRange').value;
    const customDateGroup = document.getElementById('customDateGroup');
    const customDateGroup2 = document.getElementById('customDateGroup2');
    
    if (dateRange === 'custom') {
        customDateGroup.style.display = 'flex';
        customDateGroup2.style.display = 'flex';
    } else {
        customDateGroup.style.display = 'none';
        customDateGroup2.style.display = 'none';
    }
}

// Function to reset filters
async function resetFilters() {
    document.getElementById('dateRange').value = 'month';
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    document.getElementById('descriptionFilter').value = '';
    document.getElementById('accountFilter').value = '';
    document.getElementById('methodFilter').value = 'all';
    document.getElementById('bankAccountFilter').value = 'all';
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('companyFilter').value = '';
    
    toggleCustomDateInputs();
    currentPage = 1;
    await applyFilters();
    showNotification('Filters reset to default');
}

// Pagination functions
async function goToPreviousPage() {
    if (currentPage > 1) {
        currentPage--;
        await applyFilters();
    }
}

async function goToNextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        await applyFilters();
    }
}

// Function to populate filter options
async function populateFilterOptions() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/cash_flow/filter-options.php');
        const result = await response.json();
        
        if (result.success) {
            // Populate method filter
            const methodFilter = document.getElementById('methodFilter');
            methodFilter.innerHTML = '<option value="all">All Methods</option>';
            result.methods.forEach(method => {
                methodFilter.innerHTML += `<option value="${method.id}">${method.name}</option>`;
            });
            
            // Populate bank account filter
            const bankAccountFilter = document.getElementById('bankAccountFilter');
            bankAccountFilter.innerHTML = '<option value="all">All Bank Accounts</option>';
            result.bank_accounts.forEach(bank => {
                bankAccountFilter.innerHTML += `<option value="${bank.id}">${bank.bank_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading filter options:', error);
    }
}

// Load companies
async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/cash_flow/get-companies.php');
        const result = await response.json();
        
        if (result.success) {
            const companyFilter = document.getElementById('companyFilter');
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
        const response = await fetch('../../../../server/api/financial_reports/cash_flow/get-currencies.php');
        const result = await response.json();
        
        if (result.success) {
            const currencyFilter = document.getElementById('currencyFilter');
            currencyFilter.innerHTML = '';
            result.data.forEach(currency => {
                const option = document.createElement('option');
                option.value = currency.id;
                option.textContent = `${currency.name} (${currency.symbol})`;
                if (currency.is_base) option.selected = true;
                currencyFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading currencies:', error);
    }
}

// Initialize table with data
document.addEventListener('DOMContentLoaded', async function () {
    await loadCurrencies();
    await populateFilterOptions();
    await loadCompanies();
    await applyFilters();

    // Add event listeners
    document.getElementById('dateRange').addEventListener('change', toggleCustomDateInputs);
    document.getElementById('applyFiltersBtn').addEventListener('click', applyFilters);
    document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);
    document.getElementById('printBtn').addEventListener('click', function () {
        const params = new URLSearchParams({
            date_range: document.getElementById('dateRange').value,
            from_date: document.getElementById('fromDate').value,
            to_date: document.getElementById('toDate').value,
            description: document.getElementById('descriptionFilter').value,
            account: document.getElementById('accountFilter').value,
            method: document.getElementById('methodFilter').value,
            bank_account: document.getElementById('bankAccountFilter').value,
            type: document.getElementById('typeFilter').value
        });
        
        const companyFilter = document.getElementById('companyFilter');
        if (companyFilter.value) {
            params.append('company_id', companyFilter.value);
        }
        
        const currencyFilter = document.getElementById('currencyFilter');
        if (currencyFilter.value) {
            params.append('currency_id', currencyFilter.value);
        }
        
        window.open(`print.php?${params}`, '_blank');
    });

    // Pagination buttons
    document.getElementById('prevPageBtn').addEventListener('click', goToPreviousPage);
    document.getElementById('nextPageBtn').addEventListener('click', goToNextPage);

    // Add enter key support for filter inputs
    document.getElementById('descriptionFilter').addEventListener('keyup', function (event) {
        if (event.key === 'Enter') {
            applyFilters();
        }
    });
    
    document.getElementById('accountFilter').addEventListener('keyup', function (event) {
        if (event.key === 'Enter') {
            applyFilters();
        }
    });

    // Toggle view button
    document.getElementById('toggleViewBtn').addEventListener('click', toggleView);
});

// Toggle between table and graph view
function toggleView() {
    isGraphView = !isGraphView;
    const tableView = document.getElementById('tableView');
    const graphView = document.getElementById('graphView');
    const toggleBtn = document.getElementById('toggleViewBtn');
    
    if (isGraphView) {
        tableView.style.display = 'none';
        graphView.style.display = 'block';
        toggleBtn.innerHTML = '<i class="fas fa-table"></i> Table View';
        renderChart();
    } else {
        tableView.style.display = 'block';
        graphView.style.display = 'none';
        toggleBtn.innerHTML = '<i class="fas fa-chart-bar"></i> Graph View';
    }
}

// Render cash flow chart
function renderChart() {
    const ctx = document.getElementById('cashFlowChart').getContext('2d');
    
    // Destroy existing chart
    if (cashFlowChart) {
        cashFlowChart.destroy();
    }
    
    // Prepare data
    const labels = cashFlowData.map(item => formatDate(item.date));
    const inflowData = cashFlowData.map(item => item.inflow);
    const outflowData = cashFlowData.map(item => item.outflow);
    const cashBalanceData = cashFlowData.map(item => item.cash_balance);
    const bankBalanceData = cashFlowData.map(item => item.bank_balance);
    
    cashFlowChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Inflow',
                    data: inflowData,
                    borderColor: 'rgb(47, 191, 113)',
                    backgroundColor: 'rgba(47, 191, 113, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Outflow',
                    data: outflowData,
                    borderColor: 'rgb(227, 79, 79)',
                    backgroundColor: 'rgba(227, 79, 79, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Cash Balance',
                    data: cashBalanceData,
                    borderColor: 'rgb(31, 123, 255)',
                    backgroundColor: 'rgba(31, 123, 255, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Bank Balance',
                    data: bankBalanceData,
                    borderColor: 'rgb(232, 178, 63)',
                    backgroundColor: 'rgba(232, 178, 63, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return currentCurrencySymbol + ' ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}