document.addEventListener('DOMContentLoaded', function () {
    // Check permissions
    fetch('../../../../server/api/auth/check-permission.php?category=Sale&form_name=POS Invoice')
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
    let pageLimit = parseInt(localStorage.getItem('pos_list_page_limit')) || 10;
    const invoicesTable = document.getElementById('invoicesTable').getElementsByTagName('tbody')[0];
    let selectedIds = new Set();

    // ── Multi-select system (same as daily-sale-report) ──────────────────────
    const multiSelects = {
        companies:     { triggerId: 'company-trigger',       optionsId: 'company-options',       labelId: 'company-label',       allLabel: 'All Companies',     searchPlaceholder: 'Search companies...',     selected: {} },
        customers:     { triggerId: 'customer-trigger',      optionsId: 'customer-options',      labelId: 'customer-label',      allLabel: 'All Customers',     searchPlaceholder: 'Search customers...',     selected: {} },
        salesOfficers: { triggerId: 'sales-officer-trigger', optionsId: 'sales-officer-options', labelId: 'sales-officer-label', allLabel: 'All Sales Officers',searchPlaceholder: 'Search officers...',      selected: {} },
        supplierMen:   { triggerId: 'supplier-man-trigger',  optionsId: 'supplier-man-options',  labelId: 'supplier-man-label',  allLabel: 'All Supplier Men',  searchPlaceholder: 'Search supplier men...',  selected: {} },
        invoiceTypes:  { triggerId: 'invoice-type-trigger',  optionsId: 'invoice-type-options',  labelId: 'invoice-type-label',  allLabel: 'All Types',         searchPlaceholder: 'Search types...',         selected: {} },
        vendors:       { triggerId: 'vendor-trigger',        optionsId: 'vendor-options',        labelId: 'vendor-label',        allLabel: 'All Vendors',       searchPlaceholder: 'Search vendors...',       selected: {} },
        cities:        { triggerId: 'city-filter-trigger',   optionsId: 'city-filter-options',   labelId: 'city-filter-label',   allLabel: 'All Cities',        searchPlaceholder: 'Search cities...',        selected: {} },
        cityZones:     { triggerId: 'city-zone-filter-trigger', optionsId: 'city-zone-filter-options', labelId: 'city-zone-filter-label', allLabel: 'All City Zones', searchPlaceholder: 'Search city zones...',  selected: {} },
        areas:         { triggerId: 'area-filter-trigger',   optionsId: 'area-filter-options',   labelId: 'area-filter-label',   allLabel: 'All Areas',         searchPlaceholder: 'Search areas...',         selected: {} },
    };

    function closeAllMultiSelects(exceptOptions) {
        document.querySelectorAll('.multi-select-options.open').forEach(p => { if (p !== exceptOptions) p.classList.remove('open'); });
        document.querySelectorAll('.multi-select-trigger.open').forEach(t => { if (!exceptOptions || t.nextElementSibling !== exceptOptions) t.classList.remove('open'); });
    }

    function initMultiSelect(key) {
        const config = multiSelects[key];
        const trigger = document.getElementById(config.triggerId);
        const optionsEl = document.getElementById(config.optionsId);
        if (!trigger || !optionsEl || optionsEl.dataset.ready === 'true') return;
        optionsEl.dataset.ready = 'true';

        const searchWrap = document.createElement('div');
        searchWrap.className = 'so-search-wrap';
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'so-search-input';
        searchInput.placeholder = config.searchPlaceholder;
        searchInput.autocomplete = 'off';
        searchWrap.appendChild(searchInput);
        optionsEl.appendChild(searchWrap);

        searchInput.addEventListener('input', e => {
            e.stopPropagation();
            const f = e.target.value.toLowerCase();
            optionsEl.querySelectorAll('.multi-select-option').forEach(o => {
                o.style.display = f && !o.textContent.toLowerCase().includes(f) ? 'none' : '';
            });
        });
        searchInput.addEventListener('click', e => e.stopPropagation());

        trigger.addEventListener('click', e => {
            e.stopPropagation();
            const wasOpen = optionsEl.classList.contains('open');
            closeAllMultiSelects(optionsEl);
            trigger.classList.toggle('open', !wasOpen);
            optionsEl.classList.toggle('open', !wasOpen);
            if (!wasOpen) setTimeout(() => searchInput.focus(), 10);
        });
        optionsEl.addEventListener('click', e => e.stopPropagation());
    }

    function renderMultiSelectOptions(key, options) {
        const config = multiSelects[key];
        const optionsEl = document.getElementById(config.optionsId);
        if (!optionsEl) return;
        optionsEl.querySelectorAll('.multi-select-option, .multi-select-empty').forEach(el => el.remove());
        if (options.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'multi-select-empty';
            empty.textContent = 'No options found';
            optionsEl.appendChild(empty);
            return;
        }
        options.forEach(opt => {
            const value = String(opt.value);
            const label = document.createElement('label');
            label.className = 'multi-select-option';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = value;
            cb.checked = !!config.selected[value];
            cb.addEventListener('change', function () {
                if (this.checked) config.selected[value] = opt.label;
                else delete config.selected[value];
                updateMultiSelectLabel(key);
                if (typeof config.onChange === 'function') config.onChange();
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode(opt.label));
            optionsEl.appendChild(label);
        });
        updateMultiSelectLabel(key);
    }

    function updateMultiSelectLabel(key) {
        const config = multiSelects[key];
        const label = document.getElementById(config.labelId);
        if (!label) return;
        const names = Object.values(config.selected);
        label.textContent = names.length === 0 ? config.allLabel : names.join(', ');
    }

    function getSelectedIds(key) {
        return Object.keys(multiSelects[key].selected);
    }

    function resetMultiSelect(key) {
        multiSelects[key].selected = {};
        const optionsEl = document.getElementById(multiSelects[key].optionsId);
        if (optionsEl) {
            optionsEl.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            optionsEl.querySelectorAll('.multi-select-option').forEach(o => o.style.display = '');
            const si = optionsEl.querySelector('.so-search-input');
            if (si) si.value = '';
        }
        updateMultiSelectLabel(key);
    }

    // Init all multi-selects
    Object.keys(multiSelects).forEach(key => initMultiSelect(key));
    document.addEventListener('click', () => closeAllMultiSelects());

    // City -> cascade cityZone & area
    multiSelects.cities.onChange = () => {
        loadCityZones(getSelectedIds('cities'));
        loadAreas([], getSelectedIds('cities'));
    };
    multiSelects.cityZones.onChange = () => {
        loadAreas(getSelectedIds('cityZones'), getSelectedIds('cities'));
    };

    // Populate invoice types statically
    renderMultiSelectOptions('invoiceTypes', [
        { value: 'Cash', label: 'Cash' },
        { value: 'Credit', label: 'Credit' },
        { value: 'OutStation', label: 'Out Station' }
    ]);
    // ─────────────────────────────────────────────────────────────────────────

    // ── Customize Filters ─────────────────────────────────────────────────
    const FILTER_STORAGE_KEY = 'pos_list_hidden_filters';
    const ALL_FILTER_KEYS = [
        { key: 'dateFrom',     label: 'Date From' },
        { key: 'dateTo',       label: 'Date To' },
        { key: 'company',      label: 'Company' },
        { key: 'customer',     label: 'Customer' },
        { key: 'salesOfficer', label: 'Sales Officer' },
        { key: 'supplierMan',  label: 'Supplier Man' },
        { key: 'invoiceType',  label: 'Invoice Type' },
        { key: 'vendor',       label: 'Vendor' },
        { key: 'city',         label: 'City' },
        { key: 'cityZone',     label: 'City Zone' },
        { key: 'area',         label: 'Area' },
        { key: 'project',      label: 'Project' },
    ];

    function getHiddenFilters() {
        try { return JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY)) || []; }
        catch { return []; }
    }

    function applyFilterVisibility() {
        const hidden = getHiddenFilters();
        document.querySelectorAll('.form-row [data-filter-key]').forEach(el => {
            el.style.display = hidden.includes(el.dataset.filterKey) ? 'none' : '';
        });
    }

    function openCustomizeModal() {
        const hidden = getHiddenFilters();
        const list = document.getElementById('filterToggleList');
        list.innerHTML = '';
        ALL_FILTER_KEYS.forEach(({ key, label }) => {
            const isVisible = !hidden.includes(key);
            const item = document.createElement('label');
            item.style.cssText = 'display:flex; align-items:center; gap:8px; padding:8px 12px; border:1.5px solid var(--border-default); border-radius:var(--radius); cursor:pointer; font-size:13px; user-select:none;';
            item.innerHTML = `<input type="checkbox" data-fkey="${key}" ${isVisible ? 'checked' : ''} style="width:auto; cursor:pointer;"> ${label}`;
            list.appendChild(item);
        });
        document.getElementById('customizeFiltersModal').style.display = 'flex';
    }

    document.getElementById('customizeFiltersBtn')?.addEventListener('click', openCustomizeModal);

    document.getElementById('filterCustomizeSelectAll')?.addEventListener('click', () => {
        document.querySelectorAll('#filterToggleList input[type="checkbox"]').forEach(cb => cb.checked = true);
    });

    document.getElementById('saveCustomizeFiltersBtn')?.addEventListener('click', () => {
        const hidden = [];
        document.querySelectorAll('#filterToggleList input[type="checkbox"]').forEach(cb => {
            if (!cb.checked) hidden.push(cb.dataset.fkey);
        });
        localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(hidden));
        applyFilterVisibility();
        document.getElementById('customizeFiltersModal').style.display = 'none';
    });

    document.getElementById('closeCustomizeFiltersBtn')?.addEventListener('click', () => {
        document.getElementById('customizeFiltersModal').style.display = 'none';
    });

    document.getElementById('customizeFiltersModal')?.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });

    // Apply on page load
    applyFilterVisibility();
    // ─────────────────────────────────────────────────────────────────────────

    function updateSelectionUI() {
        const count = selectedIds.size;
        const printSelectedBtn = document.getElementById('printSelectedBtn');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        const selectedCount = document.getElementById('selectedCount');
        const selectedDeleteCount = document.getElementById('selectedDeleteCount');
        if (printSelectedBtn) printSelectedBtn.style.display = count > 0 ? 'inline-flex' : 'none';
        if (deleteSelectedBtn) deleteSelectedBtn.style.display = count > 0 && permissions.includes('Delete') ? 'inline-flex' : 'none';
        if (selectedCount) selectedCount.textContent = count;
        if (selectedDeleteCount) selectedDeleteCount.textContent = count;
        const allCheckboxes = invoicesTable.querySelectorAll('.row-checkbox');
        const selectAll = document.getElementById('selectAllCheckbox');
        if (allCheckboxes.length > 0) {
            selectAll.checked = [...allCheckboxes].every(cb => cb.checked);
            selectAll.indeterminate = !selectAll.checked && [...allCheckboxes].some(cb => cb.checked);
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    function clearSelections() {
        selectedIds.clear();
        updateSelectionUI();
    }

    document.getElementById('selectAllCheckbox').addEventListener('change', function () {
        const checkboxes = invoicesTable.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            const id = parseInt(cb.dataset.id);
            this.checked ? selectedIds.add(id) : selectedIds.delete(id);
        });
        updateSelectionUI();
    });

    document.getElementById('printSelectedBtn').addEventListener('click', function () {
        if (selectedIds.size === 0) return;
        const ids = [...selectedIds].join(',');
        const savedChoice = localStorage.getItem('pos_print_preference');
        const printType = savedChoice || 'full';
        if (printType === 'thermal') {
            [...selectedIds].forEach(id => window.open(`thermal-print.php?id=${id}`, '_blank'));
        } else if (printType === 'thermal2') {
            [...selectedIds].forEach(id => window.open(`thermal-print2.php?id=${id}`, '_blank'));
        } else {
            window.open(`bulk-print.php?ids=${ids}`, '_blank');
        }
    });

    document.getElementById('deleteSelectedBtn')?.addEventListener('click', function () {
        if (selectedIds.size === 0) return;
        deleteInvoiceId = null; // bulk mode
        document.querySelector('#deleteModal p').textContent =
            `Are you sure you want to delete ${selectedIds.size} selected invoice(s)? This action cannot be undone.`;
        document.getElementById('deleteModal').style.display = 'flex';
    });
    
    // Filter elements
    const searchInput = document.getElementById('searchInput');

    function appendArrayParams(params, name, values) {
        values.forEach(v => params.append(name + '[]', v));
    }

    // Load invoices from API
    async function loadInvoices(page = 1) {
        try {
            const params = new URLSearchParams({ page, limit: pageLimit });

            const dateFrom = document.getElementById('dateFrom');
            const dateTo   = document.getElementById('dateTo');
            if (dateFrom.value) params.set('date_from', dateFrom.value);
            if (dateTo.value)   params.set('date_to',   dateTo.value);
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());

            const projectVal = document.getElementById('projectFilter')?.value;
            if (projectVal) params.set('project_id', projectVal);

            appendArrayParams(params, 'invoice_types',     getSelectedIds('invoiceTypes'));
            appendArrayParams(params, 'sales_officer_ids', getSelectedIds('salesOfficers'));
            appendArrayParams(params, 'supplier_man_ids',  getSelectedIds('supplierMen'));
            appendArrayParams(params, 'vendor_ids',        getSelectedIds('vendors'));
            appendArrayParams(params, 'company_ids',       getSelectedIds('companies'));
            appendArrayParams(params, 'customer_ids',      getSelectedIds('customers'));
            appendArrayParams(params, 'city_ids',          getSelectedIds('cities'));
            appendArrayParams(params, 'city_zone_ids',     getSelectedIds('cityZones'));
            appendArrayParams(params, 'area_ids',          getSelectedIds('areas'));

            const response = await fetch(`../../../../server/api/sale/grocery_sale_invoice/pos-list.php?${params.toString()}`);
            const data = await response.json();
            
            if (data.success) {
                invoices = data.invoices.map(invoice => ({
                    id: invoice.id,
                    invoiceNo: invoice.bill_no,
                    date: invoice.sale_date,
                    customer: invoice.customer_name,
                    company: invoice.company_name,
                    items: invoice.item_count,
                    totalAmount: parseFloat(invoice.net_amount),
                    currencySymbol: invoice.currency_symbol || '',

                    dcStatus: invoice.dc_status || 'No DC',
                    createdBy: invoice.created_by_name || 'N/A',
                    salesOfficer: invoice.sales_officer_name || 'N/A'
                }));
                currentPage = data.pagination.page;
                totalPages = data.pagination.pages;
                clearSelections();
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

            // Checkbox cell
            const checkboxCell = row.insertCell(0);
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'row-checkbox';
            checkbox.dataset.id = invoice.id;
            checkbox.checked = selectedIds.has(invoice.id);
            checkbox.addEventListener('change', function () {
                this.checked ? selectedIds.add(invoice.id) : selectedIds.delete(invoice.id);
                updateSelectionUI();
            });
            checkboxCell.appendChild(checkbox);

            // Format date
            const formattedDate = new Date(invoice.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Format amount with currency symbol
            const formattedAmount = `${invoice.currencySymbol} ${parseFloat(invoice.totalAmount).toFixed(3)}`;

            // Create cells
            row.insertCell(1).textContent = invoice.invoiceNo;
            row.insertCell(2).textContent = formattedDate;
            row.insertCell(3).textContent = invoice.customer || 'N/A';
            row.insertCell(4).textContent = invoice.salesOfficer;
            row.insertCell(5).textContent = invoice.items;
            row.insertCell(6).textContent = formattedAmount;

            // DC Status cell
            const dcStatusCell = row.insertCell(7);
            const dcBadge = document.createElement('span');
            dcBadge.className = 'status ' + (invoice.dcStatus === 'Fulfilled' ? 'status-paid' : invoice.dcStatus === 'Partial' ? 'status-pending' : 'status-overdue');
            dcBadge.textContent = invoice.dcStatus;
            dcStatusCell.appendChild(dcBadge);

            // Created By cell
            row.insertCell(8).textContent = invoice.createdBy;

            const actionsCell = row.insertCell(9);
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

            // PDF button
            const pdfBtn = document.createElement('button');
            pdfBtn.className = 'btn btn-warning btn-sm';
            pdfBtn.innerHTML = '<i class="fas fa-file-pdf"></i>';
            pdfBtn.title = 'Download PDF';
            pdfBtn.addEventListener('click', () => downloadPdf(invoice.id, invoice.invoiceNo));

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

            if (invoice.dcStatus !== 'No DC') {
                const viewDcBtn = document.createElement('button');
                viewDcBtn.className = 'btn btn-secondary btn-sm';
                viewDcBtn.innerHTML = '<i class="fas fa-tasks"></i>';
                viewDcBtn.title = 'View DC Fulfillment';
                viewDcBtn.addEventListener('click', () => viewDcFulfillment(invoice.id));
                actionButtons.appendChild(viewDcBtn);
            }

            actionButtons.appendChild(printBtn);
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

    // Page limit change
    const pageLimitSelect = document.getElementById('pageLimitSelect');
    if (pageLimitSelect) {
        pageLimitSelect.value = pageLimit;
        pageLimitSelect.addEventListener('change', function () {
            pageLimit = parseInt(this.value);
            localStorage.setItem('pos_list_page_limit', pageLimit);
            loadInvoices(1);
        });
    }

    // Set default dates to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dateFrom').value = today;
    document.getElementById('dateTo').value = today;

    // Initial load
    loadInvoices();
    loadCustomers();
    loadCompanies();
    loadFilters();
    loadCities();
    loadCityZones();
    loadAreas();
    loadProjects();

    async function loadCustomers() {
        try {
            const response = await fetch('../../../../server/api/sale/grocery_sale_invoice/get-customers.php');
            const data = await response.json();
            if (data.success) {
                renderMultiSelectOptions('customers', data.customers.map(c => ({
                    value: c.id, label: `${c.customer_code} - ${c.customer_name}`
                })));
            }
        } catch (e) { console.error(e); }
    }

    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/sale/grocery_sale_invoice/get-companies.php');
            const data = await response.json();
            if (data.success) {
                renderMultiSelectOptions('companies', data.companies.map(c => ({
                    value: c.id, label: c.company_name
                })));
            }
        } catch (e) { console.error(e); }
    }

    async function loadFilters() {
        try {
            const response = await fetch('../../../../server/api/sale/daily_sale_report/get-filters.php');
            const data = await response.json();
            if (data.success) {
                renderMultiSelectOptions('salesOfficers', data.salesOfficers.map(e => ({ value: e.id, label: e.full_name })));
                renderMultiSelectOptions('supplierMen',   data.supplierMen.map(e   => ({ value: e.id, label: e.full_name })));
                renderMultiSelectOptions('vendors',       data.vendors.map(v       => ({ value: v.id, label: v.supplier_name })));
            }
        } catch (e) { console.error(e); }
    }

    async function loadCities() {
        try {
            const response = await fetch('../../../../server/api/sale/daily_sale_report/get-cities.php');
            const data = await response.json();
            if (data.success) {
                renderMultiSelectOptions('cities', data.cities.map(c => ({ value: c.id, label: c.city_name })));
            }
        } catch (e) { console.error(e); }
    }

    async function loadCityZones(cityIds = []) {
        try {
            const params = new URLSearchParams();
            cityIds.forEach(id => params.append('city_ids[]', id));
            const response = await fetch('../../../../server/api/sale/daily_sale_report/get-city-zones.php' + (params.toString() ? '?' + params : ''));
            const data = await response.json();
            if (data.success) {
                renderMultiSelectOptions('cityZones', data.city_zones.map(z => ({ value: z.id, label: z.city_zone_name })));
            }
        } catch (e) { console.error(e); }
    }

    async function loadAreas(cityZoneIds = [], cityIds = []) {
        try {
            const params = new URLSearchParams();
            cityZoneIds.forEach(id => params.append('city_zone_ids[]', id));
            cityIds.forEach(id => params.append('city_ids[]', id));
            const response = await fetch('../../../../server/api/sale/daily_sale_report/get-areas.php' + (params.toString() ? '?' + params : ''));
            const data = await response.json();
            if (data.success) {
                renderMultiSelectOptions('areas', data.areas.map(a => ({ value: a.id, label: a.area_name })));
            }
        } catch (e) { console.error(e); }
    }

    async function loadProjects() {
        try {
            const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
            const data = await response.json();
            if (data.projects) {
                const select = document.getElementById('projectFilter');
                if (!select) return;
                select.innerHTML = '<option value="">All Projects</option>';
                data.projects.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.project_code + ' - ' + p.project_name + (p.city_name ? ' - ' + p.city_name : '');
                    select.appendChild(opt);
                });
            }
        } catch (e) { console.error(e); }
    }

    let debounceTimer = null;
    function applyFilters(delay = 0) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadInvoices(1), delay);
    }

    // Add event listeners for filters
    document.getElementById('dateFrom').addEventListener('change', () => applyFilters());
    document.getElementById('dateTo').addEventListener('change', () => applyFilters());
    searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    document.getElementById('searchBtn')?.addEventListener('click', () => applyFilters());
    document.getElementById('applyFiltersBtn')?.addEventListener('click', () => applyFilters());
    document.getElementById('projectFilter')?.addEventListener('change', () => applyFilters());

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

    // Reset filters button
    document.getElementById('resetFiltersBtn').addEventListener('click', function () {
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        searchInput.value = '';
        const proj = document.getElementById('projectFilter');
        if (proj) proj.value = '';
        Object.keys(multiSelects).forEach(key => resetMultiSelect(key));
        closeAllMultiSelects();
        loadCityZones([]);
        loadAreas([], []);
        loadInvoices(1);
    });

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

    async function downloadPdf(id, invoiceNo) {
        window.open(`invoice-print.php?id=${id}&pdf=1`, '_blank');
    }
    
    // Print options modal
    // "Print 2" (invoice-print2.php) only exists in the med_pos_invoice folder —
    // gated by page so the regular Sale Invoice list (which shares this same
    // pos-list.js) never tries to open a file that isn't there for it.
    const isMedInvoicePageForPrint = window.location.pathname.includes('med_pos_invoice');

    function resolveSavedPrintUrl(choice, invoiceId) {
        if (choice === 'full') return `invoice-print.php?id=${invoiceId}`;
        if (choice === 'thermal') return `thermal-print.php?id=${invoiceId}`;
        if (choice === 'thermal2') return `thermal-print2.php?id=${invoiceId}`;
        if (choice === 'full2' && isMedInvoicePageForPrint) return `invoice-print2.php?id=${invoiceId}`;
        return null; // unknown, or a med-only choice remembered on a page it doesn't apply to
    }

    function showPrintOptions(invoiceId) {
        const savedChoice = localStorage.getItem('pos_print_preference');
        const savedUrl = savedChoice ? resolveSavedPrintUrl(savedChoice, invoiceId) : null;
        if (savedUrl) {
            window.open(savedUrl, '_blank');
            return;
        }

        const print2ButtonHtml = isMedInvoicePageForPrint ? `
                    <button class="btn btn-secondary" id="printFull2BtnList" style="width: 100%;">
                        <i class="fas fa-file-invoice"></i> Print 2
                    </button>` : '';

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
                    </button>${print2ButtonHtml}
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

        if (isMedInvoicePageForPrint) {
            document.getElementById('printFull2BtnList').onclick = function() {
                if (document.getElementById('rememberPrintChoiceList').checked) {
                    localStorage.setItem('pos_print_preference', 'full2');
                }
                window.open(`invoice-print2.php?id=${invoiceId}`, '_blank');
                modal.remove();
            };
        }

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
        document.querySelector('#deleteModal p').textContent =
            'Are you sure you want to delete this sale invoice? This action cannot be undone.';
        deleteInvoiceId = null;
    });
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        if (deleteInvoiceId) {
            // Single delete
            try {
                const response = await fetch(`../../../../server/api/sale/grocery_sale_invoice/pos-delete.php?id=${deleteInvoiceId}`, {
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
        } else if (selectedIds.size > 0) {
            // Bulk delete
            try {
                const ids = [...selectedIds];
                await Promise.all(ids.map(id =>
                    fetch(`../../../../server/api/sale/grocery_sale_invoice/pos-delete.php?id=${id}`, { method: 'DELETE' })
                ));
                document.getElementById('deleteModal').style.display = 'none';
                document.querySelector('#deleteModal p').textContent =
                    'Are you sure you want to delete this sale invoice? This action cannot be undone.';
                clearSelections();
                loadInvoices(currentPage);
            } catch (error) {
                alert('Error deleting invoices: ' + error.message);
            }
        }
    });
    
    // DC Fulfillment modal
    document.getElementById('closeDcFulfillmentBtn')?.addEventListener('click', function() {
        document.getElementById('dcFulfillmentModal').style.display = 'none';
    });
    document.getElementById('dcFulfillmentModal')?.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });

    async function viewDcFulfillment(invoiceId) {
        document.getElementById('dcFulfillmentModal').style.display = 'flex';
        const tbody = document.getElementById('dcFulfillmentTableBody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">Loading...</td></tr>';
        try {
            const response = await fetch(`../../../../server/api/sale/delivery_chalan/get-dc-fulfillment.php?invoice_id=${invoiceId}`);
            const data = await response.json();
            if (data.success && data.fulfillment.length > 0) {
                tbody.innerHTML = '';
                data.fulfillment.forEach(item => {
                    const pct = parseFloat(item.fulfillment_percent);
                    const cls = pct >= 100 ? 'status-paid' : pct > 0 ? 'status-pending' : 'status-overdue';
                    const row = tbody.insertRow();
                    row.innerHTML = `
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);">${item.product_name} (${item.uom_name})</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${item.invoiced_qty}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${item.delivered_qty}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;">${item.remaining_qty}</td>
                        <td style="padding:10px 8px;border-bottom:1px solid var(--border-default);text-align:right;"><span class="status ${cls}">${pct.toFixed(0)}%</span></td>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No delivery chalan data found</td></tr>';
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--error);">Error loading data</td></tr>';
        }
    }

    // Hide modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            deleteInvoiceId = null;
        }
    });
}