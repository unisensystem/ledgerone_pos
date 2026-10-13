// Vouchers data and pagination
let vouchers = [];
let currentPage = 1;
let totalPages = 1;
let itemsPerPage = 10;
let currentPaymentType = 'supplier';
let suppliers = [];
let customers = [];
let companies = [];
let selectedSupplierCode = '';

// Fetch suppliers/customers for filter
function fetchFilterEntities() {
    const apiUrl = currentPaymentType === 'customer' 
        ? '../../../../server/api/vouchers/payment_voucher/get-customers.php'
        : '../../../../server/api/vouchers/payment_voucher/get-suppliers.php';
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (currentPaymentType === 'customer') {
                    customers = data.data.map(c => ({ code: c.customer_code, name: c.customer_name }));
                    setupSearchableFilter(customers);
                } else {
                    suppliers = data.data.map(s => ({ code: s.supplier_code, name: s.supplier_name }));
                    setupSearchableFilter(suppliers);
                }
            }
        })
        .catch(error => console.error('Error fetching entities:', error));
}

function setupSearchableFilter(entities) {
    const input = document.getElementById('supplierFilter');
    const dropdown = document.getElementById('supplierFilterDropdown');
    let highlightedIndex = -1;

    input.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        dropdown.innerHTML = '';
        highlightedIndex = -1;
        selectedSupplierCode = '';

        const filtered = searchTerm.length > 0 
            ? entities.filter(e => 
                e.code.toLowerCase().includes(searchTerm) || 
                e.name.toLowerCase().includes(searchTerm)
            )
            : entities;

        if (filtered.length > 0) {
            filtered.slice(0, 10).forEach(e => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                option.innerHTML = `<strong>${e.code}</strong> - ${e.name}`;
                option.dataset.code = e.code;
                option.addEventListener('click', function() {
                    input.value = `${this.dataset.code} - ${e.name}`;
                    selectedSupplierCode = this.dataset.code;
                    dropdown.classList.remove('show');
                });
                dropdown.appendChild(option);
            });
            dropdown.classList.add('show');
        } else {
            dropdown.classList.remove('show');
        }
    });

    input.addEventListener('focus', function() {
        if (this.value === '') {
            this.dispatchEvent(new Event('input'));
        }
    });

    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.dropdown-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            updateHighlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            updateHighlight(items);
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            items[highlightedIndex].click();
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('show');
        }
    });

    function updateHighlight(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === highlightedIndex);
        });
    }

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

// Payment type toggle functionality
const paymentTypeToggles = document.querySelectorAll('input[name="paymentType"]');
paymentTypeToggles.forEach(toggle => {
    toggle.addEventListener('change', function() {
        currentPaymentType = this.value;
        const label = document.getElementById('supplierFilterLabel');
        const input = document.getElementById('supplierFilter');
        
        if (this.value === 'customer') {
            label.textContent = 'Customer';
            input.placeholder = 'Search customer...';
        } else {
            label.textContent = 'Supplier';
            input.placeholder = 'Search supplier...';
        }
        
        input.value = '';
        selectedSupplierCode = '';
        document.getElementById('supplierFilterDropdown').innerHTML = '';
        fetchFilterEntities();
        fetchVouchers(1);
    });
});

// Initial fetch
fetchFilterEntities();

