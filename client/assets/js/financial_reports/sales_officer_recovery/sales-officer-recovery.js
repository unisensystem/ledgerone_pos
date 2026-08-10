// API endpoint
const API_URL = '../../../../server/api/financial_reports/sales_officer_recovery/sales-officer-recovery.php';
const COMPANIES_API_URL = '../../../../server/api/companies/get-companies.php';

// Store fetched data
let reportData = [];
let currentPage = 1;
const itemsPerPage = 10;

// Format currency in Indian Rupees
function formatCurrency(amount) {
    return CURRENCY_SYMBOL + ' ' + amount.toLocaleString('en-IN');
}

// Format date to display format
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Get status badge HTML
function getStatusBadge(status) {
    switch (status) {
        case 'overdue':
            return '<span class="badge badge-overdue">Overdue</span>';
        case 'recovered':
            return '<span class="badge badge-recovered">Recovered</span>';
        case 'partial':
            return '<span class="badge badge-pending">Partial</span>';
        default:
            return '<span class="badge">Unknown</span>';
    }
}

// Render hierarchical table
function renderHierarchicalTable(data) {
    const tableBody = document.getElementById('report-table-body');
    tableBody.innerHTML = '';

    // Paginate data
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = data.slice(startIndex, endIndex);

    let globalSerial = startIndex + 1;

    paginatedData.forEach((officerGroup, groupIndex) => {
        // Add sales officer header row
        const officerRow = document.createElement('tr');
        officerRow.className = 'sales-officer-header';
        officerRow.dataset.officerId = officerGroup.officerId;
        officerRow.dataset.expanded = 'true';

        officerRow.innerHTML = `
                    <td>
                        <span class="sales-officer-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </td>
                    <td colspan="11">
                        <div class="officer-summary">
                            <div>
                                <span style="font-weight: 600; color: var(--primary);">${officerGroup.salesOfficer}</span>
                                <span style="color: var(--subtext); margin-left: var(--spacing-sm);">• ${officerGroup.totalBills} bills</span>
                            </div>
                            <div class="officer-stats">
                                <div class="officer-stat">
                                    <div class="officer-stat-label">Total Billed</div>
                                    <div class="officer-stat-value">${formatCurrency(officerGroup.totalBillAmount)}</div>
                                </div>
                                <div class="officer-stat">
                                    <div class="officer-stat-label">Recovered</div>
                                    <div class="officer-stat-value" style="color: var(--success);">${formatCurrency(officerGroup.totalRecovery)}</div>
                                </div>
                                <div class="officer-stat">
                                    <div class="officer-stat-label">Balance</div>
                                    <div class="officer-stat-value" style="color: ${officerGroup.totalRemaining > 0 ? 'var(--error)' : 'var(--success)'};">${formatCurrency(officerGroup.totalRemaining)}</div>
                                </div>
                                <div class="officer-stat">
                                    <div class="officer-stat-label">Recovery %</div>
                                    <div class="officer-stat-value">${Math.round((officerGroup.totalRecovery / officerGroup.totalBillAmount) * 100)}%</div>
                                </div>
                            </div>
                        </div>
                    </td>
                `;

        tableBody.appendChild(officerRow);

        // Add transaction rows for this officer
        officerGroup.transactions.forEach((transaction, transactionIndex) => {
            const transactionRow = document.createElement('tr');
            transactionRow.className = 'transaction-row';
            transactionRow.dataset.officerId = officerGroup.officerId;
            transactionRow.dataset.transactionId = transaction.id;

            transactionRow.innerHTML = `
                        <td></td>
                        <td>${globalSerial++}</td>
                        <td>${formatDate(transaction.billDate)}</td>
                        <td>
                            ${transaction.overdueDays > 0 ?
                    `<span class="badge badge-overdue">${transaction.overdueDays} days</span>` :
                    '<span>On time</span>'}
                        </td>
                        <td class="font-semibold">${transaction.billNo}</td>
                        <td class="text-right">${formatCurrency(transaction.returnAmount)}</td>
                        <td>${transaction.customerName}</td>
                        <td class="text-right font-semibold">${formatCurrency(transaction.billAmount)}</td>
                        <td class="text-right">${formatCurrency(transaction.totalRecovery)}</td>
                        <td class="text-right font-semibold">
                            ${transaction.remainingBalance > 0 ?
                    `<span style="color: var(--error);">${formatCurrency(transaction.remainingBalance)}</span>` :
                    `<span style="color: var(--success);">${formatCurrency(transaction.remainingBalance)}</span>`}
                        </td>
                        <td>${getStatusBadge(transaction.status)}</td>
                    `;

            tableBody.appendChild(transactionRow);
        });
    });

    renderPagination(data.length);
}

