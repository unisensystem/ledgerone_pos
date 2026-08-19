document.addEventListener('DOMContentLoaded', function () {
    let invoices = [];
    let currentPage = 1;
    let totalPages = 1;
    const invoicesTable = document.getElementById('invoicesTable').getElementsByTagName('tbody')[0];
    let duplicateInvoiceId = null;

    // ===================== Action Buttons Visibility (Show/Hide) =====================
    const ACTION_VISIBILITY_KEY = 'purchaseInvoiceActionVisibility';

    const actionDefs = [
        { key: 'print',    label: 'Print' },
        { key: 'medPrint', label: 'Med Print' }
    ];

    function getActionVisibility() {
        let saved = {};
        try {
            saved = JSON.parse(localStorage.getItem(ACTION_VISIBILITY_KEY)) || {};
        } catch (e) {
            saved = {};
        }
        const config = {};
        actionDefs.forEach(def => {
            config[def.key] = saved.hasOwnProperty(def.key) ? !!saved[def.key] : true;
        });
        return config;
    }

    function saveActionVisibility(config) {
        try {
            localStorage.setItem(ACTION_VISIBILITY_KEY, JSON.stringify(config));
        } catch (e) {
            console.error('Error saving action visibility:', e);
        }
    }

    let actionVisibility = getActionVisibility();

    function injectActionVisibilityStyles() {
        if (document.getElementById('actionVisibilityStyles')) return;
        const style = document.createElement('style');
        style.id = 'actionVisibilityStyles';
        style.textContent = `
            .action-visibility-bar {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 14px;
                padding: 8px 14px;
                border-top: 1px solid #e5e7eb;
                background: #fafafa;
                font-size: 12px;
            }
            .action-visibility-bar .avb-label {
                font-size: 11px;
                font-weight: 600;
                color: #9ca3af;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                margin-right: 2px;
            }
            .action-visibility-bar .avb-item {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                user-select: none;
                color: #4b5563;
                line-height: 1;
            }
            .action-visibility-bar .avb-item input {
                width: 13px;
                height: 13px;
                margin: 0;
                cursor: pointer;
                accent-color: #2563eb;
            }
            .action-visibility-bar .avb-item span {
                font-size: 12px;
            }
        `;
        document.head.appendChild(style);
    }

    function createActionVisibilityControl() {
        injectActionVisibilityStyles();

        const table = document.getElementById('invoicesTable');
        if (!table || document.getElementById('actionVisibilityBar')) return;

        const bar = document.createElement('div');
        bar.className = 'action-visibility-bar';
        bar.id = 'actionVisibilityBar';

        const label = document.createElement('span');
        label.className = 'avb-label';
        label.textContent = 'Show/Hide:';
        bar.appendChild(label);

        actionDefs.forEach(def => {
            const item = document.createElement('label');
            item.className = 'avb-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = actionVisibility[def.key];
            checkbox.dataset.actionKey = def.key;
            checkbox.addEventListener('change', function () {
                actionVisibility[def.key] = this.checked;
                saveActionVisibility(actionVisibility);
                populateTable(invoices);
            });

            const span = document.createElement('span');
            span.textContent = def.label;

            item.appendChild(checkbox);
            item.appendChild(span);
            bar.appendChild(item);
        });

        // Place the bar directly below the table
        const tableParent = table.closest('.table-responsive') || table.parentElement;
        tableParent.insertAdjacentElement('afterend', bar);
    }
    // =================== End Action Buttons Visibility ===================

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
    async function loadSuppliers(companyId = '') {
        try {
            const url = companyId
                ? `../../../../server/api/purchase/purchase_invoice/get-suppliers.php?company_id=${companyId}`
                : '../../../../server/api/purchase/purchase_invoice/get-suppliers.php';
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                // Filter dropdown
                const supplierFilterOptions = document.getElementById('supplierFilterOptions');
                if (supplierFilterOptions) {
                    supplierFilterOptions.innerHTML = '<div class="dropdown-option" data-value="">All Suppliers</div>';
                    data.suppliers.forEach(supplier => {
                        const option = document.createElement('div');
                        option.className = 'dropdown-option';
                        option.setAttribute('data-value', supplier.id);
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
            const params = new URLSearchParams({ page, limit: 10 });
            if (companyFilter.value)              params.set('company_id',           companyFilter.value);
            if (supplierFilter.value)             params.set('supplier_id',          supplierFilter.value);
            if (dateFrom.value)                   params.set('date_from',            dateFrom.value);
            if (dateTo.value)                     params.set('date_to',              dateTo.value);
            if (statusFilter.value)               params.set('status',               statusFilter.value);
            if (searchInput.value.trim())         params.set('search',               searchInput.value.trim());
            if (supplierInvoiceNoFilter.value.trim()) params.set('supplier_invoice_no', supplierInvoiceNoFilter.value.trim());
            const response = await fetch(`../../../../server/api/purchase/purchase_invoice/purchase-list.php?${params}`);
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
                    paidAmount: parseFloat(invoice.paid_amount || 0),
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

            // Payment status badge
            const statusCell = row.insertCell(6);
            const paid = invoice.paidAmount;
            const total = invoice.totalAmount;
            let statusLabel, statusClass;
            if (paid >= total && total > 0) {
                statusLabel = 'Paid'; statusClass = 'status-paid';
            } else if (paid > 0) {
                statusLabel = 'Partial'; statusClass = 'status-partial';
            } else {
                statusLabel = 'Pending'; statusClass = 'status-pending';
            }
            statusCell.innerHTML = `<span class="payment-status ${statusClass}">${statusLabel}</span>`;

            const actionsCell = row.insertCell(7);
            const actionButtons = document.createElement('div');
            actionButtons.className = 'action-buttons';

            // Edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-primary btn-sm';
            editBtn.innerHTML = '<i class="fas fa-edit"></i>';
            editBtn.title = 'Edit Invoice';
            editBtn.addEventListener('click', () => editInvoice(invoice.id));
            actionButtons.appendChild(editBtn);

            // Duplicate button
            const dupBtn = document.createElement('button');
            dupBtn.className = 'btn btn-secondary btn-sm';
            dupBtn.innerHTML = '<i class="fas fa-copy"></i>';
            dupBtn.title = 'Duplicate Invoice';
            dupBtn.addEventListener('click', () => openDuplicateModal(invoice.id, invoice.invoiceNo, invoice.supplierId, invoice.companyId));
            actionButtons.appendChild(dupBtn);

            // Print button
            if (actionVisibility.print) {
                const printBtn = document.createElement('button');
                printBtn.className = 'btn btn-success btn-sm';
                printBtn.innerHTML = '<i class="fas fa-print"></i>';
                printBtn.title = 'Print Invoice';
                printBtn.addEventListener('click', () => printInvoice(invoice.id));
                actionButtons.appendChild(printBtn);
            }

            // Med Print button
            if (actionVisibility.medPrint) {
                const medPrintBtn = document.createElement('button');
                medPrintBtn.className = 'btn btn-info btn-sm';
                medPrintBtn.innerHTML = '<i class="fas fa-file-medical-alt"></i>';
                medPrintBtn.title = 'Med Print';
                medPrintBtn.addEventListener('click', () => medPrintInvoice(invoice.id));
                actionButtons.appendChild(medPrintBtn);
            }

            // PDF button
            const pdfBtn = document.createElement('button');
            pdfBtn.className = 'btn btn-warning btn-sm';
            pdfBtn.innerHTML = '<i class="fas fa-file-pdf"></i>';
            pdfBtn.title = 'Download PDF';
            pdfBtn.addEventListener('click', () => window.open(`invoice-print.php?id=${invoice.id}&pdf=1`, '_blank'));
            actionButtons.appendChild(pdfBtn);

            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-danger btn-sm';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.title = 'Delete Invoice';
            deleteBtn.addEventListener('click', () => deleteInvoice(invoice.id));
            actionButtons.appendChild(deleteBtn);

            actionsCell.appendChild(actionButtons);
        });
    }

    // Filter elements
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const companyFilter = document.getElementById('companyFilter');
    const supplierFilter = document.getElementById('supplierFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const supplierInvoiceNoFilter = document.getElementById('supplierInvoiceNoFilter');

    function applyFilters() { loadInvoices(1); }

    // Initial load
    createActionVisibilityControl();
    loadCompanies();
    loadSuppliers();
    loadInvoices();

    // Add event listeners for filters
    dateFrom.addEventListener('change', applyFilters);
    dateTo.addEventListener('change', applyFilters);
    companyFilter.addEventListener('change', function() {
        // Reset supplier filter and reload suppliers for selected company
        document.getElementById('supplierFilterSearch').value = '';
        supplierFilter.value = '';
        loadSuppliers(this.value);
        applyFilters();
    });
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

    function medPrintInvoice(id) {
        window.open(`med-invoice-print.php?id=${id}`, '_blank');
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
    function openDuplicateModal(id, currentBillNo, originalSupplierId, invoiceCompanyId) {
        duplicateInvoiceId = id;
        document.getElementById('duplicateSourceBillNo').textContent = currentBillNo;
        document.getElementById('duplicateNewBillNoDisplay').textContent = 'Generating...';
        document.getElementById('duplicateError').textContent = '';
        document.getElementById('duplicateDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('dupSupplierSearch').value = '';
        document.getElementById('dupSupplierId').value = '';
        document.getElementById('confirmDuplicateBtn').dataset.originalSupplierId = originalSupplierId || '';
        document.getElementById('confirmDuplicateBtn').dataset.invoiceCompanyId = invoiceCompanyId || '';
        const dupBalEl = document.getElementById('dupSupplierBalance');
        if (dupBalEl) { dupBalEl.textContent = ''; dupBalEl.style.display = 'none'; }
        document.getElementById('duplicateModal').style.display = 'flex';

        if (originalSupplierId) fetchDupSupplierBalance(originalSupplierId);

        fetch(`../../../../server/api/purchase/purchase_invoice/purchase-next-bill-no.php?company_id=${invoiceCompanyId || ''}`)
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
                showDuplicateSuccess(data.bill_no || document.getElementById('duplicateNewBillNoDisplay').textContent, data.invoice_id || null);
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
        dupSuccessTimer = setInterval(function() {
            secs--;
            document.getElementById('dupSuccessCountdown').textContent = secs;
            if (secs <= 0) {
                clearInterval(dupSuccessTimer);
                closeDupSuccessModal();
            }
        }, 1000);
    }

    function closeDupSuccessModal() {
        clearInterval(dupSuccessTimer);
        document.getElementById('duplicateSuccessModal').style.display = 'none';
        dupSuccessNewId = null;
    }

    document.getElementById('dupSuccessCloseBtn').addEventListener('click', closeDupSuccessModal);

    document.getElementById('dupSuccessPrintBtn').addEventListener('click', function() {
        if (dupSuccessNewId) {
            window.open(`invoice-print.php?id=${dupSuccessNewId}`, '_blank');
        }
        closeDupSuccessModal();
    });

    document.getElementById('dupSuccessEditBtn').addEventListener('click', function() {
        if (dupSuccessNewId) {
            window.location.href = `purchase-add.php?edit=${dupSuccessNewId}`;
        } else {
            closeDupSuccessModal();
        }
    });

    document.getElementById('duplicateSuccessModal').addEventListener('click', function(e) {
        if (e.target === this) closeDupSuccessModal();
    });

});