// Delivery Chalan - Custom Logic
// Handles: Sale Invoice search/fetch, Delivery Date, Save to delivery_chalan table

document.addEventListener('DOMContentLoaded', function () {
    // Set today as default delivery date
    const deliveryDateEl = document.getElementById('deliveryDate');
    if (deliveryDateEl) deliveryDateEl.value = new Date().toISOString().split('T')[0];

    // Load sale invoices for dropdown
    loadSaleInvoices();
    
    // Load projects
    loadProjects();

    // Override save to use delivery_chalan API
    overrideSaveInvoice();
});

// Load posted sale invoices
async function loadSaleInvoices() {
    try {
        const response = await fetch('../../../../server/api/sale/delivery_chalan/get-sale-invoices.php');
        const data = await response.json();
        if (!data.success) return;

        const container = document.getElementById('saleInvoiceOptions');
        if (!container) return;
        container.innerHTML = '';

        data.invoices.forEach(inv => {
            const div = document.createElement('div');
            div.className = 'dropdown-option';
            div.setAttribute('data-value', inv.id);
            div.textContent = `${inv.bill_no} - ${inv.customer_name} - ${inv.sale_date}`;
            container.appendChild(div);
        });

        initSaleInvoiceDropdown();
    } catch (e) {
        console.error('Error loading sale invoices:', e);
    }
}

