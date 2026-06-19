// DOM Elements
const startDateEl = document.getElementById('startDate');
const endDateEl = document.getElementById('endDate');
const branchFilterEl = document.getElementById('branchFilter');
const companyFilterEl = document.getElementById('companyFilter');
const currencyFilterEl = document.getElementById('currencyFilter');
const projectFilterEl = document.getElementById('projectFilter');
const detailLevelEl = document.getElementById('detailLevel');
const includeGraphsEl = document.getElementById('includeGraphs');
const generateReportBtn = document.getElementById('generateReportBtn');
const resetBtn = document.getElementById('resetBtn');
const zakatBtn = document.getElementById('zakatBtn');
const helpBtn = document.getElementById('helpBtn');
const helpSection = document.getElementById('helpSection');
const exportPDFBtn = document.getElementById('exportPDFBtn');
const exportCSVBtn = document.getElementById('exportCSVBtn');
const printBtn = document.getElementById('printBtn');
const refreshBtn = document.getElementById('refreshBtn');
const reportResults = document.getElementById('reportResults');
const summaryCards = document.getElementById('summaryCards');
const plTableBody = document.getElementById('plTableBody');
const reportPeriod = document.getElementById('reportPeriod');
const validationMessage = document.getElementById('validationMessage');
const validationText = document.getElementById('validationText');

// Summary card elements
const totalRevenueEl = document.getElementById('totalRevenue');
const totalCOGSEl = document.getElementById('totalCOGS');
const profitMarginEl = document.getElementById('profitMargin');
const grossProfitEl = document.getElementById('grossProfit');
const totalExpensesEl = document.getElementById('totalExpenses');
const netProfitEl = document.getElementById('netProfit');
const netProfitCard = document.getElementById('netProfitCard');

// Zakat Modal Elements
const zakatModal = document.getElementById('zakatModal');
const zakatNetProfitEl = document.getElementById('zakatNetProfit');
const zakatPercentageEl = document.getElementById('zakatPercentage');
const zakatAmountEl = document.getElementById('zakatAmount');
let currentNetProfit = 0;
let currentCurrencySymbol = CURRENCY_SYMBOL;

// Set default dates (current month)
const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
const lastDay = today; // Use today's date instead of last day of month

// Format date as YYYY-MM-DD
function formatDate(date) {
    return date.toISOString().split('T')[0];
}

startDateEl.value = formatDate(firstDay);
endDateEl.value = formatDate(lastDay);

// Load companies
loadCompanies();
loadCurrencies();
loadProjects();

async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_statement/get-companies.php');
        const result = await response.json();
        
        if (result.success) {
            companyFilterEl.innerHTML = '<option value="">All Companies</option>';
            result.data.forEach(company => {
                companyFilterEl.innerHTML += `<option value="${company.id}">${company.company_name}</option>`;
            });
            
            if (result.data.length === 1) {
                companyFilterEl.value = result.data[0].id;
            }
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

// Load currencies
async function loadCurrencies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=currencies');
        const result = await response.json();
        
        if (result.success) {
            currencyFilterEl.innerHTML = '';
            result.data.forEach(currency => {
                const isBase = currency.is_base_currency == 1;
                currencyFilterEl.innerHTML += `<option value="${currency.id}" ${isBase ? 'selected' : ''}>${currency.name} (${currency.symbol})${isBase ? ' - Base' : ''}</option>`;
                if (isBase) {
                    currentCurrencySymbol = currency.symbol;
                }
            });
        }
    } catch (error) {
        console.error('Error loading currencies:', error);
    }
}