// Fetch companies
fetch('../../../../server/api/vouchers/payment_voucher/get-companies.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            companies = data.data;
            const companyFilter = document.getElementById('companyFilter');
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companyFilter.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching companies:', error));

// Fetch projects for filter
fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const projectFilter = document.getElementById('projectFilter');
            data.data.forEach(p => {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = `${p.project_code} - ${p.project_name}`;
                projectFilter.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching projects:', error));

// Fetch vouchers from API with pagination
function fetchVouchers(page = 1) {
    let url = `../../../../server/api/vouchers/payment_voucher/payment-list.php?page=${page}&limit=${itemsPerPage}&payment_type=${currentPaymentType}`;
    
    // Add filters
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const searchTerm = document.getElementById('searchInput').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const companyFilter = document.getElementById('companyFilter').value;
    const projectFilter = document.getElementById('projectFilter').value;
    
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (statusFilter) url += `&status=${statusFilter}`;
    if (selectedSupplierCode) url += `&supplier_code=${selectedSupplierCode}`;
    if (companyFilter) url += `&company_id=${companyFilter}`;
    if (projectFilter) url += `&project_id=${projectFilter}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                vouchers = data.data.map(voucher => ({
    id: voucher.id,
    voucherNumber: voucher.voucher_number,
    date: voucher.voucher_date,
    supplier: voucher.supplier_name,
    supplierCode: voucher.supplier_code,
    billNo: voucher.bill_no || 'N/A',
    chequeNo: voucher.cheque_no || 'N/A',   // ADD
    amount: parseFloat(voucher.amount),
                    currencySymbol: voucher.currency_symbol || '$',
                    paymentMethod: voucher.payment_method,
                    projectName: voucher.project_name || '-',
                    companyId: voucher.company_id,
                    status: 'posted'
                }));
                
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                
                renderVouchers(vouchers);
                updatePagination(data.pagination);
                renderSummary(data.summary);
            }
        })
        .catch(error => console.error('Error fetching vouchers:', error));
}

// Initial fetch
fetchVouchers(1);

// Populate table with voucher data
const tableBody = document.getElementById('vouchersTableBody');

function renderVouchers(vouchersToRender) {
    tableBody.innerHTML = '';

    vouchersToRender.forEach(voucher => {
        const row = document.createElement('tr');

        // Format date
        const dateObj = new Date(voucher.date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        // Format amount with currency symbol
        const formattedAmount = `${voucher.currencySymbol}${parseFloat(voucher.amount).toFixed(2)}`;

        // Status badge
        let statusClass = '';
        let statusText = '';

        switch (voucher.status) {
            case 'posted':
                statusClass = 'status-posted';
                statusText = 'Posted';
                break;
            case 'pending':
                statusClass = 'status-pending';
                statusText = 'Pending';
                break;
            case 'cancelled':
                statusClass = 'status-cancelled';
                statusText = 'Cancelled';
                break;
        }

        row.innerHTML = `
                    <td>${voucher.voucherNumber}</td>
                    <td>${formattedDate}</td>
                    <td>${voucher.supplier}</td>
                    <td>${voucher.billNo}</td>
                    <td>${voucher.chequeNo}</td>
                    <td>${voucher.projectName}</td>
                    <td class="amount">${formattedAmount}</td>
                    <td>${voucher.paymentMethod}</td>
                    <td><span class="status ${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="actions">
                            <button class="action-btn edit" title="Edit" data-id="${voucher.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn print" title="Print" data-id="${voucher.id}">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="action-btn pdf" title="Download PDF" style="background:#ffc107;color:#333;" data-id="${voucher.id}">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="action-btn delete" title="Delete" data-id="${voucher.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
    });

    // Add event listeners to action buttons
    document.querySelectorAll('.action-btn.edit').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            editVoucher(id);
        });
    });

    document.querySelectorAll('.action-btn.print').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            printVoucher(id);
        });
    });

    document.querySelectorAll('.action-btn.pdf').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            window.open(`print.php?id=${id}&pdf=1`, '_blank');
        });
    });

    document.querySelectorAll('.action-btn.delete').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            deleteVoucher(id);
        });
    });
}

// Initial render will happen after data is fetched

// Filter functionality
const applyFiltersBtn = document.getElementById('applyFiltersBtn');
const resetFiltersBtn = document.getElementById('resetFiltersBtn');

applyFiltersBtn.addEventListener('click', applyFilters);
resetFiltersBtn.addEventListener('click', resetFilters);

function applyFilters() {
    // Reset to first page when applying filters
    fetchVouchers(1);
}

