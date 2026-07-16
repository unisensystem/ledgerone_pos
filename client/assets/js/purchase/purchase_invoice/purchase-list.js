document.addEventListener('DOMContentLoaded', function () {
    let invoices = [];
    let currentPage = 1;
    let totalPages = 1;
    const invoicesTable = document.getElementById('invoicesTable').getElementsByTagName('tbody')[0];
    let duplicateInvoiceId = null;

    // Load companies for filter
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-companies.php');
            const data = await response.json();
            if (data.success) {
                const companyFilter = document.getElementById('companyFilter');
                companyFilter.innerHTML = '<option value="">All Companies</option>';
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

    // Load suppliers for filter + duplicate modal
    async function loadSuppliers() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-suppliers.php');
            const data = await response.json();
            
            if (data.success) {
                // Filter dropdown
                const supplierFilterOptions = document.getElementById('supplierFilterOptions');
                if (supplierFilterOptions) {
                    supplierFilterOptions.innerHTML = '<div class="dropdown-option" data-value="">All Suppliers</div>';
                    data.suppliers.forEach(supplier => {
                        const option = document.createElement('div');
                        option.className = 'dropdown-option';
                        option.setAttribute('data-value', supplier.supplier_name);
                        option.textContent = supplier.supplier_name;
                        supplierFilterOptions.appendChild(option);
                    });
                    initSearchableDropdown('supplierFilterSearch', 'supplierFilterOptions', 'supplierFilter');
                }

                // Duplicate modal supplier dropdown
                const dupOptions = document.getElementById('dupSupplierOptions');
                if (dupOptions) {
                    dupOptions.innerHTML = '';
                    data.suppliers.forEach(supplier => {
                        const opt = document.createElement('div');
                        opt.className = 'dropdown-option';
                        opt.setAttribute('data-value', supplier.id);
                        opt.textContent = `${supplier.supplier_code} - ${supplier.supplier_name}`;
                        dupOptions.appendChild(opt);
                    });
                    initDupSupplierDropdown();
                }
            }
        } catch (error) {
            console.error('Error loading suppliers:', error);
        }
    }

    async function fetchDupSupplierBalance(supplierId) {
        const el = document.getElementById('dupSupplierBalance');
        if (!supplierId || !el) return;
        el.textContent = 'Loading...';
        el.style.display = 'block';
        try {
            const res = await fetch(`../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?supplier_id=${supplierId}&type=summary`);
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                const balance = parseFloat(data.data[0].closing_balance || 0);
                const label = balance >= 0 ? 'Dr' : 'Cr';
                el.textContent = `Balance: ${label} ${Math.abs(balance).toFixed(3)}`;
            } else {
                el.textContent = '';
                el.style.display = 'none';
            }
        } catch (e) {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    function initDupSupplierDropdown() {
        const searchInput = document.getElementById('dupSupplierSearch');
        const optionsContainer = document.getElementById('dupSupplierOptions');
        const hiddenInput = document.getElementById('dupSupplierId');

        searchInput.addEventListener('click', function(e) {
            e.stopPropagation();
            optionsContainer.style.display = 'block';
            filterDupOptions();
        });
        searchInput.addEventListener('input', filterDupOptions);

        optionsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('dropdown-option')) {
                const supplierId = e.target.getAttribute('data-value');
                hiddenInput.value = supplierId;
                searchInput.value = e.target.textContent;
                optionsContainer.style.display = 'none';
                fetchDupSupplierBalance(supplierId);
            }
        });

        function filterDupOptions() {
            const term = searchInput.value.toLowerCase();
            Array.from(optionsContainer.getElementsByClassName('dropdown-option')).forEach(opt => {
                opt.style.display = opt.textContent.toLowerCase().includes(term) ? 'block' : 'none';
            });
        }
    }

    // Load invoices from API
    async function loadInvoices(page = 1) {
        try {
            const response = await fetch(`../../../../server/api/purchase/purchase_invoice/purchase-list.php?page=${page}&limit=10`);
            const data = await response.json();
            
            if (data.success) {
                invoices = data.invoices.map(invoice => ({
                    id: invoice.id,
                    invoiceNo: invoice.bill_no,
                    date: invoice.purchase_date,
                    supplierInvoiceNo: invoice.supplier_invoice_no || '',
                    supplier: invoice.supplier_name,
                    supplierId: invoice.supplier_id,
                    companyId: invoice.company_id,
                    items: invoice.item_count,
                    totalAmount: parseFloat(invoice.net_amount),
                    currencySymbol: invoice.currency_symbol || ''
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

    // Initialize searchable dropdown
    function initSearchableDropdown(searchInputId, optionsContainerId, hiddenInputId) {
        const searchInput = document.getElementById(searchInputId);
        const optionsContainer = document.getElementById(optionsContainerId);
        const hiddenInput = document.getElementById(hiddenInputId);

        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
            optionsContainer.style.display = 'block';
            filterOptions();
        });

        searchInput.addEventListener('input', filterOptions);

        optionsContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('dropdown-option')) {
                const value = e.target.getAttribute('data-value');
                searchInput.value = e.target.textContent;
                hiddenInput.value = value;
                optionsContainer.style.display = 'none';
                applyFilters();
            }
        });

        document.addEventListener('click', function () {
            optionsContainer.style.display = 'none';
        });

        function filterOptions() {
            const searchTerm = searchInput.value.toLowerCase();
            const options = optionsContainer.getElementsByClassName('dropdown-option');
            for (let i = 0; i < options.length; i++) {
                const text = options[i].textContent.toLowerCase();
                options[i].style.display = text.includes(searchTerm) ? 'block' : 'none';
            }
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
            row.insertCell(3).textContent = invoice.supplierInvoiceNo || '-';
            row.insertCell(4).textContent = invoice.items;
            row.insertCell(5).textContent = formattedAmount;

            const actionsCell = row.insertCell(6);
            const actionButtons = document.createElement('div');
            actionButtons.className = 'action-buttons';

            // Edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-primary btn-sm';
            editBtn.innerHTML = '<i class="fas fa-edit"></i>';
            editBtn.title = 'Edit Invoice';
            editBtn.addEventListener('click', () => editInvoice(invoice.id));

            // Duplicate button
            const dupBtn = document.createElement('button');
            dupBtn.className = 'btn btn-secondary btn-sm';
            dupBtn.innerHTML = '<i class="fas fa-copy"></i>';
            dupBtn.title = 'Duplicate Invoice';
            dupBtn.addEventListener('click', () => openDuplicateModal(invoice.id, invoice.invoiceNo, invoice.supplierId));

            // Print button
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-success btn-sm';
            printBtn.innerHTML = '<i class="fas fa-print"></i>';
            printBtn.title = 'Print Invoice';
            printBtn.addEventListener('click', () => printInvoice(invoice.id));

            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-danger btn-sm';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.title = 'Delete Invoice';
            deleteBtn.addEventListener('click', () => deleteInvoice(invoice.id));

            actionButtons.appendChild(editBtn);
            actionButtons.appendChild(dupBtn);
            actionButtons.appendChild(printBtn);
            actionButtons.appendChild(deleteBtn);

            actionsCell.appendChild(actionButtons);
        });
    }

    // Initial load
    loadCompanies();
    loadSuppliers();
    loadInvoices();

    // Filter functionality
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const companyFilter = document.getElementById('companyFilter');
    const supplierFilter = document.getElementById('supplierFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const supplierInvoiceNoFilter = document.getElementById('supplierInvoiceNoFilter');

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
                invoice.companyId == companyFilter.value
            );
        }

        // Supplier filter
        if (supplierFilter.value) {
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.supplier === supplierFilter.value
            );
        }



        // Supplier Invoice No filter
        if (supplierInvoiceNoFilter.value) {
            const term = supplierInvoiceNoFilter.value.toLowerCase();
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.supplierInvoiceNo.toLowerCase().includes(term)
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
    statusFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    supplierInvoiceNoFilter.addEventListener('input', applyFilters);

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
        window.location.href = 'purchase-add.php';
    });

    // Action functions
    function editInvoice(id) {
        window.location.href = `purchase-add.php?edit=${id}`;
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
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        if (deleteInvoiceId) {
            try {
                const response = await fetch(`../../../../server/api/purchase/purchase_invoice/purchase-delete.php?id=${deleteInvoiceId}`, {
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
                alert('Error deleting invoice: ' + error.message);
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

    // Duplicate invoice
    function openDuplicateModal(id, currentBillNo, originalSupplierId) {
        duplicateInvoiceId = id;
        document.getElementById('duplicateSourceBillNo').textContent = currentBillNo;
        document.getElementById('duplicateNewBillNoDisplay').textContent = 'Generating...';
        document.getElementById('duplicateError').textContent = '';
        document.getElementById('duplicateDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('dupSupplierSearch').value = '';
        document.getElementById('dupSupplierId').value = '';
        document.getElementById('confirmDuplicateBtn').dataset.originalSupplierId = originalSupplierId || '';
        const dupBalEl = document.getElementById('dupSupplierBalance');
        if (dupBalEl) { dupBalEl.textContent = ''; dupBalEl.style.display = 'none'; }
        document.getElementById('duplicateModal').style.display = 'flex';

        // Fetch original supplier balance on modal open
        if (originalSupplierId) fetchDupSupplierBalance(originalSupplierId);

        fetch('../../../../server/api/purchase/purchase_invoice/purchase-next-bill-no.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('duplicateNewBillNoDisplay').textContent = data.bill_no;
                    document.getElementById('confirmDuplicateBtn').dataset.billNo = data.bill_no;
                }
            })
            .catch(() => {
                document.getElementById('duplicateNewBillNoDisplay').textContent = 'Error';
            });
    }

    document.getElementById('cancelDuplicateBtn').addEventListener('click', function() {
        document.getElementById('duplicateModal').style.display = 'none';
        duplicateInvoiceId = null;
    });

    document.getElementById('confirmDuplicateBtn').addEventListener('click', async function() {
        const previewBillNo = document.getElementById('duplicateNewBillNoDisplay').textContent;
        const errorEl = document.getElementById('duplicateError');
        if (!previewBillNo || previewBillNo === 'Generating...') {
            errorEl.textContent = 'Please wait, generating invoice number...';
            return;
        }
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duplicating...';
        try {
            const newSupplierId = document.getElementById('dupSupplierId').value || null;
            const originalSupplierId = this.dataset.originalSupplierId || null;
            const balanceSupplierId = newSupplierId || originalSupplierId;
            let previousBalance = 0;
            if (balanceSupplierId) {
                const balRes = await fetch(`../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?supplier_id=${balanceSupplierId}&type=summary`);
                const balData = await balRes.json();
                if (balData.success && balData.data && balData.data.length > 0) {
                    previousBalance = parseFloat(balData.data[0].closing_balance || 0);
                }
            }
            const payload = {
                invoice_id: duplicateInvoiceId,
                purchase_date: document.getElementById('duplicateDate').value || null,
                supplier_id: newSupplierId,
                previous_balance: previousBalance
            };
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/purchase-duplicate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById('duplicateModal').style.display = 'none';
                duplicateInvoiceId = null;
                loadInvoices(currentPage);
            } else {
                errorEl.textContent = data.message;
            }
        } catch (err) {
            errorEl.textContent = 'Error: ' + err.message;
        }
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-copy"></i> Confirm Duplicate';
    });

    document.getElementById('duplicateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            duplicateInvoiceId = null;
        }
    });

});