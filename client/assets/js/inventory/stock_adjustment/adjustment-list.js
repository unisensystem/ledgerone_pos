document.addEventListener('DOMContentLoaded', function () {
    // Fetch adjustments from API
    let adjustments = [];
    let companies = [];
    
    function fetchAdjustments() {
        fetch('../../../../server/api/inventory/stock_adjustment/adjustment-list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    adjustments = data.adjustments.map(adj => ({
                        id: adj.id,
                        code: adj.adjustment_code,
                        date: adj.date,
                        branch: adj.branch_name,
                        type: adj.adjustment_type.toLowerCase(),
                        reason: adj.main_reason,
                        items: adj.total_items,
                        totalValue: parseFloat(adj.total_amount),
                        status: 'posted',
                        mainReason: adj.main_reason,
                        primaryReason: adj.primary_reason,
                        secondaryReason: adj.secondary_reason,
                        remarks: adj.remarks,
                        companyId: adj.company_id,
                        companyName: adj.company_name || 'N/A'
                    }));
                    filteredAdjustments = [...adjustments];
                    initPage();
                }
            })
            .catch(error => {
                console.error('Error fetching adjustments:', error);
                showToast('Error loading adjustments', 'error');
            });
    }
    
    fetchAdjustments();
    
    // Fetch companies
    fetch('../../../../server/api/inventory/stock_adjustment/get-companies.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                companies = data.data;
                const companyFilter = document.getElementById('companyFilter');
                data.data.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companyFilter.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error fetching companies:', error));

    // Pagination variables
    let currentPage = 1;
    let itemsPerPage = 5;
    let totalPages = Math.ceil(adjustments.length / itemsPerPage);
    let filteredAdjustments = [...adjustments];
    let currentSort = { column: 'date', direction: 'desc' };

    // Initialize the page
    function initPage() {
        updateStats();
        renderTable();
        setupPagination();
        setupEventListeners();
    }

    // Update stats cards
    function updateStats() {
        const total = adjustments.length;
        const increase = adjustments.filter(a => a.type === 'increase').length;
        const decrease = adjustments.filter(a => a.type === 'decrease').length;
        const pending = 0;

        document.getElementById('totalAdjustments').textContent = total;
        document.getElementById('increaseAdjustments').textContent = increase;
        document.getElementById('decreaseAdjustments').textContent = decrease;
        document.getElementById('pendingAdjustments').textContent = pending;
    }

    // Render the table with current data
    function renderTable() {
        const tableBody = document.getElementById('tableBody');
        const emptyState = document.getElementById('emptyState');

        if (filteredAdjustments.length === 0) {
            tableBody.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';

        // Calculate pagination
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageItems = filteredAdjustments.slice(startIndex, endIndex);

        // Clear table
        tableBody.innerHTML = '';

        // Add rows
        pageItems.forEach(adj => {
            const row = document.createElement('tr');

            // Format date
            const dateObj = new Date(adj.date);
            const formattedDate = dateObj.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Status badge
            let statusBadge = '';
            if (adj.status === 'posted') {
                statusBadge = '<span class="status-badge status-posted"><i class="fas fa-check-circle"></i> Posted</span>';
            } else if (adj.status === 'pending') {
                statusBadge = '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
            } else {
                statusBadge = '<span class="status-badge status-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>';
            }

            // Type badge
            const typeBadge = adj.type === 'increase'
                ? '<span class="type-badge type-increase"><i class="fas fa-arrow-up"></i> Increase</span>'
                : '<span class="type-badge type-decrease"><i class="fas fa-arrow-down"></i> Decrease</span>';

            // Format total value
            const formattedTotal = window.currencySymbol + adj.totalValue.toFixed(2);

            row.innerHTML = `
                <td><strong>${adj.code}</strong></td>
                <td>${formattedDate}</td>
                <td>${adj.branch}</td>
                <td>${typeBadge}</td>
                <td>${adj.items} items</td>
                <td><strong>${formattedTotal}</strong></td>
                <td>${statusBadge}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn view-btn" data-id="${adj.id}" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit-btn" data-id="${adj.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn print-btn" data-id="${adj.id}" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="action-btn delete-btn" data-id="${adj.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;

            tableBody.appendChild(row);
        });

        // Update pagination info
        const start = filteredAdjustments.length > 0 ? startIndex + 1 : 0;
        const end = Math.min(startIndex + itemsPerPage, filteredAdjustments.length);
        document.getElementById('paginationInfo').textContent =
            `Showing ${start} to ${end} of ${filteredAdjustments.length} adjustments`;
    }

    // Setup pagination controls
    function setupPagination() {
        const pageNumbers = document.getElementById('pageNumbers');
        pageNumbers.innerHTML = '';

        totalPages = Math.ceil(filteredAdjustments.length / itemsPerPage);

        // Show only a few page numbers around current page
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        // Adjust if we're near the beginning
        if (currentPage <= 3) {
            endPage = Math.min(5, totalPages);
        }

        // Adjust if we're near the end
        if (currentPage >= totalPages - 2) {
            startPage = Math.max(1, totalPages - 4);
        }

        // Create page number buttons
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = 'page-btn';
            pageBtn.textContent = i;
            pageBtn.dataset.page = i;

            if (i === currentPage) {
                pageBtn.classList.add('active');
            }

            pageBtn.addEventListener('click', function () {
                currentPage = parseInt(this.dataset.page);
                renderTable();
                setupPagination();
            });

            pageNumbers.appendChild(pageBtn);
        }

        // Update navigation buttons
        document.getElementById('firstPageBtn').disabled = currentPage === 1;
        document.getElementById('prevPageBtn').disabled = currentPage === 1;
        document.getElementById('nextPageBtn').disabled = currentPage === totalPages;
        document.getElementById('lastPageBtn').disabled = currentPage === totalPages;
    }

    // Apply sorting
    function sortTable(column) {
        // Toggle direction if same column
        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.direction = 'asc';
        }

        // Update sort icons
        document.querySelectorAll('.sort-icon').forEach(icon => {
            icon.innerHTML = '↕';
        });

        const currentIcon = document.querySelector(`th[data-sort="${column}"] .sort-icon`);
        if (currentIcon) {
            currentIcon.innerHTML = currentSort.direction === 'asc' ? '↑' : '↓';
        }

        // Sort the data
        filteredAdjustments.sort((a, b) => {
            let aValue = a[column];
            let bValue = b[column];

            // Handle special cases
            if (column === 'date') {
                aValue = new Date(a.date);
                bValue = new Date(b.date);
            }

            if (aValue < bValue) {
                return currentSort.direction === 'asc' ? -1 : 1;
            }
            if (aValue > bValue) {
                return currentSort.direction === 'asc' ? 1 : -1;
            }
            return 0;
        });

        currentPage = 1;
        renderTable();
        setupPagination();
    }

    // Apply filters
    function applyFilters() {
        const statusFilter = document.getElementById('statusFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;
        const dateFrom = document.getElementById('dateFromFilter').value;
        const dateTo = document.getElementById('dateToFilter').value;
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const companyFilter = document.getElementById('companyFilter').value;

        filteredAdjustments = adjustments.filter(adj => {
            // Status filter
            if (statusFilter && adj.status !== statusFilter) {
                return false;
            }

            // Type filter
            if (typeFilter && adj.type !== typeFilter) {
                return false;
            }

            // Date range filter
            if (dateFrom && adj.date < dateFrom) {
                return false;
            }

            if (dateTo && adj.date > dateTo) {
                return false;
            }

            // Search filter
            if (searchTerm) {
                const searchIn = `${adj.code} ${adj.branch} ${adj.reason}`.toLowerCase();
                if (!searchIn.includes(searchTerm)) {
                    return false;
                }
            }
            
            // Company filter
            if (companyFilter && adj.companyId != companyFilter) {
                return false;
            }

            return true;
        });

        // Apply current sort
        sortTable(currentSort.column);
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('statusFilter').value = '';
        document.getElementById('typeFilter').value = '';
        document.getElementById('dateFromFilter').value = '';
        document.getElementById('dateToFilter').value = '';
        document.getElementById('searchInput').value = '';
        document.getElementById('companyFilter').value = '';

        filteredAdjustments = [...adjustments];
        currentPage = 1;
        sortTable(currentSort.column);
    }

    // Track currently viewed adjustment id
    let currentViewId = null;

    // Show adjustment details in modal
    function showAdjustmentDetails(id) {
        currentViewId = id;
        const adj = adjustments.find(a => a.id === id);
        if (!adj) return;

        // Format date
        const dateObj = new Date(adj.date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Format total value
        const formattedTotal = window.currencySymbol + adj.totalValue.toFixed(2);

        // Status display
        let statusDisplay = '<span class="status-badge status-posted"><i class="fas fa-check-circle"></i> Posted</span>';

        // Type display
        const typeDisplay = adj.type === 'increase'
            ? '<span class="type-badge type-increase"><i class="fas fa-arrow-up"></i> Increase</span>'
            : '<span class="type-badge type-decrease"><i class="fas fa-arrow-down"></i> Decrease</span>';

        // Populate details
        const detailsContainer = document.getElementById('modalDetails');
        detailsContainer.innerHTML = `
            <div class="detail-group">
                <div class="detail-label">Adjustment Code</div>
                <div class="detail-value">${adj.code}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Date</div>
                <div class="detail-value">${formattedDate}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Branch</div>
                <div class="detail-value">${adj.branch}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Company</div>
                <div class="detail-value">${adj.companyName}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Type</div>
                <div class="detail-value">${typeDisplay}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Main Reason</div>
                <div class="detail-value">${adj.mainReason}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Primary Reason</div>
                <div class="detail-value">${adj.primaryReason}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Secondary Reason</div>
                <div class="detail-value">${adj.secondaryReason}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Total Items</div>
                <div class="detail-value">${adj.items}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Total Value</div>
                <div class="detail-value">${formattedTotal}</div>
            </div>
            <div class="detail-group">
                <div class="detail-label">Status</div>
                <div class="detail-value">${statusDisplay}</div>
            </div>
        `;
        
        if (adj.remarks) {
            detailsContainer.innerHTML += `
                <div class="detail-group" style="grid-column: 1 / -1;">
                    <div class="detail-label">Remarks</div>
                    <div class="detail-value">${adj.remarks}</div>
                </div>
            `;
        }

        // Fetch and populate items
        fetch(`../../../../server/api/inventory/stock_adjustment/get-items.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const itemsBody = document.getElementById('modalItemsBody');
                    itemsBody.innerHTML = '';

                    data.items.forEach(item => {
                        const row = document.createElement('tr');
                        const itemTotal = window.currencySymbol + parseFloat(item.stock_value).toFixed(2);

                        row.innerHTML = `
                            <td>${item.product_code}</td>
                            <td>${item.product_name}</td>
                            <td>${item.uom_name || '-'}</td>
                            <td>${item.qty}</td>
                            <td>${window.currencySymbol}${parseFloat(item.rate).toFixed(2)}</td>
                            <td><strong>${itemTotal}</strong></td>
                        `;
                        itemsBody.appendChild(row);
                    });
                }
            });

        // Show modal
        document.getElementById('viewModal').classList.add('show');
    }

    // Print adjustment
    function printAdjustment(id) {
        const adj = adjustments.find(a => a.id === id);
        if (!adj) return;

        fetch(`../../../../server/api/inventory/stock_adjustment/get-items.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                const items = data.success ? data.items : [];
                const dateObj = new Date(adj.date);
                const formattedDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                const typeLabel = adj.type === 'increase' ? '▲ Increase' : '▼ Decrease';

                let itemRows = items.map((item, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${item.product_code}</td>
                        <td>${item.product_name}</td>
                        <td>${item.uom_name || '-'}</td>
                        <td>${item.qty}</td>
                        <td>${window.currencySymbol}${parseFloat(item.rate).toFixed(2)}</td>
                        <td>${window.currencySymbol}${parseFloat(item.stock_value).toFixed(2)}</td>
                    </tr>
                `).join('');

                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Stock Adjustment - ${adj.code}</title>
                        <style>
                            * { margin: 0; padding: 0; box-sizing: border-box; }
                            body { font-family: Arial, sans-serif; padding: 30px; color: #333; font-size: 13px; }
                            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
                            .header h1 { font-size: 20px; margin-bottom: 4px; }
                            .header p { color: #666; font-size: 12px; }
                            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 30px; margin-bottom: 20px; }
                            .info-row { display: flex; gap: 8px; }
                            .info-label { font-weight: bold; color: #555; min-width: 130px; }
                            .info-value { color: #333; }
                            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                            th { background: #f0f0f0; border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 12px; }
                            td { border: 1px solid #ccc; padding: 7px 8px; font-size: 12px; }
                            tr:nth-child(even) td { background: #fafafa; }
                            .total-row td { font-weight: bold; background: #f0f0f0; }
                            .footer { margin-top: 30px; display: flex; justify-content: space-between; }
                            .sign-box { text-align: center; }
                            .sign-line { border-top: 1px solid #333; width: 150px; margin: 40px auto 5px; }
                            @media print { body { padding: 15px; } }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Stock Adjustment</h1>
                            <p>Adjustment Code: <strong>${adj.code}</strong> &nbsp;|&nbsp; Date: ${formattedDate}</p>
                        </div>
                        <div class="info-grid">
                            <div class="info-row"><span class="info-label">Branch:</span><span class="info-value">${adj.branch}</span></div>
                            <div class="info-row"><span class="info-label">Company:</span><span class="info-value">${adj.companyName}</span></div>
                            <div class="info-row"><span class="info-label">Type:</span><span class="info-value">${typeLabel}</span></div>
                            <div class="info-row"><span class="info-label">Main Reason:</span><span class="info-value">${adj.mainReason}</span></div>
                            <div class="info-row"><span class="info-label">Primary Reason:</span><span class="info-value">${adj.primaryReason}</span></div>
                            <div class="info-row"><span class="info-label">Secondary Reason:</span><span class="info-value">${adj.secondaryReason}</span></div>
                            ${adj.remarks ? `<div class="info-row" style="grid-column:1/-1"><span class="info-label">Remarks:</span><span class="info-value">${adj.remarks}</span></div>` : ''}
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Rate</th>
                                    <th>Gross</th>
                                </tr>
                            </thead>
                            <tbody>${itemRows}</tbody>
                            <tfoot>
                                <tr class="total-row">
                                    <td colspan="6" style="text-align:right">Total:</td>
                                    <td>${window.currencySymbol}${adj.totalValue.toFixed(2)}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="footer">
                            <div class="sign-box"><div class="sign-line"></div><p>Prepared By</p></div>
                            <div class="sign-box"><div class="sign-line"></div><p>Checked By</p></div>
                            <div class="sign-box"><div class="sign-line"></div><p>Approved By</p></div>
                        </div>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => printWindow.print(), 500);
            });
    }

    // Show delete confirmation
    function showDeleteConfirmation(id) {
        const adj = adjustments.find(a => a.id === id);
        if (!adj) return;

        document.getElementById('deleteAdjustmentCode').textContent = adj.code;
        document.getElementById('deleteModal').classList.add('show');

        // Store the ID to delete
        document.getElementById('confirmDeleteBtn').dataset.id = id;
    }

    // Delete adjustment
    function deleteAdjustment(id) {
        fetch('../../../../server/api/inventory/stock_adjustment/adjustment-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fetchAdjustments();
                showToast('Adjustment deleted successfully', 'success');
            } else {
                showToast(data.message || 'Error deleting adjustment', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting adjustment', 'error');
        });
    }

    // Toast notification
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastIcon = toast.querySelector('.toast-icon i');
        const toastMessage = toast.querySelector('.toast-message');

        // Set toast content and style
        toastMessage.textContent = message;
        toast.className = 'toast';

        if (type === 'success') {
            toast.classList.add('toast-success');
            toastIcon.className = 'fas fa-check-circle';
        } else if (type === 'error') {
            toast.classList.add('toast-error');
            toastIcon.className = 'fas fa-exclamation-circle';
        }

        // Show toast
        toast.classList.add('show');

        // Hide toast after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Setup event listeners
    function setupEventListeners() {
        // Table sorting
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', function () {
                const column = this.dataset.sort;
                sortTable(column);
            });
        });

        // Filter buttons
        document.getElementById('applyFiltersBtn').addEventListener('click', applyFilters);
        document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);

        // Search input
        document.getElementById('searchInput').addEventListener('input', function () {
            // Debounce search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(applyFilters, 300);
        });

        // Table actions - commented out as these buttons don't exist in HTML
        // document.getElementById('refreshBtn')?.addEventListener('click', function () {
        //     fetchAdjustments();
        //     showToast('Table refreshed', 'success');
        // });

        // document.getElementById('exportBtn')?.addEventListener('click', function () {
        //     showToast('Export functionality would be implemented here', 'success');
        // });

        // document.getElementById('printBtn')?.addEventListener('click', function () {
        //     window.print();
        // });

        // New adjustment button
        document.getElementById('newAdjustmentBtn').addEventListener('click', function () {
            window.location.href=('adjustment-add.php');
        });

        // Pagination controls
        document.getElementById('firstPageBtn').addEventListener('click', function () {
            if (currentPage !== 1) {
                currentPage = 1;
                renderTable();
                setupPagination();
            }
        });

        document.getElementById('prevPageBtn').addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
                setupPagination();
            }
        });

        document.getElementById('nextPageBtn').addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
                setupPagination();
            }
        });

        document.getElementById('lastPageBtn').addEventListener('click', function () {
            if (currentPage !== totalPages) {
                currentPage = totalPages;
                renderTable();
                setupPagination();
            }
        });

        // Modal close buttons
        document.getElementById('closeViewModal').addEventListener('click', function () {
            document.getElementById('viewModal').classList.remove('show');
        });

        document.getElementById('closeModalBtn').addEventListener('click', function () {
            document.getElementById('viewModal').classList.remove('show');
        });

        document.getElementById('closeDeleteModal').addEventListener('click', function () {
            document.getElementById('deleteModal').classList.remove('show');
        });

        document.getElementById('cancelDeleteBtn').addEventListener('click', function () {
            document.getElementById('deleteModal').classList.remove('show');
        });

        // Print details button
        document.getElementById('printDetailsBtn').addEventListener('click', function () {
            const adj = adjustments.find(a => a.id === parseInt(document.getElementById('confirmDeleteBtn').dataset.id || 0));
            printAdjustment(currentViewId);
        });

        // Delete confirmation
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const id = parseInt(this.dataset.id);
            deleteAdjustment(id);
            document.getElementById('deleteModal').classList.remove('show');
        });

        // Close modals when clicking outside
        window.addEventListener('click', function (event) {
            const viewModal = document.getElementById('viewModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target === viewModal) {
                viewModal.classList.remove('show');
            }

            if (event.target === deleteModal) {
                deleteModal.classList.remove('show');
            }
        });

        // Delegate events for dynamic content (view/edit/delete buttons)
        document.addEventListener('click', function (event) {
            // View button
            if (event.target.closest('.view-btn')) {
                const btn = event.target.closest('.view-btn');
                const id = parseInt(btn.dataset.id);
                showAdjustmentDetails(id);
            }

            // Edit button
            if (event.target.closest('.edit-btn')) {
                const btn = event.target.closest('.edit-btn');
                const id = parseInt(btn.dataset.id);
                window.location.href = `adjustment-add.php?id=${id}`;
            }

            // Print button (from list)
            if (event.target.closest('.print-btn')) {
                const btn = event.target.closest('.print-btn');
                const id = parseInt(btn.dataset.id);
                printAdjustment(id);
            }

            // Delete button
            if (event.target.closest('.delete-btn')) {
                const btn = event.target.closest('.delete-btn');
                const id = parseInt(btn.dataset.id);
                showDeleteConfirmation(id);
            }
        });
    }

    // Initialize the page
    initPage();

    // Set default date filters to last 30 days
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    document.getElementById('dateToFilter').value = today.toISOString().split('T')[0];
    document.getElementById('dateFromFilter').value = thirtyDaysAgo.toISOString().split('T')[0];

    // Apply initial filters
    applyFilters();
});