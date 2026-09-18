document.addEventListener('DOMContentLoaded', function () {
    // Check permissions
fetch('../../../../server/api/auth/check-permission.php?category=Sale&form_name=Sale Tax Invoice')
        .then(response => {
            if (response.status === 403) {
                window.location.href = '../../../../../errors/403.php';
                return;
            }
            return response.json();
        })
        .then(data => {
            if (!data) return;
            if (data.success) {
                // Check if user has at least one permission
                if (data.permissions.length === 0) {
                    window.location.href = '../../../../../errors/403.php';
                    return;
                }
                initializeListPage(data.permissions);
            }
        });
});

function initializeListPage(permissions) {
    let invoices = [];
    let currentPage = 1;
    let totalPages = 1;
    const invoicesTable = document.getElementById('invoicesTable').getElementsByTagName('tbody')[0];
    let duplicateInvoiceId = null;
    
    // Filter elements
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const companyFilter = document.getElementById('companyFilter');
    const customerFilter = document.getElementById('customerFilter');
    const searchInput = document.getElementById('searchInput');

    // Load invoices from API
    async function loadInvoices(page = 1) {
        try {
            // Build query parameters
            const params = new URLSearchParams({
                page: page,
                limit: 10
            });
            
            // Add filters if present
            if (dateFrom.value) params.append('dateFrom', dateFrom.value);
            if (dateTo.value) params.append('dateTo', dateTo.value);
            if (companyFilter.value) params.append('company_id', companyFilter.value);
            if (customerFilter.value) params.append('customer', customerFilter.value);
            if (searchInput.value) params.append('search', searchInput.value);
            
            const response = await fetch(`../../../../server/api/sale/sale_tax_invoice/pos-list.php?${params.toString()}`);
            const data = await response.json();
            
            if (data.success) {
                invoices = data.invoices.map(invoice => ({
                    id: invoice.id,
                    invoiceNo: invoice.bill_no,
                    date: invoice.sale_date,
                    customer: invoice.customer_name,
                    customerId: invoice.customer_id || null,
                    company: invoice.company_name,
                    companyId: invoice.company_id || null,
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

    function populateTable(data) {
        invoicesTable.innerHTML = '';
        let totalAmount = 0;
        let invoiceCount = data.length;

        data.forEach(invoice => {
            totalAmount += invoice.totalAmount;
            const row = invoicesTable.insertRow();

            // Format date
            const formattedDate = new Date(invoice.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Format amount with currency symbol
            const formattedAmount = `${invoice.currencySymbol} ${parseFloat(invoice.totalAmount).toFixed(3)}`;

            // Create cells
            row.insertCell(0).textContent = invoice.invoiceNo;
            row.insertCell(1).textContent = formattedDate;
            row.insertCell(2).textContent = invoice.customer || 'N/A';
            row.insertCell(3).textContent = invoice.company || '-';
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
            if (permissions.includes('Edit')) {
                editBtn.addEventListener('click', () => editInvoice(invoice.id));
            } else {
                editBtn.disabled = true;
                editBtn.style.opacity = '0.5';
                editBtn.style.cursor = 'not-allowed';
            }

            // Print button
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-success btn-sm';
            printBtn.innerHTML = '<i class="fas fa-print"></i>';
            printBtn.title = 'Print Invoice';
            printBtn.addEventListener('click', () => printInvoice(invoice.id));

            // Custom print button
            const customPrintBtn = document.createElement('button');
            customPrintBtn.className = 'btn btn-info btn-sm';
            customPrintBtn.innerHTML = '<i class="fas fa-file-invoice"></i>';
            customPrintBtn.title = 'Custom Print (Tax Invoice)';
            customPrintBtn.addEventListener('click', () => {
                window.open(`invoice-print2.php?id=${invoice.id}`, '_blank');
            });

            // PDF button
            const pdfBtn = document.createElement('button');
            pdfBtn.className = 'btn btn-warning btn-sm';
            pdfBtn.innerHTML = '<i class="fas fa-file-pdf"></i>';
            pdfBtn.title = 'Download PDF';
            pdfBtn.addEventListener('click', () => {
                window.open(`invoice-print2.php?id=${invoice.id}&pdf=1`, '_blank');
            });

            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-danger btn-sm';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.title = 'Delete Invoice';
            if (permissions.includes('Delete')) {
                deleteBtn.addEventListener('click', () => deleteInvoice(invoice.id));
            } else {
                deleteBtn.disabled = true;
                deleteBtn.style.opacity = '0.5';
                deleteBtn.style.cursor = 'not-allowed';
            }

           // Change to:
const dupBtn = document.createElement('button');
dupBtn.className = 'btn btn-secondary btn-sm';
dupBtn.innerHTML = '<i class="fas fa-copy"></i>';
dupBtn.title = 'Duplicate Invoice';
if (permissions.includes('Duplicate')) {
    dupBtn.addEventListener('click', () => openDuplicateModal(invoice.id, invoice.invoiceNo, invoice.customerId, invoice.companyId));
} else {
    dupBtn.disabled = true;
    dupBtn.style.opacity = '0.5';
    dupBtn.style.cursor = 'not-allowed';
}
            actionButtons.appendChild(editBtn);
            actionButtons.appendChild(dupBtn);
            actionButtons.appendChild(printBtn);
            actionButtons.appendChild(customPrintBtn);
            actionButtons.appendChild(pdfBtn);
            actionButtons.appendChild(deleteBtn);

            actionsCell.appendChild(actionButtons);
        });
        
        updateSummary(invoiceCount, totalAmount, data[0]?.currencySymbol || '');
    }
    
    function updateSummary(count, total, currencySymbol) {
        document.getElementById('totalInvoiceCount').textContent = count;
        document.getElementById('totalAmountSum').textContent = `${currencySymbol} ${total.toFixed(3)}`;
    }

    // Initial load
    loadInvoices();
    loadCompanies();
    loadCustomers();
    
    // Load customers for filter
    let customerDropdownInitialized = false;
    async function loadCustomers(companyId = null) {
        try {
            const url = companyId
                ? `../../../../server/api/sale/sale_tax_invoice/get-customers.php?company_id=${companyId}`
                : '../../../../server/api/sale/sale_tax_invoice/get-customers.php';
            const response = await fetch(url);
            const data = await response.json();
            if (data.success) {
                const customerFilterOptions = document.getElementById('customerFilterOptions');
                customerFilterOptions.innerHTML = '<div class="dropdown-option" data-value="" data-name="">All Customers</div>';
                data.customers.forEach(customer => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', customer.customer_name);
                    option.setAttribute('data-name', customer.customer_name);
                    option.textContent = `${customer.customer_code} - ${customer.customer_name}`;
                    customerFilterOptions.appendChild(option);
                });
                if (!customerDropdownInitialized) {
                    initSearchableDropdown('customerFilterSearch', 'customerFilterOptions', 'customerFilter');
                    customerDropdownInitialized = true;
                }
            }
        } catch (error) {
            console.error('Error loading customers:', error);
        }
    }
    
    // Load companies for filter
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_tax_invoice/get-companies.php');
            const data = await response.json();
            if (data.success) {
                const companyFilterOptions = document.getElementById('companyFilterOptions');
                companyFilterOptions.innerHTML = '<div class="dropdown-option" data-value="" data-name="">All Companies</div>';
                data.companies.forEach(company => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', company.id);
                    option.setAttribute('data-name', company.company_name);
                    option.textContent = company.company_name;
                    companyFilterOptions.appendChild(option);
                });
                initSearchableDropdown('companyFilterSearch', 'companyFilterOptions', 'companyFilter');
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }
    
    // Initialize searchable dropdown
    function initSearchableDropdown(searchInputId, optionsContainerId, hiddenInputId) {
        const searchInput = document.getElementById(searchInputId);
        const optionsContainer = document.getElementById(optionsContainerId);
        const hiddenInput = document.getElementById(hiddenInputId);
        
        searchInput.addEventListener('click', function(e) {
            e.stopPropagation();
            optionsContainer.style.display = 'block';
            filterOptions();
        });
        
        searchInput.addEventListener('input', function() {
            filterOptions();
        });
        
        optionsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('dropdown-option')) {
                const value = e.target.getAttribute('data-value');
                const name = e.target.getAttribute('data-name');
                searchInput.value = name || (hiddenInputId === 'customerFilter' ? 'All Customers' : 'All Companies');
                hiddenInput.value = value;
                optionsContainer.style.display = 'none';

                // When company changes, reload customers and clear customer filter
                if (hiddenInputId === 'companyFilter') {
                    document.getElementById('customerFilterSearch').value = '';
                    document.getElementById('customerFilter').value = '';
                    loadCustomers(value || null);
                }

                applyFilters();
            }
        });
        
        document.addEventListener('click', function() {
            optionsContainer.style.display = 'none';
        });
        
        function filterOptions() {
            const searchTerm = searchInput.value.toLowerCase();
            const options = optionsContainer.getElementsByClassName('dropdown-option');
            
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'block' : 'none';
            }
        }
    }

    function applyFilters() {
        loadInvoices(1); // Reset to page 1 when filters change
    }

    // Add event listeners for filters
    dateFrom.addEventListener('change', applyFilters);
    dateTo.addEventListener('change', applyFilters);
    companyFilter.addEventListener('change', applyFilters);
    customerFilter.addEventListener('change', applyFilters);
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
        
        // Calculate page range to show
        const maxButtons = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        
        // Adjust start if we're near the end
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }
        
        // First page button
        if (startPage > 1) {
            const firstBtn = document.createElement('button');
            firstBtn.className = 'pagination-btn';
            firstBtn.textContent = '1';
            firstBtn.addEventListener('click', () => loadInvoices(1));
            container.appendChild(firstBtn);
            
            if (startPage > 2) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '0 8px';
                container.appendChild(dots);
            }
        }
        
        // Page number buttons
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                if (i !== currentPage) loadInvoices(i);
            });
            container.appendChild(pageBtn);
        }
        
        // Last page button
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '0 8px';
                container.appendChild(dots);
            }
            
            const lastBtn = document.createElement('button');
            lastBtn.className = 'pagination-btn';
            lastBtn.textContent = totalPages;
            lastBtn.addEventListener('click', () => loadInvoices(totalPages));
            container.appendChild(lastBtn);
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
        if (!permissions.includes('Add')) {
            alert('You do not have permission to add invoices.');
            return;
        }
        window.location.href = 'pos-add.php';
    });

    // Action functions
    function editInvoice(id) {
        if (!permissions.includes('Edit')) {
            alert('You do not have permission to edit invoices.');
            return;
        }
        window.location.href = `pos-add.php?edit=${id}`;
    }

    function printInvoice(id) {
        showPrintOptions(id);
    }
    
    // Print options modal
    function showPrintOptions(invoiceId) {
        const savedChoice = localStorage.getItem('pos_print_preference');
        if (savedChoice) {
            const url = savedChoice === 'full' ? `invoice-print.php?id=${invoiceId}` : (savedChoice === 'thermal2' ? `thermal-print2.php?id=${invoiceId}` : `thermal-print.php?id=${invoiceId}`);
            window.open(url, '_blank');
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <h3 class="modal-title">Select Print Format</h3>
                <p>Choose how you want to print the invoice:</p>
                <div class="modal-actions" style="flex-direction: column; gap: 10px;">
                    <button class="btn btn-primary" id="printFullBtnList" style="width: 100%;">
                        <i class="fas fa-file-invoice"></i> Print Full Invoice
                    </button>
                    <button class="btn btn-secondary" id="printThermalBtnList" style="width: 100%;">
                        <i class="fas fa-receipt"></i> Print Thermal Invoice
                    </button>
                    <button class="btn btn-secondary" id="printThermal2BtnList" style="width: 100%;">
                        <i class="fas fa-receipt"></i> Print Thermal Invoice 2
                    </button>
                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                        <input type="checkbox" id="rememberPrintChoiceList" style="width: auto;">
                        <span>Remember my choice</span>
                    </label>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('printFullBtnList').onclick = function() {
            if (document.getElementById('rememberPrintChoiceList').checked) {
                localStorage.setItem('pos_print_preference', 'full');
            }
            window.open(`invoice-print.php?id=${invoiceId}`, '_blank');
            modal.remove();
        };

        document.getElementById('printThermalBtnList').onclick = function() {
            if (document.getElementById('rememberPrintChoiceList').checked) {
                localStorage.setItem('pos_print_preference', 'thermal');
            }
            window.open(`thermal-print.php?id=${invoiceId}`, '_blank');
            modal.remove();
        };

        document.getElementById('printThermal2BtnList').onclick = function() {
            if (document.getElementById('rememberPrintChoiceList').checked) {
                localStorage.setItem('pos_print_preference', 'thermal2');
            }
            window.open(`thermal-print2.php?id=${invoiceId}`, '_blank');
            modal.remove();
        };

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    let deleteInvoiceId = null;
    
    function deleteInvoice(id) {
        if (!permissions.includes('Delete')) {
            alert('You do not have permission to delete invoices.');
            return;
        }
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
                const response = await fetch(`../../../../server/api/sale/sale_tax_invoice/pos-delete.php?id=${deleteInvoiceId}`, {
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

    // ── Duplicate Invoice ──────────────────────────────────────────────
    async function fetchDupCustomerBalance(customerId) {
        const el = document.getElementById('dupCustomerBalance');
        if (!customerId || !el) return;
        el.textContent = 'Loading...';
        el.style.display = 'block';
        try {
            const res = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?customer_id=${customerId}&type=summary`);
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                const balance = parseFloat(data.data[0].closing_balance || 0);
                const label = balance >= 0 ? 'Dr' : 'Cr';
                el.textContent = `Balance: ${label} ${Math.abs(balance).toFixed(3)}`;
            } else {
                el.textContent = ''; el.style.display = 'none';
            }
        } catch (e) { el.textContent = ''; el.style.display = 'none'; }
    }

    function openDuplicateModal(id, billNo, originalCustomerId, companyId) {

         // Add this at the very top:
    if (!permissions.includes('Add')) {
        alert('You do not have permission to duplicate invoices.');
        return;
    }
        duplicateInvoiceId = id;
        document.getElementById('duplicateSourceBillNo').textContent = billNo;
        document.getElementById('duplicateNewBillNoDisplay').textContent = 'Generating...';
        document.getElementById('duplicateError').textContent = '';
        document.getElementById('duplicateDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('dupCustomerSearch').value = '';
        document.getElementById('dupCustomerId').value = '';
        document.getElementById('confirmDuplicateBtn').dataset.originalCustomerId = originalCustomerId || '';
        document.getElementById('confirmDuplicateBtn').dataset.companyId = companyId || '';
        const balEl = document.getElementById('dupCustomerBalance');
        if (balEl) { balEl.textContent = ''; balEl.style.display = 'none'; }
        document.getElementById('duplicateModal').style.display = 'flex';

        if (originalCustomerId) fetchDupCustomerBalance(originalCustomerId);

        const billNoUrl = companyId
            ? `../../../../server/api/sale/sale_tax_invoice/sti-next-bill-no.php?company_id=${companyId}`
            : '../../../../server/api/sale/sale_tax_invoice/sti-next-bill-no.php';
        fetch(billNoUrl)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('duplicateNewBillNoDisplay').textContent = data.bill_no;
                    document.getElementById('confirmDuplicateBtn').dataset.billNo = data.bill_no;
                }
            })
            .catch(() => { document.getElementById('duplicateNewBillNoDisplay').textContent = 'Error'; });
    }

    document.getElementById('cancelDuplicateBtn').addEventListener('click', function () {
        document.getElementById('duplicateModal').style.display = 'none';
        duplicateInvoiceId = null;
    });

    document.getElementById('duplicateModal').addEventListener('click', function (e) {
        if (e.target === this) { this.style.display = 'none'; duplicateInvoiceId = null; }
    });

    document.getElementById('confirmDuplicateBtn').addEventListener('click', async function () {
        const previewBillNo = document.getElementById('duplicateNewBillNoDisplay').textContent;
        const errorEl = document.getElementById('duplicateError');
        if (!previewBillNo || previewBillNo === 'Generating...') {
            errorEl.textContent = 'Please wait, generating invoice number...';
            return;
        }
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Duplicating...';
        try {
            const newCustomerId = document.getElementById('dupCustomerId').value || null;
            const originalCustomerId = this.dataset.originalCustomerId || null;
            const balanceCustomerId = newCustomerId || originalCustomerId;
            let previousBalance = 0;
            if (balanceCustomerId) {
                const balRes = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?customer_id=${balanceCustomerId}&type=summary`);
                const balData = await balRes.json();
                if (balData.success && balData.data && balData.data.length > 0) {
                    previousBalance = parseFloat(balData.data[0].closing_balance || 0);
                }
            }
            const payload = {
                invoice_id:       duplicateInvoiceId,
                sale_date:        document.getElementById('duplicateDate').value || null,
                customer_id:      newCustomerId,
                previous_balance: previousBalance
            };
            const response = await fetch('../../../../server/api/sale/sale_tax_invoice/sti-duplicate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById('duplicateModal').style.display = 'none';
                duplicateInvoiceId = null;
                showDuplicateSuccess(data.bill_no, data.invoice_id);
            } else {
                errorEl.textContent = data.message;
            }
        } catch (err) {
            errorEl.textContent = 'Error: ' + err.message;
        }
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-copy"></i> Confirm Duplicate';
    });

    // Duplicate customer dropdown
    async function loadDupCustomers() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_tax_invoice/get-customers.php');
            const data = await response.json();
            if (data.success) {
                const dupOptions = document.getElementById('dupCustomerOptions');
                dupOptions.innerHTML = '';
                data.customers.forEach(c => {
                    const opt = document.createElement('div');
                    opt.className = 'dropdown-option';
                    opt.setAttribute('data-value', c.id);
                    opt.textContent = `${c.customer_code} - ${c.customer_name}`;
                    dupOptions.appendChild(opt);
                });
                initDupCustomerDropdown();
            }
        } catch (e) { console.error(e); }
    }
    loadDupCustomers();

    function initDupCustomerDropdown() {
        const searchInput      = document.getElementById('dupCustomerSearch');
        const optionsContainer = document.getElementById('dupCustomerOptions');
        const hiddenInput      = document.getElementById('dupCustomerId');

        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
            optionsContainer.style.display = 'block';
            filterDupOpts();
        });
        searchInput.addEventListener('input', filterDupOpts);

        optionsContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('dropdown-option')) {
                const customerId = e.target.getAttribute('data-value');
                hiddenInput.value = customerId;
                searchInput.value = e.target.textContent;
                optionsContainer.style.display = 'none';
                fetchDupCustomerBalance(customerId);
            }
        });

        function filterDupOpts() {
            const term = searchInput.value.toLowerCase();
            Array.from(optionsContainer.getElementsByClassName('dropdown-option')).forEach(opt => {
                opt.style.display = opt.textContent.toLowerCase().includes(term) ? 'block' : 'none';
            });
        }
    }

    // ── Duplicate Success Modal ───────────────────────────────────────
    let dupSuccessNewId = null;
    let dupSuccessTimer = null;

    function showDuplicateSuccess(billNo, newId) {
        dupSuccessNewId = newId;
        document.getElementById('dupSuccessBillNo').textContent = billNo;
        document.getElementById('duplicateSuccessModal').style.display = 'flex';
        loadInvoices(currentPage);

        let secs = 6;
        document.getElementById('dupSuccessCountdown').textContent = secs;
        clearInterval(dupSuccessTimer);
        dupSuccessTimer = setInterval(function () {
            secs--;
            document.getElementById('dupSuccessCountdown').textContent = secs;
            if (secs <= 0) { clearInterval(dupSuccessTimer); closeDupSuccessModal(); }
        }, 1000);
    }

    function closeDupSuccessModal() {
        clearInterval(dupSuccessTimer);
        document.getElementById('duplicateSuccessModal').style.display = 'none';
        dupSuccessNewId = null;
    }

    document.getElementById('dupSuccessCloseBtn').addEventListener('click', closeDupSuccessModal);

    document.getElementById('dupSuccessPrintBtn').addEventListener('click', function () {
        if (dupSuccessNewId) window.open(`invoice-print.php?id=${dupSuccessNewId}`, '_blank');
        closeDupSuccessModal();
    });

    document.getElementById('dupSuccessEditBtn').addEventListener('click', function () {
        if (dupSuccessNewId) window.location.href = `pos-add.php?edit=${dupSuccessNewId}`;
        else closeDupSuccessModal();
    });

    document.getElementById('duplicateSuccessModal').addEventListener('click', function (e) {
        if (e.target === this) closeDupSuccessModal();
    });
}