// Load projects
async function loadProjects() {
    try {
        const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const result = await response.json();
        
        if (result.success) {
            projectFilterEl.innerHTML = '<option value="">All Projects</option>';
            result.data.forEach(project => {
                projectFilterEl.innerHTML += `<option value="${project.id}">${project.project_code} - ${project.project_name} - ${project.city_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Regenerate report when project changes
projectFilterEl.addEventListener('change', async function() {
    if (reportResults.style.display !== 'none') {
        await generateReport();
    }
});

// Update currency symbol when currency changes
currencyFilterEl.addEventListener('change', async function() {
    const selectedOption = currencyFilterEl.options[currencyFilterEl.selectedIndex];
    const symbolMatch = selectedOption.text.match(/\((.+?)\)/);
    if (symbolMatch) {
        currentCurrencySymbol = symbolMatch[1];
    }
    // Regenerate report if already generated
    if (reportResults.style.display !== 'none') {
        await generateReport();
    }
});



// Format currency
function formatCurrency(amount) {
    return currentCurrencySymbol + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Calculate percentages
function calculatePercentage(amount, totalRevenue) {
    if (totalRevenue === 0 || totalRevenue < 100) return 'N/A';
    const percentage = ((amount / totalRevenue) * 100).toFixed(2);
    const percentValue = parseFloat(percentage);
    if (percentValue > 100 || percentValue < -100) return '>100%';
    return percentage + '%';
}

// Fetch P&L data from API
async function fetchPLData(startDate, endDate, branchId, companyId, currencyId, projectId) {
    let url = `../../../../server/api/financial_reports/profit_loss_statement/profit-loss.php?start_date=${startDate}&end_date=${endDate}`;
    if (branchId && branchId !== 'all') {
        url += `&branch_id=${branchId}`;
    }
    if (companyId) {
        url += `&company_id=${companyId}`;
    }
    if (currencyId) {
        url += `&currency_id=${currencyId}`;
    }
    if (projectId) {
        url += `&project_id=${projectId}`;
    }
    const response = await fetch(url);
    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.message || 'Failed to fetch data');
    }
    
    window.debugResult = result;
    return result.data;
}

// Validate form inputs
function validateForm() {
    const startDate = new Date(startDateEl.value);
    const endDate = new Date(endDateEl.value);

    // Clear previous validation
    startDateEl.classList.remove('error');
    endDateEl.classList.remove('error');

    let isValid = true;
    let errorMessage = '';

    // Check if start date is after end date
    if (startDate > endDate) {
        startDateEl.classList.add('error');
        endDateEl.classList.add('error');
        errorMessage = 'Start date cannot be after end date.';
        isValid = false;
    }

    // Show validation message
    if (!isValid) {
        validationMessage.className = 'validation-message validation-error';
        validationText.textContent = errorMessage;
        validationMessage.style.display = 'flex';
    } else {
        validationMessage.style.display = 'none';
    }

    return isValid;
}

// Generate the P&L report
async function generateReport() {
    if (!validateForm()) return;

    try {
        // Show loading message
        validationMessage.className = 'validation-message validation-success';
        validationText.textContent = 'Loading report data...';
        validationMessage.style.display = 'flex';

        // Fetch data from API
        const data = await fetchPLData(startDateEl.value, endDateEl.value, branchFilterEl.value, companyFilterEl.value, currencyFilterEl.value, projectFilterEl.value);
        
        console.log('API Response:', data);
        console.log('Sales Revenue:', data.sales_revenue);
        console.log('Service Revenue:', data.service_revenue);
        console.log('Rent Income:', data.rent_income);
        console.log('Debug Info:', window.debugResult.debug);

        // Show success message
        validationMessage.className = 'validation-message validation-success';
        validationText.textContent = 'Report generated successfully.';
        validationMessage.style.display = 'flex';

    // Calculate totals
    let totalSalesRevenue = (data.sales_revenue || []).reduce((sum, item) => sum + item.amount, 0);
    let totalServiceRevenue = (data.service_revenue || []).reduce((sum, item) => sum + item.amount, 0);
    let totalRentIncome = (data.rent_income || []).reduce((sum, item) => sum + item.amount, 0);
    let totalRevenue = totalSalesRevenue + totalServiceRevenue + totalRentIncome;
    let totalCOGS = data.cogs.reduce((sum, item) => sum + item.amount, 0);
    let totalExpenses = data.expenses.reduce((sum, item) => sum + item.amount, 0);
    let totalOtherIncome = (data.other_income || []).reduce((sum, item) => sum + item.amount, 0);

    let grossProfit = totalRevenue - totalCOGS;
    let netProfit = grossProfit - totalExpenses - totalOtherIncome;
    let profitMargin = totalRevenue > 0 ? ((grossProfit / totalRevenue) * 100).toFixed(2) : 0;

    // Update summary cards
    totalRevenueEl.textContent = formatCurrency(totalRevenue);
    totalCOGSEl.textContent = formatCurrency(totalCOGS);
    profitMarginEl.textContent = profitMargin + '%';
    grossProfitEl.textContent = formatCurrency(grossProfit);
    totalExpensesEl.textContent = formatCurrency(totalExpenses);
    netProfitEl.textContent = formatCurrency(netProfit);

    // Store net profit for Zakat calculation
    currentNetProfit = netProfit;

    // Style net profit card based on value
    if (netProfit >= 0) {
        netProfitCard.className = 'summary-card summary-card-profit';
    } else {
        netProfitCard.className = 'summary-card summary-card-loss';
    }

    // Update report period display
    const startDate = new Date(startDateEl.value);
    const endDate = new Date(endDateEl.value);
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    reportPeriod.textContent = `Period: ${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}`;

    // Build the P&L table
    let tableHTML = '';
    const detailLevel = detailLevelEl.value;

    // Sales Revenue section
    tableHTML += `<tr class="subtotal-row"><td colspan="3">SALES REVENUE</td></tr>`;
    if (detailLevel !== 'category') {
        (data.sales_revenue || []).forEach(item => {
            tableHTML += `
                        <tr>
                            <td style="padding-left: 20px;">${item.account}</td>
                            <td class="amount-cell positive">${formatCurrency(item.amount)}</td>
                            <td class="amount-cell">${calculatePercentage(item.amount, totalRevenue)}</td>
                        </tr>
                    `;
        });
    }
    tableHTML += `
                <tr class="subtotal-row">
                    <td>Total Sales Revenue</td>
                    <td class="amount-cell positive">${formatCurrency(totalSalesRevenue)}</td>
                    <td class="amount-cell">${calculatePercentage(totalSalesRevenue, totalRevenue)}</td>
                </tr>
            `;

    // Service Revenue section
    tableHTML += `<tr class="subtotal-row"><td colspan="3">SERVICE REVENUE</td></tr>`;
    if (detailLevel !== 'category') {
        (data.service_revenue || []).forEach(item => {
            tableHTML += `
                        <tr>
                            <td style="padding-left: 20px;">${item.account}</td>
                            <td class="amount-cell positive">${formatCurrency(item.amount)}</td>
                            <td class="amount-cell">${calculatePercentage(item.amount, totalRevenue)}</td>
                        </tr>
                    `;
        });
    }
    tableHTML += `
                <tr class="subtotal-row">
                    <td>Total Service Revenue</td>
                    <td class="amount-cell positive">${formatCurrency(totalServiceRevenue)}</td>
                    <td class="amount-cell">${calculatePercentage(totalServiceRevenue, totalRevenue)}</td>
                </tr>
            `;

    // Rent Income section
    tableHTML += `<tr class="subtotal-row"><td colspan="3">RENT INCOME</td></tr>`;
    if (detailLevel !== 'category') {
        (data.rent_income || []).forEach(item => {
            tableHTML += `
                        <tr>
                            <td style="padding-left: 20px;">${item.account}</td>
                            <td class="amount-cell positive">${formatCurrency(item.amount)}</td>
                            <td class="amount-cell">${calculatePercentage(item.amount, totalRevenue)}</td>
                        </tr>
                    `;
        });
    }
    tableHTML += `
                <tr class="subtotal-row">
                    <td>Total Rent Income</td>
                    <td class="amount-cell positive">${formatCurrency(totalRentIncome)}</td>
                    <td class="amount-cell">${calculatePercentage(totalRentIncome, totalRevenue)}</td>
                </tr>
            `;

    // Total Revenue
    tableHTML += `
                <tr class="subtotal-row">
                    <td><strong>Total Revenue</strong></td>
                    <td class="amount-cell positive">${formatCurrency(totalRevenue)}</td>
                    <td class="amount-cell">100.00%</td>
                </tr>
            `;

    // Cost of Goods Sold section
    tableHTML += `<tr class="subtotal-row"><td colspan="3">COST OF GOODS SOLD</td></tr>`;
    if (detailLevel !== 'category') {
        data.cogs.forEach(item => {
            tableHTML += `
                        <tr>
                            <td style="padding-left: 20px;">${item.account}</td>
                            <td class="amount-cell negative">(${formatCurrency(item.amount)})</td>
                            <td class="amount-cell">${calculatePercentage(item.amount, totalRevenue)}</td>
                        </tr>
                    `;
        });
    }
    tableHTML += `
                <tr class="subtotal-row">
                    <td>Total Cost of Goods Sold</td>
                    <td class="amount-cell negative">(${formatCurrency(totalCOGS)})</td>
                    <td class="amount-cell">${calculatePercentage(totalCOGS, totalRevenue)}</td>
                </tr>
            `;

    // Gross Profit
    tableHTML += `
                <tr class="subtotal-row">
                    <td><strong>Gross Profit</strong></td>
                    <td class="amount-cell ${grossProfit >= 0 ? 'positive' : 'negative'}">${grossProfit >= 0 ? formatCurrency(grossProfit) : `(${formatCurrency(Math.abs(grossProfit))})`}</td>
                    <td class="amount-cell">${calculatePercentage(grossProfit, totalRevenue)}</td>
                </tr>
            `;

    // Operating Expenses section
    tableHTML += `<tr class="subtotal-row"><td colspan="3">OPERATING EXPENSES</td></tr>`;
    if (detailLevel !== 'category') {
        data.expenses.forEach(item => {
            tableHTML += `
                        <tr>
                            <td style="padding-left: 20px;">${item.account}</td>
                            <td class="amount-cell negative">(${formatCurrency(item.amount)})</td>
                            <td class="amount-cell">${calculatePercentage(item.amount, totalRevenue)}</td>
                        </tr>
                    `;
        });
    }
    tableHTML += `
                <tr class="subtotal-row">
                    <td>Total Operating Expenses</td>
                    <td class="amount-cell negative">(${formatCurrency(totalExpenses)})</td>
                    <td class="amount-cell">${calculatePercentage(totalExpenses, totalRevenue)}</td>
                </tr>
            `;

    // Other Income section
    if (totalOtherIncome > 0) {
        tableHTML += `<tr class="subtotal-row"><td colspan="3"><strong>OTHER INCOME</strong></td></tr>`;
        if (detailLevel !== 'category') {
            (data.other_income || []).forEach(item => {
                tableHTML += `
                            <tr>
                                <td style="padding-left: 20px;">${item.account}</td>
                                <td class="amount-cell positive">${formatCurrency(item.amount)}</td>
                                <td class="amount-cell">${calculatePercentage(item.amount, totalRevenue)}</td>
                            </tr>
                        `;
            });
        }
        tableHTML += `
                    <tr class="subtotal-row">
                        <td>Total Other Income</td>
                        <td class="amount-cell positive">${formatCurrency(totalOtherIncome)}</td>
                        <td class="amount-cell">${calculatePercentage(totalOtherIncome, totalRevenue)}</td>
                    </tr>
                `;
    }

    // Net Profit
    tableHTML += `
                <tr class="total-row">
                    <td><strong>NET PROFIT/LOSS</strong></td>
                    <td class="amount-cell ${netProfit >= 0 ? 'positive' : 'negative'}">${netProfit >= 0 ? formatCurrency(netProfit) : `(${formatCurrency(Math.abs(netProfit))})`}</td>
                    <td class="amount-cell">${calculatePercentage(netProfit, totalRevenue)}</td>
                </tr>
            `;

    // Update table body
    plTableBody.innerHTML = tableHTML;

    // Show results sections
    summaryCards.style.display = 'grid';
    reportResults.style.display = 'block';

    // Show/hide charts based on includeGraphs setting
    const chartsSection = document.getElementById('chartsSection');
    if (includeGraphsEl.value === 'yes') {
        chartsSection.style.display = 'block';
        renderCharts(data, totalRevenue, totalCOGS, grossProfit, totalExpenses, netProfit);
    } else {
        chartsSection.style.display = 'none';
    }

        // Scroll to results
        reportResults.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
        validationMessage.className = 'validation-message validation-error';
        validationText.textContent = 'Error: ' + error.message;
        validationMessage.style.display = 'flex';
    }
}

// Reset form to default values
function resetForm() {
    startDateEl.value = formatDate(firstDay);
    endDateEl.value = formatDate(today);
    branchFilterEl.value = 'all';
    companyFilterEl.value = '';
    projectFilterEl.value = '';
    document.getElementById('comparativePeriod').value = 'none';
    detailLevelEl.value = 'detailed';
    includeGraphsEl.value = 'yes';

    // Hide results
    summaryCards.style.display = 'none';
    reportResults.style.display = 'none';
    validationMessage.style.display = 'none';

    // Clear any error states
    startDateEl.classList.remove('error');
    endDateEl.classList.remove('error');

    // Show reset confirmation
    validationMessage.className = 'validation-message validation-success';
    validationText.textContent = 'Form has been reset to default values.';
    validationMessage.style.display = 'flex';
}

// Export to PDF (simulated)
function exportToPDF() {
    validationMessage.className = 'validation-message validation-success';
    validationText.textContent = 'PDF export started. Your file will download shortly.';
    validationMessage.style.display = 'flex';

    // In a real application, this would generate and download a PDF
    setTimeout(() => {
        alert('In a real application, this would download a PDF file containing the P&L report.');
    }, 500);
}

// Export to CSV (simulated)
function exportToCSV() {
    validationMessage.className = 'validation-message validation-success';
    validationText.textContent = 'CSV export started. Your file will download shortly.';
    validationMessage.style.display = 'flex';

    // In a real application, this would generate and download a CSV
    setTimeout(() => {
        alert('In a real application, this would download a CSV file containing the P&L data.');
    }, 500);
}

// Print report
function printReport() {
    window.print();
}

// Toggle help section
function toggleHelp() {
    helpSection.style.display = helpSection.style.display === 'none' ? 'block' : 'none';
}

// Render charts
let revenueChart = null;
let expenseChart = null;

function renderCharts(data, totalRevenue, totalCOGS, grossProfit, totalExpenses, netProfit) {
    // Destroy existing charts
    if (revenueChart) revenueChart.destroy();
    if (expenseChart) expenseChart.destroy();

    // Revenue Breakdown Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    revenueChart = new Chart(revenueCtx, {
        type: 'doughnut',
        data: {
            labels: ['Gross Profit', 'COGS'],
            datasets: [{
                data: [grossProfit, totalCOGS],
                backgroundColor: ['#2fbf71', '#e34f4f']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Revenue vs COGS'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Profit Analysis Chart
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    expenseChart = new Chart(expenseCtx, {
        type: 'bar',
        data: {
            labels: ['Revenue', 'COGS', 'Expenses', 'Net Profit'],
            datasets: [{
                label: 'Amount',
                data: [totalRevenue, -totalCOGS, -totalExpenses, netProfit],
                backgroundColor: ['#2fbf71', '#e34f4f', '#e8b23f', netProfit >= 0 ? '#2fbf71' : '#e34f4f']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Profit & Loss Breakdown'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Event Listeners
generateReportBtn.addEventListener('click', generateReport);
resetBtn.addEventListener('click', resetForm);
zakatBtn.addEventListener('click', openZakatModal);
helpBtn.addEventListener('click', toggleHelp);
exportPDFBtn.addEventListener('click', exportToPDF);
exportCSVBtn.addEventListener('click', exportToCSV);
printBtn.addEventListener('click', printReport);
refreshBtn.addEventListener('click', generateReport);

// Validate dates when they change
startDateEl.addEventListener('change', validateForm);
endDateEl.addEventListener('change', validateForm);

// Initialize
window.addEventListener('DOMContentLoaded', () => {
    // Show help section by default
    helpSection.style.display = 'block';
});

// Zakat Calculator Functions
function openZakatModal() {
    if (currentNetProfit === 0) {
        validationMessage.className = 'validation-message validation-error';
        validationText.textContent = 'Please generate a report first to calculate Zakat.';
        validationMessage.style.display = 'flex';
        return;
    }
    zakatNetProfitEl.value = formatCurrency(currentNetProfit);
    calculateZakat();
    zakatModal.style.display = 'block';
}

function closeZakatModal() {
    zakatModal.style.display = 'none';
}

function calculateZakat() {
    const percentage = parseFloat(zakatPercentageEl.value) || 0;
    const zakatAmount = (currentNetProfit * percentage) / 100;
    zakatAmountEl.value = formatCurrency(zakatAmount);
}

// Event Listeners for Zakat
zakatPercentageEl.addEventListener('input', calculateZakat);

// Close modal when clicking outside
window.addEventListener('click', (event) => {
    if (event.target === zakatModal) {
        closeZakatModal();
    }
});