function initSaleInvoiceDropdown() {
    const searchInput = document.getElementById('saleInvoiceSearch');
    const optionsContainer = document.getElementById('saleInvoiceOptions');
    const hiddenInput = document.getElementById('saleInvoiceId');
    if (!searchInput || !optionsContainer || !hiddenInput) return;

    searchInput.addEventListener('click', function (e) {
        e.stopPropagation();
        optionsContainer.style.display = 'block';
        filterOpts();
    });
    searchInput.addEventListener('focus', function () {
        optionsContainer.style.display = 'block';
        filterOpts();
    });
    searchInput.addEventListener('input', filterOpts);

    optionsContainer.addEventListener('click', function (e) {
        const opt = e.target.closest('.dropdown-option');
        if (!opt) return;
        hiddenInput.value = opt.getAttribute('data-value');
        searchInput.value = opt.textContent;
        optionsContainer.style.display = 'none';
        loadSaleInvoiceItems(hiddenInput.value);
    });

    document.addEventListener('click', function () {
        optionsContainer.style.display = 'none';
    });

    function filterOpts() {
        const term = searchInput.value.toLowerCase();
        optionsContainer.querySelectorAll('.dropdown-option').forEach(opt => {
            opt.style.display = opt.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    }
}

// Load projects dropdown
async function loadProjects() {
    try {
        const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const data = await response.json();
        if (data.success) {
            const projectSelect = document.getElementById('project');
            if (projectSelect) {
                projectSelect.innerHTML = '<option value="">Select Project</option>';
                data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    const label = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
                    option.textContent = label;
                    projectSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Fetch sale invoice items and populate table
async function loadSaleInvoiceItems(invoiceId) {
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${invoiceId}`);
        const data = await response.json();
        if (!data.success) return;

        const invoice = data.invoice;

        // Auto-fill customer
        const customerSearch = document.getElementById('customerCodeSearch');
        const customerCode = document.getElementById('customerCode');
        if (customerSearch) customerSearch.value = `${invoice.customer_code} - ${invoice.customer_name}`;
        if (customerCode) customerCode.value = invoice.customer_id;

        // Auto-fill branch
        const branchSearch = document.getElementById('branchSearch');
        const branch = document.getElementById('branch');
        if (branchSearch) branchSearch.value = invoice.branch_name || '';
        if (branch) branch.value = invoice.branch_id;

        // Auto-fill company
        const companySearch = document.getElementById('companySearch');
        const company = document.getElementById('company');
        if (companySearch) companySearch.value = invoice.company_name || '';
        if (company) company.value = invoice.company_id || '';

        // Auto-fill currency
        const currencyEl = document.getElementById('currency');
        if (currencyEl) currencyEl.value = invoice.currency_id;
        
        // Auto-fill project
        const projectEl = document.getElementById('project');
        if (projectEl && invoice.project_id) projectEl.value = invoice.project_id;

        // Clear existing rows and add items
        const tbody = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
        while (tbody.rows.length > 0) tbody.deleteRow(0);

        // Group items by product (parent only)
        const parentItems = data.items.filter(i => !i.parent_row_id);
        for (const item of parentItems) {
            await addRowDynamic();
            const lastRow = tbody.rows[tbody.rows.length - 1];

            // Set product
            const searchInput = lastRow.cells[1].querySelector('.search-input');
            const codeInput = lastRow.cells[1].querySelector('.item-code');
            if (searchInput) searchInput.value = item.product_name;
            if (codeInput) codeInput.value = item.product_id;

            lastRow.dataset.productId = item.product_id;
            lastRow.dataset.stockAffects = item.stock_affects || 1;
            lastRow.dataset.invoiceAffects = item.invoice_affects || 1;

            // Set quantity in unit input
            const unitInput = lastRow.querySelector(`.unit-input[data-unit-id="${item.uom_id}"]`)
                || lastRow.querySelector('.unit-input');
            if (unitInput) unitInput.value = item.quantity;

            // Set hidden price/amounts (needed for save)
            const priceInput = lastRow.querySelector('.price-cell input');
            const grossInput = lastRow.querySelector('.gross-cell input');
            const netInput = lastRow.querySelector('.net-cell input');
            if (priceInput) priceInput.value = item.sale_price || 0;
            if (grossInput) grossInput.value = item.gross_amount || 0;
            if (netInput) netInput.value = item.net_amount || 0;
        }

        if (typeof updateInvoiceSummary === 'function') updateInvoiceSummary();

    } catch (e) {
        console.error('Error loading invoice items:', e);
    }
}

// Override saveInvoice to post to delivery_chalan API
function overrideSaveInvoice() {
    const origSave = window.saveInvoice;
    window.saveInvoice = function (status = 'Posted') {
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }

        const deliveryDate = document.getElementById('deliveryDate')?.value;
        if (!deliveryDate) {
            alert('Please select a Delivery Date.');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Invoice'; }
            return;
        }

        const tbody = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
        const items = [];

        for (const row of tbody.rows) {
            if (row.classList.contains('child-row')) continue;
            const productId = row.cells[1].querySelector('.item-code')?.value;
            if (!productId) continue;

            const unitInputs = row.querySelectorAll('.unit-input');
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                if (qty > 0) {
                    items.push({
                        productId: productId,
                        uomId: input.dataset.unitId,
                        quantity: qty,
                        stockAffects: parseInt(row.dataset.stockAffects) || 1
                    });
                }
            });
        }

        if (items.length === 0) {
            alert('Please add at least one item.');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Invoice'; }
            return;
        }

        const formData = {
            saleInvoiceId: document.getElementById('saleInvoiceId')?.value || null,
            deliveryDate: deliveryDate,
            customerId: document.getElementById('customerCode')?.value,
            companyId: document.getElementById('company')?.value || null,
            branchId: document.getElementById('branch')?.value,
            currencyId: document.getElementById('currency')?.value,
            projectId: document.getElementById('project')?.value || null,
            salesOfficerId: document.getElementById('salesOfficer')?.value || null,
            supplierManId: document.getElementById('supplierMan')?.value || null,
            biltyNo: document.getElementById('biltyNo')?.value || null,
            transportName: document.getElementById('transportName')?.value || null,
            remarks: document.getElementById('remarks')?.value || null,
            status: status,
            items: items
        };

        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('edit');
        const isEdit = editId !== null;
        if (isEdit) formData.chalan_id = editId;

        fetch(isEdit
            ? '../../../../server/api/sale/delivery_chalan/dc-edit.php'
            : '../../../../server/api/sale/delivery_chalan/dc-add.php',
            {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.lastInvoiceId = data.chalan_id;
                    document.getElementById('successModal').style.display = 'flex';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(e => alert('Error: ' + e.message))
            .finally(() => {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Invoice'; }
            });
    };
}
