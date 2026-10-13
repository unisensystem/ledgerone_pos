// Sample data for the gatepass list
let gatepassData = [];
let companies = [];

// Pagination variables
let currentPage = 1;
const itemsPerPage = 10;
let filteredData = [];

// Initialize the page
document.addEventListener('DOMContentLoaded', function () {
    // Fetch companies
    fetchCompanies();

    // Fetch gatepass data
    fetchGatepasses();

    // Setup event listeners
    setupEventListeners();
});

// Fetch companies from server
function fetchCompanies() {
    fetch('../../../../server/api/inventory/outward_gatepass/get-companies.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                companies = data.data;
                const companyFilter = document.getElementById('company-filter');
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companyFilter.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error fetching companies:', error));
}

// Fetch gatepasses from server
function fetchGatepasses() {
    fetch('../../../../server/api/inventory/outward_gatepass/gatepass-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                gatepassData = data.data;
                filteredData = [...gatepassData];
                renderTable();
            } else {
                console.error('Failed to fetch gatepasses:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching gatepasses:', error);
        });
}

// Setup all event listeners
function setupEventListeners() {
    // Search input
    document.getElementById('search-input').addEventListener('input', function () {
        currentPage = 1;
        filterData();
        renderTable();
    });

    // Filter dropdowns
    document.getElementById('date-from').addEventListener('change', function () {
        currentPage = 1;
        filterData();
        renderTable();
    });

    document.getElementById('date-to').addEventListener('change', function () {
        currentPage = 1;
        filterData();
        renderTable();
    });

    document.getElementById('company-filter').addEventListener('change', function () {
        currentPage = 1;
        filterData();
        renderTable();
    });

    // Clear filters button
    document.getElementById('clear-filters-btn').addEventListener('click', function () {
        clearFilters();
        filterData();
        renderTable();
    });

    // Refresh button
    document.getElementById('refresh-btn').addEventListener('click', function () {
        fetchGatepasses();
        showNotification('Data refreshed successfully', 'success');
    });

    // New gatepass button
    document.getElementById('new-gatepass-btn').addEventListener('click', function () {
        window.location.href = 'gatepass-add.php';
    });

    // Pagination controls
    document.getElementById('first-page').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage = 1;
            renderTable();
        }
    });

    document.getElementById('prev-page').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    document.getElementById('next-page').addEventListener('click', function () {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    });

    document.getElementById('last-page').addEventListener('click', function () {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage = totalPages;
            renderTable();
        }
    });

    // Page input
    document.getElementById('page-input').addEventListener('change', function () {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        let page = parseInt(this.value);

        if (isNaN(page) || page < 1) {
            page = 1;
        } else if (page > totalPages) {
            page = totalPages;
        }

        currentPage = page;
        this.value = page;
        renderTable();
    });
}

// Filter data based on search and filters
function filterData() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const companyId = document.getElementById('company-filter').value;

    filteredData = gatepassData.filter(item => {
        // Search filter
        if (searchTerm) {
            const matchesSearch =
                item.gatepass_code.toLowerCase().includes(searchTerm) ||
                item.customer_name.toLowerCase().includes(searchTerm);

            if (!matchesSearch) return false;
        }

        // Date filter
        if (dateFrom && item.date < dateFrom) return false;
        if (dateTo && item.date > dateTo) return false;

        // Company filter
        if (companyId && item.company_id != companyId) return false;

        return true;
    });
}

// Clear all filters
function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    document.getElementById('company-filter').value = '';
}

// Render the table with paginated data
function renderTable() {
    const tableBody = document.getElementById('table-body');
    const emptyState = document.getElementById('empty-state');

    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = filteredData.slice(startIndex, endIndex);
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);

    // Show empty state if no data
    if (filteredData.length === 0) {
        tableBody.innerHTML = '';
        emptyState.style.display = 'block';
    } else {
        emptyState.style.display = 'none';

        // Clear table body
        tableBody.innerHTML = '';

        // Add rows for each gatepass entry
        paginatedData.forEach(gatepass => {
            const row = document.createElement('tr');

            row.innerHTML = `
                        <td>
                            <strong>${gatepass.gatepass_code}</strong>
                        </td>
                        <td>${formatDate(gatepass.date)}</td>
                        <td>${gatepass.customer_name}</td>
                        <td>${gatepass.branch_name}</td>
                        <td>${gatepass.item_count} items</td>
                        <td><strong>${gatepass.total_qty}</strong></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" title="Print" onclick="printGatepass(${gatepass.id})">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="action-btn edit" title="Edit" onclick="editGatepass(${gatepass.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" title="Delete" onclick="deleteGatepass(${gatepass.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;

            tableBody.appendChild(row);
        });
    }

    // Update pagination controls
    updatePagination(totalPages);

    // Update pagination info text
    const totalItems = filteredData.length;
    const startItem = totalItems > 0 ? startIndex + 1 : 0;
    const endItem = Math.min(endIndex, totalItems);

    document.getElementById('pagination-info').textContent =
        `Showing ${startItem} to ${endItem} of ${totalItems} entries`;
}

// Update pagination controls
function updatePagination(totalPages) {
    const pageInput = document.getElementById('page-input');
    const totalPagesElement = document.getElementById('total-pages');
    const firstPageBtn = document.getElementById('first-page');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const lastPageBtn = document.getElementById('last-page');

    // Update page input and total pages
    pageInput.value = currentPage;
    pageInput.max = totalPages;
    totalPagesElement.textContent = `of ${totalPages}`;

    // Enable/disable navigation buttons
    firstPageBtn.disabled = currentPage === 1;
    prevPageBtn.disabled = currentPage === 1;
    nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
    lastPageBtn.disabled = currentPage === totalPages || totalPages === 0;
}

// Format date from YYYY-MM-DD to readable format
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Show notification
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: var(--radius-md);
                font-weight: 500;
                box-shadow: var(--shadow-lg);
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;

    // Style based on type
    if (type === 'success') {
        notification.style.backgroundColor = 'var(--success)';
        notification.style.color = 'white';
    } else if (type === 'error') {
        notification.style.backgroundColor = 'var(--error)';
        notification.style.color = 'white';
    } else {
        notification.style.backgroundColor = 'var(--info)';
        notification.style.color = 'white';
    }

    notification.textContent = message;
    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Add CSS animation for notifications
const style = document.createElement('style');
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

// Action functions
function printGatepass(id) {
    window.open(`print.php?id=${id}`, '_blank');
}

function editGatepass(id) {
    window.location.href = `gatepass-add.php?id=${id}`;
}

function deleteGatepass(id) {
    if (confirm('Are you sure you want to delete this gatepass entry? This action cannot be undone.')) {
        fetch('../../../../server/api/inventory/outward_gatepass/gatepass-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Gatepass deleted successfully', 'success');
                fetchGatepasses();
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to delete gatepass', 'error');
        });
    }
}