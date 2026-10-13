// Sample data for rate lists
let rateListsData = [];

// State management
let currentPage = 1;
const itemsPerPage = 5;
let filteredData = [...rateListsData];
let rateListToDelete = null;

// Initialize the page
document.addEventListener('DOMContentLoaded', function () {
    // Fetch rate lists from API
    fetchRateLists();

    // Set up event listeners
    document.getElementById('searchInput').addEventListener('input', filterRateLists);
    document.getElementById('statusFilter').addEventListener('change', filterRateLists);
    document.getElementById('dateFilter').addEventListener('change', filterRateLists);
    document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
    document.getElementById('addRateListBtn').addEventListener('click', addNewRateList);
    document.getElementById('filterBtn').addEventListener('click', toggleAdvancedFilter);

    // Pagination
    document.getElementById('prevPageBtn').addEventListener('click', goToPrevPage);
    document.getElementById('nextPageBtn').addEventListener('click', goToNextPage);

    // Modal close buttons
    document.getElementById('closeViewModal').addEventListener('click', closeViewModal);
    document.getElementById('closeDeleteModal').addEventListener('click', closeDeleteModal);
    document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteModal);
    document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);

    // Close modals when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
});

// Fetch rate lists from API
function fetchRateLists() {
    fetch('../../../../server/api/master_setup/rate_list/list-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                rateListsData = data.rateLists.map(rl => ({
                    id: rl.id,
                    code: rl.list_code,
                    name: rl.list_name,
                    customers: rl.customers.map(c => ({ id: c.id, name: c.customer_name })),
                    customerCount: parseInt(rl.customer_count),
                    items: parseInt(rl.item_count),
                    createdDate: rl.created_at.split(' ')[0]
                }));
                filteredData = [...rateListsData];
                renderTable();
            } else {
                showMessage('Failed to load rate lists', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading rate lists', 'error');
        });
}

// Render the table with current data
function renderTable() {
    const tableBody = document.getElementById('tableBody');
    const emptyState = document.getElementById('emptyState');
    const paginationInfo = document.getElementById('paginationInfo');

    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredData.slice(startIndex, endIndex);

    if (pageData.length === 0) {
        tableBody.innerHTML = '';
        emptyState.style.display = 'block';
        document.querySelector('.table-container').style.border = 'none';
    } else {
        emptyState.style.display = 'none';
        document.querySelector('.table-container').style.border = '1px solid var(--border-default)';

        tableBody.innerHTML = pageData.map(rateList => `
                    <tr>
                        <td>
                            <strong style="color: var(--primary);">${rateList.code}</strong>
                        </td>
                        <td>${rateList.name}</td>
                        <td>
                            <div class="customer-tags">
                                ${renderCustomerTags(rateList.customers, rateList.customerCount)}
                            </div>
                        </td>
                        <td>${rateList.items} items</td>
                        <td>${formatDate(rateList.createdDate)}</td>
                        <td>-</td>
                        <td>
                            <span class="status-badge status-active">
                                Active
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" data-id="${rateList.id}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" data-id="${rateList.id}" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');

        // Add event listeners to action buttons
        document.querySelectorAll('.action-btn.edit').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = parseInt(this.dataset.id);
                editRateList(id);
            });
        });

        document.querySelectorAll('.action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = parseInt(this.dataset.id);
                showDeleteConfirmation(id);
            });
        });
    }

    // Update pagination info
    const totalItems = filteredData.length;
    const startItem = totalItems > 0 ? startIndex + 1 : 0;
    const endItem = Math.min(endIndex, totalItems);

    paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${totalItems} rate lists`;

    // Update pagination controls
    updatePaginationControls();
}

// Render customer tags with overflow handling
function renderCustomerTags(customers, totalCount) {
    if (!customers || customers.length === 0) return '<span class="customer-tag">No customers</span>';

    const maxVisible = 2;
    const visibleCustomers = customers.slice(0, maxVisible);
    const remainingCount = totalCount - maxVisible;

    let html = visibleCustomers.map(customer =>
        `<span class="customer-tag" title="${customer.name}">${customer.name}</span>`
    ).join('');

    if (remainingCount > 0) {
        html += `<span class="customer-tag more" title="${remainingCount} more customers">+${remainingCount}</span>`;
    }

    return html;
}

// Format date for display
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Get status display text
function getStatusText(status) {
    const statusMap = {
        'active': 'Active',
        'inactive': 'Inactive',
        'pending': 'Pending',
        'expired': 'Expired'
    };
    return statusMap[status] || status;
}

// Filter rate lists based on search and filters
function filterRateLists() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;

    filteredData = rateListsData.filter(rateList => {
        // Search filter
        const matchesSearch = !searchTerm ||
            rateList.code.toLowerCase().includes(searchTerm) ||
            rateList.name.toLowerCase().includes(searchTerm) ||
            rateList.customers.some(c => c.name.toLowerCase().includes(searchTerm));

        // Status filter
        const matchesStatus = !statusFilter || rateList.status === statusFilter;

        // Date filter
        let matchesDate = true;
        if (dateFilter) {
            const createdDate = new Date(rateList.createdDate);
            const today = new Date();

            switch (dateFilter) {
                case 'today':
                    matchesDate = createdDate.toDateString() === today.toDateString();
                    break;
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(today.getDate() - 7);
                    matchesDate = createdDate >= weekAgo;
                    break;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(today.getMonth() - 1);
                    matchesDate = createdDate >= monthAgo;
                    break;
                case 'quarter':
                    const quarterAgo = new Date(today);
                    quarterAgo.setMonth(today.getMonth() - 3);
                    matchesDate = createdDate >= quarterAgo;
                    break;
            }
        }

        return matchesSearch && matchesStatus && matchesDate;
    });

    // Reset to first page
    currentPage = 1;
    renderTable();
}

// Clear all filters
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFilter').value = '';

    filteredData = [...rateListsData];
    currentPage = 1;
    renderTable();
}