function resetFilters() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('supplierFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('companyFilter').value = '';
    document.getElementById('projectFilter').value = '';
    document.getElementById('searchInput').value = '';
    selectedSupplierCode = '';
    
    fetchVouchers(1);
}

// Search functionality
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('input', applyFilters);

// Update pagination display
function updatePagination(pagination) {
    const { current_page, total_pages, total_records, limit } = pagination;
    const startRecord = (current_page - 1) * limit + 1;
    const endRecord = Math.min(current_page * limit, total_records);
    
    // Update pagination info
    document.getElementById('paginationInfo').textContent = 
        `Showing ${startRecord} to ${endRecord} of ${total_records} entries`;
    
    // Update pagination controls
    const paginationControls = document.querySelector('.pagination-controls');
    paginationControls.innerHTML = '';
    
    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'pagination-btn';
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.disabled = current_page === 1;
    prevBtn.addEventListener('click', () => {
        if (current_page > 1) fetchVouchers(current_page - 1);
    });
    paginationControls.appendChild(prevBtn);
    
    // Page numbers
    const startPage = Math.max(1, current_page - 2);
    const endPage = Math.min(total_pages, current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-btn ${i === current_page ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => fetchVouchers(i));
        paginationControls.appendChild(pageBtn);
    }
    
    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'pagination-btn';
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.disabled = current_page === total_pages;
    nextBtn.addEventListener('click', () => {
        if (current_page < total_pages) fetchVouchers(current_page + 1);
    });
    paginationControls.appendChild(nextBtn);
}

// Action functions
function printVoucher(id) {
    window.open(`print.php?id=${id}`, '_blank');
}

