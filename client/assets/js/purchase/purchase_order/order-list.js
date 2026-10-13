document.addEventListener('DOMContentLoaded', function () {
    let invoices = [];
    let currentPage = 1;
    let totalPages = 1;
    const invoicesTable = document.getElementById('invoicesTable').getElementsByTagName('tbody')[0];

    // Load invoices from API
    async function loadInvoices(page = 1) {
        try {
            const response = await fetch(`../../../../server/api/purchase/purchase_order/order-list.php?page=${page}&limit=10`);
            const data = await response.json();
            
            if (data.success) {
                invoices = data.invoices.map(invoice => ({
                    id: invoice.id,
                    invoiceNo: invoice.bill_no,
                    date: invoice.purchase_date,
                    supplier: invoice.supplier_name,
                    supplier_id: invoice.supplier_id,
                    items: invoice.item_count,
                    totalAmount: parseFloat(invoice.net_amount),
                    currencySymbol: invoice.currency_symbol || '',
                    status: invoice.fulfillment_status || 'Pending',
                    company_id: invoice.company_id
                }));
                currentPage = data.pagination.page;
                totalPages = data.pagination.pages;
                populateTable(invoices);
                updatePagination(data.pagination);
            } else {
                console.error('Error loading invoices:', data.message);
            }
        } catch (error) {
            console.error('Error loading invoices:', error);
        }
    }

    // Load companies
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_order/get-companies.php');
            const data = await response.json();
            if (data.success) {
                const companyFilter = document.getElementById('companyFilter');
                data.companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = `${company.company_code} - ${company.company_name}`;
                    companyFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    // Load suppliers
    async function loadSuppliers() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_order/get-suppliers.php');
            const data = await response.json();
            if (data.success) {
                const supplierFilter = document.getElementById('supplierFilter');
                data.suppliers.forEach(supplier => {
                    const option = document.createElement('option');
                    option.value = supplier.id;
                    option.textContent = `${supplier.supplier_code} - ${supplier.supplier_name}`;
                    supplierFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading suppliers:', error);
        }
    }

    function populateTable(data) {
        invoicesTable.innerHTML = '';

        data.forEach(invoice => {
            const row = invoicesTable.insertRow();

            // Format date
            const formattedDate = new Date(invoice.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Format amount with currency symbol
            const formattedAmount = `${invoice.currencySymbol} ${parseFloat(invoice.totalAmount).toFixed(2)}`;

            // Create cells
            row.insertCell(0).textContent = invoice.invoiceNo;
            row.insertCell(1).textContent = formattedDate;
            row.insertCell(2).textContent = invoice.supplier || 'N/A';
            row.insertCell(3).textContent = invoice.items;
            row.insertCell(4).textContent = formattedAmount;
            
            // Status cell
            const statusCell = row.insertCell(5);
            const statusBadge = document.createElement('span');
            statusBadge.className = 'status ' + (invoice.status === 'Partially Fulfilled' ? 'status-pending' : 'status-overdue');
            statusBadge.textContent = invoice.status || 'Pending';
            statusCell.appendChild(statusBadge);

            const actionsCell = row.insertCell(6);
            const actionButtons = document.createElement('div');
            actionButtons.className = 'action-buttons';

            // Edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-primary btn-sm';
            editBtn.innerHTML = '<i class="fas fa-edit"></i>';
            editBtn.title = 'Edit Order';
            editBtn.addEventListener('click', () => editInvoice(invoice.id));

            // Print button
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-success btn-sm';
            printBtn.innerHTML = '<i class="fas fa-print"></i>';
            printBtn.title = 'Print Order';
            printBtn.addEventListener('click', () => printInvoice(invoice.id));
            
            // View Fulfillment button
            const fulfillmentBtn = document.createElement('button');
            fulfillmentBtn.className = 'btn btn-warning btn-sm';
            fulfillmentBtn.innerHTML = '<i class="fas fa-tasks"></i>';
            fulfillmentBtn.title = 'View Fulfillment';
            fulfillmentBtn.addEventListener('click', () => viewFulfillment(invoice.id));

            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-danger btn-sm';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.title = 'Delete Order';
            deleteBtn.addEventListener('click', () => deleteInvoice(invoice.id));

            actionButtons.appendChild(editBtn);
            actionButtons.appendChild(printBtn);
            actionButtons.appendChild(fulfillmentBtn);
            actionButtons.appendChild(deleteBtn);

            actionsCell.appendChild(actionButtons);
        });
    }

    // Initial load
    loadInvoices();
    loadCompanies();
    loadSuppliers();

    // Filter functionality
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const companyFilter = document.getElementById('companyFilter');
    const supplierFilter = document.getElementById('supplierFilter');
    const searchInput = document.getElementById('searchInput');

    function applyFilters() {
        let filteredInvoices = [...invoices];

        // Date filter
        if (dateFrom.value) {
            filteredInvoices = filteredInvoices.filter(invoice =>
                new Date(invoice.date) >= new Date(dateFrom.value)
            );
        }

        if (dateTo.value) {
            filteredInvoices = filteredInvoices.filter(invoice =>
                new Date(invoice.date) <= new Date(dateTo.value)
            );
        }

        // Company filter
        if (companyFilter.value) {
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.company_id == companyFilter.value
            );
        }

        // Supplier filter
        if (supplierFilter.value) {
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.supplier_id == supplierFilter.value
            );
        }



        // Search filter
        if (searchInput.value) {
            const searchTerm = searchInput.value.toLowerCase();
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.invoiceNo.toLowerCase().includes(searchTerm) ||
                invoice.supplier.toLowerCase().includes(searchTerm)
            );
        }

        populateTable(filteredInvoices);
    }

    // Add event listeners for filters
    dateFrom.addEventListener('change', applyFilters);
    dateTo.addEventListener('change', applyFilters);
    companyFilter.addEventListener('change', applyFilters);
    supplierFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);

    // Update pagination
    function updatePagination(pagination) {
        const { page, limit, total, pages } = pagination;
        const start = (page - 1) * limit + 1;
        const end = Math.min(page * limit, total);
        
        document.getElementById('paginationInfo').textContent = 
            `Showing ${start} to ${end} of ${total} entries`;
            
        generatePaginationButtons(page, pages);
    }
    
    // Generate dynamic pagination buttons
    function generatePaginationButtons(currentPage, totalPages) {
        const container = document.getElementById('paginationControls');
        container.innerHTML = '';
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.disabled = currentPage <= 1;
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) loadInvoices(currentPage - 1);
        });
        container.appendChild(prevBtn);
        
        // Page number buttons
        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                if (i !== currentPage) loadInvoices(i);
            });
            container.appendChild(pageBtn);
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.disabled = currentPage >= totalPages;
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) loadInvoices(currentPage + 1);
        });
        container.appendChild(nextBtn);
    }

    // New invoice button
    document.getElementById('newInvoiceBtn').addEventListener('click', function () {
        window.location.href = 'order-add.php';
    });

    // Action functions
    function editInvoice(id) {
        window.location.href = `order-add.php?edit=${id}`;
    }

    function printInvoice(id) {
        window.open(`invoice-print.php?id=${id}`, '_blank');
    }

    let deleteInvoiceId = null;
    
    function deleteInvoice(id) {
        deleteInvoiceId = id;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    // Modal event listeners
    document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
        document.getElementById('deleteModal').style.display = 'none';
        deleteInvoiceId = null;
    });
    
    document.getElementById('closeFulfillmentBtn').addEventListener('click', function() {
        document.getElementById('fulfillmentModal').style.display = 'none';
    });
    
    // View fulfillment function
    async function viewFulfillment(orderId) {
        document.getElementById('fulfillmentModal').style.display = 'flex';
        const tbody = document.getElementById('fulfillmentTableBody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">Loading...</td></tr>';
        
        try {
            const response = await fetch(`../../../../server/api/purchase/purchase_order/get-order-fulfillment.php?order_id=${orderId}`);
            const data = await response.json();
            
            if (data.success && data.fulfillment && data.fulfillment.length > 0) {
                tbody.innerHTML = '';
                data.fulfillment.forEach(item => {
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${item.product_name}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">${parseFloat(item.ordered_qty).toFixed(2)} ${item.uom_name}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">${parseFloat(item.served_qty).toFixed(2)} ${item.uom_name}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">${parseFloat(item.remaining_qty).toFixed(2)} ${item.uom_name}</td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">
                            <span class="status ${parseFloat(item.fulfillment_percent) >= 100 ? 'status-paid' : parseFloat(item.fulfillment_percent) > 0 ? 'status-pending' : 'status-overdue'}">
                                ${parseFloat(item.fulfillment_percent).toFixed(0)}%
                            </span>
                        </td>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No fulfillment data available</td></tr>';
            }
        } catch (error) {
            console.error('Fulfillment error:', error);
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--error);">Error loading fulfillment data</td></tr>';
        }
    }
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        if (deleteInvoiceId) {
            try {
                const response = await fetch(`../../../../server/api/purchase/purchase_order/order-delete.php?id=${deleteInvoiceId}`, {
                    method: 'DELETE'
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('deleteModal').style.display = 'none';
                    loadInvoices(currentPage);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error deleting order: ' + error.message);
            }
            deleteInvoiceId = null;
        }
    });
    
    // Hide modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            deleteInvoiceId = null;
        }
    });
    
    document.getElementById('fulfillmentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

});