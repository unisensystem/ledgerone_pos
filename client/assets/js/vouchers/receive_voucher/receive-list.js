// Vouchers data and pagination
let vouchers = [];
let currentPage = 1;
let totalPages = 1;
let itemsPerPage = 10;
let paymentMethods = [];
let bankAccounts = [];
let companies = [];
let currencies = [];
let projects = [];
let userPermissions = { can_edit: false, can_delete: false };

// Check permissions
fetch('../../../../server/api/vouchers/receive_voucher/check-permissions.php')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            userPermissions = data;
        }
    });

// Fetch payment methods and bank accounts
fetch('../../../../server/api/vouchers/receive_voucher/get-payment-methods.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            paymentMethods = data.data;
        }
    });

fetch('../../../../server/api/vouchers/receive_voucher/get-bank-accounts.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bankAccounts = data.data;
        }
    });

// Fetch companies
fetch('../../../../server/api/vouchers/receive_voucher/get-companies.php')
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
    });

// Fetch projects for filter
fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && Array.isArray(data.data)) {
            projects = data.data;
            const projectFilter = document.getElementById('projectFilter');
            projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                const cityName = project.city_name || 'N/A';
                option.textContent = `${project.project_code} - ${project.project_name} - ${cityName}`;
                projectFilter.appendChild(option);
            });
        }
    });