// Toggle advanced filter (placeholder for more advanced filtering)
function toggleAdvancedFilter() {
    alert('Advanced filter functionality would open a panel with more filtering options.');
}

// Add new rate list
function addNewRateList() {
    window.location.href = 'list-add.php';
}

// View rate list details
function viewRateList(id) {
    const rateList = rateListsData.find(rl => rl.id === id);
    if (!rateList) return;

    const modalContent = document.getElementById('viewModalContent');
    modalContent.innerHTML = `
                <div style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 16px;">
                        <div>
                            <h3 style="color: var(--heading); margin-bottom: 4px;">${rateList.name}</h3>
                            <p style="color: var(--subtext); font-size: 14px;">${rateList.code}</p>
                        </div>
                        <span class="status-badge status-${rateList.status}" style="height: fit-content;">
                            ${getStatusText(rateList.status)}
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px;">
                        <div>
                            <p style="font-size: 12px; color: var(--subtext); margin-bottom: 4px;">Created Date</p>
                            <p style="font-weight: 500;">${formatDate(rateList.createdDate)}</p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: var(--subtext); margin-bottom: 4px;">Valid Until</p>
                            <p style="font-weight: 500;">${formatDate(rateList.validUntil)}</p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: var(--subtext); margin-bottom: 4px;">Items Count</p>
                            <p style="font-weight: 500;">${rateList.items} items</p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: var(--subtext); margin-bottom: 4px;">Customers</p>
                            <p style="font-weight: 500;">${rateList.customers.length} customers</p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--heading); margin-bottom: 12px;">Customers</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        ${rateList.customers.map(customer => `
                            <span style="background-color: var(--surface-2); padding: 6px 12px; border-radius: 6px; font-size: 13px;">
                                ${customer.name}
                            </span>
                        `).join('')}
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--heading); margin-bottom: 12px;">Products</h4>
                    <ul style="padding-left: 20px;">
                        ${rateList.details.products.map(product => `
                            <li style="margin-bottom: 4px;">${product}</li>
                        `).join('')}
                    </ul>
                </div>
                
                <div>
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--heading); margin-bottom: 12px;">Notes</h4>
                    <p style="background-color: var(--surface-1); padding: 12px; border-radius: 8px; border-left: 3px solid var(--primary);">
                        ${rateList.details.notes}
                    </p>
                </div>
            `;

    document.getElementById('viewModal').classList.add('active');
}

// Edit rate list
function editRateList(id) {
    window.location.href = `list-add.php?id=${id}`;
}

// Show delete confirmation modal
function showDeleteConfirmation(id) {
    rateListToDelete = id;
    document.getElementById('deleteModal').classList.add('active');
}

function showMessage(message, type) {
    alert(message);
}

// Close view modal
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
}

// Close delete modal
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    rateListToDelete = null;
}

// Confirm delete
function confirmDelete() {
    if (!rateListToDelete) return;

    fetch('../../../../server/api/master_setup/rate_list/list-delete.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: rateListToDelete })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove from data array
            const index = rateListsData.findIndex(rl => rl.id === rateListToDelete);
            if (index !== -1) {
                rateListsData.splice(index, 1);
            }
            
            // Update UI
            filterRateLists();
            closeDeleteModal();
            showMessage('Rate list deleted successfully!', 'success');
        } else {
            showMessage(data.message || 'Failed to delete rate list', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deleting rate list', 'error');
    });
}

// Pagination functions
function goToPrevPage() {
    if (currentPage > 1) {
        currentPage--;
        renderTable();
    }
}

function goToNextPage() {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        renderTable();
    }
}

function updatePaginationControls() {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const paginationControls = document.querySelector('.pagination-controls');

    // Update prev/next buttons
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;

    // Update page number buttons
    const pageButtons = paginationControls.querySelectorAll('.page-btn:not(#prevPageBtn):not(#nextPageBtn)');

    // Clear existing page number buttons (except prev/next)
    pageButtons.forEach(btn => btn.remove());

    // Add new page number buttons
    const pageNumbers = getVisiblePageNumbers(currentPage, totalPages);

    // Insert before next button
    const nextBtnElement = document.getElementById('nextPageBtn');

    pageNumbers.forEach(page => {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'page-btn';
        if (page === currentPage) pageBtn.classList.add('active');
        pageBtn.textContent = page;
        pageBtn.addEventListener('click', () => {
            currentPage = page;
            renderTable();
        });

        paginationControls.insertBefore(pageBtn, nextBtnElement);
    });
}

// Get visible page numbers for pagination
function getVisiblePageNumbers(currentPage, totalPages) {
    const maxVisiblePages = 3;
    const halfVisible = Math.floor(maxVisiblePages / 2);

    let startPage = Math.max(1, currentPage - halfVisible);
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    // Adjust if we're at the end
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    const pages = [];
    for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
    }

    return pages;
}