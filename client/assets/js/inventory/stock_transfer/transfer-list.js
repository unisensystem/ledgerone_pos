document.addEventListener('DOMContentLoaded', function () {
    // Initialize variables
    let currentPage = 1;
    const itemsPerPage = 5;
    let transfersData = [];
    let filteredTransfers = [];
    let branches = [];
    let companies = [];

    // Fetch data
    fetchCompanies();
    fetchBranches();
    fetchTransfers();

    // DOM Elements
    const tableBody = document.getElementById('transfers-table-body');
    const emptyState = document.getElementById('empty-state');
    const paginationInfo = document.getElementById('pagination-info');
    const paginationControls = document.querySelector('.pagination-controls');
    const newTransferBtn = document.getElementById('new-transfer-btn');
    const emptyNewTransferBtn = document.getElementById('empty-new-transfer');
    const searchInput = document.getElementById('search');
    const dateFromFilter = document.getElementById('date-from');
    const dateToFilter = document.getElementById('date-to');
    const companyFilter = document.getElementById('company-filter');
    const clearFiltersBtn = document.getElementById('clear-filters');

    // Set default date values (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    dateFromFilter.value = thirtyDaysAgo.toISOString().split('T')[0];
    dateToFilter.value = today.toISOString().split('T')[0];

    // Event Listeners
    newTransferBtn.addEventListener('click', function () {
        window.location.href = 'transfer-add.php';
    });

    emptyNewTransferBtn.addEventListener('click', function () {
        window.location.href = 'transfer-add.php';
    });

    searchInput.addEventListener('input', filterTransfers);
    dateFromFilter.addEventListener('change', filterTransfers);
    dateToFilter.addEventListener('change', filterTransfers);
    companyFilter.addEventListener('change', filterTransfers);
    clearFiltersBtn.addEventListener('click', clearFilters);

    // Functions
    async function fetchCompanies() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-companies.php');
            const data = await response.json();
            
            if (data.success) {
                companies = data.companies;
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companyFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching companies:', error);
        }
    }
    async function fetchBranches() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/get-branches.php');
            const data = await response.json();

            if (data.success) {
                branches = data.branches;
            }
        } catch (error) {
            console.error('Error fetching branches:', error);
        }
    }

    async function fetchTransfers() {
        try {
            const response = await fetch('../../../../server/api/inventory/stock_transfer/transfer-list.php');
            const data = await response.json();

            if (data.success) {
                transfersData = data.transfers;
                filteredTransfers = [...transfersData];
                renderTable();
                setupPagination();
            } else {
                console.error('Failed to fetch transfers:', data.message);
            }
        } catch (error) {
            console.error('Error fetching transfers:', error);
        }
    }

    // Filter Functions
    function filterTransfers() {
        const searchTerm = searchInput.value.toLowerCase();
        const dateFrom = dateFromFilter.value;
        const dateTo = dateToFilter.value;
        const companyId = companyFilter.value;

        filteredTransfers = transfersData.filter(transfer => {
            // Search filter
            const matchesSearch = !searchTerm ||
                transfer.transfer_code.toLowerCase().includes(searchTerm) ||
                transfer.from_branch.toLowerCase().includes(searchTerm) ||
                transfer.to_branch.toLowerCase().includes(searchTerm) ||
                (transfer.company_name && transfer.company_name.toLowerCase().includes(searchTerm)) ||
                (transfer.remarks && transfer.remarks.toLowerCase().includes(searchTerm));

            // Date range filter
            const transferDate = new Date(transfer.date);
            const fromDate = dateFrom ? new Date(dateFrom) : null;
            const toDate = dateTo ? new Date(dateTo) : null;

            let matchesDate = true;
            if (fromDate) matchesDate = matchesDate && transferDate >= fromDate;
            if (toDate) matchesDate = matchesDate && transferDate <= toDate;

            // Company filter
            const matchesCompany = !companyId || transfer.company_id == companyId;

            return matchesSearch && matchesDate && matchesCompany;
        });

        currentPage = 1;
        renderTable();
        setupPagination();
    }

    function clearFilters() {
        searchInput.value = '';
        companyFilter.value = '';

        // Reset to default date range (last 30 days)
        dateFromFilter.value = thirtyDaysAgo.toISOString().split('T')[0];
        dateToFilter.value = today.toISOString().split('T')[0];

        filterTransfers();
    }

    // Render Functions
    function renderTable() {
        // Calculate pagination
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageTransfers = filteredTransfers.slice(startIndex, endIndex);

        // Clear table body
        tableBody.innerHTML = '';

        if (pageTransfers.length === 0) {
            // Show empty state
            tableBody.style.display = 'none';
            emptyState.style.display = 'flex';
        } else {
            // Hide empty state
            tableBody.style.display = 'table-row-group';
            emptyState.style.display = 'none';

            // Populate table rows
            pageTransfers.forEach(transfer => {
                const row = document.createElement('tr');

                // Format date
                const dateObj = new Date(transfer.date);
                const formattedDate = dateObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

        // Format currency
        const formattedValue = window.currencySymbol + parseFloat(transfer.total_amount || 0).toFixed(2);

                row.innerHTML = `
                    <td>
                        <strong>${transfer.transfer_code}</strong>
                    </td>
                    <td>${formattedDate}</td>
                    <td>${transfer.company_name || '-'}</td>
                    <td>${transfer.from_branch}</td>
                    <td>${transfer.to_branch}</td>
                    <td>${transfer.total_items} items</td>
                    <td>${formattedValue}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" data-id="${transfer.id}" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn edit" data-id="${transfer.id}" title="Edit Transfer">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete" data-id="${transfer.id}" title="Delete Transfer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;

                tableBody.appendChild(row);
            });

            // Add event listeners to action buttons
            document.querySelectorAll('.action-btn.view').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = parseInt(this.getAttribute('data-id'));
                    viewTransferDetails(id);
                });
            });

            document.querySelectorAll('.action-btn.edit').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = parseInt(this.getAttribute('data-id'));
                    editTransfer(id);
                });
            });

            document.querySelectorAll('.action-btn.delete').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = parseInt(this.getAttribute('data-id'));
                    showDeleteModal(id);
                });
            });
        }

        // Update pagination info
        const totalItems = filteredTransfers.length;
        const startItem = totalItems > 0 ? startIndex + 1 : 0;
        const endItem = Math.min(endIndex, totalItems);

        paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${totalItems} transfers`;
    }

    // Pagination Functions
    function setupPagination() {
        // Clear existing page buttons
        const pageBtns = paginationControls.querySelectorAll('.page-btn');
        pageBtns.forEach(btn => btn.remove());

        const totalPages = Math.ceil(filteredTransfers.length / itemsPerPage);

        // Create page buttons
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

        // Adjust if we're at the start or end
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        // Add page number buttons
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = 'pagination-btn page-btn';
            pageBtn.textContent = i;
            if (i === currentPage) {
                pageBtn.classList.add('active');
            }

            pageBtn.addEventListener('click', function () {
                currentPage = i;
                renderTable();
                setupPagination();
            });

            // Insert before the next-page button
            const nextPageBtn = document.getElementById('next-page');
            paginationControls.insertBefore(pageBtn, nextPageBtn);
        }

        // Update navigation buttons
        document.getElementById('first-page').disabled = currentPage === 1;
        document.getElementById('prev-page').disabled = currentPage === 1;
        document.getElementById('next-page').disabled = currentPage === totalPages || totalPages === 0;
        document.getElementById('last-page').disabled = currentPage === totalPages || totalPages === 0;

        // Add event listeners to navigation buttons
        document.getElementById('first-page').addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage = 1;
                renderTable();
                setupPagination();
            }
        });

        document.getElementById('prev-page').addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
                setupPagination();
            }
        });

        document.getElementById('next-page').addEventListener('click', function () {
            const totalPages = Math.ceil(filteredTransfers.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
                setupPagination();
            }
        });

        document.getElementById('last-page').addEventListener('click', function () {
            const totalPages = Math.ceil(filteredTransfers.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage = totalPages;
                renderTable();
                setupPagination();
            }
        });
    }

    // Modal Functions
    function viewTransferDetails(id) {
        const transfer = transfersData.find(t => t.id === id);
        if (!transfer) return;

        const detailsContainer = document.getElementById('transfer-details');

        // Format date
        const dateObj = new Date(transfer.date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Format currency
        const formattedValue = window.currencySymbol + parseFloat(transfer.total_amount).toFixed(2);

        // Status text
        let statusText = '';
        switch (transfer.status) {
            case 'completed':
                statusText = 'Completed';
                break;
            case 'pending':
                statusText = 'Pending';
                break;
            case 'processing':
                statusText = 'Processing';
                break;
            case 'cancelled':
                statusText = 'Cancelled';
                break;
        }

        detailsContainer.innerHTML = `
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">Transfer Code</div>
                <div style="font-size: 18px; font-weight: 600; color: #0E1A2B;">${transfer.transfer_code}</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">Date</div>
                <div style="font-weight: 500;">${formattedDate}</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">Company</div>
                <div style="font-weight: 500;">${transfer.company_name || '-'}</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">From Branch</div>
                <div style="font-weight: 500; font-size: 15px;">${transfer.from_branch}</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">To Branch</div>
                <div style="font-weight: 500; font-size: 15px;">${transfer.to_branch}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">Items Count</div>
                    <div style="font-weight: 500;">${transfer.total_items} items</div>
                </div>
                <div>
                    <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">Total Value</div>
                    <div style="font-weight: 500;">${formattedValue}</div>
                </div>
            </div>
            
            <div>
                <div style="font-size: 13px; color: #6B7280; margin-bottom: 4px;">Remarks</div>
                <div style="background-color: #F7F9FC; padding: 12px; border-radius: 8px; min-height: 60px;">
                    ${transfer.remarks || '<span style="color: #9AA1AE; font-style: italic;">No remarks provided</span>'}
                </div>
            </div>
        `;

        // Set transfer code for delete modal
        document.getElementById('delete-transfer-code').textContent = transfer.transfer_code;

        // Show modal
        document.getElementById('view-modal').style.display = 'flex';
    }

    function editTransfer(id) {
        window.location.href = `transfer-add.php?edit=${id}`;
    }

    function showDeleteModal(id) {
        const transfer = transfersData.find(t => t.id === id);
        if (!transfer) return;

        // Set transfer code
        document.getElementById('delete-transfer-code').textContent = transfer.transfer_code;

        // Set up delete confirmation
        document.getElementById('confirm-delete').onclick = function () {
            deleteTransfer(id);
        };

        // Show modal
        document.getElementById('delete-modal').style.display = 'flex';
    }

    function deleteTransfer(id) {
        // Call API to delete transfer
        fetch('../../../../server/api/inventory/stock_transfer/transfer-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ transfer_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Transfer deleted successfully!');
                fetchTransfers(); // Reload the list
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete transfer');
        });

        // Close modal
        document.getElementById('delete-modal').style.display = 'none';
    }

    // Modal close handlers
    document.getElementById('close-delete-modal').addEventListener('click', function () {
        document.getElementById('delete-modal').style.display = 'none';
    });

    document.getElementById('cancel-delete').addEventListener('click', function () {
        document.getElementById('delete-modal').style.display = 'none';
    });

    document.getElementById('close-view-modal').addEventListener('click', function () {
        document.getElementById('view-modal').style.display = 'none';
    });

    document.getElementById('close-view').addEventListener('click', function () {
        document.getElementById('view-modal').style.display = 'none';
    });

    document.getElementById('print-transfer').addEventListener('click', function () {
        window.print();
    });

    // Close modals when clicking outside
    window.addEventListener('click', function (event) {
        const deleteModal = document.getElementById('delete-modal');
        const viewModal = document.getElementById('view-modal');

        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
        }

        if (event.target === viewModal) {
            viewModal.style.display = 'none';
        }
    });
});