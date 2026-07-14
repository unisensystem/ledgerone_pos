let reportData = [];
let filteredData = [];
let summaryData = {};
let currentPage = 1;
const itemsPerPage = 10;

function buildUrl() {
    const company = document.getElementById('companyFilter').value;
    const dateRange = document.getElementById('dateRangeFilter').value;
    const params = new URLSearchParams();

    if (company) params.set('company_id', company);

    if (dateRange === 'custom') {
        const from = document.getElementById('dateFrom').value;
        const to = document.getElementById('dateTo').value;
        if (from) params.set('date_from', from);
        if (to) params.set('date_to', to);
    } else if (dateRange !== 'all') {
        params.set('date_range', dateRange);
    }

    const base = '../../../../server/api/financial_reports/supplier_aging/supplier-aging.php';
    return params.toString() ? base + '?' + params.toString() : base;
}

async function fetchReportData() {
    try {
        const response = await fetch(buildUrl());
        const result = await response.json();
        if (result.success) {
            reportData = result.data;
            summaryData = result.summary;
            applyBucketFilter();
            updateSummary();
        } else {
            alert('Error loading data: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load report data');
    }
}

function applyBucketFilter() {
    const bucket = document.getElementById('bucketFilter').value;
    if (bucket === 'all') {
        filteredData = [...reportData];
    } else {
        filteredData = reportData.filter(item => item.bucket === bucket);
    }
    currentPage = 1;
    renderTable();
}

function updateSummary() {
    const cards = document.querySelectorAll('.bucket-card');
    const map = {
        'all':    { value: summaryData.total_outstanding, count: null,                    label: 'Across all suppliers' },
        'current':{ value: summaryData.bucket_current,    count: summaryData.count_current },
        '1-15':   { value: summaryData.bucket_1_15,       count: summaryData.count_1_15 },
        '16-30':  { value: summaryData.bucket_16_30,      count: summaryData.count_16_30 },
        '31-45':  { value: summaryData.bucket_31_45,      count: summaryData.count_31_45 },
        '45+':    { value: summaryData.bucket_45,         count: summaryData.count_45 }
    };

    cards.forEach(card => {
        const key = card.dataset.bucket;
        const d = map[key];
        if (!d) return;
        card.querySelector('.bucket-value').textContent = d.value || '$0.00';
        if (d.count !== null && d.count !== undefined) {
            card.querySelector('.mt-2').textContent = `${d.count} invoices`;
        }
    });
}

function renderTable() {
    const tableBody = document.getElementById('reportTableBody');
    tableBody.innerHTML = '';

    if (filteredData.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px;">No invoices found</td></tr>';
        updatePaginationInfo();
        return;
    }

    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginatedData = filteredData.slice(startIndex, startIndex + itemsPerPage);

    paginatedData.forEach(item => {
        let badgeClass = 'badge-success';
        if (item.bucket === '1-15') badgeClass = 'badge-primary';
        else if (item.bucket === '16-30' || item.bucket === '31-45') badgeClass = 'badge-warning';
        else if (item.bucket === '45+') badgeClass = 'badge-error';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.id}</td>
            <td><strong>${item.supplierCode}</strong></td>
            <td>${item.supplierName}</td>
            <td>${item.address}</td>
            <td><span class="badge badge-primary">${item.invoiceNo}</span></td>
            <td>${item.invoiceDate}</td>
            <td>${item.type}</td>
            <td><strong>${item.amount}</strong></td>
            <td>${item.daysOverdue} days</td>
            <td><span class="badge ${badgeClass}">${item.bucket === 'current' ? 'Current' : item.bucket + ' days'}</span></td>
        `;
        tableBody.appendChild(row);
    });

    updatePaginationInfo();
    renderPaginationButtons();
}

function updatePaginationInfo() {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    const startEntry = filteredData.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endEntry = Math.min(currentPage * itemsPerPage, filteredData.length);

    document.querySelector('.card-header .pagination-info').textContent =
        `Showing ${startEntry}-${endEntry} of ${filteredData.length} entries`;
    document.querySelector('.pagination .pagination-info').textContent =
        `Page ${filteredData.length === 0 ? 0 : currentPage} of ${Math.max(totalPages, 1)}`;
}

function renderPaginationButtons() {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    const paginationControls = document.querySelector('.pagination-controls');
    paginationControls.innerHTML = '';

    const prevBtn = document.createElement('button');
    prevBtn.className = 'page-btn';
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => changePage(currentPage - 1);
    paginationControls.appendChild(prevBtn);

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

    const nextBtn = document.createElement('button');
    nextBtn.className = 'page-btn';
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    nextBtn.onclick = () => changePage(currentPage + 1);
    paginationControls.appendChild(nextBtn);
}

function changePage(page) {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderTable();
}

function printReport() {
    window.open('print.php', '_blank');
}

function exportToExcel() {
    alert('Exporting report to Excel format');
}

function exportToJSON() {
    const dataStr = JSON.stringify(filteredData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'supplier-aging-report.json';
    link.click();
    URL.revokeObjectURL(url);
}

async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/supplier_aging/get-companies.php');
        const result = await response.json();
        if (result.success) {
            const companyFilter = document.getElementById('companyFilter');
            companyFilter.innerHTML = '<option value="">All Companies</option>';
            result.data.forEach(company => {
                companyFilter.innerHTML += `<option value="${company.id}">${company.company_name}</option>`;
            });
            if (result.data.length === 1) companyFilter.value = result.data[0].id;
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

document.addEventListener('DOMContentLoaded', async function () {
    await loadCompanies();
    await fetchReportData();

    // Date range toggle for custom inputs
    document.getElementById('dateRangeFilter').addEventListener('change', function () {
        const isCustom = this.value === 'custom';
        document.getElementById('customDateGroup').style.display = isCustom ? '' : 'none';
        document.getElementById('customDateGroupTo').style.display = isCustom ? '' : 'none';
    });

    // Apply filters button
    document.getElementById('applyFiltersBtn').addEventListener('click', fetchReportData);

    // Bucket filter dropdown change
    document.getElementById('bucketFilter').addEventListener('change', applyBucketFilter);

    // Bucket cards click to filter
    document.querySelectorAll('.bucket-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () {
            const bucket = this.dataset.bucket;
            document.getElementById('bucketFilter').value = bucket;
            applyBucketFilter();
        });
    });

    // Reset filters
    document.getElementById('resetFiltersBtn').addEventListener('click', function () {
        document.getElementById('companyFilter').value = '';
        document.getElementById('dateRangeFilter').value = '30days';
        document.getElementById('bucketFilter').value = 'all';
        document.getElementById('customDateGroup').style.display = 'none';
        document.getElementById('customDateGroupTo').style.display = 'none';
        fetchReportData();
    });
});