// Render pagination
function renderPagination(totalItems) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const paginationContainer = document.querySelector('.footer > div:last-child');
    
    let paginationHTML = '';
    
    paginationHTML += `<button class="btn btn-secondary btn-icon" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})"><i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            paginationHTML += `<button class="btn ${i === currentPage ? 'btn-primary' : 'btn-ghost'}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            paginationHTML += `<button class="btn btn-ghost">...</button>`;
        }
    }
    
    paginationHTML += `<button class="btn btn-secondary btn-icon" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})"><i class="fas fa-chevron-right"></i></button>`;
    
    paginationContainer.innerHTML = paginationHTML;
}

// Change page
function changePage(page) {
    const totalPages = Math.ceil(reportData.length / itemsPerPage);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderHierarchicalTable(reportData);
    updateFooterText();
}

// Update footer text
function updateFooterText() {
    const totalOfficers = reportData.length;
    const totalBills = reportData.reduce((sum, officer) => sum + officer.totalBills, 0);
    const startIndex = (currentPage - 1) * itemsPerPage + 1;
    const endIndex = Math.min(currentPage * itemsPerPage, totalOfficers);
    
    document.querySelector('.footer div:first-child').textContent = 
        `Showing ${startIndex}-${endIndex} of ${totalOfficers} Sales Officers with ${totalBills} total transactions`;
}

// Analytics Charts
let officerChart, statusChart, performanceChart, invoiceDistributionChart;

function showAnalytics() {
    document.getElementById('analytics-modal').style.display = 'block';
    renderCharts();
}

function closeAnalytics() {
    document.getElementById('analytics-modal').style.display = 'none';
    if (officerChart) officerChart.destroy();
    if (statusChart) statusChart.destroy();
    if (performanceChart) performanceChart.destroy();
    if (invoiceDistributionChart) invoiceDistributionChart.destroy();
}