function editVoucher(id) {
    // Fetch voucher details from API
    fetch(`../../../../server/api/vouchers/payment_voucher/payment-get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const voucher = data.data;
                
                // Populate basic fields
                document.getElementById('editVoucherNumber').value = voucher.voucher_number;
                document.getElementById('editVoucherDate').value = voucher.voucher_date;
                
                // Determine supplier or customer
                const entityName = voucher.supplier_id ? 
                    `${voucher.supplier_code} - ${voucher.supplier_name}` : 
                    `${voucher.customer_code} - ${voucher.customer_name}`;
                document.getElementById('editSupplier').value = entityName;
                
                document.getElementById('editBillNo').value = voucher.bill_no || '';
                document.getElementById('editAmount').value = voucher.amount;
                document.getElementById('editDescription').value = voucher.description || '';
                document.getElementById('editChequeNo').value = voucher.cheque_no || '';
                document.getElementById('editChequeDate').value = voucher.cheque_date || '';
                
                // Fetch and populate companies
                fetch('../../../../server/api/vouchers/payment_voucher/get-companies.php')
                    .then(r => r.json())
                    .then(compData => {
                        const companySelect = document.getElementById('editCompany');
                        companySelect.innerHTML = '<option value="">Select Company</option>';
                        if (compData.success) {
                            compData.data.forEach(company => {
                                const option = document.createElement('option');
                                option.value = company.id;
                                option.textContent = company.company_name;
                                if (company.id == voucher.company_id) option.selected = true;
                                companySelect.appendChild(option);
                            });
                        }
                    });
                
                // Fetch and populate sub-accounts
                const entityCode = voucher.supplier_code || voucher.customer_code;
                const apiUrl = voucher.supplier_id 
                    ? `../../../../server/api/vouchers/payment_voucher/get-sub-accounts.php?supplier_code=${entityCode}`
                    : `../../../../server/api/vouchers/payment_voucher/get-customer-sub-accounts.php?customer_code=${entityCode}`;
                
                fetch(apiUrl)
                    .then(r => r.json())
                    .then(subData => {
                        const subSelect = document.getElementById('editSubAccount');
                        subSelect.innerHTML = '<option value="">Select Sub Account</option>';
                        if (subData.success && subData.data.length > 0) {
                            subData.data.forEach(sa => {
                                const option = document.createElement('option');
                                option.value = sa.id;
                                option.textContent = sa.sub_account_name;
                                if (sa.id == voucher.sub_account_id) option.selected = true;
                                subSelect.appendChild(option);
                            });
                        }
                    });
                
                // Fetch and populate currencies
                fetch('../../../../server/api/vouchers/payment_voucher/get-currencies.php')
                    .then(r => r.json())
                    .then(currData => {
                        const currSelect = document.getElementById('editCurrency');
                        currSelect.innerHTML = '<option value="">Select Currency</option>';
                        if (currData.success) {
                            currData.data.forEach(c => {
                                const option = document.createElement('option');
                                option.value = c.currency_id;
                                option.textContent = `${c.name} (${c.symbol})`;
                                if (c.currency_id == voucher.currency_id) option.selected = true;
                                currSelect.appendChild(option);
                            });
                        }
                    });
                
                // Fetch and populate payment methods
                fetch('../../../../server/api/vouchers/payment_voucher/get-payment-methods.php')
                    .then(r => r.json())
                    .then(pmData => {
                        const pmSelect = document.getElementById('editPaymentMethod');
                        pmSelect.innerHTML = '<option value="">Select Payment Method</option>';
                        if (pmData.success) {
                            pmData.data.forEach(pm => {
                                const option = document.createElement('option');
                                option.value = pm.id;
                                option.textContent = pm.name;
                                if (pm.id == voucher.payment_method_id) option.selected = true;
                                pmSelect.appendChild(option);
                            });
                        }
                        
                        // Handle visibility based on payment method
                        handleEditPaymentMethodChange(voucher.payment_method_id);
                        
                        // Add change listener
                        pmSelect.onchange = function() {
                            handleEditPaymentMethodChange(this.value);
                        };
                    });
                
                // Fetch and populate bank accounts
                fetch('../../../../server/api/vouchers/payment_voucher/get-bank-accounts.php')
                    .then(r => r.json())
                    .then(baData => {
                        const baSelect = document.getElementById('editBankAccount');
                        baSelect.innerHTML = '<option value="">Select Bank Account</option>';
                        if (baData.success) {
                            baData.data.forEach(ba => {
                                const option = document.createElement('option');
                                option.value = ba.id;
                                option.textContent = `${ba.bank_name} - ${ba.account_number}`;
                                if (ba.id == voucher.bank_account_id) option.selected = true;
                                baSelect.appendChild(option);
                            });
                        }
                    });
                
                // Fetch and populate projects
                fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
                    .then(r => r.json())
                    .then(prData => {
                        const prSelect = document.getElementById('editProject');
                        prSelect.innerHTML = '<option value="">Select Project</option>';
                        if (prData.success) {
                            prData.data.forEach(p => {
                                const option = document.createElement('option');
                                option.value = p.id;
                                option.textContent = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
                                if (p.id == voucher.project_id) option.selected = true;
                                prSelect.appendChild(option);
                            });
                        }
                    });

                // Load CR expenses for this supplier
                const crSelect = document.getElementById('editCrExpenseRef');
                crSelect.innerHTML = '<option value="">Select CR Expense</option>';
                const supplierCodeForCR = voucher.supplier_code;
                if (supplierCodeForCR) {
                    fetch(`../../../../server/api/vouchers/payment_voucher/get-supplier-cr-expenses.php?supplier_code=${supplierCodeForCR}`)
                        .then(r => r.json())
                        .then(crData => {
                            if (crData.success && crData.data.length > 0) {
                                crData.data.forEach(v => {
                                    const opt = document.createElement('option');
                                    opt.value = v.id;
                                    opt.dataset.amount = v.total_amount;
                                    opt.textContent = `${v.voucher_no} - ${v.date} - ${parseFloat(v.total_amount).toFixed(2)}${v.description ? ' (' + v.description + ')' : ''}`;
                                    if (v.id == voucher.expense_voucher_ref_id) opt.selected = true;
                                    crSelect.appendChild(opt);
                                });
                            }
                        });
                }

                crSelect.onchange = function() {
                    const sel = this.options[this.selectedIndex];
                    if (sel && sel.dataset.amount) {
                        document.getElementById('editAmount').value = parseFloat(sel.dataset.amount).toFixed(2);
                    }
                };

                // Store voucher ID for saving
                document.getElementById('editModal').dataset.voucherId = id;
                document.getElementById('editModal').classList.add('show');
            } else {
                alert('Error loading voucher: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error occurred');
        });
}

function handleEditPaymentMethodChange(methodId) {
    const bankGroup = document.getElementById('editBankAccountGroup');
    const chequeNoGroup = document.getElementById('editChequeNoGroup');
    const chequeDateGroup = document.getElementById('editChequeDateGroup');
    const bankSelect = document.getElementById('editBankAccount');
    const chequeNoInput = document.getElementById('editChequeNo');
    const chequeDateInput = document.getElementById('editChequeDate');
    
    if (methodId == 7) {
        bankGroup.style.display = 'none';
        chequeNoGroup.style.display = 'none';
        chequeDateGroup.style.display = 'none';
        bankSelect.value = '';
        chequeNoInput.value = '';
        chequeDateInput.value = '';
    } else {
        bankGroup.style.display = 'flex';
        if (methodId == 6) {
            chequeNoGroup.style.display = 'flex';
            chequeDateGroup.style.display = 'flex';
        } else {
            chequeNoGroup.style.display = 'none';
            chequeDateGroup.style.display = 'none';
        }
    }
}

let voucherToDelete = null;

function deleteVoucher(id) {
    const voucher = vouchers.find(v => v.id == id);
    if (voucher) {
        voucherToDelete = id;
        document.getElementById('deleteVoucherNumber').textContent = voucher.voucherNumber;
        document.getElementById('deleteModal').classList.add('show');
    }
}

// Modal event handlers
document.getElementById('deleteModalClose').addEventListener('click', function() {
    document.getElementById('deleteModal').classList.remove('show');
});

document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
    document.getElementById('deleteModal').classList.remove('show');
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (voucherToDelete) {
        fetch(`../../../../server/api/vouchers/payment_voucher/payment-delete.php?id=${voucherToDelete}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Voucher deleted successfully!');
                // Refresh the list
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error occurred');
        });
        
        document.getElementById('deleteModal').classList.remove('show');
        voucherToDelete = null;
    }
});

