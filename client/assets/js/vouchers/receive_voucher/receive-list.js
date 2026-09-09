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

// Fetch currencies
fetch('../../../../server/api/vouchers/receive_voucher/get-currencies.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currencies = data.data;
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

// Fetch areas for filter
fetch('../../../../server/api/vouchers/receive_voucher/get-areas.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const areaFilterOptions = document.getElementById('areaFilterOptions');
            areaFilterOptions.innerHTML = '<div class="dropdown-option" data-value="" data-name="All Areas">All Areas</div>';
            data.data.forEach(area => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.setAttribute('data-value', area.id);
                option.setAttribute('data-name', area.area_name);
                option.textContent = area.area_name;
                areaFilterOptions.appendChild(option);
            });
            initSearchableDropdown('areaFilterSearch', 'areaFilterOptions', 'areaFilter');
        }
    });

// Fetch cities for filter
fetch('../../../../server/api/vouchers/receive_voucher/get-cities.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cityFilterOptions = document.getElementById('cityFilterOptions');
            cityFilterOptions.innerHTML = '<div class="dropdown-option" data-value="" data-name="All Cities">All Cities</div>';
            data.data.forEach(city => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.setAttribute('data-value', city.id);
                option.setAttribute('data-name', city.city_name);
                option.textContent = city.city_name;
                cityFilterOptions.appendChild(option);
            });
            initSearchableDropdown('cityFilterSearch', 'cityFilterOptions', 'cityFilter');
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
    const areaFilter = document.getElementById('areaFilter').value;
    const cityFilter = document.getElementById('cityFilter').value;
    const voucherNumberFilter = document.getElementById('voucherNumberFilter').value;

    let url = `../../../../server/api/vouchers/receive_voucher/receive-list.php?page=${page}&limit=${itemsPerPage}`;

    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (customerFilter) url += `&customer_id=${customerFilter}`;
    if (recoveryOfficerFilter) url += `&recovery_officer_id=${recoveryOfficerFilter}`;
    if (statusFilter) url += `&status=${statusFilter}`;
    if (companyFilter) url += `&company_id=${companyFilter}`;
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (areaFilter) url += `&area_id=${areaFilter}`;
    if (cityFilter) url += `&city_id=${cityFilter}`;
    if (voucherNumberFilter) url += `&voucher_number=${encodeURIComponent(voucherNumberFilter)}`;

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
                    area: voucher.area_name,
                    city: voucher.city_name,
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
            const recoveryOfficerFilterOptions = document.getElementById('recoveryOfficerFilterOptions');
            recoveryOfficerFilterOptions.innerHTML = '<div class="dropdown-option" data-value="" data-name="All Officers">All Officers</div>';
            data.data.forEach(emp => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.setAttribute('data-value', emp.id);
                option.setAttribute('data-name', emp.full_name);
                option.textContent = emp.full_name;
                recoveryOfficerFilterOptions.appendChild(option);
            });
            initSearchableDropdown('recoveryOfficerFilterSearch', 'recoveryOfficerFilterOptions', 'recoveryOfficerFilter');
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
                    <td>${voucher.area || 'N/A'}</td>
                    <td>${voucher.city || 'N/A'}</td>
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
                            <button class="action-btn" title="Thermal Print" style="background:#17a2b8;color:#fff;" data-id="${voucher.id}" onclick="printThermalVoucher(${voucher.id}); return false;">
                                <i class="fas fa-receipt"></i>
                            </button>
                            <button class="action-btn" title="Download PDF" style="background:#ffc107;color:#333;" data-id="${voucher.id}" onclick="window.open('print.php?id=${voucher.id}&pdf=1','_blank'); return false;">
                                <i class="fas fa-file-pdf"></i>
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
    document.getElementById('customerFilterSearch').value = '';
    document.getElementById('recoveryOfficerFilter').value = '';
    document.getElementById('recoveryOfficerFilterSearch').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('companyFilter').value = '';
    document.getElementById('projectFilter').value = '';
    document.getElementById('areaFilter').value = '';
    document.getElementById('areaFilterSearch').value = '';
    document.getElementById('cityFilter').value = '';
    document.getElementById('cityFilterSearch').value = '';
    document.getElementById('voucherNumberFilter').value = '';
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

