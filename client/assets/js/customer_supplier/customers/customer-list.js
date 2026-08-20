let customers = [];
let currentPage = 1;
let totalPages = 1;
let rowsPerPage = 10;
let editSuppliers = [];
let editEmployees = [];
let editCustomerCategories = [];
let editBrands = [];
let editSubAccountCounter = 0;

document.addEventListener('DOMContentLoaded', function () {
    const customersTable = document.getElementById('customersTable');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const companyFilter = document.getElementById('companyFilter');
    
    let userPermissions = [];

    // Check permissions first
    checkPermissions().then(() => {
        loadCompanyFilter();
        loadCustomers();
    });
    
    function loadCustomers() {
        const params = new URLSearchParams({
            search: searchInput.value,
            status: statusFilter.value,
            company: companyFilter.value,
            page: currentPage,
            limit: rowsPerPage === 'all' ? 999999 : rowsPerPage
        });
        
        fetch(`../../../../server/api/customer_supplier/customers/customer-list.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customers = data.customers;
                    renderCustomers(customers);
                    updateStats(data.stats);
                    updatePagination(data.pagination);
                } else {
                    showNotification('Error', data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error', 'Failed to load customers', 'error');
            });
    }

    function renderCustomers(customersToRender) {
        customersTable.innerHTML = '';

        const startSr = ((currentPage - 1) * (rowsPerPage === 'all' ? customersToRender.length : rowsPerPage)) + 1;

        customersToRender.forEach((customer, index) => {
            const status = customer.is_blacklisted ? 'blacklisted' : 'active';

            const editBtn = userPermissions.includes('Edit') ? 
                `<button class="action-btn edit" title="Edit Customer"><i class="fas fa-edit"></i></button>` : '';
            const deleteBtn = userPermissions.includes('Delete') ? 
                `<button class="action-btn delete" title="Delete Customer"><i class="fas fa-trash"></i></button>` : '';

            const balance = parseFloat(customer.current_balance || 0);
            const openingDebit = parseFloat(customer.opening_debit_amount || 0);
            const openingCredit = parseFloat(customer.opening_credit_amount || 0);
            const hasOpeningHistory = openingDebit > 0 || openingCredit > 0;

            const row = document.createElement('tr');
            row.dataset.id = customer.id;
            row.innerHTML = `
                        <td>${startSr + index}</td>
                        <td><span class="customer-code">${customer.customer_code}</span></td>
                        <td><span class="customer-name">${customer.customer_name}</span></td>
                        <td>${customer.shop_name || '-'}</td>
                        <td>${customer.address || '-'}</td>
                        <td>${customer.customer_group_name || '-'}</td>
                        <td>${customer.customer_category_name || '-'}</td>
                        <td>${customer.city_name || '-'}</td>
                        <td>${customer.zone_name || '-'}</td>
                        <td>${customer.area_name || '-'}</td>
                        <td>${customer.primary_phone || '-'}</td>
                        <td>
                            <span class="balance-badge" title="${hasOpeningHistory ? 'Click to view opening balance / invoice history' : 'No opening balance history'}" style="cursor:${hasOpeningHistory ? 'pointer' : 'default'}; ${hasOpeningHistory ? 'text-decoration: underline;' : ''}">
                                ${currencySymbol}${balance.toFixed(2)}
                            </span>
                        </td>
                        <td><span class="status-badge status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                        <td>
                            <div class="action-buttons">
                                ${editBtn}
                                ${deleteBtn}
                                <button class="action-btn" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    `;

            // Clicking the balance badge opens the same View modal (already shows
            // opening balance invoices + sub accounts history)
            const balanceBadge = row.querySelector('.balance-badge');
            if (hasOpeningHistory) {
                balanceBadge.addEventListener('click', () => openViewModal(customer.id));
            }

            customersTable.appendChild(row);
        });
    }

    function updateStats(stats) {
        document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = stats.total_customers;
        document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = stats.active_customers;
        document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = stats.blacklisted_customers;
    }
    
    function updatePagination(pagination) {
        currentPage = pagination.page;
        totalPages = pagination.pages;
        const tableInfo = document.getElementById('tableInfo');
        const start = ((pagination.page - 1) * pagination.limit) + 1;
        const end = Math.min(pagination.page * pagination.limit, pagination.total);
        tableInfo.textContent = `Showing ${start}-${end} of ${pagination.total} customers`;
        
        // Update pagination buttons
        const paginationControls = document.querySelector('.pagination-controls');
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        
        // Clear existing page buttons
        const pageButtons = paginationControls.querySelectorAll('.pagination-btn:not(#prevPage):not(#nextPage)');
        pageButtons.forEach(btn => btn.remove());
        
        // Generate page buttons
        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                currentPage = i;
                loadCustomers();
            });
            paginationControls.insertBefore(pageBtn, nextBtn);
        }
        
        // Update prev/next button states
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;
    }

    // Search and filter functionality
    let searchTimeout;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadCustomers();
        }, 500);
    });

    function loadCompanyFilter() {
        fetch('../../../../server/api/customer_supplier/customers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.companies.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.company_name;
                        companyFilter.appendChild(option);
                    });
                }
            });
    }

    statusFilter.addEventListener('change', function() {
        currentPage = 1;
        loadCustomers();
    });

    companyFilter.addEventListener('change', function() {
        currentPage = 1;
        loadCustomers();
    });

    const rowsPerPageSelect = document.getElementById('rowsPerPage');
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
            currentPage = 1;
            loadCustomers();
        });
    }


    // Delete Modal Elements
    const deleteModal = document.getElementById('deleteModal');
    const deleteModalClose = document.getElementById('deleteModalClose');
    const deleteCancelBtn = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
    const deleteCustomerName = document.getElementById('deleteCustomerName');
    let currentDeletingCustomerId = null;

    // Delete Customer Function
    function deleteCustomer(customerId, customerName) {
        currentDeletingCustomerId = customerId;
        deleteCustomerName.textContent = customerName;
        deleteModal.classList.add('show');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('show');
        currentDeletingCustomerId = null;
    }

    function confirmDelete() {
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        fetch('../../../../server/api/customer_supplier/customers/customer-delete.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: currentDeletingCustomerId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                closeDeleteModal();
                loadCustomers();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error', 'Failed to delete customer', 'error');
        })
        .finally(() => {
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Customer';
        });
    }

    // Delete Modal Event Listeners
    deleteModalClose.addEventListener('click', closeDeleteModal);
    deleteCancelBtn.addEventListener('click', closeDeleteModal);
    deleteConfirmBtn.addEventListener('click', confirmDelete);
    
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            closeDeleteModal();
        }
    });

    // View Modal Elements
    const viewModal = document.getElementById('viewModal');
    const viewModalClose = document.getElementById('viewModalClose');
    const viewCloseBtn = document.getElementById('viewCloseBtn');
    const viewEditBtn = document.getElementById('viewEditBtn');
    let currentViewingCustomerId = null;

    // View Modal Functions
    function openViewModal(customerId) {
        currentViewingCustomerId = customerId;
        
        // Fetch customer data
        fetch(`../../../../server/api/customer_supplier/customers/customer-edit.php?id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateViewModal(data.customer);
                    viewModal.classList.add('show');
                } else {
                    showNotification('Error', data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error', 'Failed to load customer data', 'error');
            });
    }

    function populateViewModal(customer) {
        document.getElementById('viewCustomerCode').textContent = customer.customer_code;
        document.getElementById('viewCustomerName').textContent = customer.customer_name;
        document.getElementById('viewShopName').textContent = customer.shop_name || '-';
        
        // Load and display customer type
        if (customer.customer_type_id) {
            fetch('../../../../server/api/customer_supplier/customers/customer-types.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const type = data.types.find(t => t.id == customer.customer_type_id);
                        document.getElementById('viewCustomerType').textContent = type ? type.type_name : '-';
                    }
                })
                .catch(() => {
                    document.getElementById('viewCustomerType').textContent = '-';
                });
        } else {
            document.getElementById('viewCustomerType').textContent = '-';
        }
        
        // Display customer category
        document.getElementById('viewCustomerCategory').textContent = customer.customer_category_name || '-';
        
        // Display brand name
        document.getElementById('viewBrandName').textContent = customer.brand_name || '-';
        
        document.getElementById('viewAddress').textContent = customer.address || '';
        document.getElementById('viewPoBoxNo').textContent = customer.po_box_no || '';
        // Load and display company
        fetch('../../../../server/api/customer_supplier/customers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && customer.company_id) {
                    const company = data.companies.find(c => c.id == customer.company_id);
                    document.getElementById('viewCompany').textContent = company ? company.company_name : '-';
                } else {
                    document.getElementById('viewCompany').textContent = '-';
                }
            })
            .catch(() => {
                document.getElementById('viewCompany').textContent = '-';
            });
        
        document.getElementById('viewPrimaryPhone').textContent = customer.primary_phone || '';
        document.getElementById('viewSecondaryPhone').textContent = customer.secondary_phone || '';
        document.getElementById('viewEmail').textContent = customer.email || '';
        document.getElementById('viewIdentityCard').textContent = customer.identity_card_no || '';
        document.getElementById('viewLicenseNo').textContent = customer.license_no || '';
        
        // Fetch and display related data
        if (customer.associated_sales_officer_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-employees.php`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const officer = data.employees.find(e => e.id == customer.associated_sales_officer_id);
                        document.getElementById('viewSalesOfficer').textContent = officer ? officer.full_name : '';
                    }
                });
        } else {
            document.getElementById('viewSalesOfficer').textContent = '';
        }
        
        if (customer.supplier_man_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-employees.php`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const supplierMan = data.employees.find(e => e.id == customer.supplier_man_id);
                        document.getElementById('viewSupplierMan').textContent = supplierMan ? supplierMan.full_name : '';
                    }
                });
        } else {
            document.getElementById('viewSupplierMan').textContent = '';
        }

        // Project
        if (customer.project_id) {
            const projLabel = customer.project_code
                ? `${customer.project_code} - ${customer.project_name}${customer.project_city_name ? ' - ' + customer.project_city_name : ''}`
                : '-';
            document.getElementById('viewProject').textContent = projLabel;
        } else {
            document.getElementById('viewProject').textContent = '-';
        }
        
        // Territory fields
        if (customer.country_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-countries.php`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const country = data.countries.find(c => c.id == customer.country_id);
                        document.getElementById('viewCountry').textContent = country ? country.country_name : '';
                    }
                });
        } else {
            document.getElementById('viewCountry').textContent = '';
        }
        
        if (customer.region_id && customer.country_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-regions.php?country_id=${customer.country_id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const region = data.regions.find(r => r.id == customer.region_id);
                        document.getElementById('viewRegion').textContent = region ? region.region_name : '';
                    }
                });
        } else {
            document.getElementById('viewRegion').textContent = '';
        }
        
        if (customer.city_id && customer.region_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-cities.php?region_id=${customer.region_id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const city = data.cities.find(c => c.id == customer.city_id);
                        document.getElementById('viewCity').textContent = city ? city.city_name : '';
                    }
                });
        } else {
            document.getElementById('viewCity').textContent = '';
        }
        
        if (customer.city_zone_id && customer.city_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-city-zones.php?city_id=${customer.city_id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const zone = data.city_zones.find(z => z.id == customer.city_zone_id);
                        document.getElementById('viewCityZone').textContent = zone ? zone.city_zone_name : '';
                    }
                });
        } else {
            document.getElementById('viewCityZone').textContent = '';
        }
        
        if (customer.area_id && customer.city_zone_id) {
            fetch(`../../../../server/api/customer_supplier/customers/get-areas.php?city_zone_id=${customer.city_zone_id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const area = data.areas.find(a => a.id == customer.area_id);
                        document.getElementById('viewArea').textContent = area ? area.area_name : '';
                    }
                });
        } else {
            document.getElementById('viewArea').textContent = '';
        }
        
        // Taxation
        document.getElementById('viewIsSalesTaxRegistered').textContent = customer.is_sales_tax_registered ? 'Yes' : 'No';
        document.getElementById('viewStrn').textContent = customer.strn || '';
        document.getElementById('viewIsFiler').textContent = customer.is_filer ? 'Yes' : 'No';
        document.getElementById('viewNtn').textContent = customer.ntn || '';
        document.getElementById('viewAdvanceIncomeTax').textContent = `${parseFloat(customer.advance_income_tax_percentage || 0).toFixed(2)}%`;
        document.getElementById('viewDefaultDiscount').textContent = `${parseFloat(customer.default_discount_percentage || 0).toFixed(2)}%`;
        
        // Financial
        document.getElementById('viewBalanceLimit').textContent = `${currencySymbol}${parseFloat(customer.credit_limit || 0).toFixed(2)}`;
        document.getElementById('viewBalancePeriodLimit').textContent = `${customer.credit_period_limit_days || 0} days`;
        document.getElementById('viewOpeningDebit').textContent = `${currencySymbol}${parseFloat(customer.opening_debit_amount || 0).toFixed(2)}`;
        document.getElementById('viewOpeningCredit').textContent = `${currencySymbol}${parseFloat(customer.opening_credit_amount || 0).toFixed(2)}`;
        
        // Load opening invoices
        loadViewOpeningInvoices(customer.id);
        
        // Load sub accounts
        loadViewSubAccounts(customer.id);
        
        document.getElementById('viewCurrentBalance').textContent = `${currencySymbol}${parseFloat(customer.current_balance || 0).toFixed(2)}`;
        
        // Other
        document.getElementById('viewIsWholesaler').textContent = customer.is_wholesaler ? 'Yes' : 'No';
        document.getElementById('viewOutStation').textContent = customer.is_out_station ? 'Yes' : 'No';
        document.getElementById('viewStatus').innerHTML = customer.is_blacklisted ? 
            '<span class="status-badge status-blacklisted">Blacklisted</span>' : 
            '<span class="status-badge status-active">Active</span>';
        document.getElementById('viewCreatedAt').textContent = new Date(customer.created_at).toLocaleDateString();
        document.getElementById('viewUpdatedAt').textContent = new Date(customer.updated_at).toLocaleDateString();
    }

    function closeViewModal() {
        viewModal.classList.remove('show');
        currentViewingCustomerId = null;
    }
    
    function loadViewOpeningInvoices(customerId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-opening-invoices.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('viewInvoicesTableBody');
                tbody.innerHTML = '';
                
                if (data.success && data.invoices && data.invoices.length > 0) {
                    data.invoices.forEach(invoice => {
                        // Fetch supplier and employee names
                        let distributionName = '-';
                        let salesOfficerName = '-';
                        
                        if (invoice.distribution_id) {
                            const supplier = editSuppliers.find(s => s.id == invoice.distribution_id);
                            distributionName = supplier ? supplier.supplier_name : '-';
                        }
                        
                        if (invoice.employee_id) {
                            const employee = editEmployees.find(e => e.id == invoice.employee_id);
                            salesOfficerName = employee ? employee.full_name : '-';
                        }
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${distributionName}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${salesOfficerName}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${invoice.invoice_number || '-'}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${currencySymbol}${parseFloat(invoice.debit || 0).toFixed(2)}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${invoice.invoice_date ? new Date(invoice.invoice_date).toLocaleDateString() : '-'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding: 12px; border: 1px solid var(--border-default); text-align: center; color: var(--subtext);">No invoices</td></tr>';
                }
            });
    }
    
    function loadViewSubAccounts(customerId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-sub-accounts.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('viewSubAccountsTableBody');
                tbody.innerHTML = '';
                
                if (data.success && data.sub_accounts && data.sub_accounts.length > 0) {
                    data.sub_accounts.forEach((subAccount, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${index + 1}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${subAccount.sub_account_name}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${currencySymbol}${parseFloat(subAccount.debit || 0).toFixed(2)}</td>
                            <td style="padding: 12px; border: 1px solid var(--border-default);">${currencySymbol}${parseFloat(subAccount.credit || 0).toFixed(2)}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="padding: 12px; border: 1px solid var(--border-default); text-align: center; color: var(--subtext);">No sub accounts</td></tr>';
                }
            });
    }

    // View Modal Event Listeners
    viewModalClose.addEventListener('click', closeViewModal);
    viewCloseBtn.addEventListener('click', closeViewModal);
    
    viewEditBtn.addEventListener('click', function() {
        closeViewModal();
        openEditModal(currentViewingCustomerId);
    });
    
    viewModal.addEventListener('click', function(e) {
        if (e.target === viewModal) {
            closeViewModal();
        }
    });

    // Export dropdown functionality
    const exportBtn = document.getElementById('exportBtn');
    const exportDropdown = document.getElementById('exportDropdown');
    
    exportBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        exportDropdown.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        exportDropdown.classList.remove('show');
    });
    
    // Handle export options
    exportDropdown.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            const exportType = e.target.dataset.export;
            exportDropdown.classList.remove('show');
            
            if (exportType === 'print') {
                // Redirect to print page
                window.open('customer-print.php', '_blank');
            } else if (exportType === 'excel') {
                // Download Excel file
                window.location.href = '../../../../server/api/customer_supplier/customers/customer-export.php';
                showNotification('Export Started', 'Excel file download started...', 'success');
            } else if (exportType === 'json') {
                // Download JSON file
                window.location.href = '../../../../server/api/customer_supplier/customers/customer-export-json.php';
                showNotification('Export Started', 'JSON file download started...', 'success');
            }
        }
    });

    // Edit Modal Elements
    const editModal = document.getElementById('editModal');
    const editModalClose = document.getElementById('editModalClose');
    const editCancelBtn = document.getElementById('editCancelBtn');
    const editCustomerForm = document.getElementById('editCustomerForm');
    let currentEditingCustomerId = null;

    // Action buttons
    customersTable.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;

        const row = target.closest('tr');
        const customerName = row.querySelector('.customer-name').textContent;
        const customerId = row.dataset.id;

        if (target.classList.contains('edit')) {
            if (!userPermissions.includes('Edit')) {
                showNotification('Access Denied', 'You do not have permission to edit customers', 'error');
                return;
            }
            openEditModal(customerId);
        } else if (target.classList.contains('delete')) {
            if (!userPermissions.includes('Delete')) {
                showNotification('Access Denied', 'You do not have permission to delete customers', 'error');
                return;
            }
            deleteCustomer(customerId, customerName);
        } else if (target.querySelector('.fa-eye')) {
            openViewModal(customerId);
        }
    });

    // Initialize Select2 for edit modal
    $(document).ready(function() {
        $('#editCompany').select2({ placeholder: 'Select Company', allowClear: false, dropdownParent: $('#editModal') });
        $('#editCustomerType').select2({ placeholder: 'Select Customer Type', allowClear: true, dropdownParent: $('#editModal') });
        $('#editCustomerCategory').select2({ placeholder: 'Select Customer Category', allowClear: true, dropdownParent: $('#editModal') });
        $('#editBrandName').select2({ placeholder: 'Select Brand Name', allowClear: true, dropdownParent: $('#editModal') });
        $('#editSalesOfficer').select2({ placeholder: 'Select Sales Officer', allowClear: true, dropdownParent: $('#editModal') });
        $('#editCountry').select2({ placeholder: 'Select Country', allowClear: true, dropdownParent: $('#editModal') });
        $('#editRegion').select2({ placeholder: 'Select Region', allowClear: true, dropdownParent: $('#editModal') });
        $('#editCity').select2({ placeholder: 'Select City', allowClear: true, dropdownParent: $('#editModal') });
        $('#editCityZone').select2({ placeholder: 'Select City Zone', allowClear: true, dropdownParent: $('#editModal') });
        $('#editArea').select2({ placeholder: 'Select Area', allowClear: true, dropdownParent: $('#editModal') });
        $('#editSupplierMan').select2({ placeholder: 'Select Supplier Man', allowClear: true, dropdownParent: $('#editModal') });
        $('#editProject').select2({ placeholder: 'Select Project', allowClear: true, dropdownParent: $('#editModal') });
        
        // Load initial data
        loadEditCountries();
        loadEditSalesOfficers();
        loadEditSupplierMen();
        loadEditCompanies();
        loadEditCustomerTypes();
        loadEditCustomerCategories();
        loadEditBrands();
        loadEditProjects();
        
        // Territory cascading
        $('#editCountry').on('change', function() {
            const countryId = this.value;
            $('#editRegion').prop('disabled', !countryId).empty().append('<option value="">Select Region</option>').trigger('change');
            $('#editCity').prop('disabled', true).empty().append('<option value="">Select City</option>').trigger('change');
            $('#editCityZone').prop('disabled', true).empty().append('<option value="">Select City Zone</option>').trigger('change');
            $('#editArea').prop('disabled', true).empty().append('<option value="">Select Area</option>').trigger('change');
            if (countryId) loadEditRegions(countryId);
        });
        
        $('#editRegion').on('change', function() {
            const regionId = this.value;
            $('#editCity').prop('disabled', !regionId).empty().append('<option value="">Select City</option>').trigger('change');
            $('#editCityZone').prop('disabled', true).empty().append('<option value="">Select City Zone</option>').trigger('change');
            $('#editArea').prop('disabled', true).empty().append('<option value="">Select Area</option>').trigger('change');
            if (regionId) loadEditCities(regionId);
        });
        
        $('#editCity').on('change', function() {
            const cityId = this.value;
            $('#editCityZone').prop('disabled', !cityId).empty().append('<option value="">Select City Zone</option>').trigger('change');
            $('#editArea').prop('disabled', true).empty().append('<option value="">Select Area</option>').trigger('change');
            if (cityId) loadEditCityZones(cityId);
        });
        
        $('#editCityZone').on('change', function() {
            const cityZoneId = this.value;
            $('#editArea').prop('disabled', !cityZoneId).empty().append('<option value="">Select Area</option>').trigger('change');
            if (cityZoneId) loadEditAreas(cityZoneId);
        });
    });
    
    function loadEditCountries() {
        fetch('../../../../server/api/customer_supplier/customers/get-countries.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.countries.forEach(country => {
                        $('#editCountry').append(new Option(country.country_name, country.id));
                    });
                }
            });
    }
    
    function loadEditRegions(countryId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-regions.php?country_id=${countryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#editRegion').empty().append('<option value="">Select Region</option>');
                    data.regions.forEach(region => {
                        $('#editRegion').append(new Option(region.region_name, region.id));
                    });
                }
            });
    }
    
    function loadEditCities(regionId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-cities.php?region_id=${regionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#editCity').empty().append('<option value="">Select City</option>');
                    data.cities.forEach(city => {
                        $('#editCity').append(new Option(city.city_name, city.id));
                    });
                }
            });
    }
    
    function loadEditCityZones(cityId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-city-zones.php?city_id=${cityId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#editCityZone').empty().append('<option value="">Select City Zone</option>');
                    data.city_zones.forEach(zone => {
                        $('#editCityZone').append(new Option(zone.city_zone_name, zone.id));
                    });
                }
            });
    }
    
    function loadEditAreas(cityZoneId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-areas.php?city_zone_id=${cityZoneId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#editArea').empty().append('<option value="">Select Area</option>');
                    data.areas.forEach(area => {
                        $('#editArea').append(new Option(area.area_name, area.id));
                    });
                }
            });
    }
    
    function loadEditSalesOfficers() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editEmployees = data.employees;
                    data.employees.forEach(employee => {
                        $('#editSalesOfficer').append(new Option(employee.full_name, employee.id));
                    });
                }
            });
    }
    
    function loadEditCompanies() {
        fetch('../../../../server/api/customer_supplier/customers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.companies.forEach(company => {
                        $('#editCompany').append(new Option(company.company_name, company.id));
                    });
                }
            });
    }
    
    function loadEditCustomerTypes() {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#editCustomerType').empty().append('<option value="">Select Customer Type</option>');
                    data.types.forEach(type => {
                        $('#editCustomerType').append(new Option(type.type_name, type.id));
                    });
                }
            });
    }
    
    function loadEditCustomerCategories() {
        fetch('../../../../server/api/customer_supplier/customers/get-customer-categories.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editCustomerCategories = data.categories;
                    $('#editCustomerCategory').empty().append('<option value="">Select Customer Category</option>');
                    data.categories.forEach(category => {
                        $('#editCustomerCategory').append(new Option(category.category_name, category.id));
                    });
                }
            });
    }
    
    function loadEditBrands() {
        fetch('../../../../server/api/customer_supplier/customers/get-brands.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editBrands = data.brands;
                    $('#editBrandName').empty().append('<option value="">Select Brand Name</option>');
                    data.brands.forEach(brand => {
                        $('#editBrandName').append(new Option(brand.brand_name, brand.id));
                    });
                }
            });
    }
    
    function loadEditSupplierMen() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.employees.forEach(employee => {
                        $('#editSupplierMan').append(new Option(employee.full_name, employee.id));
                    });
                }
            });
    }

    function loadEditProjects() {
        return fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#editProject').empty().append('<option value="">Select Project</option>');
                    data.data.forEach(project => {
                        const label = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
                        $('#editProject').append(new Option(label, project.id));
                    });
                }
            });
    }
    
    // Load suppliers for edit modal
    fetch('../../../../server/api/customer_supplier/customers/get-suppliers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                editSuppliers = data.suppliers;
            }
        });

    // Edit Modal Functions
    function openEditModal(customerId) {
        currentEditingCustomerId = customerId;
        
        // Fetch customer data
        fetch(`../../../../server/api/customer_supplier/customers/customer-edit.php?id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateEditForm(data.customer);
                    editModal.classList.add('show');
                } else {
                    showNotification('Error', data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error', 'Failed to load customer data', 'error');
            });
    }

    function populateEditForm(customer) {
        const editOpeningDebit = document.getElementById('editOpeningDebit');
        const editOpeningCredit = document.getElementById('editOpeningCredit');
        const editIsSalesTaxRegistered = document.getElementById('editIsSalesTaxRegistered');
        const editIsFiler = document.getElementById('editIsFiler');
        
        document.getElementById('editCustomerCode').value = customer.customer_code;
        document.getElementById('editCustomerName').value = customer.customer_name;
        document.getElementById('editShopName').value = customer.shop_name || '';
        
        // Set customer type
        setTimeout(() => {
            $('#editCustomerType').val(customer.customer_type_id || '').trigger('change');
            $('#editCustomerCategory').val(customer.customer_category_id || '').trigger('change');
            $('#editBrandName').val(customer.brand_id || '').trigger('change');
        }, 100);
        
        document.getElementById('editAddress').value = customer.address || '';
        document.getElementById('editPoBoxNo').value = customer.po_box_no || '';
        document.getElementById('editPrimaryPhone').value = customer.primary_phone || '';
        document.getElementById('editSecondaryPhone').value = customer.secondary_phone || '';
        document.getElementById('editEmail').value = customer.email || '';
        document.getElementById('editIdentityCard').value = customer.identity_card_no || '';
        document.getElementById('editLicenseNo').value = customer.license_no || '';
        
        // Set company (after options are loaded)
        setTimeout(() => {
            $('#editCompany').val(customer.company_id || '').trigger('change');
        }, 100);
        
        // Sales Officer
        $('#editSalesOfficer').val(customer.associated_sales_officer_id || '').trigger('change');
        
        // Supplier Man
        $('#editSupplierMan').val(customer.supplier_man_id || '').trigger('change');

        // Project — wait for options to load then set value
        loadEditProjects().then(() => {
            $('#editProject').val(customer.project_id || '').trigger('change');
        });
        
        // Territory - load cascading data
        if (customer.country_id) {
            $('#editCountry').val(customer.country_id).trigger('change');
            setTimeout(() => {
                if (customer.region_id) {
                    loadEditRegions(customer.country_id).then(() => {
                        $('#editRegion').val(customer.region_id).trigger('change');
                        if (customer.city_id) {
                            setTimeout(() => {
                                loadEditCities(customer.region_id).then(() => {
                                    $('#editCity').val(customer.city_id).trigger('change');
                                    if (customer.city_zone_id) {
                                        setTimeout(() => {
                                            loadEditCityZones(customer.city_id).then(() => {
                                                $('#editCityZone').val(customer.city_zone_id).trigger('change');
                                                if (customer.area_id) {
                                                    setTimeout(() => {
                                                        loadEditAreas(customer.city_zone_id).then(() => {
                                                            $('#editArea').val(customer.area_id).trigger('change');
                                                        });
                                                    }, 100);
                                                }
                                            });
                                        }, 100);
                                    }
                                });
                            }, 100);
                        }
                    });
                }
            }, 100);
        }
        
        // Taxation
        editIsSalesTaxRegistered.checked = customer.is_sales_tax_registered;
        document.getElementById('editStrnGroup').style.display = customer.is_sales_tax_registered ? 'flex' : 'none';
        document.getElementById('editStrn').value = customer.strn || '';
        
        editIsFiler.checked = customer.is_filer;
        document.getElementById('editNtnGroup').style.display = customer.is_filer ? 'flex' : 'none';
        document.getElementById('editNtn').value = customer.ntn || '';
        
        document.getElementById('editAdvanceIncomeTax').value = customer.advance_income_tax_percentage || 0;
        document.getElementById('editDefaultDiscount').value = customer.default_discount_percentage || 0;
        document.getElementById('editBalanceLimit').value = customer.credit_limit || 0;
        document.getElementById('editBalancePeriodLimit').value = customer.credit_period_limit_days || 0;
        
        editOpeningDebit.value = customer.opening_debit_amount || 0;
        editOpeningCredit.value = customer.opening_credit_amount || 0;
        
        document.getElementById('editIsWholesaler').checked = customer.is_wholesaler;
        document.getElementById('editBlacklist').checked = customer.is_blacklisted;
        document.getElementById('editOutStation').checked = !!customer.is_out_station;
        
        // Load opening invoices
        loadEditOpeningInvoices(customer.id).then(hasInvoices => {
            // Only set readonly if there are invoices
            if (hasInvoices) {
                editOpeningDebit.setAttribute('readonly', 'readonly');
            } else {
                editOpeningDebit.removeAttribute('readonly');
            }
        });
        
        // Load sub accounts
        loadEditSubAccounts(customer.id);
        
        // Set up mutual exclusion for edit form
        editOpeningDebit.disabled = false;
        editOpeningCredit.disabled = false;
        
        if (parseFloat(customer.opening_credit_amount || 0) > 0) {
            editOpeningDebit.disabled = true;
        }
        
        // Taxation field visibility
        editIsSalesTaxRegistered.addEventListener('change', function() {
            document.getElementById('editStrnGroup').style.display = this.checked ? 'flex' : 'none';
            if (!this.checked) document.getElementById('editStrn').value = '';
        });
        
        editIsFiler.addEventListener('change', function() {
            document.getElementById('editNtnGroup').style.display = this.checked ? 'flex' : 'none';
            if (!this.checked) document.getElementById('editNtn').value = '';
        });
        
        // Opening credit mutual exclusion
        editOpeningCredit.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                editOpeningDebit.disabled = true;
                editOpeningDebit.value = '';
                document.getElementById('editInvoicesTableBody').innerHTML = '';
            } else {
                editOpeningDebit.disabled = false;
            }
        });
        
        // Opening debit input handler
        editOpeningDebit.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                editOpeningCredit.disabled = true;
                editOpeningCredit.value = '';
            } else {
                editOpeningCredit.disabled = false;
            }
        });
    }
    
    function loadEditOpeningInvoices(customerId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-opening-invoices.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('editInvoicesTableBody');
                tbody.innerHTML = '';
                if (data.success && data.invoices && data.invoices.length > 0) {
                    data.invoices.forEach(invoice => {
                        addEditInvoiceRow(invoice);
                    });
                    calculateEditInvoiceTotal();
                    return true; // Has invoices
                }
                return false; // No invoices
            });
    }
    
    function loadEditSubAccounts(customerId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-sub-accounts.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.sub_accounts) {
                    const tbody = document.getElementById('editSubAccountsTableBody');
                    tbody.innerHTML = '';
                    editSubAccountCounter = 0;
                    data.sub_accounts.forEach(subAccount => {
                        addEditSubAccountRow(subAccount);
                    });
                }
            });
    }
    
    function addEditInvoiceRow(invoice = null) {
        const tbody = document.getElementById('editInvoicesTableBody');
        const rowId = Date.now();
        const row = document.createElement('tr');
        row.dataset.rowId = rowId;
        
        const distributionName = invoice && invoice.distribution_id ? 
            (editSuppliers.find(s => s.id == invoice.distribution_id)?.supplier_name || '') : '';
        const salesOfficerName = invoice && invoice.employee_id ? 
            (editEmployees.find(e => e.id == invoice.employee_id)?.full_name || '') : '';
        
        row.innerHTML = `
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="edit-invoice-distribution-search" list="edit-suppliers-list-${rowId}" value="${distributionName}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Search distribution...">
                <datalist id="edit-suppliers-list-${rowId}">
                    ${editSuppliers.map(s => `<option value="${s.supplier_name}" data-id="${s.id}">`).join('')}
                </datalist>
                <input type="hidden" class="edit-invoice-distribution" value="${invoice?.distribution_id || ''}">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="edit-invoice-sales-officer-search" list="edit-employees-list-${rowId}" value="${salesOfficerName}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Search sales officer...">
                <datalist id="edit-employees-list-${rowId}">
                    ${editEmployees.map(e => `<option value="${e.full_name}" data-id="${e.id}">`).join('')}
                </datalist>
                <input type="hidden" class="edit-invoice-sales-officer" value="${invoice?.employee_id || ''}">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="edit-invoice-number" value="${invoice?.invoice_number || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Invoice #">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="number" class="edit-invoice-debit" value="${invoice?.debit || ''}" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="date" class="edit-invoice-date" value="${invoice?.invoice_date || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default); text-align: center;">
                <button type="button" class="btn-remove-edit-invoice" style="background: var(--error); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-minus"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
        
        const distSearch = row.querySelector('.edit-invoice-distribution-search');
        const distHidden = row.querySelector('.edit-invoice-distribution');
        distSearch.addEventListener('input', function() {
            const supplier = editSuppliers.find(s => s.supplier_name === this.value);
            distHidden.value = supplier ? supplier.id : '';
        });
        
        const salesSearch = row.querySelector('.edit-invoice-sales-officer-search');
        const salesHidden = row.querySelector('.edit-invoice-sales-officer');
        salesSearch.addEventListener('input', function() {
            const employee = editEmployees.find(e => e.full_name === this.value);
            salesHidden.value = employee ? employee.id : '';
        });
        
        row.querySelector('.edit-invoice-debit').addEventListener('input', calculateEditInvoiceTotal);
        row.querySelector('.btn-remove-edit-invoice').addEventListener('click', function() {
            row.remove();
            calculateEditInvoiceTotal();
        });
    }
    
    function calculateEditInvoiceTotal() {
        const rows = document.getElementById('editInvoicesTableBody').querySelectorAll('tr');
        if (rows.length === 0) return; // Don't clear if no invoice rows
        
        let total = 0;
        rows.forEach(row => {
            const debit = parseFloat(row.querySelector('.edit-invoice-debit').value) || 0;
            total += debit;
        });
        const editOpeningDebit = document.getElementById('editOpeningDebit');
        editOpeningDebit.value = total > 0 ? total.toFixed(2) : '';
        if (total > 0) {
            document.getElementById('editOpeningCredit').disabled = true;
        } else {
            document.getElementById('editOpeningCredit').disabled = false;
        }
    }
    
    function addEditSubAccountRow(subAccount = null) {
        const tbody = document.getElementById('editSubAccountsTableBody');
        editSubAccountCounter++;
        const row = document.createElement('tr');
        if (subAccount && subAccount.id) {
            row.dataset.subAccountId = subAccount.id;
        }
        row.innerHTML = `
            <td style="padding: 12px; border: 1px solid var(--border-default);">${editSubAccountCounter}</td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="text" class="edit-sub-account-name" value="${subAccount?.sub_account_name || ''}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Enter sub account name" required>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="number" class="edit-sub-account-debit" value="${subAccount?.debit || ''}" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00" ${subAccount && subAccount.credit > 0 ? 'disabled' : ''}>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="number" class="edit-sub-account-credit" value="${subAccount?.credit || ''}" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00" ${subAccount && subAccount.debit > 0 ? 'disabled' : ''}>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default); text-align: center;">
                <button type="button" class="btn-remove-edit-sub-account" style="background: var(--error); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-minus"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
        
        const debitInput = row.querySelector('.edit-sub-account-debit');
        const creditInput = row.querySelector('.edit-sub-account-credit');
        
        debitInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                creditInput.disabled = true;
                creditInput.value = '';
            } else {
                creditInput.disabled = false;
            }
        });
        
        creditInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                debitInput.disabled = true;
                debitInput.value = '';
            } else {
                debitInput.disabled = false;
            }
        });
        
        row.querySelector('.btn-remove-edit-sub-account').addEventListener('click', function() {
            row.remove();
            renumberEditSubAccounts();
        });
    }
    
    function renumberEditSubAccounts() {
        const rows = document.getElementById('editSubAccountsTableBody').querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
        });
        editSubAccountCounter = rows.length;
    }
    
    function collectEditInvoiceData() {
        const rows = document.getElementById('editInvoicesTableBody').querySelectorAll('tr');
        const invoices = [];
        rows.forEach(row => {
            const distribution = row.querySelector('.edit-invoice-distribution').value;
            const salesOfficer = row.querySelector('.edit-invoice-sales-officer').value;
            const invoiceNumber = row.querySelector('.edit-invoice-number').value.trim();
            const debit = parseFloat(row.querySelector('.edit-invoice-debit').value) || 0;
            const invoiceDate = row.querySelector('.edit-invoice-date').value;
            
            if (distribution || salesOfficer || invoiceNumber || debit > 0 || invoiceDate) {
                invoices.push({
                    distribution,
                    salesOfficer,
                    invoiceNumber,
                    debit,
                    invoiceDate
                });
            }
        });
        return invoices;
    }
    
    function collectEditSubAccountData() {
        const rows = document.getElementById('editSubAccountsTableBody').querySelectorAll('tr');
        const subAccounts = [];
        rows.forEach(row => {
            const name = row.querySelector('.edit-sub-account-name').value.trim();
            const debit = parseFloat(row.querySelector('.edit-sub-account-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.edit-sub-account-credit').value) || 0;
            if (name) {
                const subAccount = { 
                    sub_account_name: name,
                    debit: debit,
                    credit: credit
                };
                if (row.dataset.subAccountId) {
                    subAccount.id = parseInt(row.dataset.subAccountId);
                }
                subAccounts.push(subAccount);
            }
        });
        return subAccounts;
    }
    
    // Add invoice button
    document.getElementById('editAddInvoiceBtn').addEventListener('click', function() {
        addEditInvoiceRow();
    });
    
    // Add sub account button
    document.getElementById('editAddSubAccountBtn').addEventListener('click', function() {
        addEditSubAccountRow();
    });

    function closeEditModal() {
        editModal.classList.remove('show');
        currentEditingCustomerId = null;
        editCustomerForm.reset();
        // Re-enable both fields when closing
        document.getElementById('editOpeningDebit').disabled = false;
        document.getElementById('editOpeningCredit').disabled = false;
        document.getElementById('editOpeningDebit').removeAttribute('readonly');
        // Reset territory dropdowns
        $('#editRegion').prop('disabled', true).empty().append('<option value="">Select Region</option>').trigger('change');
        $('#editCity').prop('disabled', true).empty().append('<option value="">Select City</option>').trigger('change');
        $('#editCityZone').prop('disabled', true).empty().append('<option value="">Select City Zone</option>').trigger('change');
        $('#editArea').prop('disabled', true).empty().append('<option value="">Select Area</option>').trigger('change');
        $('#editCountry').val('').trigger('change');
        $('#editSalesOfficer').val('').trigger('change');
        $('#editProject').val('').trigger('change');
        $('#editCustomerCategory').val('').trigger('change');
        $('#editBrandName').val('').trigger('change');
        // Hide conditional fields
        document.getElementById('editStrnGroup').style.display = 'none';
        document.getElementById('editNtnGroup').style.display = 'none';
        // Clear tables
        document.getElementById('editInvoicesTableBody').innerHTML = '';
        document.getElementById('editSubAccountsTableBody').innerHTML = '';
        editSubAccountCounter = 0;
    }

    // Edit Modal Event Listeners
    editModalClose.addEventListener('click', closeEditModal);
    editCancelBtn.addEventListener('click', closeEditModal);
    
    editModal.addEventListener('click', function(e) {
        if (e.target === editModal) {
            closeEditModal();
        }
    });

    // Edit Form Submission
    editCustomerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            id: currentEditingCustomerId,
            companyId: $('#editCompany').val(),
            customerTypeId: $('#editCustomerType').val() || null,
            customerCategoryId: $('#editCustomerCategory').val() || null,
            brandId: $('#editBrandName').val() || null,
            customerName: document.getElementById('editCustomerName').value.trim(),
            shopName: document.getElementById('editShopName').value.trim(),
            address: document.getElementById('editAddress').value.trim(),
            poBoxNo: document.getElementById('editPoBoxNo').value.trim(),
            primaryPhone: document.getElementById('editPrimaryPhone').value.trim(),
            secondaryPhone: document.getElementById('editSecondaryPhone').value.trim(),
            email: document.getElementById('editEmail').value.trim(),
            identityCard: document.getElementById('editIdentityCard').value.trim(),
            licenseNo: document.getElementById('editLicenseNo').value.trim(),
            salesOfficerId: $('#editSalesOfficer').val() || null,
            supplierManId: $('#editSupplierMan').val() || null,
            projectId: $('#editProject').val() || null,
            countryId: $('#editCountry').val() || null,
            regionId: $('#editRegion').val() || null,
            cityId: $('#editCity').val() || null,
            cityZoneId: $('#editCityZone').val() || null,
            areaId: $('#editArea').val() || null,
            isSalesTaxRegistered: document.getElementById('editIsSalesTaxRegistered').checked ? 1 : 0,
            strn: document.getElementById('editIsSalesTaxRegistered').checked ? document.getElementById('editStrn').value.trim() : null,
            isFiler: document.getElementById('editIsFiler').checked ? 1 : 0,
            ntn: document.getElementById('editIsFiler').checked ? document.getElementById('editNtn').value.trim() : null,
            advanceIncomeTax: document.getElementById('editAdvanceIncomeTax').value || 0,
            defaultDiscount: document.getElementById('editDefaultDiscount').value || 0,
            balanceLimit: document.getElementById('editBalanceLimit').value || 0,
            balancePeriodLimit: document.getElementById('editBalancePeriodLimit').value || 0,
            openingDebit: document.getElementById('editOpeningDebit').value || 0,
            openingCredit: document.getElementById('editOpeningCredit').value || 0,
            isWholesaler: document.getElementById('editIsWholesaler').checked ? 1 : 0,
            blacklist: document.getElementById('editBlacklist').checked ? 1 : 0,
            outStation: document.getElementById('editOutStation').checked ? 1 : 0,
            openingInvoices: collectEditInvoiceData(),
            subAccounts: collectEditSubAccountData()
        };

        const editSaveBtn = document.getElementById('editSaveBtn');
        editSaveBtn.disabled = true;
        editSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

        fetch('../../../../server/api/customer_supplier/customers/customer-edit.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                closeEditModal();
                loadCustomers(); // Reload the customer list
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error', 'Failed to update customer', 'error');
        })
        .finally(() => {
            editSaveBtn.disabled = false;
            editSaveBtn.innerHTML = '<i class="fas fa-save"></i> Update Customer';
        });
    });

    // Pagination
    const prevPage = document.getElementById('prevPage');
    const nextPage = document.getElementById('nextPage');

    prevPage.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadCustomers();
        }
    });

    nextPage.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            loadCustomers();
        }
    });

    // Show notification
    function showNotification(title, message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationTitle = document.getElementById('notificationTitle');
        const notificationMessage = document.getElementById('notificationMessage');

        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');

        // Auto hide after 5 seconds
        setTimeout(function () {
            notification.classList.remove('show');
        }, 5000);
    }

    // Close notification
    const notificationClose = document.getElementById('notificationClose');
    notificationClose.addEventListener('click', function () {
        const notification = document.getElementById('notification');
        notification.classList.remove('show');
    });
    
    // Check permissions
    function checkPermissions() {
        return fetch('../../../../server/api/auth/check-permission.php?category=Customer / Supplier&form_name=New Customer')
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = '../../../../errors/403.php';
                    throw new Error('No permissions');
                }
                if (data.success && data.permissions) {
                    userPermissions = data.permissions;
                }
            });
    }
});