// Edit modal event handlers
document.getElementById('editModalClose').addEventListener('click', function() {
    document.getElementById('editModal').classList.remove('show');
});

document.getElementById('cancelEditBtn').addEventListener('click', function() {
    document.getElementById('editModal').classList.remove('show');
});

document.getElementById('saveEditBtn').addEventListener('click', function() {
    const voucherId = document.getElementById('editModal').dataset.voucherId;
    
    // Validate required fields
    const companyId = document.getElementById('editCompany').value;
    const currencyId = document.getElementById('editCurrency').value;
    const paymentMethodId = document.getElementById('editPaymentMethod').value;
    const amount = document.getElementById('editAmount').value;
    
    if (!companyId || !currencyId || !paymentMethodId || !amount) {
        alert('Please fill in all required fields');
        return;
    }
    
    const formData = {
        voucher_date: document.getElementById('editVoucherDate').value,
        company_id: companyId,
        sub_account_id: document.getElementById('editSubAccount').value || null,
        bill_no: document.getElementById('editBillNo').value || null,
        amount: amount,
        currency_id: currencyId,
        payment_method_id: paymentMethodId,
        bank_account_id: document.getElementById('editBankAccount').value || null,
        cheque_no: document.getElementById('editChequeNo').value || null,
        cheque_date: document.getElementById('editChequeDate').value || null,
        description: document.getElementById('editDescription').value || null,
        project_id: document.getElementById('editProject').value || null,
        expense_voucher_ref_id: document.getElementById('editCrExpenseRef').value || null
    };
    
    // Disable button to prevent double submission
    this.disabled = true;
    this.textContent = 'Saving...';
    
    fetch(`../../../../server/api/vouchers/payment_voucher/payment-edit.php?id=${voucherId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Voucher updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            this.disabled = false;
            this.textContent = 'Save Changes';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
        this.disabled = false;
        this.textContent = 'Save Changes';
    });
});

// New voucher button
document.getElementById('newVoucherBtn').addEventListener('click', function () {
    window.location.href = 'payment-add.php';
});

// Export button
document.getElementById('exportBtn').addEventListener('click', function () {
    exportVouchersToExcel();
});

// Refresh button
document.getElementById('refreshBtn').addEventListener('click', function () {
    fetchVouchers(currentPage);
});



// Set default date values for filters (last 30 days)
const today = new Date();
const thirtyDaysAgo = new Date();
thirtyDaysAgo.setDate(today.getDate() - 30);

document.getElementById('dateFrom').valueAsDate = thirtyDaysAgo;
document.getElementById('dateTo').valueAsDate = today;

const methodIcons = {
    'cash': 'fas fa-money-bill-wave',
    'cheque': 'fas fa-money-check',
    'check': 'fas fa-money-check',
    'bank': 'fas fa-university',
    'transfer': 'fas fa-exchange-alt',
    'online': 'fas fa-globe',
    'card': 'fas fa-credit-card',
};

function getMethodIcon(name) {
    const key = (name || '').toLowerCase();
    for (const k in methodIcons) {
        if (key.includes(k)) return methodIcons[k];
    }
    return 'fas fa-coins';
}

function exportVouchersToExcel() {
    if (!vouchers || vouchers.length === 0) {
        alert('No vouchers to export.');
        return;
    }

    const entityLabel = currentPaymentType === 'customer' ? 'Customer' : 'Supplier';

    const rows = vouchers.map(v => {
        const dateObj = new Date(v.date);
        const formattedDate = isNaN(dateObj) ? v.date : dateObj.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });

        return {
            'Voucher #': v.voucherNumber,
            'Date': formattedDate,
            'Type': entityLabel,
            [entityLabel + ' Code']: v.supplierCode || '',
            [entityLabel]: v.supplier,
            'Bill No': v.billNo,
            'Cheque No': v.chequeNo,
            'Project': v.projectName,
            'Amount': v.amount,
            'Currency': v.currencySymbol,
            'Payment Method': v.paymentMethod,
            'Status': v.status.charAt(0).toUpperCase() + v.status.slice(1)
        };
    });

    const worksheet = XLSX.utils.json_to_sheet(rows);

    worksheet['!cols'] = [
        { wch: 14 }, { wch: 12 }, { wch: 10 }, { wch: 14 }, { wch: 24 },
        { wch: 12 }, { wch: 20 }, { wch: 12 }, { wch: 8 }, { wch: 16 }, { wch: 10 }
    ];

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Payment Vouchers');

    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(workbook, `payment-vouchers-${currentPaymentType}-${dateStr}.xlsx`);
}
function renderSummary(summary) {
    if (!summary) return;
    const sym = (vouchers[0] && vouchers[0].currencySymbol) ? vouchers[0].currencySymbol : '';
    document.getElementById('summaryTotalValue').textContent = sym + Number(summary.grand_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const methodsDiv = document.getElementById('summaryMethods');
    methodsDiv.innerHTML = '';
    (summary.by_payment_method || []).forEach(item => {
        const card = document.createElement('div');
        card.className = 'summary-card summary-method-card';
        card.innerHTML = `
            <div class="summary-icon"><i class="${getMethodIcon(item.payment_method)}"></i></div>
            <div class="summary-info">
                <div class="summary-label">${item.payment_method}</div>
                <div class="summary-value">${sym}${Number(item.total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
            </div>
        `;
        methodsDiv.appendChild(card);
    });
}