// Opens the 80mm thermal receipt view for a voucher in a new tab
function printThermalVoucher(id) {
    window.open(`thermal-print.php?id=${id}`, '_blank');
}

function editVoucher(id) {
    loadEditVoucher(id);
}

// Safe getter — returns null if the element doesn't exist or has no value.
// Prevents crashes when a new field hasn't been added to the modal HTML yet.
function getEditVal(id) {
    const el = document.getElementById(id);
    return (el && el.value) ? el.value : null;
}

function loadEditVoucher(id) {
    Promise.all([
        fetch(`../../../../server/api/vouchers/receive_voucher/receive-get.php?id=${id}`).then(r => r.json()),
        fetch('../../../../server/api/vouchers/receive_voucher/get-companies.php').then(r => r.json()),
        fetch('../../../../server/api/vouchers/receive_voucher/get-currencies.php').then(r => r.json()),
        fetch('../../../../server/api/vouchers/receive_voucher/get-payment-methods.php').then(r => r.json()),
        fetch('../../../../server/api/vouchers/receive_voucher/get-bank-accounts.php').then(r => r.json()),
        fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php').then(r => r.json()),
        fetch('../../../../server/api/vouchers/receive_voucher/get-employees.php').then(r => r.json()),
        fetch('../../../../server/api/vouchers/receive_voucher/get-containers.php').then(r => r.json())
    ]).then(([voucherRes, companiesRes, currenciesRes, methodsRes, banksRes, projectsRes, employeesRes, containersRes]) => {
        if (!voucherRes.success) {
            alert('Error loading voucher: ' + (voucherRes.error || 'Unknown error'));
            return;
        }

        if (companiesRes.success) companies = companiesRes.data;
        if (currenciesRes.success) currencies = currenciesRes.data;
        if (methodsRes.success) paymentMethods = methodsRes.data;
        if (banksRes.success) bankAccounts = banksRes.data;
        if (projectsRes.success) projects = projectsRes.projects || [];
        const editEmployees = employeesRes.success ? employeesRes.data : [];
        const editContainers = containersRes.success ? containersRes.data : [];

        const voucher = voucherRes.data;

        // ---- Core (required) fields — populate these FIRST, unconditionally ----
        document.getElementById('editVoucherNumber').value = voucher.voucher_number;
        document.getElementById('editVoucherDate').value = voucher.voucher_date;
        document.getElementById('editCustomer').value = `${voucher.customer_code} - ${voucher.customer_name}`;
        document.getElementById('editBillNo').value = voucher.bill_no || '';
        document.getElementById('editAmount').value = voucher.amount;
        document.getElementById('editAdjustAmount').value = voucher.adjust_amount || 0;
        document.getElementById('editDescription').value = voucher.description || '';
        document.getElementById('editChequeDate').value = voucher.cheque_date || '';
        document.getElementById('editChequeNo').value = voucher.cheque_no || '';

        const companySelect = document.getElementById('editCompany');
        companySelect.innerHTML = '<option value="">Select Company</option>';
        companies.forEach(company => {
            const option = document.createElement('option');
            option.value = company.id;
            option.textContent = company.company_name;
            if (String(company.id) === String(voucher.company_id)) option.selected = true;
            companySelect.appendChild(option);
        });

        const currencySelect = document.getElementById('editCurrency');
        currencySelect.innerHTML = '<option value="">Select Currency</option>';
        currencies.forEach(currency => {
            const option = document.createElement('option');
            option.value = currency.currency_id;
            option.textContent = `${currency.name} (${currency.symbol})`;
            if (String(currency.currency_id) === String(voucher.currency_id)) option.selected = true;
            currencySelect.appendChild(option);
        });

        const paymentMethodSelect = document.getElementById('editPaymentMethod');
        paymentMethodSelect.innerHTML = '<option value="">Select Payment Method</option>';
        paymentMethods.forEach(method => {
            const option = document.createElement('option');
            option.value = method.id;
            option.textContent = method.name;
            const dbId = String(voucher.payment_method_id);
            const matchId = (dbId === '1') ? '7' : dbId;
            if (String(method.id) === matchId) option.selected = true;
            paymentMethodSelect.appendChild(option);
        });

        const bankAccountSelect = document.getElementById('editBankAccount');
        bankAccountSelect.innerHTML = '<option value="">Select Bank Account</option>';
        bankAccounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${account.bank_name} - ${account.account_number}`;
            if (String(account.id) === String(voucher.bank_account_id)) option.selected = true;
            bankAccountSelect.appendChild(option);
        });

        const projectSelect = document.getElementById('editProject');
        projectSelect.innerHTML = '<option value="">Select Project</option>';
        projects.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id;
            option.textContent = `${project.project_code} - ${project.project_name} - ${project.city_name || 'N/A'}`;
            if (String(project.id) === String(voucher.project_id)) option.selected = true;
            projectSelect.appendChild(option);
        });

        // ---- Debug: confirm required values actually got set ----
        console.log('[editVoucher] company=', companySelect.value, 'currency=', currencySelect.value, 'payment=', paymentMethodSelect.value, 'amount=', document.getElementById('editAmount').value);

        // ---- New optional fields — null-safe, won't break the above if missing from HTML ----
        const officerSelect = document.getElementById('editRecoveryOfficer');
        if (officerSelect) {
            officerSelect.innerHTML = '<option value="">Select Recovery Officer</option>';
            editEmployees.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = `${emp.employee_id} - ${emp.full_name}`;
                if (String(emp.id) === String(voucher.recovery_officer_id)) option.selected = true;
                officerSelect.appendChild(option);
            });
        } else {
            console.warn('[editVoucher] #editRecoveryOfficer not found in DOM — add it to receive-list.php');
        }

        const containerSelect = document.getElementById('editContainer');
        if (containerSelect) {
            containerSelect.innerHTML = '<option value="">Select Container</option>';
            editContainers.forEach(c => {
                const option = document.createElement('option');
                option.value = c.id;
                option.textContent = `${c.container_code} - ${c.container_name}`;
                if (String(c.id) === String(voucher.container_id)) option.selected = true;
                containerSelect.appendChild(option);
            });
        } else {
            console.warn('[editVoucher] #editContainer not found in DOM — add it to receive-list.php');
        }

        const dsrInput = document.getElementById('editDsrNo');
        if (dsrInput) {
            dsrInput.value = voucher.dsr_no || '';
        } else {
            console.warn('[editVoucher] #editDsrNo not found in DOM — add it to receive-list.php');
        }

        const subAccountSelect = document.getElementById('editSubAccount');
        if (subAccountSelect) {
            subAccountSelect.innerHTML = '<option value="">Select Sub Account</option>';
            if (voucher.customer_id) {
                fetch(`../../../../server/api/vouchers/receive_voucher/get-sub-accounts.php?customer_id=${voucher.customer_id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            data.data.forEach(sa => {
                                const option = document.createElement('option');
                                option.value = sa.id;
                                option.textContent = sa.sub_account_name;
                                if (String(sa.id) === String(voucher.sub_account_id)) option.selected = true;
                                subAccountSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(err => console.warn('[editVoucher] sub-account fetch failed', err));
            }
        } else {
            console.warn('[editVoucher] #editSubAccount not found in DOM — add it to receive-list.php');
        }

        const pdcSelect = document.getElementById('editAgainstPdc');
        if (pdcSelect) {
            pdcSelect.innerHTML = '<option value="">— No PDC —</option>';
            if (voucher.customer_id) {
                fetch(`../../../../server/api/vouchers/receive_voucher/get-pending-pdcs.php?customer_id=${voucher.customer_id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            data.data.forEach(pdc => {
                                const opt = document.createElement('option');
                                opt.value = pdc.id;
                                const date = new Date(pdc.cheque_date).toLocaleDateString();
                                opt.textContent = `${pdc.cheque_no} (${date}) Rem:${parseFloat(pdc.remaining).toFixed(0)}`;
                                if (String(pdc.id) === String(voucher.against_pdc_id)) opt.selected = true;
                                pdcSelect.appendChild(opt);
                            });
                            if (voucher.against_pdc_id && !data.data.some(p => String(p.id) === String(voucher.against_pdc_id))) {
                                const opt = document.createElement('option');
                                opt.value = voucher.against_pdc_id;
                                opt.textContent = `PDC #${voucher.against_pdc_id} (current)`;
                                opt.selected = true;
                                pdcSelect.appendChild(opt);
                            }
                        }
                    })
                    .catch(err => console.warn('[editVoucher] pdc fetch failed', err));
            }
        } else {
            console.warn('[editVoucher] #editAgainstPdc not found in DOM — add it to receive-list.php');
        }

        handleEditPaymentMethodChange(voucher.payment_method_id);
        paymentMethodSelect.onchange = function() {
            handleEditPaymentMethodChange(this.value);
        };

        document.getElementById('editModal').dataset.voucherId = id;
        document.getElementById('editModal').classList.add('show');
    }).catch(error => {
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

    if (methodId == 7 || methodId == 1) {
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

    const companyId = document.getElementById('editCompany').value;
    const currencyId = document.getElementById('editCurrency').value;
    const paymentMethodId = document.getElementById('editPaymentMethod').value;
    const amount = document.getElementById('editAmount').value;

    const missing = [];
    if (!companyId) missing.push('Company');
    if (!currencyId) missing.push('Currency');
    if (!paymentMethodId) missing.push('Payment Method');
    if (!amount) missing.push('Amount');

    if (missing.length > 0) {
        alert('Please fill in: ' + missing.join(', '));
        return;
    }

    // Optional / new fields fetched safely — won't crash if not yet present in the modal HTML
    const formData = {
        voucher_date: document.getElementById('editVoucherDate').value,
        company_id: companyId,
        bill_no: getEditVal('editBillNo'),
        dsr_no: getEditVal('editDsrNo'),
        amount: amount,
        adjust_amount: getEditVal('editAdjustAmount') || 0,
        currency_id: currencyId,
        project_id: getEditVal('editProject'),
        container_id: getEditVal('editContainer'),
        sub_account_id: getEditVal('editSubAccount'),
        recovery_officer_id: getEditVal('editRecoveryOfficer'),
        against_pdc_id: getEditVal('editAgainstPdc'),
        payment_method_id: paymentMethodId,
        bank_account_id: getEditVal('editBankAccount'),
        cheque_date: getEditVal('editChequeDate'),
        cheque_no: getEditVal('editChequeNo'),
        description: getEditVal('editDescription')
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

function exportVouchersToExcel() {
    if (!vouchers || vouchers.length === 0) {
        alert('No vouchers to export.');
        return;
    }

    const rows = vouchers.map(v => {
        const dateObj = new Date(v.date);
        const formattedDate = isNaN(dateObj) ? v.date : dateObj.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });

        return {
            'Voucher #': v.voucherNumber,
            'Date': formattedDate,
            'Customer Code': v.customerCode || '',
            'Customer': v.customer,
            'Area': v.area || 'N/A',
            'City': v.city || 'N/A',
            'Bill No': v.billNo,
            'Project Code': v.projectCode || '',
            'Project Name': v.projectName || '',
            'Amount': v.amount,
            'Currency': v.currencySymbol,
            'Payment Method': v.paymentMethod,
            'Cheque No': v.chequeNo || '',
            'Cheque Date': v.chequeDate || '',
            'Recovery Officer': v.recoveryOfficer || 'N/A',
            'Status': v.status.charAt(0).toUpperCase() + v.status.slice(1)
        };
    });

    const worksheet = XLSX.utils.json_to_sheet(rows);

    worksheet['!cols'] = [
        { wch: 14 }, { wch: 12 }, { wch: 14 }, { wch: 24 }, { wch: 14 }, { wch: 14 }, { wch: 12 },
        { wch: 12 }, { wch: 22 }, { wch: 12 }, { wch: 8 }, { wch: 16 },
        { wch: 12 }, { wch: 12 }, { wch: 18 }, { wch: 10 }
    ];

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Receive Vouchers');

    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(workbook, `receive-vouchers-${dateStr}.xlsx`);
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