document.addEventListener('DOMContentLoaded', function () {
    // Check permissions
    fetch('../../../../server/api/auth/check-permission.php?category=Sale&form_name=Sale Order')
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

    // Load invoices from API
    async function loadInvoices(page = 1) {
        try {
            const companyFilter = document.getElementById('companyFilter').value;
            const dateFromVal = document.getElementById('dateFrom').value;
            const dateToVal = document.getElementById('dateTo').value;
            const customerVal = document.getElementById('customerFilter').value;
            const statusVal = document.getElementById('statusFilter').value;
            const orderStatusVal = document.getElementById('orderStatusFilter').value;
            const searchVal = document.getElementById('searchInput').value.trim();

            let url = `../../../../server/api/sale/sale_order/order-list.php?page=${page}&limit=10`;
            if (companyFilter) url += `&company_id=${encodeURIComponent(companyFilter)}`;
            if (dateFromVal) url += `&date_from=${encodeURIComponent(dateFromVal)}`;
            if (dateToVal) url += `&date_to=${encodeURIComponent(dateToVal)}`;
            if (customerVal) url += `&customer_id=${encodeURIComponent(customerVal)}`;
            if (statusVal) url += `&status=${encodeURIComponent(statusVal)}`;
            if (orderStatusVal) url += `&order_status=${encodeURIComponent(orderStatusVal)}`;
            if (searchVal) url += `&search=${encodeURIComponent(searchVal)}`;

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                invoices = data.invoices.map(invoice => ({
                    id: invoice.id,
                    orderStatus: invoice.status,
                    invoiceNo: invoice.bill_no,
                    date: invoice.sale_date,
                    customer: invoice.customer_name,
                    items: invoice.item_count,
                    totalAmount: parseFloat(invoice.net_amount),
                    currencySymbol: invoice.currency_symbol || '',
                    status: invoice.fulfillment_status || 'Pending'
                }));
                // Finalizing only makes sense while reviewing Drafts — the
                // checkbox column and bulk button are only useful then.
                const isDraftView = orderStatusVal === 'Draft';
                document.getElementById('selectAllHeaderCell').style.display = isDraftView ? '' : 'none';
                document.getElementById('finalizeSelectedBtn').style.display = isDraftView ? '' : 'none';
                document.getElementById('selectAllCheckbox').checked = false;
                currentPage = data.pagination.page;
                totalPages = data.pagination.pages;
                populateTable(invoices, isDraftView);
                updatePagination(data.pagination);
            } else {
                console.error('Error loading invoices:', data.message);
            }
        } catch (error) {
            console.error('Error loading invoices:', error);
        }
    }

    function populateTable(data, isDraftView) {
        invoicesTable.innerHTML = '';

        data.forEach(invoice => {
            const row = invoicesTable.insertRow();
            const isDraft = invoice.orderStatus === 'Draft';

            // Checkbox cell — only populated for Draft rows, since that's
            // the only status Finalize Selected can act on. Always present
            // so column indexes stay stable; display is kept in sync with
            // the header cell's (both hidden outside Draft view) so the
            // table doesn't end up with more body columns than header
            // columns.
            const checkboxCell = row.insertCell(0);
            checkboxCell.style.display = isDraftView ? '' : 'none';
            if (isDraft) {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'draft-row-checkbox';
                checkbox.value = invoice.id;
                checkboxCell.appendChild(checkbox);
            }

            // Format date
            const formattedDate = new Date(invoice.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Format amount with currency symbol
            const formattedAmount = `${invoice.currencySymbol} ${parseFloat(invoice.totalAmount).toFixed(2)}`;

            // Create cells
            row.insertCell(1).textContent = invoice.invoiceNo;
            row.insertCell(2).textContent = formattedDate;
            row.insertCell(3).textContent = invoice.customer || 'N/A';
            row.insertCell(4).textContent = invoice.items;
            row.insertCell(5).textContent = formattedAmount;

            // Status cell — a Draft order has no meaningful fulfillment
            // progress yet (nothing's been posted against it), so show the
            // order's own Draft status instead of the fulfillment badge.
            const statusCell = row.insertCell(6);
            const statusBadge = document.createElement('span');
            if (isDraft) {
                statusBadge.className = 'status status-overdue';
                statusBadge.textContent = 'Draft';
            } else {
                statusBadge.className = 'status ' + (invoice.status === 'Partially Fulfilled' ? 'status-pending' : 'status-overdue');
                statusBadge.textContent = invoice.status || 'Pending';
            }
            statusCell.appendChild(statusBadge);

            const actionsCell = row.insertCell(7);
            const actionButtons = document.createElement('div');
            actionButtons.className = 'action-buttons';

            // Edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-primary btn-sm';
            editBtn.innerHTML = '<i class="fas fa-edit"></i>';
            editBtn.title = 'Edit Order';
            if (permissions.includes('Edit')) {
                editBtn.addEventListener('click', () => editInvoice(invoice.id));
            } else {
                editBtn.disabled = true;
                editBtn.style.opacity = '0.5';
                editBtn.style.cursor = 'not-allowed';
            }
            actionButtons.appendChild(editBtn);

            // Convert to Invoice / View Fulfillment only apply to Posted
            // orders — a Draft hasn't been reviewed/approved yet.
            if (!isDraft) {
                const convertBtn = document.createElement('button');
                convertBtn.className = 'btn btn-warning btn-sm';
                convertBtn.innerHTML = '<i class="fas fa-exchange-alt"></i>';
                convertBtn.title = 'Convert to Sale Invoice';
                convertBtn.addEventListener('click', () => convertToInvoice(invoice.id));
                actionButtons.appendChild(convertBtn);
            }

            // Print button
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-success btn-sm';
            printBtn.innerHTML = '<i class="fas fa-print"></i>';
            printBtn.title = 'Print Order';
            printBtn.addEventListener('click', () => printInvoice(invoice.id));
            actionButtons.appendChild(printBtn);

            if (!isDraft) {
                const fulfillmentBtn = document.createElement('button');
                fulfillmentBtn.className = 'btn btn-warning btn-sm';
                fulfillmentBtn.innerHTML = '<i class="fas fa-tasks"></i>';
                fulfillmentBtn.title = 'View Fulfillment';
                fulfillmentBtn.addEventListener('click', () => viewFulfillment(invoice.id));
                actionButtons.appendChild(fulfillmentBtn);
            }

            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-danger btn-sm';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.title = 'Delete Order';
            if (permissions.includes('Delete')) {
                deleteBtn.addEventListener('click', () => deleteInvoice(invoice.id));
            } else {
                deleteBtn.disabled = true;
                deleteBtn.style.opacity = '0.5';
                deleteBtn.style.cursor = 'not-allowed';
            }
            actionButtons.appendChild(deleteBtn);

            actionsCell.appendChild(actionButtons);
        });
    }

    // Initial load
    loadInvoices();
    loadCompanies();

    // Load companies
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/sale/sale_order/get-companies.php');
            const data = await response.json();
            if (data.success) {
                const select = document.getElementById('companyFilter');
                data.companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    // Filter elements
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const companyFilterEl = document.getElementById('companyFilter');
    const customerFilter = document.getElementById('customerFilter');
    const statusFilter = document.getElementById('statusFilter');
    const orderStatusFilter = document.getElementById('orderStatusFilter');
    const searchInput = document.getElementById('searchInput');

    // Server-side filtering: every filter change triggers a fresh page-1 fetch
    let searchDebounce;
    function reload() {
        loadInvoices(1);
    }

    dateFrom.addEventListener('change', reload);
    dateTo.addEventListener('change', reload);
    companyFilterEl.addEventListener('change', reload);
    customerFilter.addEventListener('change', reload);
    statusFilter.addEventListener('change', reload);
    orderStatusFilter.addEventListener('change', reload);
    searchInput.addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(reload, 300); // debounce so typing doesn't spam the API
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
            alert('You do not have permission to add orders.');
            return;
        }
        window.location.href = 'order-add.php';
    });

    // Select-all checkbox for the Draft view's bulk-finalize column.
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    selectAllCheckbox.addEventListener('change', function () {
        invoicesTable.querySelectorAll('.draft-row-checkbox').forEach(cb => {
            cb.checked = selectAllCheckbox.checked;
        });
    });

    // Finalize Selected — bulk-posts the checked Draft orders via the same
    // order-finalize.php the drafts modal on order-add.php already uses.
    document.getElementById('finalizeSelectedBtn').addEventListener('click', async function () {
        const btn = this;
        const ids = Array.from(invoicesTable.querySelectorAll('.draft-row-checkbox:checked'))
            .map(cb => parseInt(cb.value, 10))
            .filter(id => id > 0);
        if (ids.length === 0) {
            alert('Select at least one draft order to finalize.');
            return;
        }
        if (!confirm(`Finalize ${ids.length} selected order(s) as Posted? This treats the review as approved.`)) {
            return;
        }
        btn.disabled = true;
        try {
            const response = await fetch('../../../../server/api/sale/sale_order/order-finalize.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids })
            });
            const data = await response.json();
            if (data.success) {
                if (data.finalized === 0) {
                    alert('None of the selected orders could be finalized — a draft you created yourself needs another user to review it.');
                } else {
                    alert(`Finalized ${data.finalized} order(s).`);
                }
                loadInvoices(currentPage);
            } else {
                alert('Error: ' + (data.message || 'Could not finalize orders.'));
            }
        } catch (error) {
            alert('Network error — could not finalize orders.');
        } finally {
            btn.disabled = false;
        }
    });

    // Action functions
    function editInvoice(id) {
        if (!permissions.includes('Edit')) {
            alert('You do not have permission to edit orders.');
            return;
        }
        window.location.href = `order-add.php?edit=${id}`;
    }

    function convertToInvoice(id) {
        if (confirm('Are you sure you want to convert this sale order to a sale invoice?')) {
            fetch('../../../../server/api/sale/sale_order/convert-to-invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Sale order converted to invoice successfully!');
                    loadInvoices(currentPage);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error converting order: ' + error.message);
            });
        }
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
                <p>Choose how you want to print the order:</p>
                <div class="modal-actions" style="flex-direction: column; gap: 10px;">
                    <button class="btn btn-primary" id="printFullBtnList" style="width: 100%;">
                        <i class="fas fa-file-invoice"></i> Print Full Order
                    </button>
                    <button class="btn btn-secondary" id="printThermalBtnList" style="width: 100%;">
                        <i class="fas fa-receipt"></i> Print Thermal Order
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
            alert('You do not have permission to delete orders.');
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

    document.getElementById('closeFulfillmentBtn').addEventListener('click', function() {
        document.getElementById('fulfillmentModal').style.display = 'none';
    });

    // View fulfillment function
    async function viewFulfillment(orderId) {
        document.getElementById('fulfillmentModal').style.display = 'flex';
        const tbody = document.getElementById('fulfillmentTableBody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">Loading...</td></tr>';

        try {
            const response = await fetch(`../../../../server/api/sale/sale_order/get-order-fulfillment.php?order_id=${orderId}`);
            const data = await response.json();

            if (data.success && data.fulfillment.length > 0) {
                tbody.innerHTML = '';
                data.fulfillment.forEach(item => {
                    const pct = parseFloat(item.fulfillment_percent);
                    const statusClass = pct >= 100 ? 'status-paid' : pct > 0 ? 'status-pending' : 'status-overdue';
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);">${item.product_name}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${parseFloat(item.ordered_qty).toFixed(2)} ${item.uom_name}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${parseFloat(item.served_qty).toFixed(2)} ${item.uom_name}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${parseFloat(item.remaining_qty).toFixed(2)} ${item.uom_name}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"><span class="status ${statusClass}">${pct.toFixed(0)}%</span></td>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No fulfillment data available</td></tr>';
            }
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--error);">Error loading fulfillment data</td></tr>';
        }
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        if (deleteInvoiceId) {
            try {
                const response = await fetch(`../../../../server/api/sale/sale_order/order-delete.php?id=${deleteInvoiceId}`, {
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
}
