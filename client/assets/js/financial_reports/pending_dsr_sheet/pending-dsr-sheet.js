let dsrData = [];

// DOM elements
const tableBody = document.getElementById('table-body');
const emptyState = document.getElementById('empty-state');
const filterSalesOfficer = document.getElementById('filter-sales-officer');
const filterCompany = document.getElementById('filter-company');
const filterReference = document.getElementById('filter-reference');
const filterAmount = document.getElementById('filter-amount');
const filterStatus = document.getElementById('filter-status');
const applyBtn = document.getElementById('apply-btn');
const resetBtn = document.getElementById('reset-btn');
const refreshBtn = document.getElementById('refresh-btn');
const prevBtn = document.getElementById('prev-btn');
const nextBtn = document.getElementById('next-btn');
const paginationInfo = document.getElementById('pagination-info');

// Pagination variables
let currentPage = 1;
const rowsPerPage = 10;
let filteredData = [...dsrData];

// Format currency
function formatCurrency(amount) {
    return currencySymbol + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// Calculate remaining balance
function calculateBalance(sheetAmount, recoveryAmount) {
    return sheetAmount - recoveryAmount;
}

// Get status badge class
function getStatusClass(status) {
    switch (status) {
        case 'no-recovery': return 'status-no-recovery';
        case 'pending': return 'status-pending';
        case 'completed': return 'status-completed';
        default: return '';
    }
}

// Get status text
function getStatusText(status) {
    switch (status) {
        case 'no-recovery': return 'No Recovery';
        case 'pending': return 'Pending';
        case 'completed': return 'Completed';
        default: return status;
    }
}

// Render table rows
function renderTable(data) {
    tableBody.innerHTML = '';

    if (data.length === 0) {
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';

    // Calculate pagination
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const pageData = data.slice(startIndex, endIndex);

    // Update pagination info
    const totalEntries = data.length;
    const showingStart = totalEntries === 0 ? 0 : startIndex + 1;
    const showingEnd = Math.min(endIndex, totalEntries);
    paginationInfo.textContent = `Showing ${showingStart}-${showingEnd} of ${totalEntries} entries`;

    // Render rows
    pageData.forEach((item, index) => {
        const remainingBalance = calculateBalance(item.sheetAmount, item.recoveryAmount);
        const row = document.createElement('tr');

        row.innerHTML = `
                    <td>${startIndex + index + 1}</td>
                    <td>${item.salesOfficer}</td>
                    <td><strong>${item.reference}</strong></td>
                    <td class="amount">${formatCurrency(item.sheetAmount)}</td>
                    <td class="amount">${formatCurrency(item.recoveryAmount)}</td>
                    <td class="amount ${remainingBalance > 0 ? 'negative-amount' : 'positive-amount'}">
                        ${formatCurrency(Math.abs(remainingBalance))}
                    </td>
                    <td>
                        <span class="status-badge ${getStatusClass(item.status)}">
                            ${getStatusText(item.status)}
                        </span>
                    </td>
                `;

        tableBody.appendChild(row);
    });
    
    updatePaginationButtons();
}

// Filter data based on filters
function filterData() {
    const salesOfficerFilter = filterSalesOfficer.value.toLowerCase().trim();
    const referenceFilter = filterReference.value.toLowerCase().trim();
    const amountFilter = filterAmount.value;
    const statusFilter = filterStatus.value;

    filteredData = dsrData.filter(item => {
        // Filter by sales officer
        if (salesOfficerFilter && item.salesOfficer.toLowerCase() !== salesOfficerFilter) {
            return false;
        }

        // Filter by reference
        if (referenceFilter && !item.reference.toLowerCase().includes(referenceFilter)) {
            return false;
        }

        // Filter by amount range
        if (amountFilter) {
            if (amountFilter === 'low' && item.sheetAmount >= 1000) return false;
            if (amountFilter === 'medium' && (item.sheetAmount < 1000 || item.sheetAmount > 5000)) return false;
            if (amountFilter === 'high' && item.sheetAmount <= 5000) return false;
        }

        // Filter by status
        if (statusFilter && item.status !== statusFilter) {
            return false;
        }

        return true;
    });

    currentPage = 1; // Reset to first page after filtering
    renderTable(filteredData);
}

function resetFilters() {
    filterSalesOfficer.value = '';
    filterCompany.value = '';
    filterReference.value = '';
    filterAmount.value = '';
    filterStatus.value = '';
    filteredData = [...dsrData];
    currentPage = 1;
    renderTable(filteredData);
}

// Fetch companies
async function fetchCompanies() {
    try {
        const response = await fetch('../../../../server/api/companies/get-companies.php');
        const result = await response.json();
        
        if (result.success && result.data) {
            filterCompany.innerHTML = '<option value="">All Companies</option>';
            
            result.data.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                filterCompany.appendChild(option);
            });
            
            if (result.data.length === 1) {
                filterCompany.value = result.data[0].id;
            }
        }
    } catch (error) {
        console.error('Error fetching companies:', error);
    }
}

// Event listeners
applyBtn.addEventListener('click', filterData);

resetBtn.addEventListener('click', resetFilters);

refreshBtn.addEventListener('click', function () {
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing';
    fetchData().then(() => {
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
    });
});

// Filter when Enter is pressed in the reference field
filterReference.addEventListener('keyup', function (event) {
    if (event.key === 'Enter') {
        filterData();
    }
});

// Pagination controls
prevBtn.addEventListener('click', function () {
    if (currentPage > 1) {
        currentPage--;
        renderTable(filteredData);
        updatePaginationButtons();
    }
});

nextBtn.addEventListener('click', function () {
    const totalPages = Math.ceil(filteredData.length / rowsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        renderTable(filteredData);
        updatePaginationButtons();
    }
});

// Update pagination buttons
function updatePaginationButtons() {
    const totalPages = Math.ceil(filteredData.length / rowsPerPage);
    const paginationControls = document.querySelector('.pagination-controls');
    
    let html = `<button class="pagination-btn" id="prev-btn"><i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = 1; i <= Math.min(totalPages, 5); i++) {
        html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
    }
    
    html += `<button class="pagination-btn" id="next-btn"><i class="fas fa-chevron-right"></i></button>`;
    
    paginationControls.innerHTML = html;
    
    document.getElementById('prev-btn').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderTable(filteredData);
            updatePaginationButtons();
        }
    });
    
    document.getElementById('next-btn').addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            renderTable(filteredData);
            updatePaginationButtons();
        }
    });
}

function goToPage(page) {
    currentPage = page;
    renderTable(filteredData);
    updatePaginationButtons();
}

// Fetch data from API
async function fetchData() {
    try {
        const companyId = filterCompany.value;
        const url = companyId 
            ? `../../../../server/api/financial_reports/pending_dsr_sheet/pending-dsr-sheet.php?company_id=${companyId}`
            : '../../../../server/api/financial_reports/pending_dsr_sheet/pending-dsr-sheet.php';
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            dsrData = result.data;
            filteredData = [...dsrData];
            populateSalesOfficers();
            renderTable(filteredData);
        } else {
            console.error('Error:', result.message);
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

// Populate sales officers dropdown
function populateSalesOfficers() {
    const officers = [...new Set(dsrData.map(item => item.salesOfficer))].sort();
    filterSalesOfficer.innerHTML = '<option value="">All Sales Officers</option>';
    officers.forEach(officer => {
        const option = document.createElement('option');
        option.value = officer.toLowerCase();
        option.textContent = officer;
        filterSalesOfficer.appendChild(option);
    });
}

// Initialize the table
fetchCompanies();
fetchData();