document.addEventListener('DOMContentLoaded', function () {
    // Check permissions
    fetch('../../../../server/api/auth/check-permission.php?category=Sale&form_name=Sale Return')
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

// Turns a native <select> into a searchable dropdown.
// The original <select> stays in the DOM (hidden), so existing code that reads
// select.value or listens to the 'change' event keeps working.
function initSearchableSelect(selectEl) {
    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'searchable-select-input';
    input.autocomplete = 'off';
    input.placeholder = 'Search...';

    const list = document.createElement('div');
    list.className = 'searchable-select-list';

    selectEl.parentNode.insertBefore(wrapper, selectEl);
    wrapper.appendChild(input);
    wrapper.appendChild(list);
    wrapper.appendChild(selectEl);
    selectEl.style.display = 'none';

    function getSelectedLabel() {
        const opt = selectEl.options[selectEl.selectedIndex];
        return opt && opt.value !== '' ? opt.textContent : '';
    }

    function renderList(filterText = '') {
        list.innerHTML = '';
        const term = filterText.trim().toLowerCase();
        let count = 0;

        Array.from(selectEl.options).forEach(opt => {
            if (term && !opt.textContent.toLowerCase().includes(term)) return;
            const item = document.createElement('div');
            item.className = 'searchable-select-item';
            if (opt.value === selectEl.value) item.classList.add('selected');
            item.textContent = opt.textContent;
            item.dataset.value = opt.value;
            // mousedown fires before input blur, so the selection is not lost
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectValue(opt.value);
            });
            list.appendChild(item);
            count++;
        });

        if (count === 0) {
            const empty = document.createElement('div');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'No results found';
            list.appendChild(empty);
        }
    }

    function openList() {
        renderList(input.value === getSelectedLabel() ? '' : input.value);
        list.style.display = 'block';
    }

    function closeList() {
        list.style.display = 'none';
        input.value = getSelectedLabel();
    }

    function selectValue(value) {
        const changed = selectEl.value !== value;
        selectEl.value = value;
        closeList();
        input.blur();
        if (changed) {
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    input.addEventListener('focus', function () {
        input.select();
        openList();
    });

    input.addEventListener('input', function () {
        renderList(input.value);
        list.style.display = 'block';
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeList();
            input.blur();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const first = list.querySelector('.searchable-select-item');
            if (first) selectValue(first.dataset.value);
        }
    });

    input.addEventListener('blur', closeList);

    // Call this after the <select> options are rebuilt
    function refresh() {
        input.placeholder = selectEl.options.length ? selectEl.options[0].textContent : 'Search...';
        input.value = getSelectedLabel();
    }

    refresh();
    return { refresh };
}

function initializeListPage(permissions) {
    let invoices = [];
    let currentPage = 1;
    let totalPages = 1;
    const invoicesTable = document.getElementById('invoicesTable').getElementsByTagName('tbody')[0];
    const customerSearch = initSearchableSelect(document.getElementById('customerFilter'));

    // Load companies
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_return/get-companies.php');
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

    // Load customers for filter dropdown
    async function loadCustomers(companyId = '') {
        try {
            const url = companyId
                ? `../../../../server/api/sale/sale_return/get-customers.php?company_id=${companyId}`
                : '../../../../server/api/sale/sale_return/get-customers.php';
            const response = await fetch(url);
            const data = await response.json();
            if (data.success) {
                const customerFilter = document.getElementById('customerFilter');
                customerFilter.innerHTML = '<option value="">All Customers</option>';
                data.customers.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.id;
                    option.textContent = c.customer_name;
                    customerFilter.appendChild(option);
                });
                customerSearch.refresh();
            }
        } catch (error) {
            console.error('Error loading customers:', error);
        }
    }

    // Load invoices from API
    async function loadInvoices(page = 1) {
        try {
            const companyId = document.getElementById('companyFilter').value;
            const params = new URLSearchParams({ page, limit: 10 });
            if (companyId) params.append('company_id', companyId);
            const response = await fetch(`../../../../server/api/sale/sale_return/return-list.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                invoices = data.invoices.map(invoice => ({
                    id: invoice.id,
                    invoiceNo: invoice.bill_no,
                    date: invoice.sale_date,
                    customer: invoice.customer_name,
                    customer_id: invoice.customer_id,
                    company_id: invoice.company_id,
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

    // Update summary boxes (count + total amount of the rows currently shown)
    function updateSummary(data) {
        const countEl = document.getElementById('summaryInvoiceCount');
        const amountEl = document.getElementById('summaryTotalAmount');
        if (!countEl || !amountEl) return;

        const total = data.reduce((sum, invoice) => sum + (parseFloat(invoice.totalAmount) || 0), 0);
        const symbols = new Set(data.map(invoice => invoice.currencySymbol).filter(Boolean));
        // Show the symbol only when all rows share the same currency
        const symbol = symbols.size === 1 ? [...symbols][0] + ' ' : '';

        countEl.textContent = data.length;
        amountEl.textContent = symbol + total.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function populateTable(data) {
        invoicesTable.innerHTML = '';
        updateSummary(data);

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
            row.insertCell(2).textContent = invoice.customer || 'N/A';
            row.insertCell(3).textContent = invoice.items;
            row.insertCell(4).textContent = formattedAmount;

            const actionsCell = row.insertCell(5);
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

            actionButtons.appendChild(editBtn);
            actionButtons.appendChild(printBtn);
            actionButtons.appendChild(deleteBtn);

            actionsCell.appendChild(actionButtons);
        });
    }

    // Initial load
    loadCompanies();
    loadCustomers();
    loadInvoices();

    // Filter functionality
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const companyFilter = document.getElementById('companyFilter');
    const customerFilter = document.getElementById('customerFilter');
    const statusFilter = document.getElementById('statusFilter');
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

        // Customer filter
        if (customerFilter.value) {
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.customer_id == customerFilter.value
            );
        }

        // Search filter
        if (searchInput.value) {
            const searchTerm = searchInput.value.toLowerCase();
            filteredInvoices = filteredInvoices.filter(invoice =>
                invoice.invoiceNo.toLowerCase().includes(searchTerm) ||
                (invoice.customer && invoice.customer.toLowerCase().includes(searchTerm))
            );
        }

        populateTable(filteredInvoices);
    }

    // Add event listeners for filters
    dateFrom.addEventListener('change', applyFilters);
    dateTo.addEventListener('change', applyFilters);
    companyFilter.addEventListener('change', () => { loadCustomers(companyFilter.value); loadInvoices(1); });
    customerFilter.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);

    // ✅ REFRESH BUTTON - Clear all filters and reload data
    document.getElementById('refreshBtn').addEventListener('click', function() {
        // Clear all filter values
        dateFrom.value = '';
        dateTo.value = '';
        companyFilter.value = '';
        customerFilter.value = '';
        statusFilter.value = '';
        searchInput.value = '';
        
        // Refresh customer dropdown
        customerSearch.refresh();
        
        // Reload data from page 1
        currentPage = 1;
        loadCompanies();
        loadCustomers();
        loadInvoices(1);
    });

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
        if (!permissions.includes('Add')) {
            alert('You do not have permission to add returns.');
            return;
        }
        window.location.href = 'counter-return.php';
    });

    // Action functions
    function editInvoice(id) {
        if (!permissions.includes('Edit')) {
            alert('You do not have permission to edit returns.');
            return;
        }
        window.location.href = `counter-return.php?edit=${id}`;
    }

    function printInvoice(id) {
        showPrintOptions(id);
    }
    
    // Print options modal
    function showPrintOptions(invoiceId) {
        const savedChoice = localStorage.getItem('pos_print_preference');
        if (savedChoice) {
            const url = savedChoice === 'full' ? `invoice-print.php?id=${invoiceId}` : `thermal-print.php?id=${invoiceId}`;
            window.open(url, '_blank');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <h3 class="modal-title">Select Print Format</h3>
                <p>Choose how you want to print the return:</p>
                <div class="modal-actions" style="flex-direction: column; gap: 10px;">
                    <button class="btn btn-primary" id="printFullBtnList" style="width: 100%;">
                        <i class="fas fa-file-invoice"></i> Print Full Return
                    </button>
                    <button class="btn btn-secondary" id="printThermalBtnList" style="width: 100%;">
                        <i class="fas fa-receipt"></i> Print Thermal Return
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
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    let deleteInvoiceId = null;
    
    function deleteInvoice(id) {
        if (!permissions.includes('Delete')) {
            alert('You do not have permission to delete returns.');
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
                const response = await fetch(`../../../../server/api/sale/sale_return/return-delete.php?id=${deleteInvoiceId}`, {
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
}