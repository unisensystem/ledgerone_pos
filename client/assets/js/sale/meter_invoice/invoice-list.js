document.addEventListener('DOMContentLoaded', function() {
    let readingsData = [];
    let currentPage = 1;
    let totalPages = 1;
    
    // Fetch and initialize the table
    fetchReadings(currentPage);
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const filteredData = readingsData.filter(item => 
            item.id.toString().includes(searchTerm) ||
            item.branch_name.toLowerCase().includes(searchTerm) ||
            item.station_name.toLowerCase().includes(searchTerm) ||
            item.product_name.toLowerCase().includes(searchTerm)
        );
        renderTable(filteredData);
    });
    
    // Add event listeners to filter selects
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', applyFilters);
    });
    
    // Fetch filter options
    fetchFilterOptions();
    
    // Add new reading button
    document.getElementById('addReadingBtn').addEventListener('click', function() {
        window.location.href = 'invoice-add.php';
    });
    
    // Pagination buttons
    document.getElementById('prevPage').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            fetchReadings(currentPage);
        }
    });
    
    document.getElementById('nextPage').addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            fetchReadings(currentPage);
        }
    });
    
    // Function to render the table
    function renderTable(data) {
        const tableBody = document.getElementById('readingsTableBody');
        
        if (data.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="12">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No readings found</h3>
                            <p>Try adjusting your search or filter to find what you're looking for.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tableBody.innerHTML = data.map(item => `
            <tr>
                <td>${formatDate(item.usage_date)}</td>
                <td>${item.branch_name}</td>
                <td>${item.station_name}</td>
                <td>${item.product_name}</td>
                <td>${item.unit_name}</td>
                <td>${item.rate ? parseFloat(item.rate).toFixed(3) : '-'}</td>
                <td>${parseFloat(item.opening_reading).toFixed(2)}</td>
                <td>${item.closing_reading ? parseFloat(item.closing_reading).toFixed(2) : '-'}</td>
                <td>${item.total_dispensed ? parseFloat(item.total_dispensed).toFixed(2) : '-'}</td>
                <td>${item.total_revenue ? parseFloat(item.total_revenue).toFixed(2) : '-'}</td>
                <td>
                    <span class="status-badge ${item.closing_reading === null ? 'status-pending' : 'status-completed'}">
                        ${item.closing_reading === null ? 'Not Completed' : 'Completed'}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        ${item.closing_reading === null ? 
                            `<button class="action-btn complete" data-id="${item.id}">
                                <i class="fas fa-check"></i>
                            </button>` : 
                            `<button class="action-btn edit" data-id="${item.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn print" data-id="${item.id}">
                                <i class="fas fa-print"></i>
                            </button>`
                        }
                        <button class="action-btn delete" data-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Add event listeners to action buttons
        document.querySelectorAll('.action-btn.complete').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                openCompleteModal(id);
            });
        });
        
        document.querySelectorAll('.action-btn.edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                openEditModal(id);
            });
        });
        
        document.querySelectorAll('.action-btn.print').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                window.open(`print.php?id=${id}`, '_blank');
            });
        });
        
        document.querySelectorAll('.action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                openDeleteModal(id);
            });
        });
    }
    
    // Function to apply filters
    function applyFilters() {
        const branchFilter = document.getElementById('branchFilter').value;
        const stationFilter = document.getElementById('stationFilter').value;
        const productFilter = document.getElementById('productFilter').value;
        const dateFilter = document.getElementById('dateFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        let filteredData = readingsData;
        
        if (branchFilter) {
            filteredData = filteredData.filter(item => item.branch_id == branchFilter);
        }
        
        if (stationFilter) {
            filteredData = filteredData.filter(item => item.station_id == stationFilter);
        }
        
        if (productFilter) {
            filteredData = filteredData.filter(item => item.product_id == productFilter);
        }
        
        if (statusFilter) {
            if (statusFilter === 'completed') {
                filteredData = filteredData.filter(item => item.closing_reading !== null);
            } else if (statusFilter === 'pending') {
                filteredData = filteredData.filter(item => item.closing_reading === null);
            }
        }
        
        if (dateFilter) {
            filteredData = filteredData.filter(item => {
                const itemDate = new Date(item.usage_date);
                const today = new Date();
                
                switch(dateFilter) {
                    case 'today':
                        return itemDate.toDateString() === today.toDateString();
                    case 'week':
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(today.getDate() - today.getDay());
                        return itemDate >= startOfWeek;
                    case 'month':
                        return itemDate.getMonth() === today.getMonth() && 
                               itemDate.getFullYear() === today.getFullYear();
                    case 'quarter':
                        const currentQuarter = Math.floor(today.getMonth() / 3) + 1;
                        const itemQuarter = Math.floor(itemDate.getMonth() / 3) + 1;
                        return itemQuarter === currentQuarter && 
                               itemDate.getFullYear() === today.getFullYear();
                    default:
                        return true;
                }
            });
        }
        
        renderTable(filteredData);
    }
    
    // Fetch filter options from API
    async function fetchFilterOptions() {
        try {
            // Fetch branches
            const branchRes = await fetch('../../../../server/api/sale/meter_invoice/get-branches.php');
            const branchData = await branchRes.json();
            if (branchData.success) {
                const branchFilter = document.getElementById('branchFilter');
                branchFilter.innerHTML = '<option value="">All Branches</option>' +
                    branchData.data.map(b => `<option value="${b.id}">${b.branch_name}</option>`).join('');
            }
            
            // Fetch stations
            const stationRes = await fetch('../../../../server/api/sale/meter_invoice/get-stations.php');
            const stationData = await stationRes.json();
            if (stationData.success) {
                const stationFilter = document.getElementById('stationFilter');
                stationFilter.innerHTML = '<option value="">All Stations</option>' +
                    stationData.data.map(s => `<option value="${s.id}">${s.station_name}</option>`).join('');
            }
            
            // Fetch products
            const productRes = await fetch('../../../../server/api/sale/meter_invoice/get-products.php');
            const productData = await productRes.json();
            if (productData.success) {
                const productFilter = document.getElementById('productFilter');
                productFilter.innerHTML = '<option value="">All Products</option>' +
                    productData.data.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error fetching filter options:', error);
        }
    }
    
    // Fetch readings from API
    async function fetchReadings(page = 1) {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/invoice-list.php?page=${page}&limit=10`);
            const result = await response.json();
            
            if (result.success) {
                readingsData = result.data;
                currentPage = result.pagination.current_page;
                totalPages = result.pagination.total_pages;
                renderTable(readingsData);
                updatePagination(result.pagination);
            } else {
                console.error('Failed to load readings');
            }
        } catch (error) {
            console.error('Error loading readings:', error);
        }
    }
    
    // Update pagination UI
    function updatePagination(pagination) {
        document.getElementById('paginationInfo').textContent = 
            `Showing ${((pagination.current_page - 1) * pagination.limit) + 1} to ${Math.min(pagination.current_page * pagination.limit, pagination.total_records)} of ${pagination.total_records} entries`;
        
        document.getElementById('prevPage').disabled = pagination.current_page === 1;
        document.getElementById('nextPage').disabled = pagination.current_page === pagination.total_pages;
        
        // Update page numbers
        const paginationControls = document.querySelector('.pagination-controls');
        const pageButtons = paginationControls.querySelectorAll('.pagination-btn:not(#prevPage):not(#nextPage)');
        pageButtons.forEach(btn => btn.remove());
        
        const nextBtn = document.getElementById('nextPage');
        for (let i = 1; i <= Math.min(5, pagination.total_pages); i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `pagination-btn ${i === pagination.current_page ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                currentPage = i;
                fetchReadings(currentPage);
            });
            paginationControls.insertBefore(pageBtn, nextBtn);
        }
    }

    // Modal functionality
    let currentInvoiceId = null;
    let currentInvoiceData = null;
    let deleteInvoiceId = null;
    let editInvoiceId = null;
    let editInvoiceData = null;
    let paymentMethods = [];
    
    function openCompleteModal(invoiceId) {
        currentInvoiceId = invoiceId;
        currentInvoiceData = readingsData.find(item => item.id == invoiceId);
        
        if (currentInvoiceData) {
            document.getElementById('modalDate').value = currentInvoiceData.usage_date;
            document.getElementById('unitName').textContent = currentInvoiceData.unit_name;
            document.getElementById('modalRate').value = currentInvoiceData.product_mrp || '';
            document.getElementById('modalClosingReading').value = '';
            document.getElementById('totalQty').textContent = '0.00';
            document.getElementById('qtyUnit').textContent = currentInvoiceData.unit_name;
            document.getElementById('totalRevenue').textContent = '0.00';
            document.getElementById('totalStock').textContent = 'Loading...';
            
            fetchTotalStock(invoiceId);
            fetchPaymentMethods();
            resetSplitTable();
            
            if (currentInvoiceData.product_mrp) {
                calculateTotals();
            }
            
            document.getElementById('completeModal').classList.add('show');
        }
    }
    
    function closeCompleteModal() {
        document.getElementById('completeModal').classList.remove('show');
        currentInvoiceId = null;
        currentInvoiceData = null;
    }
    
    // Modal event listeners
    document.getElementById('modalClose').addEventListener('click', closeCompleteModal);
    document.getElementById('modalCancel').addEventListener('click', closeCompleteModal);
    
    // Calculate totals when inputs change
    function calculateTotals() {
        const rate = parseFloat(document.getElementById('modalRate').value) || 0;
        const closingReading = parseFloat(document.getElementById('modalClosingReading').value) || 0;
        const openingReading = parseFloat(currentInvoiceData?.opening_reading) || 0;
        
        const totalQty = closingReading - openingReading;
        const totalRevenue = totalQty * rate;
        
        document.getElementById('totalQty').textContent = totalQty.toFixed(2);
        document.getElementById('qtyUnit').textContent = currentInvoiceData.unit_name;
        document.getElementById('totalRevenue').textContent = totalRevenue.toFixed(2);
        updateTotalSplit();
    }
    
    document.getElementById('modalRate').addEventListener('input', calculateTotals);
    document.getElementById('modalClosingReading').addEventListener('input', calculateTotals);
    
    // Revenue splitting functionality
    function fetchPaymentMethods() {
        fetch('../../../../server/api/sale/meter_invoice/get-payment-methods.php')
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    paymentMethods = result.data;
                    updatePaymentMethodDropdowns();
                }
            })
            .catch(err => console.error('Error fetching payment methods:', err));
    }
    
    function updatePaymentMethodDropdowns() {
        document.querySelectorAll('.split-payment').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select Payment Method</option>' +
                paymentMethods.map(pm => `<option value="${pm.id}">${pm.account_name}</option>`).join('');
            if (currentValue) select.value = currentValue;
        });
    }
    
    function resetSplitTable() {
        const tbody = document.getElementById('splitTableBody');
        tbody.innerHTML = `
            <tr>
                <td>
                    <select class="form-input split-payment">
                        <option value="">Select Payment Method</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-input split-amount" step="0.01" min="0" placeholder="0.00">
                </td>
                <td>
                    <button type="button" class="action-btn add-split"><i class="fas fa-plus"></i></button>
                </td>
            </tr>`;
        updatePaymentMethodDropdowns();
        attachSplitEventListeners();
        updateTotalSplit();
    }
    
    function attachSplitEventListeners() {
        document.querySelectorAll('.add-split').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });
        document.querySelectorAll('.remove-split').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });
        
        document.querySelectorAll('.add-split').forEach(btn => {
            btn.addEventListener('click', addSplitRow);
        });
        
        document.querySelectorAll('.remove-split').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('tr').remove();
                updateTotalSplit();
            });
        });
        
        document.querySelectorAll('.split-amount').forEach(input => {
            input.addEventListener('input', updateTotalSplit);
        });
    }
    
    function addSplitRow() {
        const tbody = document.getElementById('splitTableBody');
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <select class="form-input split-payment">
                    <option value="">Select Payment Method</option>
                </select>
            </td>
            <td>
                <input type="number" class="form-input split-amount" step="0.01" min="0" placeholder="0.00">
            </td>
            <td>
                <button type="button" class="action-btn remove-split"><i class="fas fa-minus"></i></button>
            </td>`;
        tbody.appendChild(newRow);
        updatePaymentMethodDropdowns();
        attachSplitEventListeners();
    }
    
    function updateTotalSplit() {
        let total = 0;
        document.querySelectorAll('.split-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        
        const totalSplitEl = document.getElementById('totalSplit');
        const totalCashEl = document.getElementById('totalCash');
        const totalRevenueEl = document.getElementById('totalRevenue');
        const errorDiv = document.getElementById('splitError');
        
        if (totalSplitEl) totalSplitEl.textContent = total.toFixed(2);
        
        const totalRevenue = parseFloat(totalRevenueEl?.textContent) || 0;
        const totalCash = totalRevenue - total;
        if (totalCashEl) totalCashEl.textContent = totalCash.toFixed(2);
        
        if (errorDiv) {
            if (total > totalRevenue) {
                errorDiv.textContent = 'Total split cannot exceed total revenue';
                errorDiv.style.display = 'block';
                errorDiv.style.color = 'var(--error)';
            } else {
                errorDiv.style.display = 'none';
            }
        }
    }
    
    // Fetch total stock for invoice
    async function fetchTotalStock(invoiceId) {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-stock.php?invoice_id=${invoiceId}`);
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('totalStock').textContent = parseFloat(result.total_stock).toFixed(2);
                document.getElementById('stockUnit').textContent = currentInvoiceData.unit_name;
            } else {
                document.getElementById('totalStock').textContent = 'Error';
                document.getElementById('stockUnit').textContent = '';
            }
        } catch (error) {
            document.getElementById('totalStock').textContent = 'Error';
        }
    }
    
    // Delete modal functionality
    function openDeleteModal(invoiceId) {
        deleteInvoiceId = invoiceId;
        document.getElementById('deleteModal').classList.add('show');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        deleteInvoiceId = null;
    }
    
    // Delete modal event listeners
    document.getElementById('deleteModalClose').addEventListener('click', closeDeleteModal);
    document.getElementById('deleteCancel').addEventListener('click', closeDeleteModal);
    
    // Edit modal functionality
    function openEditModal(invoiceId) {
        editInvoiceId = invoiceId;
        editInvoiceData = readingsData.find(item => item.id == invoiceId);
        
        if (editInvoiceData) {
            document.getElementById('editDate').value = editInvoiceData.usage_date;
            document.getElementById('editOpeningReading').value = parseFloat(editInvoiceData.opening_reading).toFixed(2);
            document.getElementById('editUnitName').textContent = editInvoiceData.unit_name;
            document.getElementById('editRate').value = editInvoiceData.product_mrp || '';
            document.getElementById('editClosingReading').value = editInvoiceData.closing_reading || '';
            document.getElementById('editTotalQty').textContent = '0.00';
            document.getElementById('editQtyUnit').textContent = editInvoiceData.unit_name;
            document.getElementById('editTotalRevenue').textContent = '0.00';
            
            // Calculate totals if values are present
            calculateEditTotals();
            
            document.getElementById('editModal').classList.add('show');
        }
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
        editInvoiceId = null;
        editInvoiceData = null;
    }
    
    // Edit modal event listeners
    document.getElementById('editModalClose').addEventListener('click', closeEditModal);
    document.getElementById('editCancel').addEventListener('click', closeEditModal);
    
    // Calculate totals for edit modal
    function calculateEditTotals() {
        const rate = parseFloat(document.getElementById('editRate').value) || 0;
        const closingReading = parseFloat(document.getElementById('editClosingReading').value) || 0;
        const openingReading = parseFloat(document.getElementById('editOpeningReading').value) || 0;
        
        const totalQty = closingReading - openingReading;
        const totalRevenue = totalQty * rate;
        
        document.getElementById('editTotalQty').textContent = totalQty.toFixed(2);
        document.getElementById('editTotalRevenue').textContent = totalRevenue.toFixed(2);
    }
    
    document.getElementById('editOpeningReading').addEventListener('input', calculateEditTotals);
    document.getElementById('editRate').addEventListener('input', calculateEditTotals);
    document.getElementById('editClosingReading').addEventListener('input', calculateEditTotals);
    
    // Edit form submission
    document.getElementById('editForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const date = document.getElementById('editDate').value;
        const openingReading = parseFloat(document.getElementById('editOpeningReading').value);
        const rate = parseFloat(document.getElementById('editRate').value);
        const closingReading = parseFloat(document.getElementById('editClosingReading').value);
        
        if (!date || !openingReading || !rate || !closingReading) {
            showNotification('Please fill in all required fields', 'error');
            return;
        }
        
        if (closingReading < openingReading) {
            showNotification('Closing reading cannot be less than opening reading', 'error');
            return;
        }
        

        
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/invoice-edit.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    invoice_id: editInvoiceId,
                    date: date,
                    opening_reading: openingReading,
                    rate: rate,
                    closing_reading: closingReading
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Invoice updated successfully!', 'success');
                closeEditModal();
                fetchReadings(currentPage);
            } else {
                showNotification(result.message || 'Error updating invoice', 'error');
            }
        } catch (error) {
            showNotification('Error updating invoice', 'error');
        }
    });
    
    document.getElementById('confirmDelete').addEventListener('click', async function() {
        if (!deleteInvoiceId) return;
        
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/invoice-delete.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ invoice_id: deleteInvoiceId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Invoice deleted successfully!', 'success');
                closeDeleteModal();
                fetchReadings(currentPage);
            } else {
                showNotification(result.message || 'Error deleting invoice', 'error');
            }
        } catch (error) {
            showNotification('Error deleting invoice', 'error');
        }
    });
    
    // Complete form submission
    document.getElementById('completeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const rate = parseFloat(document.getElementById('modalRate').value);
        const closingReading = parseFloat(document.getElementById('modalClosingReading').value);
        
        if (!rate || !closingReading || isNaN(rate) || isNaN(closingReading)) {
            showNotification('Please fill in all required fields', 'error');
            return;
        }
        
        if (closingReading <= parseFloat(currentInvoiceData.opening_reading)) {
            showNotification('Closing reading must be greater than opening reading', 'error');
            return;
        }
        
        const splits = [];
        let totalSplit = 0;
        const rows = document.querySelectorAll('#splitTableBody tr');
        
        for (let row of rows) {
            const paymentMethod = row.querySelector('.split-payment').value;
            const amount = parseFloat(row.querySelector('.split-amount').value) || 0;
            
            if (amount > 0) {
                if (!paymentMethod) {
                    showNotification('Please select payment method for all rows with amounts', 'error');
                    return;
                }
                splits.push({ account_id: paymentMethod, amount: amount });
                totalSplit += amount;
            }
        }
        
        const totalRevenue = parseFloat(document.getElementById('totalRevenue').textContent) || 0;
        const totalCash = parseFloat(document.getElementById('totalCash').textContent) || 0;
        
        if (Math.abs((totalSplit + totalCash) - totalRevenue) > 0.01) {
            showNotification('Total Split + Total Cash must equal Total Revenue', 'error');
            return;
        }
        
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/invoice-complete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    invoice_id: currentInvoiceId,
                    rate: rate,
                    closing_reading: closingReading,
                    revenue_splits: splits,
                    total_cash: totalCash
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Invoice completed successfully!', 'success');
                closeCompleteModal();
                fetchReadings(currentPage);
            } else {
                showNotification(result.message || 'Error completing invoice', 'error');
            }
        } catch (error) {
            showNotification('Error completing invoice', 'error');
        }
    });
    
    // Show notification
    function showNotification(message, type) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type} show`;

        setTimeout(() => {
            notification.classList.remove('show');
        }, 4000);
    }

    // Helper function to format date
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }
    
    // Helper functions to get names from IDs
    function getBranchName(id) {
        const branches = {
            '1': 'Downtown Branch',
            '2': 'Westside Station',
            '3': 'Eastgate Fuel Center',
            '4': 'Northpoint Outlet'
        };
        return branches[id] || '';
    }
    
    function getStationName(id) {
        const stations = {
            '1': 'Station A - Main Pumps',
            '2': 'Station B - Express Lane',
            '3': 'Station C - Truck Stop',
            '4': 'Station D - Premium Fuel'
        };
        return stations[id] || '';
    }
    
    function getProductName(id) {
        const products = {
            '1': 'Regular Unleaded',
            '2': 'Premium Unleaded',
            '3': 'Super Unleaded',
            '4': 'Diesel'
        };
        return products[id] || '';
    }
});