function renderCharts() {
    // Officer Recovery Chart
    const officerLabels = reportData.map(o => o.salesOfficer);
    const officerRecovery = reportData.map(o => o.totalRecovery);
    const officerRemaining = reportData.map(o => o.totalRemaining);
    
    const ctxOfficer = document.getElementById('officerChart').getContext('2d');
    officerChart = new Chart(ctxOfficer, {
        type: 'bar',
        data: {
            labels: officerLabels,
            datasets: [{
                label: 'Recovered',
                data: officerRecovery,
                backgroundColor: '#2fbf71'
            }, {
                label: 'Remaining',
                data: officerRemaining,
                backgroundColor: '#e34f4f'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Status Distribution Chart
    const statusCounts = { overdue: 0, recovered: 0, partial: 0 };
    reportData.forEach(officer => {
        officer.transactions.forEach(txn => {
            statusCounts[txn.status]++;
        });
    });
    
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    statusChart = new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Overdue', 'Recovered', 'Partial'],
            datasets: [{
                data: [statusCounts.overdue, statusCounts.recovered, statusCounts.partial],
                backgroundColor: ['#e34f4f', '#2fbf71', '#e8b23f']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
    
    // Invoice Distribution Chart
    const invoiceLabels = reportData.map(o => o.salesOfficer);
    const invoiceCounts = reportData.map(o => o.totalBills);
    
    const ctxInvoice = document.getElementById('invoiceDistributionChart').getContext('2d');
    invoiceDistributionChart = new Chart(ctxInvoice, {
        type: 'pie',
        data: {
            labels: invoiceLabels,
            datasets: [{
                data: invoiceCounts,
                backgroundColor: [
                    '#1f7bff',
                    '#2fbf71',
                    '#e8b23f',
                    '#e34f4f',
                    '#9b59b6',
                    '#3498db',
                    '#e67e22',
                    '#1abc9c'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Performance Comparison Chart
    const performanceData = reportData.map(o => ({
        officer: o.salesOfficer,
        percentage: (o.totalRecovery / o.totalBillAmount) * 100
    }));
    
    const ctxPerformance = document.getElementById('performanceChart').getContext('2d');
    performanceChart = new Chart(ctxPerformance, {
        type: 'line',
        data: {
            labels: performanceData.map(p => p.officer),
            datasets: [{
                label: 'Recovery %',
                data: performanceData.map(p => p.percentage),
                borderColor: '#1f7bff',
                backgroundColor: 'rgba(31, 123, 255, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

// Toggle expand/collapse for a sales officer group
function toggleOfficerGroup(officerId) {
    const officerRow = document.querySelector(`tr[data-officer-id="${officerId}"]`);
    const transactionRows = document.querySelectorAll(`tr.transaction-row[data-officer-id="${officerId}"]`);
    const toggleIcon = officerRow.querySelector('.sales-officer-toggle i');
    const isExpanded = officerRow.dataset.expanded === 'true';

    if (isExpanded) {
        // Collapse
        transactionRows.forEach(row => row.classList.add('hidden'));
        officerRow.classList.add('collapsed');
        toggleIcon.className = 'fas fa-chevron-right';
        officerRow.dataset.expanded = 'false';
    } else {
        // Expand
        transactionRows.forEach(row => row.classList.remove('hidden'));
        officerRow.classList.remove('collapsed');
        toggleIcon.className = 'fas fa-chevron-down';
        officerRow.dataset.expanded = 'true';
    }
}

// Expand all officer groups
function expandAllOfficers() {
    document.querySelectorAll('.sales-officer-header').forEach(officerRow => {
        if (officerRow.dataset.expanded === 'false') {
            toggleOfficerGroup(officerRow.dataset.officerId);
        }
    });
}

// Collapse all officer groups
function collapseAllOfficers() {
    document.querySelectorAll('.sales-officer-header').forEach(officerRow => {
        if (officerRow.dataset.expanded === 'true') {
            toggleOfficerGroup(officerRow.dataset.officerId);
        }
    });
}

// Fetch data from API
async function fetchData() {
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const salesOfficer = document.getElementById('sales-officer').value;
    const customer = document.getElementById('customer').value;
    const distribution = document.getElementById('distribution').value;
    const status = document.getElementById('status').value;
    const includeZeroBalance = document.getElementById('include-zero-balance').checked ? '1' : '0';
    const companyId = document.getElementById('company').value;

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (salesOfficer) params.append('sales_officer_id', salesOfficer);
    if (customer) params.append('customer_id', customer);
    if (distribution) params.append('distribution_id', distribution);
    if (status) params.append('status', status);
    params.append('include_zero_balance', includeZeroBalance);
    if (companyId) params.append('company_id', companyId);

    try {
        const response = await fetch(`${API_URL}?${params.toString()}`);
        const result = await response.json();
        
        if (result.success) {
            reportData = result.data;
            currentPage = 1;
            renderHierarchicalTable(reportData);
            updateSummaryCards(reportData);
            updateFooterText();
        } else {
            console.error('Error:', result.message);
            alert('Failed to fetch data: ' + result.message);
        }
    } catch (error) {
        console.error('Fetch error:', error);
        alert('Failed to fetch data from server');
    }
}

// Filter data based on form inputs
function filterData() {
    fetchData();
}

// Update summary cards with filtered data
function updateSummaryCards(data) {
    const totalOfficers = data.length;
    const totalBills = data.reduce((sum, officer) => sum + officer.totalBills, 0);
    const totalBillAmount = data.reduce((sum, officer) => sum + officer.totalBillAmount, 0);
    const totalRecovery = data.reduce((sum, officer) => sum + officer.totalRecovery, 0);
    const totalRemaining = data.reduce((sum, officer) => sum + officer.totalRemaining, 0);

    document.querySelector('.summary-cards').innerHTML = `
                <div class="summary-card">
                    <div class="summary-card-title">Total Sales Officers</div>
                    <div class="summary-card-value">${totalOfficers}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-title">Total Bills</div>
                    <div class="summary-card-value">${totalBills}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-title">Total Recovered</div>
                    <div class="summary-card-value summary-card-positive">${formatCurrency(totalRecovery)}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-title">Remaining Balance</div>
                    <div class="summary-card-value summary-card-negative">${formatCurrency(totalRemaining)}</div>
                </div>
            `;
}

// Reset all filters
function resetFilters() {
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    document.getElementById('sales-officer').value = '';
    document.getElementById('customer').value = '';
    document.getElementById('distribution').value = '';
    document.getElementById('status').value = '';
    document.getElementById('include-zero-balance').checked = false;
    document.getElementById('company').value = '';

    fetchData();
}

// Fetch companies
async function fetchCompanies() {
    try {
        const response = await fetch(COMPANIES_API_URL);
        const result = await response.json();
        
        if (result.success && result.data) {
            const companySelect = document.getElementById('company');
            companySelect.innerHTML = '<option value="">All Companies</option>';
            
            result.data.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companySelect.appendChild(option);
            });
            
            if (result.data.length === 1) {
                companySelect.value = result.data[0].id;
            }
        }
    } catch (error) {
        console.error('Error fetching companies:', error);
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function () {
    // Initialize Select2 for customer dropdown
    $('#customer').select2({
        placeholder: 'All Customers',
        allowClear: true
    });

    // Fetch companies
    fetchCompanies();

    // Fetch and render initial data
    fetchData();

    // Add event listeners for expand/collapse buttons
    document.getElementById('expand-all-btn').addEventListener('click', expandAllOfficers);
    document.getElementById('collapse-all-btn').addEventListener('click', collapseAllOfficers);

    // Add event listener for officer header clicks
    document.addEventListener('click', function (e) {
        if (e.target.closest('.sales-officer-header')) {
            const officerRow = e.target.closest('.sales-officer-header');
            const officerId = officerRow.dataset.officerId;
            toggleOfficerGroup(officerId);
        }
    });

    // Add event listeners for filter buttons
    document.getElementById('apply-filters-btn').addEventListener('click', filterData);

    // Add event listener for reset filters
    document.getElementById('reset-filters-btn').addEventListener('click', resetFilters);
    
    // Add event listener for analytics button
    document.getElementById('view-analytics-btn').addEventListener('click', showAnalytics);
    document.getElementById('close-analytics-btn').addEventListener('click', closeAnalytics);
    
    // Close modal on outside click
    document.getElementById('analytics-modal').addEventListener('click', function(e) {
        if (e.target.id === 'analytics-modal') {
            closeAnalytics();
        }
    });
    
    // Add event listener for print button
    document.getElementById('print-btn').addEventListener('click', function() {
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;
        const salesOfficer = document.getElementById('sales-officer').value;
        const customer = document.getElementById('customer').value;
        const distribution = document.getElementById('distribution').value;
        const status = document.getElementById('status').value;
        const includeZeroBalance = document.getElementById('include-zero-balance').checked ? '1' : '0';
        const companyId = document.getElementById('company').value;
        
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (salesOfficer) params.append('sales_officer_id', salesOfficer);
        if (customer) params.append('customer_id', customer);
        if (distribution) params.append('distribution_id', distribution);
        if (status) params.append('status', status);
        params.append('include_zero_balance', includeZeroBalance);
        if (companyId) params.append('company_id', companyId);
        
        window.open('print.php?' + params.toString(), '_blank');
    });
});