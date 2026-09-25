// Sample data for expense entries
let expenseData = [];
let allExpenseAccounts = [];
let allCostCenters = [];
let allCompanies = [];
let allProjects = [];

// DOM Elements
const tableBody = document.getElementById('tableBody');
const globalSearch = document.getElementById('globalSearch');
const newEntryBtn = document.getElementById('newEntryBtn');
const applyFiltersBtn = document.getElementById('applyFiltersBtn');
const resetFiltersBtn = document.getElementById('resetFiltersBtn');
const exportBtn = document.getElementById('exportBtn');
const exportDropdown = document.getElementById('exportDropdown');
const exportExcel = document.getElementById('exportExcel');
const exportJSON = document.getElementById('exportJSON');
const printBtn = document.getElementById('printBtn');
const deleteModal = document.getElementById('deleteModal');
const closeDeleteModal = document.getElementById('closeDeleteModal');
const cancelDelete = document.getElementById('cancelDelete');
const confirmDelete = document.getElementById('confirmDelete');
const voucherToDelete = document.getElementById('voucherToDelete');
const prevPageBtn = document.getElementById('prevPage');
const nextPageBtn = document.getElementById('nextPage');
const paginationInfo = document.getElementById('paginationInfo');
const filterExpenseAccount = document.getElementById('filterExpenseAccount');
const filterCostCenter = document.getElementById('filterCostCenter');
const filterCompany = document.getElementById('filterCompany');
const filterProject = document.getElementById('filterProject');

// State
let currentPage = 1;
const itemsPerPage = 10;
let filteredData = [];
let voucherToDeleteId = null;
let trendChart = null;
let accountsChart = null;
let costCentersChart = null;

// Fetch expense vouchers from API
async function fetchExpenseVouchers() {
    try {
        const response = await fetch('../../../../server/api/vouchers/expense_voucher/expense-list.php');
        const data = await response.json();
        if (data.success) {
            expenseData = data.data;
            filteredData = [...expenseData];
            updateStats();
            renderCharts();
            renderTable();
        }
    } catch (error) {
        console.error('Error fetching expense vouchers:', error);
    }
}

// Fetch dropdown data
async function fetchDropdownData() {
    try {
        const [expenseRes, companyRes, projectRes] = await Promise.all([
            fetch('../../../../server/api/vouchers/expense_voucher/get-expense-accounts.php'),
            fetch('../../../../server/api/vouchers/expense_voucher/get-companies.php'),
            fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
        ]);

        const expenseAccountsRes = await expenseRes.json();
        const companyData = await companyRes.json();
        const projectData = await projectRes.json();

        if (expenseAccountsRes.success) {
            allExpenseAccounts = expenseAccountsRes.data;
            initSearchableDropdown('filterExpenseAccountOptions', allExpenseAccounts, filterExpenseAccount);
        }

        if (companyData.success) {
            allCompanies = companyData.data;
            companyData.data.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                filterCompany.appendChild(option);
            });
        }

        if (projectData.success) {
            allProjects = projectData.projects;
            projectData.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = `${project.project_code} - ${project.project_name} - ${project.city_name}`;
                filterProject.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching dropdown data:', error);
    }
}

// Update stats cards
function updateStats() {
    const totalVouchers = filteredData.length;
    const totalAmount = filteredData.reduce((sum, v) => sum + parseFloat(v.total_amount), 0);

    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = totalVouchers;
    document.getElementById('totalAmountStat').textContent = CURRENCY_SYMBOL + totalAmount.toFixed(2);
}

// Render charts
function renderCharts() {
    renderExpenseTrendChart();
    renderTopAccountsChart(filteredData);
    renderTopCostCentersChart(filteredData);
}