// Fetch vouchers from API with pagination
function fetchVouchers(page = 1) {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const customerFilter = document.getElementById('customerFilter').value;
    const recoveryOfficerFilter = document.getElementById('recoveryOfficerFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const companyFilter = document.getElementById('companyFilter').value;
    const searchTerm = document.getElementById('searchInput').value;
    
    let url = `../../../../server/api/vouchers/receive_voucher/receive-list.php?page=${page}&limit=${itemsPerPage}`;
    
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (customerFilter) url += `&customer_id=${customerFilter}`;
    if (recoveryOfficerFilter) url += `&recovery_officer_id=${recoveryOfficerFilter}`;
    if (statusFilter) url += `&status=${statusFilter}`;
    if (companyFilter) url += `&company_id=${companyFilter}`;
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    
    const projectFilter = document.getElementById('projectFilter').value;
    if (projectFilter) url += `&project_id=${projectFilter}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                vouchers = data.data.map(voucher => ({
                    id: voucher.id,
                    voucherNumber: voucher.voucher_number,
                    date: voucher.voucher_date,
                    customer: voucher.customer_name,
                    customerCode: voucher.customer_code,
                    billNo: voucher.bill_no || 'N/A',
                    amount: parseFloat(voucher.amount),
                    currencySymbol: voucher.currency_symbol || '$',
                    paymentMethod: voucher.payment_method,
                    paymentMethodId: voucher.payment_method_id,
                    bankAccountId: voucher.bank_account_id,
                    chequeDate: voucher.cheque_date,
                    chequeNo: voucher.cheque_no,
                    recoveryOfficer: voucher.recovery_officer,
                    projectCode: voucher.project_code,
                    projectName: voucher.project_name,
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

// Fetch customers for filter dropdown
fetch('../../../../server/api/vouchers/receive_voucher/get-suppliers.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customerFilterOptions = document.getElementById('customerFilterOptions');
            customerFilterOptions.innerHTML = '<div class="dropdown-option" data-value="" data-name="">All Customers</div>';
            data.data.forEach(customer => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.setAttribute('data-value', customer.id);
                option.setAttribute('data-name', customer.customer_name);
                option.textContent = `${customer.customer_code} - ${customer.customer_name}`;
                customerFilterOptions.appendChild(option);
            });
            initSearchableDropdown('customerFilterSearch', 'customerFilterOptions', 'customerFilter');
        }
    });

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
            searchInput.value = name || 'All Customers';
            hiddenInput.value = value;
            optionsContainer.style.display = 'none';
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

// Fetch recovery officers for filter dropdown
fetch('../../../../server/api/vouchers/receive_voucher/get-employees.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const recoveryOfficerFilter = document.getElementById('recoveryOfficerFilter');
            data.data.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = emp.full_name;
                recoveryOfficerFilter.appendChild(option);
            });
        }
    });

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
                    <td>${voucher.customer}</td>
                    <td>${voucher.billNo}</td>
                    <td>${voucher.projectCode ? `${voucher.projectCode} - ${voucher.projectName}` : 'N/A'}</td>
                    <td class="amount">${formattedAmount}</td>
                    <td>${voucher.paymentMethod}</td>
                    <td>${voucher.recoveryOfficer || 'N/A'}</td>
                    <td><span class="status ${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="actions">
                            ${userPermissions.can_edit ? `<button class="action-btn edit" title="Edit" data-id="${voucher.id}" onclick="editVoucher(${voucher.id}); return false;">
                                <i class="fas fa-edit"></i>
                            </button>` : ''}
                            <button class="action-btn print" title="Print" data-id="${voucher.id}" onclick="printVoucher(${voucher.id}); return false;">
                                <i class="fas fa-print"></i>
                            </button>
                            ${userPermissions.can_delete ? `<button class="action-btn delete" title="Delete" data-id="${voucher.id}" onclick="deleteVoucher(${voucher.id}); return false;">
                                <i class="fas fa-trash"></i>
                            </button>` : ''}
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
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
    document.getElementById('customerFilter').value = '';
    document.getElementById('recoveryOfficerFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('companyFilter').value = '';
    document.getElementById('projectFilter').value = '';
    document.getElementById('searchInput').value = '';
    
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
    loadEditVoucher(id);
}

function loadEditVoucher(id) {
    // Fetch voucher details from API
    fetch(`../../../../server/api/vouchers/receive_voucher/receive-get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const voucher = data.data;
                
                // Populate basic fields
                document.getElementById('editVoucherNumber').value = voucher.voucher_number;
                document.getElementById('editVoucherDate').value = voucher.voucher_date;
                document.getElementById('editCustomer').value = `${voucher.customer_code} - ${voucher.customer_name}`;
                document.getElementById('editBillNo').value = voucher.bill_no || '';
                document.getElementById('editAmount').value = voucher.amount;
                document.getElementById('editDescription').value = voucher.description || '';
                document.getElementById('editChequeDate').value = voucher.cheque_date || '';
                document.getElementById('editChequeNo').value = voucher.cheque_no || '';
                
                // Populate companies
                const companySelect = document.getElementById('editCompany');
                companySelect.innerHTML = '<option value="">Select Company</option>';
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    if (company.id == voucher.company_id) option.selected = true;
                    companySelect.appendChild(option);
                });
                
                // Populate currencies
                const currencySelect = document.getElementById('editCurrency');
                currencySelect.innerHTML = '<option value="">Select Currency</option>';
                currencies.forEach(currency => {
                    const option = document.createElement('option');
                    option.value = currency.currency_id;
                    option.textContent = `${currency.name} (${currency.symbol})`;
                    if (currency.currency_id == voucher.currency_id) option.selected = true;
                    currencySelect.appendChild(option);
                });
                
                // Populate payment methods
                const paymentMethodSelect = document.getElementById('editPaymentMethod');
                paymentMethodSelect.innerHTML = '<option value="">Select Payment Method</option>';
                paymentMethods.forEach(method => {
                    const option = document.createElement('option');
                    option.value = method.id;
                    option.textContent = method.name;
                    if (method.id == voucher.payment_method_id) option.selected = true;
                    paymentMethodSelect.appendChild(option);
                });
                
                // Populate bank accounts
                const bankAccountSelect = document.getElementById('editBankAccount');
                bankAccountSelect.innerHTML = '<option value="">Select Bank Account</option>';
                bankAccounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.bank_name} - ${account.account_number}`;
                    if (account.id == voucher.bank_account_id) option.selected = true;
                    bankAccountSelect.appendChild(option);
                });
                
                // Populate projects
                const projectSelect = document.getElementById('editProject');
                projectSelect.innerHTML = '<option value="">Select Project</option>';
                if (projects && projects.length > 0) {
                    projects.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        const cityName = project.city_name || 'N/A';
                        option.textContent = `${project.project_code} - ${project.project_name} - ${cityName}`;
                        if (project.id == voucher.project_id) {
                            option.selected = true;
                        }
                        projectSelect.appendChild(option);
                    });
                }
                
                // Handle visibility based on payment method
                handleEditPaymentMethodChange(voucher.payment_method_id);
                
                // Add change listener
                paymentMethodSelect.onchange = function() {
                    handleEditPaymentMethodChange(this.value);
                };
                
                // Store voucher ID for saving
                document.getElementById('editModal').dataset.voucherId = id;
                document.getElementById('editModal').classList.add('show');
            } else {
                alert('Error loading voucher: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Network error occurred');
        });
}

function handleEditPaymentMethodChange(methodId) {
    const bankGroup = document.getElementById('editBankAccountGroup');
    const chequeGroup = document.getElementById('editChequeDateGroup');
    const chequeNoGroup = document.getElementById('editChequeNoGroup');
    const bankSelect = document.getElementById('editBankAccount');
    const chequeInput = document.getElementById('editChequeDate');
    const chequeNoInput = document.getElementById('editChequeNo');
    
    if (methodId == 7) {
        bankGroup.style.display = 'none';
        chequeGroup.style.display = 'none';
        chequeNoGroup.style.display = 'none';
        bankSelect.value = '';
        chequeInput.value = '';
        chequeNoInput.value = '';
    } else {
        bankGroup.style.display = 'flex';
        chequeGroup.style.display = methodId == 6 ? 'flex' : 'none';
        chequeNoGroup.style.display = methodId == 6 ? 'flex' : 'none';
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
        fetch(`../../../../server/api/vouchers/receive_voucher/receive-delete.php?id=${voucherToDelete}`, {
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
        bill_no: document.getElementById('editBillNo').value || null,
        amount: amount,
        currency_id: currencyId,
        project_id: document.getElementById('editProject').value || null,
        payment_method_id: paymentMethodId,
        bank_account_id: document.getElementById('editBankAccount').value || null,
        cheque_date: document.getElementById('editChequeDate').value || null,
        cheque_no: document.getElementById('editChequeNo').value || null,
        description: document.getElementById('editDescription').value || null
    };
    
    // Disable button to prevent double submission
    this.disabled = true;
    this.textContent = 'Saving...';
    
    // Call edit API
    fetch(`../../../../server/api/vouchers/receive_voucher/receive-edit.php?id=${voucherId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
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
    window.location.href = 'receive-add.php';
});

// Export button
document.getElementById('exportBtn').addEventListener('click', function () {
    alert('Exporting voucher data...');
    // In a real application, this would trigger a file download
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

// Payment method icons map
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