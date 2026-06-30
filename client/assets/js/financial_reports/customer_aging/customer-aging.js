let reportData = [];
let summaryData = {};
let currentPage = 1;
const itemsPerPage = 10;

// Fetch data from API
async function fetchReportData() {
    try {
        const companyFilter = document.getElementById('companyFilter');
        let url = '../../../../server/api/financial_reports/customer_aging/customer-aging.php';
        if (companyFilter && companyFilter.value) {
            url += '?company_id=' + companyFilter.value;
        }
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            reportData = result.data;
            summaryData = result.summary;
            renderTable();
            updateSummary();
        } else {
            alert('Error loading data: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load report data');
    }
}

// Update summary cards
function updateSummary() {
    const bucketCards = document.querySelectorAll('.bucket-card');
    if (bucketCards.length >= 4) {
        bucketCards[0].querySelector('.bucket-value').textContent = summaryData.total_overdue || '$0.00';
        bucketCards[1].querySelector('.bucket-value').textContent = summaryData.bucket_15 || '$0.00';
        bucketCards[1].querySelector('.mt-2').textContent = `${summaryData.count_15 || 0} invoices`;
        bucketCards[2].querySelector('.bucket-value').textContent = summaryData.bucket_25 || '$0.00';
        bucketCards[2].querySelector('.mt-2').textContent = `${summaryData.count_25 || 0} invoices`;
        bucketCards[3].querySelector('.bucket-value').textContent = summaryData.bucket_45 || '$0.00';
        bucketCards[3].querySelector('.mt-2').textContent = `${summaryData.count_45 || 0} invoices`;
    }
}

// Function to render table rows
function renderTable() {
    const tableBody = document.getElementById('reportTableBody');
    tableBody.innerHTML = '';

    if (reportData.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px;">No overdue invoices found</td></tr>';
        updatePaginationInfo();
        return;
    }

    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = reportData.slice(startIndex, endIndex);

    paginatedData.forEach(item => {
        let badgeClass = "badge-primary";
        if (item.bucket === "25+" || item.bucket === "35+") {
            badgeClass = "badge-warning";
        } else if (item.bucket === "45+") {
            badgeClass = "badge-error";
        }

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.id}</td>
            <td><strong>${item.customerCode}</strong></td>
            <td>${item.customerName}</td>
            <td>${item.address}</td>
            <td><span class="badge badge-primary">${item.invoiceNo}</span></td>
            <td>${item.invoiceDate}</td>
            <td>${item.type}</td>
            <td><strong>${item.amount}</strong></td>
            <td>${item.daysOverdue} days</td>
            <td><span class="badge ${badgeClass}">${item.bucket} days</span></td>
        `;
        tableBody.appendChild(row);
    });
    
    updatePaginationInfo();
    renderPaginationButtons();
}

function updatePaginationInfo() {
    const totalPages = Math.ceil(reportData.length / itemsPerPage);
    const startEntry = reportData.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endEntry = Math.min(currentPage * itemsPerPage, reportData.length);
    
    document.querySelector('.card-header .pagination-info').textContent = 
        `Showing ${startEntry}-${endEntry} of ${reportData.length} entries`;
    document.querySelector('.pagination .pagination-info').textContent = 
        `Page ${reportData.length === 0 ? 0 : currentPage} of ${totalPages}`;
}

function renderPaginationButtons() {
    const totalPages = Math.ceil(reportData.length / itemsPerPage);
    const paginationControls = document.querySelector('.pagination-controls');
    paginationControls.innerHTML = '';
    
    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'page-btn';
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => changePage(currentPage - 1);
    paginationControls.appendChild(prevBtn);
    
    // Page number buttons
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            const pageBtn = document.createElement('button');
            pageBtn.className = 'page-btn' + (i === currentPage ? ' active' : '');
            pageBtn.textContent = i;
            pageBtn.onclick = () => changePage(i);
            paginationControls.appendChild(pageBtn);
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.padding = '0 8px';
            paginationControls.appendChild(dots);
        }
    }
    
    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'page-btn';
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => changePage(currentPage + 1);
    paginationControls.appendChild(nextBtn);
}

function changePage(page) {
    const totalPages = Math.ceil(reportData.length / itemsPerPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderTable();
}

// Action functions
function viewDetails(id) {
    alert(`Viewing details for record #${id}`);
}

function sendReminder(id) {
    alert(`Sending payment reminder for record #${id}`);
}

function addNote(id) {
    alert(`Adding note to record #${id}`);
}

// Action functions
function viewDetails(id) {
    alert(`Viewing details for record #${id}`);
}

function sendReminder(id) {
    alert(`Sending payment reminder for record #${id}`);
}

function addNote(id) {
    alert(`Adding note to record #${id}`);
}

// Filter functionality
function filterByBucket(bucket) {
    // In a real app, this would filter the data
    alert(`Filtering by bucket: ${bucket}`);
}

// Export functionality
function printReport() {
    window.open('print.php', '_blank');
}

function exportToExcel() {
    alert('Exporting report to Excel format');
}

function exportToJSON() {
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'customer-aging-report.json';
    link.click();
    URL.revokeObjectURL(url);
}

// Initialize the table on page load
document.addEventListener('DOMContentLoaded', function () {
    loadCompanies();
    fetchReportData();

    // Add event listeners to bucket cards
    document.querySelectorAll('.bucket-card').forEach((card, index) => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () {
            const buckets = ['15+', '25+', '35+', '45+'];
            if (index < buckets.length) {
                filterByBucket(buckets[index]);
            }
        });
    });
});

// Load companies
async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/customer_aging/get-companies.php');
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
            
            // Add change event listener
            companyFilter.addEventListener('change', fetchReportData);
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}