// Expense Trend Chart
function renderExpenseTrendChart() {
    const last30Days = [];
    const today = new Date();
    for (let i = 29; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(today.getDate() - i);
        last30Days.push(date.toISOString().split('T')[0]);
    }

    const expensesByDate = {};
    last30Days.forEach(date => expensesByDate[date] = 0);
    filteredData.forEach(expense => {
        if (expensesByDate.hasOwnProperty(expense.date)) {
            expensesByDate[expense.date] += parseFloat(expense.total_amount);
        }
    });

    const seriesData = last30Days.map(date => expensesByDate[date]);
    const categories = last30Days.map(date => new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));

    if (trendChart) {
        trendChart.updateOptions({ xaxis: { categories } });
        trendChart.updateSeries([{ name: 'Expenses', data: seriesData }]);
        return;
    }

    trendChart = new ApexCharts(document.querySelector('#expenseTrendChart'), {
        series: [{ name: 'Expenses', data: seriesData }],
        chart: { type: 'area', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        colors: ['#1f7bff'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        xaxis: { categories, labels: { rotate: -45 } },
        yaxis: { labels: { formatter: (value) => CURRENCY_SYMBOL + value.toFixed(0) } },
        tooltip: { y: { formatter: (value) => CURRENCY_SYMBOL + value.toFixed(2) } }
    });
    trendChart.render();
}

// Top Accounts Chart
function renderTopAccountsChart(data) {
    // Aggregate from filteredData
    const accountMap = {};
    data.forEach(expense => {
        if (expense.expense_accounts) {
            expense.expense_accounts.split(',').forEach((acc, i) => {
                const name = acc.trim();
                const amounts = expense.expense_amounts ? expense.expense_amounts.split(',') : [];
                const amount = parseFloat(amounts[i]) || (parseFloat(expense.total_amount) / (expense.total_items || 1));
                accountMap[name] = (accountMap[name] || 0) + amount;
            });
        }
    });

    const sorted = Object.entries(accountMap).sort((a, b) => b[1] - a[1]).slice(0, 5);
    if (!sorted.length) return;

    const labels = sorted.map(e => e[0]);
    const series = sorted.map(e => e[1]);

    if (accountsChart) {
        accountsChart.updateOptions({ labels });
        accountsChart.updateSeries(series);
        return;
    }

    accountsChart = new ApexCharts(document.querySelector('#topAccountsChart'), {
        series,
        chart: { type: 'donut', height: 300 },
        labels,
        colors: ['#1f7bff', '#2fbf71', '#e8b23f', '#e34f4f', '#6b7280'],
        legend: { position: 'bottom' },
        dataLabels: { formatter: (val) => val.toFixed(1) + '%' },
        tooltip: { y: { formatter: (value) => CURRENCY_SYMBOL + value.toFixed(2) } }
    });
    accountsChart.render();
}

// Top Cost Centers Chart
function renderTopCostCentersChart(data) {
    const ccMap = {};
    data.forEach(expense => {
        if (expense.cost_centers) {
            expense.cost_centers.split(',').forEach((cc, i) => {
                const name = cc.trim();
                if (!name || name === '-') return;
                const amounts = expense.expense_amounts ? expense.expense_amounts.split(',') : [];
                const amount = parseFloat(amounts[i]) || (parseFloat(expense.total_amount) / (expense.total_items || 1));
                ccMap[name] = (ccMap[name] || 0) + amount;
            });
        }
    });

    const sorted = Object.entries(ccMap).sort((a, b) => b[1] - a[1]).slice(0, 5);
    if (!sorted.length) return;

    const labels = sorted.map(e => e[0]);
    const series = sorted.map(e => e[1]);

    if (costCentersChart) {
        costCentersChart.updateOptions({ labels });
        costCentersChart.updateSeries(series);
        return;
    }

    costCentersChart = new ApexCharts(document.querySelector('#topCostCentersChart'), {
        series,
        chart: { type: 'donut', height: 300 },
        labels,
        colors: ['#1f7bff', '#2fbf71', '#e8b23f', '#e34f4f', '#6b7280'],
        legend: { position: 'bottom' },
        dataLabels: { formatter: (val) => val.toFixed(1) + '%' },
        tooltip: { y: { formatter: (value) => CURRENCY_SYMBOL + value.toFixed(2) } }
    });
    costCentersChart.render();
}

// Render table - each voucher's line items (account / cost center / amount) get
// their own row via rowspan on the voucher-level columns.
function renderTable() {
    tableBody.innerHTML = '';

    if (filteredData.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h3>No expense vouchers found</h3>
                        <p>Try adjusting your search or filters</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    // Pagination - by voucher; each voucher may render as multiple line-item rows
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredData.slice(startIndex, endIndex);

    pageData.forEach(expense => {
        const dateObj = new Date(expense.date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        const cfVal = parseInt(expense.is_cash_flow);
        const cashFlowBadge = cfVal === 1
            ? `<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Yes</span>`
            : `<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> No</span>`;

        const actionButtons = `
            <div class="action-buttons">
                <button class="action-btn view" title="View Full Details" onclick="viewVoucher(${expense.voucher_id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn edit" title="Edit" onclick="editVoucher(${expense.voucher_id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="action-btn cash-flow ${cfVal === 1 ? 'cf-yes' : 'cf-no'}" title="Toggle Cash Flow" onclick="toggleCashFlow(${expense.voucher_id}, ${cfVal}, this)">
                    <i class="fas fa-water"></i>
                </button>
                <button class="action-btn print" title="Print" onclick="printVoucher(${expense.voucher_id})">
                    <i class="fas fa-print"></i>
                </button>
                <button class="action-btn delete" title="Delete" onclick="openDeleteModal(${expense.voucher_id}, '${expense.voucher_no}')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        // Split the comma-lists into individual line items
        const accounts = expense.expense_accounts ? expense.expense_accounts.split(',') : ['-'];
        const costCenters = expense.cost_centers ? expense.cost_centers.split(',') : [];
        const amounts = expense.expense_amounts ? expense.expense_amounts.split(',') : [];
        const lineCount = accounts.length || 1;

        for (let i = 0; i < lineCount; i++) {
            const row = document.createElement('tr');

            const accName = (accounts[i] || '-').trim();
            const ccName = (costCenters[i] || '-').trim() || '-';
            // Fall back to voucher total only if a per-line amount isn't available
            const lineAmount = amounts[i] !== undefined
                ? parseFloat(amounts[i])
                : parseFloat(expense.total_amount);
            const formattedAmount = CURRENCY_SYMBOL + lineAmount.toFixed(2);

            if (i === 0) {
                // First line: include voucher-level columns, spanning all its lines
                row.innerHTML = `
                    <td rowspan="${lineCount}"><strong>${expense.voucher_no}</strong></td>
                    <td rowspan="${lineCount}">${formattedDate}</td>
                    <td rowspan="${lineCount}">${expense.description}</td>
                    <td rowspan="${lineCount}">${expense.project_name || '-'}</td>
                    <td>${accName}</td>
                    <td>${ccName}</td>
                    <td class="amount">${formattedAmount}</td>
                    <td rowspan="${lineCount}">${cashFlowBadge}</td>
                    <td rowspan="${lineCount}">${actionButtons}</td>
                `;
            } else {
                // Subsequent lines: only the per-line columns
                row.innerHTML = `
                    <td>${accName}</td>
                    <td>${ccName}</td>
                    <td class="amount">${formattedAmount}</td>
                `;
            }

            tableBody.appendChild(row);
        }
    });

    // Update pagination
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    const startEntry = filteredData.length > 0 ? startIndex + 1 : 0;
    const endEntry = Math.min(endIndex, filteredData.length);

    paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${filteredData.length} entries`;

    prevPageBtn.disabled = currentPage === 1;
    nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;

    updatePaginationButtons(totalPages);
}

// Update pagination number buttons
function updatePaginationButtons(totalPages) {
    const paginationControls = document.querySelector('.pagination-controls');
    const pageButtons = paginationControls.querySelectorAll('.pagination-btn:not(#prevPage):not(#nextPage)');

    // Clear existing number buttons (except prev/next)
    pageButtons.forEach(btn => {
        if (!btn.id) btn.remove();
    });

    // Add page number buttons
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = startPage + maxVisiblePages - 1;

    if (endPage > totalPages) {
        endPage = totalPages;
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    const numberButtonsContainer = prevPageBtn.nextElementSibling;

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => {
            currentPage = i;
            renderTable();
        });

        numberButtonsContainer.before(pageBtn);
    }
}

// Initialize searchable dropdown
function initSearchableDropdown(optionsId, data, inputElement) {
    const dropdownOptions = document.getElementById(optionsId);
    const inputField = inputElement;

    function populateOptions(filter = '') {
        dropdownOptions.innerHTML = '';
        const filteredData = data.filter(item =>
            item.name.toLowerCase().includes(filter.toLowerCase())
        );

        filteredData.forEach(item => {
            const option = document.createElement('div');
            option.className = 'dropdown-option';
            option.textContent = item.name;
            option.dataset.id = item.id;
            option.addEventListener('click', () => {
                inputField.value = item.name;
                inputField.dataset.id = item.id;
                dropdownOptions.classList.remove('active');
                inputField.dispatchEvent(new Event('change'));
            });
            dropdownOptions.appendChild(option);
        });
    }

    inputField.addEventListener('focus', () => {
        populateOptions(inputField.value);
        dropdownOptions.classList.add('active');
    });

    inputField.addEventListener('input', (e) => {
        populateOptions(e.target.value);
        if (!e.target.value) {
            delete e.target.dataset.id;
        }
    });

    document.addEventListener('click', (e) => {
        if (!dropdownOptions.contains(e.target) && e.target !== inputField) {
            dropdownOptions.classList.remove('active');
        }
    });
}

// Fetch cost centers based on account
async function fetchCostCenters(accountId) {
    try {
        const response = await fetch(`../../../../server/api/vouchers/expense_voucher/get-cost-centers.php?account_id=${accountId}`);
        const data = await response.json();
        return data.success ? data.data : [];
    } catch (error) {
        console.error('Error fetching cost centers:', error);
        return [];
    }
}

// Filter data based on search and filters
function filterData() {
    const searchTerm = globalSearch.value.toLowerCase();
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const minAmount = parseFloat(document.getElementById('minAmount').value) || 0;
    const maxAmount = parseFloat(document.getElementById('maxAmount').value) || Infinity;
    const expenseAccountId = filterExpenseAccount.dataset.id || '';
    const costCenterId = filterCostCenter.dataset.id || '';
    const companyId = filterCompany.value;
    const projectId = filterProject.value;

    filteredData = expenseData.filter(expense => {
        // Search filter
        if (searchTerm && !(
            expense.voucher_no.toLowerCase().includes(searchTerm) ||
            expense.description.toLowerCase().includes(searchTerm) ||
            (expense.expense_accounts && expense.expense_accounts.toLowerCase().includes(searchTerm)) ||
            (expense.cost_centers && expense.cost_centers.toLowerCase().includes(searchTerm))
        )) {
            return false;
        }

        // Date range filter
        const expenseDate = new Date(expense.date);
        if (fromDate) {
            const from = new Date(fromDate);
            if (expenseDate < from) return false;
        }
        if (toDate) {
            const to = new Date(toDate);
            to.setHours(23, 59, 59, 999);
            if (expenseDate > to) return false;
        }

        // Amount range filter
        const amount = parseFloat(expense.total_amount);
        if (amount < minAmount || amount > maxAmount) {
            return false;
        }

        // Company filter
        if (companyId && expense.company_id != companyId) {
            return false;
        }

        // Project filter
        if (projectId && expense.project_id != projectId) {
            return false;
        }

        // Expense Account filter (client-side by name)
        if (expenseAccountId && !(expense.expense_account_ids && expense.expense_account_ids.split(',').map(s => s.trim()).includes(String(expenseAccountId)))) {
            return false;
        }

        // Cost Center filter (client-side by id)
        if (costCenterId && !(expense.cost_center_ids && expense.cost_center_ids.split(',').map(s => s.trim()).includes(String(costCenterId)))) {
            return false;
        }

        return true;
    });

    currentPage = 1;
    updateStats();
    renderCharts();
    renderTable();
}

// View voucher details
function viewVoucher(id) {
    fetch(`../../../../server/api/vouchers/expense_voucher/get-voucher-details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const voucher = data.data;

                // Populate header info
                document.getElementById('viewVoucherNo').textContent = voucher.voucher_no;
                document.getElementById('viewDate').textContent = new Date(voucher.date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                document.getElementById('viewCompany').textContent = voucher.company_name || '-';
                document.getElementById('viewVendor').textContent = voucher.vendor_name || '-';
                const typeEl = document.getElementById('viewType');
                typeEl.textContent = voucher.type || 'CR';
                typeEl.style.fontWeight = '600';
                typeEl.style.color = (voucher.type === 'DR') ? '#e34f4f' : '#2fbf71';
                const cfEl = document.getElementById('viewCashFlow');
                cfEl.textContent = parseInt(voucher.is_cash_flow) === 1 ? 'Yes' : 'No';
                cfEl.style.fontWeight = '600';
                cfEl.style.color = parseInt(voucher.is_cash_flow) === 1 ? '#2fbf71' : '#e34f4f';
                document.getElementById('viewDescription').textContent = voucher.description;
                document.getElementById('viewTotalAmount').textContent = CURRENCY_SYMBOL + parseFloat(voucher.total_amount).toFixed(2);

                // Populate lines
                const linesBody = document.getElementById('viewLinesBody');
                linesBody.innerHTML = voucher.lines.map(line => `
                    <tr>
                        <td>${line.account_name}</td>
                        <td>${line.cost_center_name || '-'}</td>
                        <td>${CURRENCY_SYMBOL}${parseFloat(line.amount).toFixed(2)}</td>
                        <td>${line.payment_method_name}</td>
                        <td>${line.bank_name ? line.bank_name + ' - ' + line.account_number : '-'}</td>
                        <td>${line.cheque_no || '-'}</td>
                        <td>${line.cheque_date ? new Date(line.cheque_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-'}</td>
                    </tr>
                `).join('');

                document.getElementById('viewVoucherModal').classList.add('active');
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error fetching voucher details:', error);
            alert('Failed to fetch voucher details');
        });
}

document.getElementById('closeViewModal').addEventListener('click', () => {
    document.getElementById('viewVoucherModal').classList.remove('active');
});

document.getElementById('viewVoucherModal').addEventListener('click', (e) => {
    if (e.target.id === 'viewVoucherModal') {
        document.getElementById('viewVoucherModal').classList.remove('active');
    }
});

// Toggle cash flow
async function toggleCashFlow(id, currentVal, btn) {
    const newVal = currentVal === 1 ? 0 : 1;
    try {
        const response = await fetch('../../../../server/api/vouchers/expense_voucher/toggle-cash-flow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, is_cash_flow: newVal })
        });
        const data = await response.json();
        if (data.success) {
            // Update in-memory data
            const record = expenseData.find(e => e.id == id);
            if (record) record.is_cash_flow = newVal;
            const fRecord = filteredData.find(e => e.id == id);
            if (fRecord) fRecord.is_cash_flow = newVal;
            // Full re-render since a voucher's rows can now span multiple <tr> elements
            renderTable();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error toggling cash flow:', error);
    }
}

// Edit voucher
function editVoucher(id) {
    window.location.href = `expense-add.php?id=${id}`;
}

// Print voucher
function printVoucher(id) {
    window.open(`print.php?id=${id}`, '_blank');
}

// Open delete confirmation modal
function openDeleteModal(id, voucherNo) {
    voucherToDeleteId = id;
    voucherToDelete.textContent = voucherNo;
    deleteModal.classList.add('active');
}

// Close delete confirmation modal
function closeDeleteModalFunc() {
    deleteModal.classList.remove('active');
    voucherToDeleteId = null;
}

// Delete voucher
function deleteVoucher() {
    if (voucherToDeleteId) {
        fetch(`../../../../server/api/vouchers/expense_voucher/expense-delete.php?id=${voucherToDeleteId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Voucher ${voucherToDelete.textContent} has been deleted successfully.`);
                fetchExpenseVouchers();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting voucher:', error);
            alert('Failed to delete voucher');
        });
    }
    closeDeleteModalFunc();
}

// Export to Excel
function exportToExcel() {
    const data = filteredData.map(expense => ({
        'Voucher #': expense.voucher_no,
        'Date': new Date(expense.date).toLocaleDateString('en-US'),
        'Description': expense.description,
        'Total Items': expense.total_items,
        'Total Amount': parseFloat(expense.total_amount).toFixed(2)
    }));

    const worksheet = data.map(row => Object.values(row));
    const headers = Object.keys(data[0] || {});
    worksheet.unshift(headers);

    let csv = worksheet.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `expense-vouchers-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Export to JSON
function exportToJSON() {
    const json = JSON.stringify(filteredData, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `expense-vouchers-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print list
function printList() {
    alert('In a real application, this would open a print-friendly version of the expense list.');
}

// Create new expense entry
function newExpenseEntry() {
    window.location.href = 'expense-add.php';
}

// Reset filters
function resetFilters() {
    globalSearch.value = '';
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    document.getElementById('minAmount').value = '';
    document.getElementById('maxAmount').value = '';
    filterExpenseAccount.value = '';
    delete filterExpenseAccount.dataset.id;
    filterCostCenter.value = '';
    delete filterCostCenter.dataset.id;
    filterCompany.value = '';
    filterProject.value = '';

    // Reload all data
    fetchExpenseVouchers();
}

// Initialize
function init() {
    fetchExpenseVouchers();
    fetchDropdownData();

    // Load cost centers when expense account is selected
    filterExpenseAccount.addEventListener('change', async function() {
        const accountId = this.dataset.id;
        filterCostCenter.value = '';
        delete filterCostCenter.dataset.id;

        if (accountId) {
            allCostCenters = await fetchCostCenters(accountId);
            initSearchableDropdown('filterCostCenterOptions', allCostCenters, filterCostCenter);
        } else {
            allCostCenters = [];
            initSearchableDropdown('filterCostCenterOptions', [], filterCostCenter);
        }
    });

    // Event Listeners
    globalSearch.addEventListener('input', filterData);
    newEntryBtn.addEventListener('click', newExpenseEntry);
    applyFiltersBtn.addEventListener('click', filterData);
    resetFiltersBtn.addEventListener('click', resetFilters);

    // Export dropdown
    exportBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        exportDropdown.classList.toggle('active');
    });

    exportExcel.addEventListener('click', exportToExcel);
    exportJSON.addEventListener('click', exportToJSON);

    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        exportDropdown.classList.remove('active');
    });

    // Delete modal events
    closeDeleteModal.addEventListener('click', closeDeleteModalFunc);
    cancelDelete.addEventListener('click', closeDeleteModalFunc);
    confirmDelete.addEventListener('click', deleteVoucher);

    // Pagination events
    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    });

    // Close modal when clicking outside
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            closeDeleteModalFunc();
        }
    });
}

// Start the application
init();