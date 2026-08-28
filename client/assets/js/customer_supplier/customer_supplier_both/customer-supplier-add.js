document.addEventListener('DOMContentLoaded', function () {
    // Escapes tenant-supplied names (customer types/categories, employees, companies,
    // cities) before they're interpolated into innerHTML template strings below.
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // ===== CUSTOMIZE FIELDS =====
    const CUSTOMIZE_FIELDS_KEY = 'bothFormCustomizeFields';

    const allFields = [
        { id: 'company', label: 'Company', visible: true },
        { id: 'customerCode', label: 'Customer Code', visible: true },
        { id: 'supplierCode', label: 'Supplier Code', visible: true },
        { id: 'partyName', label: 'Party Name', visible: true },
        { id: 'project', label: 'Project', visible: false },
        { id: 'address', label: 'Address', visible: false },
        { id: 'primaryPhone', label: 'Primary Phone', visible: false },
        { id: 'secondaryPhone', label: 'Secondary Phone', visible: false },
        { id: 'email', label: 'Email Address', visible: false },
        { id: 'identityCard', label: 'Identity Card No', visible: false },
        { id: 'blacklist', label: 'Blacklist Party', visible: false },
        { id: 'customerGroup', label: 'Customer Group', visible: true },
        { id: 'customerCategory', label: 'Customer Category', visible: true },
        { id: 'brandName', label: 'Brand Name', visible: true },
        { id: 'shopkeeperName', label: 'Shopkeeper Name', visible: false },
        { id: 'poBoxNo', label: 'P.O. Box No', visible: false },
        { id: 'licenseNo', label: 'License #', visible: false },
        { id: 'salesOfficer', label: 'Associated Sales Officer', visible: false },
        { id: 'supplierMan', label: 'Supplier Man', visible: false },
        { id: 'outStation', label: 'Out Station', visible: false },
        { id: 'country', label: 'Country', visible: true },
        { id: 'region', label: 'Region', visible: true },
        { id: 'city', label: 'City', visible: true },
        { id: 'cityZone', label: 'City Zone', visible: true },
        { id: 'area', label: 'Area', visible: true },
        { id: 'isSalesTaxRegistered', label: 'Is Sales Tax Registered?', visible: false },
        { id: 'strn', label: 'STRN', visible: false },
        { id: 'isFiler', label: 'Is Filer?', visible: false },
        { id: 'ntn', label: 'NTN', visible: false },
        { id: 'advanceIncomeTax', label: 'Advance Income Tax %', visible: false },
        { id: 'defaultDiscount', label: 'Default Discount %', visible: false },
        { id: 'balanceLimit', label: 'Credit Limit', visible: false },
        { id: 'balancePeriodLimit', label: 'Credit Period Limit (Days)', visible: false },
        { id: 'isWholesaler', label: 'Is Wholesaler?', visible: false },
        { id: 'customerOpeningDebit', label: 'Customer Opening Debit', visible: false },
        { id: 'customerOpeningCredit', label: 'Customer Opening Credit', visible: false },
        { id: 'openingInvoicesSection', label: '📋 Opening Balance Invoices', visible: false },
        { id: 'subAccountsTableBody', label: '👥 Customer Sub Accounts', visible: false },
        { id: 'assocCompanySearch', label: 'Associated Companies', visible: true },
        { id: 'salesmanSearch', label: 'Salesman', visible: true },
        { id: 'supplierCategory', label: 'Supplier Category', visible: true },
        { id: 'brandNameSupplier', label: "Supplier's Brand Name", visible: false },
        { id: 'citySearch', label: 'Supplier City', visible: false },
        { id: 'partyType', label: 'Party Type', visible: false },
        { id: 'aitPercent', label: 'AIT %', visible: false },
        { id: 'creditDays', label: 'Credit Days', visible: false },
        { id: 'supplierOpeningDebit', label: 'Supplier Opening Debit', visible: false },
        { id: 'supplierOpeningCredit', label: 'Supplier Opening Credit', visible: false },
        { id: 'supplierSubAccountsBody', label: '📋 Supplier Sub Accounts', visible: false }
    ];

    function getDefaultVisibility() {
        const d = {};
        allFields.forEach(f => { d[f.id] = f.visible; });
        return d;
    }

    function getFieldVisibility() {
        const stored = localStorage.getItem(CUSTOMIZE_FIELDS_KEY);
        if (stored) {
            try { return Object.assign({}, getDefaultVisibility(), JSON.parse(stored)); }
            catch (e) {}
        }
        return getDefaultVisibility();
    }

    function saveFieldVisibility(v) {
        localStorage.setItem(CUSTOMIZE_FIELDS_KEY, JSON.stringify(v));
    }

    function getFieldFormGroup(fieldId) {
        const el = document.getElementById(fieldId);
        if (!el) return null;
        if (fieldId === 'openingInvoicesSection') return el;
        if (fieldId === 'subAccountsTableBody' || fieldId === 'supplierSubAccountsBody') {
            return el.closest('.form-section');
        }
        const fg = el.closest('.form-group');
        if (fg) return fg;
        const cg = el.closest('.checkbox-group');
        return cg ? cg.closest('.form-group') : null;
    }

    function refreshSectionHeadings() {
        document.querySelectorAll('.form-section').forEach(section => {
            const groups = section.querySelectorAll('.form-group');
            let hasVisible = false;
            groups.forEach(fg => {
                const hasInput = fg.querySelector('input, select, textarea');
                if (hasInput && fg.style.display !== 'none') hasVisible = true;
            });
            const heading = section.querySelector('.section-title');
            if (heading) heading.style.display = hasVisible ? '' : 'none';
        });
    }

    function applyFieldVisibility() {
        const vis = getFieldVisibility();
        allFields.forEach(f => {
            const fg = getFieldFormGroup(f.id);
            if (fg) fg.style.display = vis[f.id] ? '' : 'none';
        });
        refreshSectionHeadings();
    }

    const customizeFieldsBtn = document.getElementById('customizeFieldsBtn');
    const customizeFieldsModal = document.getElementById('customizeFieldsModal');
    const customizeFieldsModalClose = document.getElementById('customizeFieldsModalClose');
    const customizeFieldsList = document.getElementById('customizeFieldsList');
    const customizeFieldsSaveBtn = document.getElementById('customizeFieldsSaveBtn');
    const customizeFieldsResetBtn = document.getElementById('customizeFieldsResetBtn');
    const customizeFieldsSelectAllBtn = document.getElementById('customizeFieldsSelectAllBtn');
    const customizeFieldsDeselectAllBtn = document.getElementById('customizeFieldsDeselectAllBtn');

    function populateCustomizeFieldsList() {
        const vis = getFieldVisibility();
        customizeFieldsList.innerHTML = '';
        allFields.forEach(f => {
            const item = document.createElement('div');
            item.className = 'customize-field-item';
            item.innerHTML = `<label class="customize-field-label">
                <input type="checkbox" class="customize-field-checkbox" data-field-id="${f.id}" ${vis[f.id] ? 'checked' : ''}>
                <span>${f.label}</span>
            </label>`;
            customizeFieldsList.appendChild(item);
        });
    }

    function closeCustomizeFieldsModal() {
        customizeFieldsModal.classList.remove('show');
    }

    customizeFieldsBtn.addEventListener('click', function () {
        populateCustomizeFieldsList();
        customizeFieldsModal.classList.add('show');
    });
    customizeFieldsModalClose.addEventListener('click', closeCustomizeFieldsModal);
    customizeFieldsModal.addEventListener('click', function (e) {
        if (e.target === customizeFieldsModal) closeCustomizeFieldsModal();
    });

    customizeFieldsSaveBtn.addEventListener('click', function () {
        const vis = {};
        customizeFieldsList.querySelectorAll('.customize-field-checkbox').forEach(cb => {
            vis[cb.dataset.fieldId] = cb.checked;
        });
        saveFieldVisibility(vis);
        applyFieldVisibility();
        closeCustomizeFieldsModal();
        showNotification('Success', 'Field preferences saved successfully!', 'success');
    });

    customizeFieldsResetBtn.addEventListener('click', function () {
        if (confirm('Reset to default field visibility?')) {
            saveFieldVisibility(getDefaultVisibility());
            applyFieldVisibility();
            populateCustomizeFieldsList();
            showNotification('Success', 'Field visibility reset to default', 'success');
        }
    });

    customizeFieldsSelectAllBtn.addEventListener('click', function () {
        customizeFieldsList.querySelectorAll('.customize-field-checkbox').forEach(cb => cb.checked = true);
    });
    customizeFieldsDeselectAllBtn.addEventListener('click', function () {
        customizeFieldsList.querySelectorAll('.customize-field-checkbox').forEach(cb => cb.checked = false);
    });

    // ===== NOTIFICATION =====
    const notification = document.getElementById('notification');
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationClose = document.getElementById('notificationClose');

    function showNotification(title, message, type = 'success') {
        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');
        setTimeout(() => notification.classList.remove('show'), 5000);
    }
    notificationClose.addEventListener('click', () => notification.classList.remove('show'));

    applyFieldVisibility();

    // ===== PERMISSION CHECK =====
    function checkPermissions() {
        const category = encodeURIComponent('Customer / Supplier');
        const formName = encodeURIComponent('New Customer + Supplier (Both)');
        fetch(`../../../../server/api/auth/check-permission.php?category=${category}&form_name=${formName}`)
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = '../../../../errors/403.php';
                    return;
                }
                if (data.success && data.permissions) {
                    const requiredPermission = isEditMode ? 'Edit' : 'Add';
                    if (!data.permissions.includes(requiredPermission)) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-lock"></i> No Permission';
                        form.addEventListener('submit', function (e) {
                            e.preventDefault();
                            showNotification('Access Denied', 'You do not have permission to ' + (isEditMode ? 'edit' : 'add') + ' customers/suppliers', 'error');
                        });
                    }
                }
            });
    }
    checkPermissions();

    // ===== FORM ELEMENTS =====
    const form = document.getElementById('bothForm');
    const partyName = document.getElementById('partyName');
    const primaryPhone = document.getElementById('primaryPhone');
    const secondaryPhone = document.getElementById('secondaryPhone');
    const identityCard = document.getElementById('identityCard');
    const email = document.getElementById('email');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    const companySelect = document.getElementById('company');

    const countrySelect = document.getElementById('country');
    const regionSelect = document.getElementById('region');
    const citySelect = document.getElementById('city');
    const cityZoneSelect = document.getElementById('cityZone');
    const areaSelect = document.getElementById('area');
    const salesOfficerSelect = document.getElementById('salesOfficer');
    const supplierManSelect = document.getElementById('supplierMan');

    let isHierarchyLoading = false;

    const isSalesTaxRegistered = document.getElementById('isSalesTaxRegistered');
    const strnGroup = document.getElementById('strnGroup');
    const strn = document.getElementById('strn');
    const isFiler = document.getElementById('isFiler');
    const ntnGroup = document.getElementById('ntnGroup');
    const ntn = document.getElementById('ntn');

    const invoicesTableBody = document.getElementById('invoicesTableBody');
    const addInvoiceBtn = document.getElementById('addInvoiceBtn');
    const customerOpeningDebit = document.getElementById('customerOpeningDebit');
    const customerOpeningCredit = document.getElementById('customerOpeningCredit');
    let suppliersList = [];
    let employees = [];

    const subAccountsTableBody = document.getElementById('subAccountsTableBody');
    const addSubAccountBtn = document.getElementById('addSubAccountBtn');
    let subAccountCounter = 0;

    let isSubmitting = false;

    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const isEditMode = !!editId;

    if (isEditMode) {
        document.querySelector('.card-title').innerHTML = '<i class="fas fa-user-edit"></i> Edit Customer + Supplier (Both)';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Customer + Supplier';
    }

    function validateName(name) { return name.trim().length > 0; }
    function validatePhone(phone) {
        if (!phone) return true;
        return /^[0-9]*$/.test(phone) && phone.length <= 15;
    }
    function validateIdentityCard(card) {
        if (!card) return true;
        return /^[0-9]*$/.test(card) && card.length <= 15;
    }
    function validateEmail(val) {
        if (!val) return true;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) && val.length <= 300;
    }

    partyName.addEventListener('input', function () {
        const errorElement = document.getElementById('partyNameError');
        if (!validateName(this.value)) {
            this.classList.add('error');
            errorElement.style.display = 'flex';
        } else {
            this.classList.remove('error');
            errorElement.style.display = 'none';
        }
    });

    primaryPhone.addEventListener('input', function () {
        const errorElement = document.getElementById('primaryPhoneError');
        if (this.value && !validatePhone(this.value)) {
            this.classList.add('error'); errorElement.style.display = 'flex';
        } else { this.classList.remove('error'); errorElement.style.display = 'none'; }
    });
    secondaryPhone.addEventListener('input', function () {
        const errorElement = document.getElementById('secondaryPhoneError');
        if (this.value && !validatePhone(this.value)) {
            this.classList.add('error'); errorElement.style.display = 'flex';
        } else { this.classList.remove('error'); errorElement.style.display = 'none'; }
    });
    identityCard.addEventListener('input', function () {
        const errorElement = document.getElementById('identityCardError');
        if (this.value && !validateIdentityCard(this.value)) {
            this.classList.add('error'); errorElement.style.display = 'flex';
        } else { this.classList.remove('error'); errorElement.style.display = 'none'; }
    });
    email.addEventListener('input', function () {
        const errorElement = document.getElementById('emailError');
        if (this.value && !validateEmail(this.value)) {
            this.classList.add('error'); errorElement.style.display = 'flex';
        } else { this.classList.remove('error'); errorElement.style.display = 'none'; }
    });

    isSalesTaxRegistered.addEventListener('change', function () {
        strnGroup.style.display = this.checked ? 'flex' : 'none';
        if (!this.checked) strn.value = '';
    });
    isFiler.addEventListener('change', function () {
        ntnGroup.style.display = this.checked ? 'flex' : 'none';
        if (!this.checked) ntn.value = '';
    });

    // ===== SELECT2 =====
    $(document).ready(function () {
        $('#company').select2({ placeholder: 'Select Company', allowClear: false });
        $('#customerGroup').select2({ placeholder: 'Select Customer Group', allowClear: true });
        $('#customerCategory').select2({ placeholder: 'Select Customer Category', allowClear: true });
        $('#brandName').select2({ placeholder: 'Select Brand', allowClear: true });
        $('#country').select2({ placeholder: 'Select Country', allowClear: true });
        $('#region').select2({ placeholder: 'Select Region', allowClear: true });
        $('#city').select2({ placeholder: 'Select City', allowClear: true });
        $('#cityZone').select2({ placeholder: 'Select City Zone', allowClear: true });
        $('#area').select2({ placeholder: 'Select Area', allowClear: true });
        $('#salesOfficer').select2({ placeholder: 'Select Sales Officer', allowClear: true });
        $('#supplierMan').select2({ placeholder: 'Select Supplier Man', allowClear: true });
        $('#project').select2({ placeholder: 'Select Project', allowClear: true });
        $('#supplierCategory').select2({ placeholder: 'Select Supplier Category', allowClear: true });
        $('#partyType').select2({ placeholder: 'Select Party Type', allowClear: true });
    });

    // ===== DROPDOWN / TERRITORY LOADERS (customer side, incl. hierarchy autofill) =====
    function loadCountries() {
        fetch('../../../../server/api/customer_supplier/customers/get-countries.php')
            .then(r => r.json()).then(data => {
                if (data.success) data.countries.forEach(c => $('#country').append(new Option(c.country_name, c.id)));
            });
    }
    function loadAllRegions() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-regions.php')
            .then(r => r.json()).then(data => {
                if (data.success) data.regions.forEach(r2 => $('#region').append(new Option(r2.region_name, r2.id)));
            });
    }
    function loadAllCities() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-cities.php')
            .then(r => r.json()).then(data => {
                if (data.success) data.cities.forEach(c => $('#city').append(new Option(c.city_name, c.id)));
            });
    }
    function loadAllCityZones() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-city-zones.php')
            .then(r => r.json()).then(data => {
                if (data.success) data.city_zones.forEach(z => $('#cityZone').append(new Option(z.city_zone_name, z.id)));
            });
    }
    function loadRegions(countryId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-regions.php?country_id=${countryId}`)
            .then(r => r.json()).then(data => {
                if (data.success) {
                    $('#region').empty().append('<option value="">Select Region</option>');
                    data.regions.forEach(r2 => $('#region').append(new Option(r2.region_name, r2.id)));
                }
            });
    }
    function loadCities(regionId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-cities.php?region_id=${regionId}`)
            .then(r => r.json()).then(data => {
                if (data.success) {
                    $('#city').empty().append('<option value="">Select City</option>');
                    data.cities.forEach(c => $('#city').append(new Option(c.city_name, c.id)));
                }
            });
    }
    function loadCityZones(cityId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-city-zones.php?city_id=${cityId}`)
            .then(r => r.json()).then(data => {
                if (data.success) {
                    $('#cityZone').empty().append('<option value="">Select City Zone</option>');
                    data.city_zones.forEach(z => $('#cityZone').append(new Option(z.city_zone_name, z.id)));
                }
            });
    }
    function loadAreas(cityZoneId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-areas.php?city_zone_id=${cityZoneId}`)
            .then(r => r.json()).then(data => {
                if (data.success) {
                    $('#area').empty().append('<option value="">Select Area</option>');
                    data.areas.forEach(a => $('#area').append(new Option(a.area_name, a.id)));
                }
            });
    }
    function loadAllAreas() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-areas.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    $('#area').empty().append('<option value="">Select Area</option>');
                    data.areas.forEach(a => $('#area').append(new Option(a.area_name, a.id)));
                }
            });
    }
    function loadRegionHierarchy(regionId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-region-hierarchy.php?region_id=${regionId}`)
            .then(r => r.json()).then(data => {
                if (data.success && data.hierarchy) {
                    isHierarchyLoading = true;
                    $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                    isHierarchyLoading = false;
                }
            });
    }
    function loadCityHierarchy(cityId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-city-hierarchy.php?city_id=${cityId}`)
            .then(r => r.json()).then(data => {
                if (data.success && data.hierarchy) {
                    isHierarchyLoading = true;
                    loadRegions(data.hierarchy.country_id).then(function () {
                        $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                        $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                        isHierarchyLoading = false;
                    });
                }
            });
    }
    function loadCityZoneHierarchy(cityZoneId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-city-zone-hierarchy.php?city_zone_id=${cityZoneId}`)
            .then(r => r.json()).then(data => {
                if (data.success && data.hierarchy) {
                    isHierarchyLoading = true;
                    loadRegions(data.hierarchy.country_id).then(function () {
                        return loadCities(data.hierarchy.region_id);
                    }).then(function () {
                        $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                        $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                        $('#city').val(data.hierarchy.city_id).trigger('change.select2');
                        isHierarchyLoading = false;
                    });
                }
            });
    }
    function loadFullHierarchyFromArea(areaId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-area-hierarchy.php?area_id=${areaId}`)
            .then(r => r.json()).then(data => {
                if (data.success && data.hierarchy) {
                    isHierarchyLoading = true;
                    loadRegions(data.hierarchy.country_id).then(function () {
                        return loadCities(data.hierarchy.region_id);
                    }).then(function () {
                        return loadCityZones(data.hierarchy.city_id);
                    }).then(function () {
                        $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                        $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                        $('#city').val(data.hierarchy.city_id).trigger('change.select2');
                        $('#cityZone').val(data.hierarchy.city_zone_id).trigger('change.select2');
                        isHierarchyLoading = false;
                    });
                }
            });
    }

    loadCountries();
    loadAllRegions();
    loadAllCities();
    loadAllCityZones();
    loadAllAreas();

    $('#country').on('change', function () {
        if (isHierarchyLoading) return;
        const countryId = this.value;
        if (countryId) loadRegions(countryId); else loadAllRegions();
    });
    $('#region').on('change', function () {
        if (isHierarchyLoading) return;
        const regionId = this.value;
        if (regionId) { loadRegionHierarchy(regionId); loadCities(regionId); } else loadAllCities();
    });
    $('#city').on('change', function () {
        if (isHierarchyLoading) return;
        const cityId = this.value;
        if (cityId) { loadCityHierarchy(cityId); loadCityZones(cityId); } else loadAllCityZones();
    });
    $('#cityZone').on('change', function () {
        if (isHierarchyLoading) return;
        const cityZoneId = this.value;
        if (cityZoneId) { loadCityZoneHierarchy(cityZoneId); loadAreas(cityZoneId); } else loadAllAreas();
    });
    $('#area').on('change', function () {
        const areaId = this.value;
        if (areaId) loadFullHierarchyFromArea(areaId);
    });

    function loadSalesOfficers() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    data.employees.forEach(e => $('#salesOfficer').append(new Option(e.full_name, e.id)));
                    $('#salesOfficer').trigger('change');
                }
            });
    }
    function loadSupplierMenDropdown() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    data.employees.forEach(e => $('#supplierMan').append(new Option(e.full_name, e.id)));
                    $('#supplierMan').trigger('change');
                }
            });
    }
    function loadCompanies() {
        fetch('../../../../server/api/customer_supplier/customers/get-companies.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    data.companies.forEach(c => {
                        $('#company').append(new Option(c.company_name, c.id));
                        allCompanies.push(c);
                    });
                    if (data.companies.length === 1) $('#company').val(data.companies[0].id).trigger('change');
                }
            });
    }
    function loadProjects() {
        fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    (data.projects || []).forEach(p => {
                        const label = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
                        $('#project').append(new Option(label, p.id));
                    });
                    $('#project').trigger('change');
                }
            });
    }
    function loadBrands() {
        fetch('../../../../server/api/customer_supplier/customers/get-brands.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    data.brands.forEach(b => $('#brandName').append(new Option(b.brand_name, b.id)));
                    $('#brandName').trigger('change');
                }
            });
    }
    function loadSuppliersForInvoices() {
        fetch('../../../../server/api/customer_supplier/customers/get-suppliers.php')
            .then(r => r.json()).then(data => { if (data.success) suppliersList = data.suppliers; });
    }
    function loadEmployeesForInvoices() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(r => r.json()).then(data => { if (data.success) employees = data.employees; });
    }

    loadSalesOfficers();
    loadSupplierMenDropdown();
    loadCompanies();
    loadProjects();
    loadBrands();
    loadSuppliersForInvoices();
    loadEmployeesForInvoices();
    loadCustomerTypes();
    loadCustomerCategories();
    loadSupplierCategoriesDropdown();

    // ===== EDIT MODE: prefill from an existing Both record =====
    // Dropdown lists above load asynchronously; give them a moment to populate
    // before selecting a value in them (same pattern used elsewhere in this app).
    if (isEditMode) {
        setTimeout(() => loadForEdit(editId), 800);
    }

    function loadForEdit(customerId) {
        fetch(`../../../../server/api/customer_supplier/customer_supplier_both/both-edit.php?id=${customerId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showNotification('Error', data.message || 'Failed to load record', 'error');
                    return;
                }
                const c = data.customer;
                const s = data.supplier || {};

                document.getElementById('customerCode').value = c.customer_code || '';
                document.getElementById('supplierCode').value = s.supplier_code || '';

                isHierarchyLoading = true;
                $('#company').val(c.company_id || '').trigger('change.select2');
                partyName.value = c.customer_name || '';
                document.getElementById('address').value = c.address || '';
                primaryPhone.value = c.primary_phone || '';
                secondaryPhone.value = c.secondary_phone || '';
                email.value = c.email || '';
                identityCard.value = c.identity_card_no || '';
                document.getElementById('blacklist').checked = c.is_blacklisted == 1;
                $('#project').val(c.project_id || '').trigger('change.select2');

                $('#customerGroup').val(c.customer_type_id || '').trigger('change.select2');
                $('#customerCategory').val(c.customer_category_id || '').trigger('change.select2');
                $('#brandName').val(c.brand_id || '').trigger('change.select2');
                document.getElementById('shopkeeperName').value = c.shop_name || '';
                document.getElementById('poBoxNo').value = c.po_box_no || '';
                document.getElementById('licenseNo').value = c.license_no || '';
                $('#salesOfficer').val(c.associated_sales_officer_id || '').trigger('change.select2');
                $('#supplierMan').val(c.supplier_man_id || '').trigger('change.select2');
                document.getElementById('outStation').checked = c.is_out_station == 1;

                $('#country').val(c.country_id || '').trigger('change.select2');
                $('#region').val(c.region_id || '').trigger('change.select2');
                $('#city').val(c.city_id || '').trigger('change.select2');
                $('#cityZone').val(c.city_zone_id || '').trigger('change.select2');
                $('#area').val(c.area_id || '').trigger('change.select2');
                isHierarchyLoading = false;

                if (c.is_sales_tax_registered == 1) {
                    isSalesTaxRegistered.checked = true;
                    strnGroup.style.display = 'flex';
                    strn.value = c.strn || '';
                }
                if (c.is_filer == 1) {
                    isFiler.checked = true;
                    ntnGroup.style.display = 'flex';
                    ntn.value = c.ntn || '';
                }
                document.getElementById('advanceIncomeTax').value = c.advance_income_tax_percentage || 0;
                document.getElementById('defaultDiscount').value = c.default_discount_percentage || 0;
                document.getElementById('balanceLimit').value = c.credit_limit || 0;
                document.getElementById('balancePeriodLimit').value = c.credit_period_limit_days || 0;
                document.getElementById('isWholesaler').checked = c.is_wholesaler == 1;
                customerOpeningDebit.removeAttribute('readonly');
                customerOpeningDebit.value = parseFloat(c.opening_debit_amount || 0) > 0 ? parseFloat(c.opening_debit_amount).toFixed(2) : '';
                customerOpeningCredit.value = parseFloat(c.opening_credit_amount || 0) > 0 ? parseFloat(c.opening_credit_amount).toFixed(2) : '';

                // Customer sub-accounts
                subAccountsTableBody.innerHTML = '';
                subAccountCounter = 0;
                (data.customer_sub_accounts || []).forEach(sub => {
                    addSubAccountBtn.click();
                    const row = subAccountsTableBody.lastElementChild;
                    row.dataset.subId = sub.id;
                    row.querySelector('.sub-account-name').value = sub.sub_account_name;
                    row.querySelector('.sub-account-debit').value = parseFloat(sub.debit || 0) > 0 ? sub.debit : '';
                    row.querySelector('.sub-account-credit').value = parseFloat(sub.credit || 0) > 0 ? sub.credit : '';
                });

                // Supplier-side fields
                document.getElementById('brandNameSupplier').value = s.brand_name || '';
                citySearch.value = s.city || '';
                supplierCityHidden.value = s.city || '';
                $('#partyType').val(s.party_type || '').trigger('change.select2');
                document.getElementById('aitPercent').value = s.ait_percent || 0;
                document.getElementById('creditDays').value = s.credit_days || 0;
                document.getElementById('supplierOpeningDebit').value = parseFloat(s.opening_debit_amount || 0) > 0 ? parseFloat(s.opening_debit_amount).toFixed(2) : 0;
                document.getElementById('supplierOpeningCredit').value = parseFloat(s.opening_credit_amount || 0) > 0 ? parseFloat(s.opening_credit_amount).toFixed(2) : 0;

                // Salesman chips (salesman_id is a comma-separated list of employee ids)
                if (s.salesman_id) {
                    String(s.salesman_id).split(',').map(id => id.trim()).filter(Boolean).forEach(id => {
                        const emp = employees.find(e => String(e.id) === id);
                        addSalesmanChip(id, emp ? emp.full_name : id);
                    });
                }

                // Associated companies chips
                (data.associated_company_ids || []).forEach(id => {
                    const comp = allCompanies.find(c2 => String(c2.id) === String(id));
                    addAssocCompanyChip(String(id), comp ? comp.company_name : String(id));
                });

                // Supplier sub-accounts (replace the single default empty row)
                supplierSubAccountsBody.innerHTML = '';
                (data.supplier_sub_accounts || []).forEach((sub, idx) => {
                    addSupplierSubAccountRow();
                    const row = supplierSubAccountsBody.lastElementChild;
                    row.dataset.subId = sub.id;
                    row.querySelector('.sub-account-input').value = sub.sub_account_name;
                    row.querySelector('.sub-account-debit').value = parseFloat(sub.debit || 0) > 0 ? sub.debit : '';
                    row.querySelector('.sub-account-credit').value = parseFloat(sub.credit || 0) > 0 ? sub.credit : '';
                });
                if (supplierSubAccountsBody.children.length === 0) {
                    addSupplierSubAccountRow();
                }
            })
            .catch(err => {
                console.error(err);
                showNotification('Error', 'Failed to load record for editing', 'error');
            });
    }

    // ===== CUSTOMER OPENING BALANCE (invoices + mutual exclusion) =====
    customerOpeningDebit.addEventListener('click', function () {
        if (invoicesTableBody.querySelectorAll('tr').length === 0) this.removeAttribute('readonly');
    });
    customerOpeningDebit.addEventListener('blur', function () {
        if (invoicesTableBody.querySelectorAll('tr').length > 0) this.setAttribute('readonly', 'readonly');
    });
    customerOpeningDebit.addEventListener('input', function () {
        if (this.value && parseFloat(this.value) > 0) {
            customerOpeningCredit.disabled = true; customerOpeningCredit.value = '';
        } else customerOpeningCredit.disabled = false;
    });
    customerOpeningCredit.addEventListener('input', function () {
        if (this.value && parseFloat(this.value) > 0) {
            customerOpeningDebit.disabled = true; customerOpeningDebit.value = '';
            invoicesTableBody.innerHTML = '';
        } else customerOpeningDebit.disabled = false;
    });

    function calculateInvoiceTotal() {
        const rows = invoicesTableBody.querySelectorAll('tr');
        let total = 0;
        rows.forEach(row => { total += parseFloat(row.querySelector('.invoice-debit').value) || 0; });
        customerOpeningDebit.value = total > 0 ? total.toFixed(2) : '';
        customerOpeningDebit.setAttribute('readonly', 'readonly');
        customerOpeningCredit.disabled = total > 0;
    }

    addInvoiceBtn.addEventListener('click', function () {
        const rowId = Date.now();
        const row = document.createElement('tr');
        row.dataset.rowId = rowId;
        row.innerHTML = `
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="invoice-distribution-search" list="suppliers-list-${rowId}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Search distribution...">
                <datalist id="suppliers-list-${rowId}">
                    ${suppliersList.map(s => `<option value="${esc(s.supplier_name)}" data-id="${s.id}">`).join('')}
                </datalist>
                <input type="hidden" class="invoice-distribution" value="">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="invoice-sales-officer-search" list="employees-list-${rowId}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Search sales officer...">
                <datalist id="employees-list-${rowId}">
                    ${employees.map(e => `<option value="${esc(e.full_name)}" data-id="${e.id}">`).join('')}
                </datalist>
                <input type="hidden" class="invoice-sales-officer" value="">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="invoice-number" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Invoice #">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="number" class="invoice-debit" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="date" class="invoice-date" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default); text-align: center;">
                <button type="button" class="btn-remove-invoice" style="background: var(--error); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-minus"></i>
                </button>
            </td>
        `;
        invoicesTableBody.appendChild(row);

        const distSearch = row.querySelector('.invoice-distribution-search');
        const distHidden = row.querySelector('.invoice-distribution');
        distSearch.addEventListener('input', function () {
            const supplier = suppliersList.find(s => s.supplier_name === this.value);
            distHidden.value = supplier ? supplier.id : '';
        });

        const salesSearch = row.querySelector('.invoice-sales-officer-search');
        const salesHidden = row.querySelector('.invoice-sales-officer');
        salesSearch.addEventListener('input', function () {
            const employee = employees.find(e => e.full_name === this.value);
            salesHidden.value = employee ? employee.id : '';
        });

        row.querySelector('.invoice-debit').addEventListener('input', calculateInvoiceTotal);
        row.querySelector('.btn-remove-invoice').addEventListener('click', function () {
            row.remove();
            calculateInvoiceTotal();
        });
    });

    function collectInvoiceData() {
        const rows = invoicesTableBody.querySelectorAll('tr');
        const invoices = [];
        rows.forEach(row => {
            const distribution = row.querySelector('.invoice-distribution').value;
            const salesOfficer = row.querySelector('.invoice-sales-officer').value;
            const invoiceNumber = row.querySelector('.invoice-number').value.trim();
            const debit = parseFloat(row.querySelector('.invoice-debit').value) || 0;
            const invoiceDate = row.querySelector('.invoice-date').value;
            if (distribution || salesOfficer || invoiceNumber || debit > 0 || invoiceDate) {
                invoices.push({ distribution, salesOfficer, invoiceNumber, debit, invoiceDate });
            }
        });
        return invoices;
    }

    // ===== CUSTOMER SUB ACCOUNTS =====
    function calculateSubAccountTotals() {
        const rows = subAccountsTableBody.querySelectorAll('tr');
        let totalDebit = 0, totalCredit = 0;
        rows.forEach(row => {
            totalDebit += parseFloat(row.querySelector('.sub-account-debit').value) || 0;
            totalCredit += parseFloat(row.querySelector('.sub-account-credit').value) || 0;
        });
        customerOpeningDebit.value = totalDebit > 0 ? totalDebit.toFixed(2) : '';
        customerOpeningCredit.value = totalCredit > 0 ? totalCredit.toFixed(2) : '';
    }

    addSubAccountBtn.addEventListener('click', function () {
        subAccountCounter++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td style="padding: 12px; border: 1px solid var(--border-default);">${subAccountCounter}</td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="text" class="sub-account-name" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Enter sub account name" required>
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="number" class="sub-account-debit" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00">
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default);">
                <input type="number" class="sub-account-credit" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="0.00">
            </td>
            <td style="padding: 12px; border: 1px solid var(--border-default); text-align: center;">
                <button type="button" class="btn-remove-sub-account" style="background: var(--error); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-minus"></i>
                </button>
            </td>
        `;
        subAccountsTableBody.appendChild(row);

        const debitInput = row.querySelector('.sub-account-debit');
        const creditInput = row.querySelector('.sub-account-credit');
        debitInput.addEventListener('input', function () {
            if (this.value && parseFloat(this.value) > 0) { creditInput.disabled = true; creditInput.value = ''; }
            else creditInput.disabled = false;
            calculateSubAccountTotals();
        });
        creditInput.addEventListener('input', function () {
            if (this.value && parseFloat(this.value) > 0) { debitInput.disabled = true; debitInput.value = ''; }
            else debitInput.disabled = false;
            calculateSubAccountTotals();
        });
        row.querySelector('.btn-remove-sub-account').addEventListener('click', function () {
            row.remove();
            renumberSubAccounts();
            calculateSubAccountTotals();
        });
    });

    function renumberSubAccounts() {
        const rows = subAccountsTableBody.querySelectorAll('tr');
        rows.forEach((row, index) => { row.querySelector('td:first-child').textContent = index + 1; });
        subAccountCounter = rows.length;
    }

    function collectCustomerSubAccountData() {
        const rows = subAccountsTableBody.querySelectorAll('tr');
        const subAccounts = [];
        rows.forEach(row => {
            const name = row.querySelector('.sub-account-name').value.trim();
            const debit = parseFloat(row.querySelector('.sub-account-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.sub-account-credit').value) || 0;
            if (name) subAccounts.push({ id: row.dataset.subId || null, sub_account_name: name, debit, credit });
        });
        return subAccounts;
    }

    // ===== SUPPLIER: SALESMAN CHIPS =====
    const salesmanSearch = document.getElementById('salesmanSearch');
    const salesmanChipsList = document.getElementById('salesmanChipsList');
    const salesmanDropdown = document.getElementById('salesmanDropdown');
    const salesmanIdsInput = document.getElementById('salesmanIds');
    const salesmanChipsContainer = document.getElementById('salesmanChipsContainer');
    let selectedSalesmen = [];

    salesmanSearch.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        if (!term) { salesmanDropdown.style.display = 'none'; return; }
        const filtered = employees.filter(e => e.full_name.toLowerCase().includes(term) && !selectedSalesmen.find(s => s.id === e.id));
        if (filtered.length > 0) {
            salesmanDropdown.innerHTML = filtered.map(e => `<div class="salesman-dropdown-item" data-id="${e.id}" data-name="${esc(e.full_name)}">${esc(e.full_name)}</div>`).join('');
            salesmanDropdown.style.display = 'block';
        } else salesmanDropdown.style.display = 'none';
    });
    salesmanDropdown.addEventListener('click', function (e) {
        const item = e.target.closest('.salesman-dropdown-item');
        if (item) {
            addSalesmanChip(item.dataset.id, item.dataset.name);
            salesmanSearch.value = '';
            salesmanDropdown.style.display = 'none';
        }
    });
    function addSalesmanChip(id, name) {
        if (selectedSalesmen.find(s => s.id === id)) return;
        selectedSalesmen.push({ id, name });
        updateSalesmanChips();
        updateSalesmanIdsInput();
    }
    function removeSalesmanChip(id) {
        selectedSalesmen = selectedSalesmen.filter(s => s.id !== id);
        updateSalesmanChips();
        updateSalesmanIdsInput();
    }
    function updateSalesmanChips() {
        salesmanChipsList.innerHTML = selectedSalesmen.map(s => `
            <div class="salesman-chip"><span>${esc(s.name)}</span><span class="chip-remove" data-id="${s.id}">×</span></div>
        `).join('');
        salesmanChipsList.querySelectorAll('.chip-remove').forEach(btn => {
            btn.addEventListener('click', function () { removeSalesmanChip(this.dataset.id); });
        });
    }
    function updateSalesmanIdsInput() { salesmanIdsInput.value = selectedSalesmen.map(s => s.id).join(','); }
    salesmanChipsContainer.addEventListener('click', function () { salesmanSearch.focus(); });

    // ===== SUPPLIER: ASSOCIATED COMPANIES CHIPS =====
    const assocCompanySearch = document.getElementById('assocCompanySearch');
    const assocCompanyChipsList = document.getElementById('assocCompanyChipsList');
    const assocCompanyDropdown = document.getElementById('assocCompanyDropdown');
    const assocCompanyIdsInput = document.getElementById('assocCompanyIds');
    const assocCompanyChipsContainer = document.getElementById('assocCompanyChipsContainer');
    let allCompanies = [];
    let selectedAssocCompanies = [];

    assocCompanyChipsContainer.addEventListener('click', function () { assocCompanySearch.focus(); });
    assocCompanySearch.addEventListener('focus', function () {
        const term = this.value.toLowerCase();
        const list = term
            ? allCompanies.filter(c => c.company_name.toLowerCase().includes(term) && !selectedAssocCompanies.find(s => s.id == c.id))
            : allCompanies.filter(c => !selectedAssocCompanies.find(s => s.id == c.id));
        if (list.length > 0) {
            assocCompanyDropdown.innerHTML = list.map(c => `<div class="salesman-dropdown-item" data-id="${c.id}" data-name="${esc(c.company_name)}">${esc(c.company_name)}</div>`).join('');
            assocCompanyDropdown.style.display = 'block';
        }
    });
    assocCompanySearch.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        if (!term) { assocCompanyDropdown.style.display = 'none'; return; }
        const filtered = allCompanies.filter(c => c.company_name.toLowerCase().includes(term) && !selectedAssocCompanies.find(s => s.id == c.id));
        if (filtered.length > 0) {
            assocCompanyDropdown.innerHTML = filtered.map(c => `<div class="salesman-dropdown-item" data-id="${c.id}" data-name="${esc(c.company_name)}">${esc(c.company_name)}</div>`).join('');
            assocCompanyDropdown.style.display = 'block';
        } else assocCompanyDropdown.style.display = 'none';
    });
    assocCompanyDropdown.addEventListener('click', function (e) {
        const item = e.target.closest('.salesman-dropdown-item');
        if (item) {
            addAssocCompanyChip(item.dataset.id, item.dataset.name);
            assocCompanySearch.value = '';
            assocCompanyDropdown.style.display = 'none';
        }
    });
    function addAssocCompanyChip(id, name) {
        if (selectedAssocCompanies.find(s => s.id == id)) return;
        selectedAssocCompanies.push({ id, name });
        renderAssocCompanyChips();
    }
    function renderAssocCompanyChips() {
        assocCompanyChipsList.innerHTML = selectedAssocCompanies.map(s =>
            `<div class="assoc-company-chip"><span>${esc(s.name)}</span><span class="chip-remove" data-id="${s.id}">×</span></div>`
        ).join('');
        assocCompanyChipsList.querySelectorAll('.chip-remove').forEach(btn => {
            btn.addEventListener('click', function () {
                selectedAssocCompanies = selectedAssocCompanies.filter(s => s.id != this.dataset.id);
                renderAssocCompanyChips();
                assocCompanyIdsInput.value = selectedAssocCompanies.map(s => s.id).join(',');
            });
        });
        assocCompanyIdsInput.value = selectedAssocCompanies.map(s => s.id).join(',');
    }

    // ===== SUPPLIER: CITY SEARCH =====
    const citySearch = document.getElementById('citySearch');
    const supplierCityHidden = document.getElementById('supplierCity');
    const cityDropdown = document.getElementById('cityDropdown');
    let allSupplierCities = [];

    fetch('../../../../server/api/customer_supplier/suppliers/get-cities.php')
        .then(r => r.json()).then(data => { if (data.success) allSupplierCities = data.cities; })
        .catch(err => console.error('loadCities error:', err));

    function renderCityDropdown(list) {
        if (list.length > 0) {
            cityDropdown.innerHTML = list.map(c => `<div class="salesman-dropdown-item" data-name="${esc(c.city_name)}">${esc(c.city_name)}</div>`).join('');
            cityDropdown.style.display = 'block';
        } else cityDropdown.style.display = 'none';
    }
    citySearch.addEventListener('focus', function () {
        const term = this.value.toLowerCase();
        const list = term ? allSupplierCities.filter(c => c.city_name.toLowerCase().includes(term)) : allSupplierCities;
        renderCityDropdown(list);
    });
    citySearch.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        supplierCityHidden.value = '';
        if (!term) { cityDropdown.style.display = 'none'; return; }
        renderCityDropdown(allSupplierCities.filter(c => c.city_name.toLowerCase().includes(term)));
    });
    cityDropdown.addEventListener('click', function (e) {
        const item = e.target.closest('.salesman-dropdown-item');
        if (item) {
            citySearch.value = item.dataset.name;
            supplierCityHidden.value = item.dataset.name;
            cityDropdown.style.display = 'none';
        }
    });

    document.addEventListener('click', function (e) {
        if (!salesmanChipsContainer.contains(e.target) && !salesmanDropdown.contains(e.target)) salesmanDropdown.style.display = 'none';
        if (!assocCompanyChipsContainer.contains(e.target) && !assocCompanyDropdown.contains(e.target)) assocCompanyDropdown.style.display = 'none';
        if (!citySearch.contains(e.target) && !cityDropdown.contains(e.target)) cityDropdown.style.display = 'none';
    });

    // ===== SUPPLIER SUB ACCOUNTS =====
    const supplierSubAccountsBody = document.getElementById('supplierSubAccountsBody');

    function updateSupplierSubAccountRowNumbers() {
        supplierSubAccountsBody.querySelectorAll('tr').forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
        });
    }
    function addSupplierSubAccountRow() {
        const rowCount = supplierSubAccountsBody.querySelectorAll('tr').length + 1;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>${rowCount}</td>
            <td><input type="text" class="sub-account-input" placeholder="Enter sub account name"></td>
            <td><input type="number" class="sub-account-debit" step="0.01" min="0" placeholder="0.00"></td>
            <td><input type="number" class="sub-account-credit" step="0.01" min="0" placeholder="0.00"></td>
            <td>
                <button type="button" class="btn-icon btn-add" title="Add Row"><i class="fas fa-plus"></i></button>
                <button type="button" class="btn-icon btn-remove" title="Remove Row"><i class="fas fa-minus"></i></button>
            </td>
        `;
        supplierSubAccountsBody.appendChild(newRow);
        attachSupplierSubAccountRowListeners(newRow);
    }
    function removeSupplierSubAccountRow(button) {
        const row = button.closest('tr');
        if (supplierSubAccountsBody.querySelectorAll('tr').length > 1) {
            row.remove();
            updateSupplierSubAccountRowNumbers();
        } else alert('At least one sub-account row must remain');
    }
    function attachSupplierSubAccountRowListeners(row) {
        const addBtn = row.querySelector('.btn-add');
        const removeBtn = row.querySelector('.btn-remove');
        if (addBtn) addBtn.addEventListener('click', function (e) { e.preventDefault(); addSupplierSubAccountRow(); });
        if (removeBtn) removeBtn.addEventListener('click', function (e) { e.preventDefault(); removeSupplierSubAccountRow(this); });
    }
    supplierSubAccountsBody.querySelectorAll('tr').forEach(row => attachSupplierSubAccountRowListeners(row));

    function collectSupplierSubAccountData() {
        const subAccounts = [];
        supplierSubAccountsBody.querySelectorAll('tr').forEach(row => {
            const nameInput = row.querySelector('.sub-account-input');
            const debitInput = row.querySelector('.sub-account-debit');
            const creditInput = row.querySelector('.sub-account-credit');
            if (nameInput && nameInput.value.trim()) {
                subAccounts.push({
                    id: row.dataset.subId || null,
                    name: nameInput.value.trim(),
                    debit: debitInput ? parseFloat(debitInput.value) || 0 : 0,
                    credit: creditInput ? parseFloat(creditInput.value) || 0 : 0
                });
            }
        });
        return subAccounts;
    }

    // ===== CUSTOMER TYPES MANAGEMENT =====
    const typesModal = document.getElementById('typesModal');
    const typesModalClose = document.getElementById('typesModalClose');
    const typesCloseBtn = document.getElementById('typesCloseBtn');
    const manageTypesBtn = document.getElementById('manageTypesBtn');
    const addTypeBtn = document.getElementById('addTypeBtn');
    const typesTableBody = document.getElementById('typesTableBody');

    const typeFormModal = document.getElementById('typeFormModal');
    const typeFormModalClose = document.getElementById('typeFormModalClose');
    const typeFormCancelBtn = document.getElementById('typeFormCancelBtn');
    const typeForm = document.getElementById('typeForm');
    const typeFormTitle = document.getElementById('typeFormTitle');
    const typeName = document.getElementById('typeName');
    const typeFormSaveBtn = document.getElementById('typeFormSaveBtn');
    let currentEditingTypeId = null;
    let customerTypes = [];

    function loadCustomerTypes() {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    customerTypes = data.types;
                    const select = document.getElementById('customerGroup');
                    select.innerHTML = '<option value="">Select Customer Group</option>';
                    data.types.forEach(t => select.appendChild(new Option(t.type_name, t.id)));
                    $('#customerGroup').trigger('change');
                }
            });
    }
    function loadTypesTable() {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php')
            .then(r => r.json()).then(data => { if (data.success) { customerTypes = data.types; renderTypesTable(data.types); } });
    }
    function renderTypesTable(types) {
        typesTableBody.innerHTML = '';
        types.forEach(type => {
            const isSystem = type.tenant_id == 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default);">
                    ${esc(type.type_name)} ${isSystem ? '<span class="system-badge"><i class="fas fa-lock"></i> System</span>' : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default); text-align: center;">
                    <button class="action-btn edit" data-id="${type.id}" ${isSystem ? 'disabled' : ''}><i class="fas fa-edit"></i></button>
                    <button class="action-btn delete" data-id="${type.id}" ${isSystem ? 'disabled' : ''}><i class="fas fa-trash"></i></button>
                </td>
            `;
            typesTableBody.appendChild(row);
        });
    }
    manageTypesBtn.addEventListener('click', function () { loadTypesTable(); typesModal.classList.add('show'); });
    function closeTypesModal() { typesModal.classList.remove('show'); }
    typesModalClose.addEventListener('click', closeTypesModal);
    typesCloseBtn.addEventListener('click', closeTypesModal);
    typesModal.addEventListener('click', function (e) { if (e.target === typesModal) closeTypesModal(); });
    addTypeBtn.addEventListener('click', function () {
        currentEditingTypeId = null;
        typeFormTitle.innerHTML = '<i class="fas fa-plus"></i> Add Customer Type';
        typeName.value = '';
        typeFormModal.classList.add('show');
    });
    typesTableBody.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;
        const typeId = target.dataset.id;
        if (target.classList.contains('edit')) {
            const type = customerTypes.find(t => t.id == typeId);
            if (type) {
                currentEditingTypeId = typeId;
                typeFormTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Customer Type';
                typeName.value = type.type_name;
                typeFormModal.classList.add('show');
            }
        } else if (target.classList.contains('delete')) {
            if (confirm('Are you sure you want to delete this customer type?')) deleteType(typeId);
        }
    });
    function closeTypeFormModal() { typeFormModal.classList.remove('show'); currentEditingTypeId = null; typeName.value = ''; }
    typeFormModalClose.addEventListener('click', closeTypeFormModal);
    typeFormCancelBtn.addEventListener('click', closeTypeFormModal);
    typeFormModal.addEventListener('click', function (e) { if (e.target === typeFormModal) closeTypeFormModal(); });
    typeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!typeName.value.trim()) { showNotification('Error', 'Type name is required', 'error'); return; }
        typeFormSaveBtn.disabled = true;
        typeFormSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        const url = '../../../../server/api/customer_supplier/customers/customer-types.php';
        const method = currentEditingTypeId ? 'PUT' : 'POST';
        const data = { type_name: typeName.value.trim() };
        if (currentEditingTypeId) data.id = currentEditingTypeId;
        fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    showNotification('Success', data.message, 'success');
                    closeTypeFormModal(); loadTypesTable(); loadCustomerTypes();
                } else showNotification('Error', data.message, 'error');
            })
            .catch(() => showNotification('Error', 'Failed to save customer type', 'error'))
            .finally(() => { typeFormSaveBtn.disabled = false; typeFormSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save'; });
    });
    function deleteType(typeId) {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php', {
            method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: typeId })
        }).then(r => r.json()).then(data => {
            if (data.success) { showNotification('Success', data.message, 'success'); loadTypesTable(); loadCustomerTypes(); }
            else showNotification('Error', data.message, 'error');
        }).catch(() => showNotification('Error', 'Failed to delete customer type', 'error'));
    }

    // ===== CUSTOMER CATEGORIES MANAGEMENT =====
    const categoriesModal = document.getElementById('categoriesModal');
    const categoriesModalClose = document.getElementById('categoriesModalClose');
    const categoriesCloseBtn = document.getElementById('categoriesCloseBtn');
    const manageCategoriesBtn = document.getElementById('manageCategoriesBtn');
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const categoriesTableBody = document.getElementById('categoriesTableBody');

    const categoryFormModal = document.getElementById('categoryFormModal');
    const categoryFormModalClose = document.getElementById('categoryFormModalClose');
    const categoryFormCancelBtn = document.getElementById('categoryFormCancelBtn');
    const categoryForm = document.getElementById('categoryForm');
    const categoryFormTitle = document.getElementById('categoryFormTitle');
    const categoryName = document.getElementById('categoryName');
    const categoryFormSaveBtn = document.getElementById('categoryFormSaveBtn');
    let currentEditingCategoryId = null;
    let customerCategories = [];

    function loadCustomerCategories() {
        fetch('../../../../server/api/customer_supplier/customers/customer-categories.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    customerCategories = data.categories;
                    const select = document.getElementById('customerCategory');
                    select.innerHTML = '<option value="">Select Customer Category</option>';
                    data.categories.forEach(c => select.appendChild(new Option(c.category_name, c.id)));
                    $('#customerCategory').trigger('change');
                }
            });
    }
    function loadCategoriesTable() {
        fetch('../../../../server/api/customer_supplier/customers/customer-categories.php')
            .then(r => r.json()).then(data => { if (data.success) { customerCategories = data.categories; renderCategoriesTable(data.categories); } });
    }
    function renderCategoriesTable(categories) {
        categoriesTableBody.innerHTML = '';
        categories.forEach(category => {
            const isSystem = category.tenant_id == 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default);">
                    ${esc(category.category_name)} ${isSystem ? '<span class="system-badge"><i class="fas fa-lock"></i> System</span>' : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default); text-align: center;">
                    <button class="action-btn edit" data-id="${category.id}" ${isSystem ? 'disabled' : ''}><i class="fas fa-edit"></i></button>
                    <button class="action-btn delete" data-id="${category.id}" ${isSystem ? 'disabled' : ''}><i class="fas fa-trash"></i></button>
                </td>
            `;
            categoriesTableBody.appendChild(row);
        });
    }
    manageCategoriesBtn.addEventListener('click', function () { loadCategoriesTable(); categoriesModal.classList.add('show'); });
    function closeCategoriesModal() { categoriesModal.classList.remove('show'); }
    categoriesModalClose.addEventListener('click', closeCategoriesModal);
    categoriesCloseBtn.addEventListener('click', closeCategoriesModal);
    categoriesModal.addEventListener('click', function (e) { if (e.target === categoriesModal) closeCategoriesModal(); });
    addCategoryBtn.addEventListener('click', function () {
        currentEditingCategoryId = null;
        categoryFormTitle.innerHTML = '<i class="fas fa-plus"></i> Add Customer Category';
        categoryName.value = '';
        categoryFormModal.classList.add('show');
    });
    categoriesTableBody.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;
        const categoryId = target.dataset.id;
        if (target.classList.contains('edit')) {
            const category = customerCategories.find(c => c.id == categoryId);
            if (category) {
                currentEditingCategoryId = categoryId;
                categoryFormTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Customer Category';
                categoryName.value = category.category_name;
                categoryFormModal.classList.add('show');
            }
        } else if (target.classList.contains('delete')) {
            if (confirm('Are you sure you want to delete this customer category?')) deleteCategory(categoryId);
        }
    });
    function closeCategoryFormModal() { categoryFormModal.classList.remove('show'); currentEditingCategoryId = null; categoryName.value = ''; }
    categoryFormModalClose.addEventListener('click', closeCategoryFormModal);
    categoryFormCancelBtn.addEventListener('click', closeCategoryFormModal);
    categoryFormModal.addEventListener('click', function (e) { if (e.target === categoryFormModal) closeCategoryFormModal(); });
    categoryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!categoryName.value.trim()) { showNotification('Error', 'Category name is required', 'error'); return; }
        categoryFormSaveBtn.disabled = true;
        categoryFormSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        const url = '../../../../server/api/customer_supplier/customers/customer-categories.php';
        const method = currentEditingCategoryId ? 'PUT' : 'POST';
        const data = { category_name: categoryName.value.trim() };
        if (currentEditingCategoryId) data.id = currentEditingCategoryId;
        fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    showNotification('Success', data.message, 'success');
                    closeCategoryFormModal(); loadCategoriesTable(); loadCustomerCategories();
                } else showNotification('Error', data.message, 'error');
            })
            .catch(() => showNotification('Error', 'Failed to save customer category', 'error'))
            .finally(() => { categoryFormSaveBtn.disabled = false; categoryFormSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save'; });
    });
    function deleteCategory(categoryId) {
        fetch('../../../../server/api/customer_supplier/customers/customer-categories.php', {
            method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: categoryId })
        }).then(r => r.json()).then(data => {
            if (data.success) { showNotification('Success', data.message, 'success'); loadCategoriesTable(); loadCustomerCategories(); }
            else showNotification('Error', data.message, 'error');
        }).catch(() => showNotification('Error', 'Failed to delete customer category', 'error'));
    }

    // ===== SUPPLIER CATEGORIES MANAGEMENT =====
    const supplierCategoriesModal = document.getElementById('supplierCategoriesModal');
    const supplierCategoriesModalClose = document.getElementById('supplierCategoriesModalClose');
    const supplierCategoriesCloseBtn = document.getElementById('supplierCategoriesCloseBtn');
    const manageSupplierCategoriesBtn = document.getElementById('manageSupplierCategoriesBtn');
    const addSupplierCategoryBtn = document.getElementById('addSupplierCategoryBtn');
    const supplierCategoriesTableBody = document.getElementById('supplierCategoriesTableBody');

    const supplierCategoryFormModal = document.getElementById('supplierCategoryFormModal');
    const supplierCategoryFormModalClose = document.getElementById('supplierCategoryFormModalClose');
    const supplierCategoryFormCancelBtn = document.getElementById('supplierCategoryFormCancelBtn');
    const supplierCategoryForm = document.getElementById('supplierCategoryForm');
    const supplierCategoryFormTitle = document.getElementById('supplierCategoryFormTitle');
    const supplierCategoryNameInput = document.getElementById('supplierCategoryName');
    const supplierCategoryFormSaveBtn = document.getElementById('supplierCategoryFormSaveBtn');
    let currentEditingSupplierCategoryId = null;
    let supplierCategories = [];

    function loadSupplierCategoriesDropdown() {
        fetch('../../../../server/api/customer_supplier/suppliers/supplier-categories.php')
            .then(r => r.json()).then(data => {
                if (data.success) {
                    supplierCategories = data.categories;
                    const select = document.getElementById('supplierCategory');
                    select.innerHTML = '<option value="">Select Supplier Category</option>';
                    data.categories.forEach(c => select.appendChild(new Option(c.category_name, c.id)));
                    $('#supplierCategory').trigger('change');
                }
            }).catch(err => console.error('loadSupplierCategories error:', err));
    }
    function loadSupplierCategoriesTable() {
        fetch('../../../../server/api/customer_supplier/suppliers/supplier-categories.php')
            .then(r => r.json()).then(data => { if (data.success) { supplierCategories = data.categories; renderSupplierCategoriesTable(data.categories); } });
    }
    function renderSupplierCategoriesTable(categories) {
        supplierCategoriesTableBody.innerHTML = '';
        categories.forEach(category => {
            const isSystem = category.tenant_id == 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default);">
                    ${esc(category.category_name)} ${isSystem ? '<span class="system-badge"><i class="fas fa-lock"></i> System</span>' : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default); text-align: center;">
                    <button class="action-btn edit" data-id="${category.id}" ${isSystem ? 'disabled' : ''}><i class="fas fa-edit"></i></button>
                    <button class="action-btn delete" data-id="${category.id}" ${isSystem ? 'disabled' : ''}><i class="fas fa-trash"></i></button>
                </td>
            `;
            supplierCategoriesTableBody.appendChild(row);
        });
    }
    manageSupplierCategoriesBtn.addEventListener('click', function () { loadSupplierCategoriesTable(); supplierCategoriesModal.classList.add('show'); });
    function closeSupplierCategoriesModal() { supplierCategoriesModal.classList.remove('show'); }
    supplierCategoriesModalClose.addEventListener('click', closeSupplierCategoriesModal);
    supplierCategoriesCloseBtn.addEventListener('click', closeSupplierCategoriesModal);
    supplierCategoriesModal.addEventListener('click', function (e) { if (e.target === supplierCategoriesModal) closeSupplierCategoriesModal(); });
    addSupplierCategoryBtn.addEventListener('click', function () {
        currentEditingSupplierCategoryId = null;
        supplierCategoryFormTitle.innerHTML = '<i class="fas fa-plus"></i> Add Supplier Category';
        supplierCategoryNameInput.value = '';
        supplierCategoryFormModal.classList.add('show');
    });
    supplierCategoriesTableBody.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;
        const categoryId = target.dataset.id;
        if (target.classList.contains('edit')) {
            const category = supplierCategories.find(c => c.id == categoryId);
            if (category) {
                currentEditingSupplierCategoryId = categoryId;
                supplierCategoryFormTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Supplier Category';
                supplierCategoryNameInput.value = category.category_name;
                supplierCategoryFormModal.classList.add('show');
            }
        } else if (target.classList.contains('delete')) {
            if (confirm('Are you sure you want to delete this supplier category?')) deleteSupplierCategory(categoryId);
        }
    });
    function closeSupplierCategoryFormModal() { supplierCategoryFormModal.classList.remove('show'); currentEditingSupplierCategoryId = null; supplierCategoryNameInput.value = ''; }
    supplierCategoryFormModalClose.addEventListener('click', closeSupplierCategoryFormModal);
    supplierCategoryFormCancelBtn.addEventListener('click', closeSupplierCategoryFormModal);
    supplierCategoryFormModal.addEventListener('click', function (e) { if (e.target === supplierCategoryFormModal) closeSupplierCategoryFormModal(); });
    supplierCategoryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!supplierCategoryNameInput.value.trim()) { showNotification('Error', 'Category name is required', 'error'); return; }
        supplierCategoryFormSaveBtn.disabled = true;
        supplierCategoryFormSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        const url = '../../../../server/api/customer_supplier/suppliers/supplier-categories.php';
        const method = currentEditingSupplierCategoryId ? 'PUT' : 'POST';
        const data = { category_name: supplierCategoryNameInput.value.trim() };
        if (currentEditingSupplierCategoryId) data.id = currentEditingSupplierCategoryId;
        fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    showNotification('Success', data.message, 'success');
                    closeSupplierCategoryFormModal(); loadSupplierCategoriesTable(); loadSupplierCategoriesDropdown();
                } else showNotification('Error', data.message, 'error');
            })
            .catch(() => showNotification('Error', 'Failed to save supplier category', 'error'))
            .finally(() => { supplierCategoryFormSaveBtn.disabled = false; supplierCategoryFormSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save'; });
    });
    function deleteSupplierCategory(categoryId) {
        fetch('../../../../server/api/customer_supplier/suppliers/supplier-categories.php', {
            method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: categoryId })
        }).then(r => r.json()).then(data => {
            if (data.success) { showNotification('Success', data.message, 'success'); loadSupplierCategoriesTable(); loadSupplierCategoriesDropdown(); }
            else showNotification('Error', data.message, 'error');
        }).catch(() => showNotification('Error', 'Failed to delete supplier category', 'error'));
    }

    // ===== FORM SUBMIT =====
    function resetFormState() {
        form.reset();
        document.getElementById('customerCode').value = 'Auto Generated';
        document.getElementById('supplierCode').value = 'Auto Generated';
        customerOpeningDebit.disabled = false;
        customerOpeningCredit.disabled = false;
        $('#region').empty().append('<option value="">Select Region</option>');
        $('#city').empty().append('<option value="">Select City</option>');
        $('#cityZone').empty().append('<option value="">Select City Zone</option>');
        loadAllAreas();
        $('#country').val('').trigger('change');
        $('#project').val('').trigger('change');
        $('#salesOfficer').val('').trigger('change');
        $('#supplierMan').val('').trigger('change');
        if ($('#company option').length > 2) $('#company').val('').trigger('change');
        isSalesTaxRegistered.checked = false;
        isFiler.checked = false;
        strnGroup.style.display = 'none';
        ntnGroup.style.display = 'none';
        invoicesTableBody.innerHTML = '';
        subAccountCounter = 0;
        subAccountsTableBody.innerHTML = '';
        selectedSalesmen = [];
        updateSalesmanChips();
        updateSalesmanIdsInput();
        selectedAssocCompanies = [];
        renderAssocCompanyChips();
        citySearch.value = '';
        supplierCityHidden.value = '';
        supplierSubAccountsBody.innerHTML = `
            <tr>
                <td>1</td>
                <td><input type="text" class="sub-account-input" placeholder="Enter sub account name"></td>
                <td><input type="number" class="sub-account-debit" step="0.01" min="0" placeholder="0.00"></td>
                <td><input type="number" class="sub-account-credit" step="0.01" min="0" placeholder="0.00"></td>
                <td>
                    <button type="button" class="btn-icon btn-add" title="Add Row"><i class="fas fa-plus"></i></button>
                    <button type="button" class="btn-icon btn-remove" title="Remove Row"><i class="fas fa-minus"></i></button>
                </td>
            </tr>
        `;
        supplierSubAccountsBody.querySelectorAll('tr').forEach(row => attachSupplierSubAccountRowListeners(row));
        document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
        document.querySelectorAll('.error-text').forEach(el => { el.style.display = 'none'; });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isSubmitting) return;

        const isNameValid = validateName(partyName.value);
        const isCompanyValid = companySelect.value !== '';

        if (!isCompanyValid) {
            showNotification('Validation Error', 'Company is required', 'error');
            companySelect.focus();
            document.getElementById('companyError').style.display = 'flex';
            return;
        }
        if (!isNameValid) {
            showNotification('Validation Error', 'Party Name is required', 'error');
            partyName.focus();
            return;
        }

        const isPrimaryPhoneValid = !primaryPhone.value || validatePhone(primaryPhone.value);
        const isSecondaryPhoneValid = !secondaryPhone.value || validatePhone(secondaryPhone.value);
        const isIdentityCardValid = !identityCard.value || validateIdentityCard(identityCard.value);
        const isEmailValid = !email.value || validateEmail(email.value);

        if (!isPrimaryPhoneValid || !isSecondaryPhoneValid || !isIdentityCardValid || !isEmailValid) {
            showNotification('Validation Error', 'Please correct the errors in the form', 'error');
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const formData = {
            companyId: companySelect.value,
            partyName: partyName.value.trim(),
            address: document.getElementById('address').value.trim(),
            primaryPhone: primaryPhone.value.trim(),
            secondaryPhone: secondaryPhone.value.trim(),
            identityCard: identityCard.value.trim(),
            email: email.value.trim(),
            projectId: document.getElementById('project').value || null,
            blacklist: document.getElementById('blacklist').checked ? 1 : 0,

            // Customer-specific
            customerTypeId: document.getElementById('customerGroup').value || null,
            customerCategoryId: document.getElementById('customerCategory').value || null,
            brandId: document.getElementById('brandName').value || null,
            shopName: document.getElementById('shopkeeperName').value.trim(),
            poBoxNo: document.getElementById('poBoxNo').value.trim(),
            licenseNo: document.getElementById('licenseNo').value.trim(),
            countryId: countrySelect.value || null,
            regionId: regionSelect.value || null,
            cityId: citySelect.value || null,
            cityZoneId: cityZoneSelect.value || null,
            areaId: areaSelect.value || null,
            salesOfficerId: salesOfficerSelect.value || null,
            supplierManId: supplierManSelect.value || null,
            outStation: document.getElementById('outStation').checked ? 1 : 0,
            isSalesTaxRegistered: isSalesTaxRegistered.checked ? 1 : 0,
            strn: isSalesTaxRegistered.checked && strn.value.trim() ? strn.value.trim() : null,
            isFiler: isFiler.checked ? 1 : 0,
            ntn: isFiler.checked && ntn.value.trim() ? ntn.value.trim() : null,
            advanceIncomeTax: document.getElementById('advanceIncomeTax').value || 0,
            defaultDiscount: document.getElementById('defaultDiscount').value || 0,
            balanceLimit: document.getElementById('balanceLimit').value || 0,
            balancePeriodLimit: document.getElementById('balancePeriodLimit').value || 0,
            isWholesaler: document.getElementById('isWholesaler').checked ? 1 : 0,
            customerOpeningDebit: customerOpeningDebit.value || 0,
            customerOpeningCredit: customerOpeningCredit.value || 0,
            openingInvoices: collectInvoiceData(),
            customerSubAccounts: collectCustomerSubAccountData(),

            // Supplier-specific
            salesmanId: salesmanIdsInput.value || null,
            categoryId: document.getElementById('supplierCategory').value || null,
            associatedCompanyIds: assocCompanyIdsInput.value || null,
            brandNameSupplier: document.getElementById('brandNameSupplier').value.trim(),
            supplierCity: supplierCityHidden.value.trim(),
            partyType: document.getElementById('partyType').value || null,
            aitPercent: document.getElementById('aitPercent').value || 0,
            creditDays: document.getElementById('creditDays').value || 0,
            supplierOpeningDebit: document.getElementById('supplierOpeningDebit').value || 0,
            supplierOpeningCredit: document.getElementById('supplierOpeningCredit').value || 0,
            supplierSubAccounts: collectSupplierSubAccountData()
        };

        const apiUrl = '../../../../server/api/customer_supplier/customer_supplier_both/' + (isEditMode ? 'both-edit.php' : 'both-add.php');
        const method = isEditMode ? 'PUT' : 'POST';
        if (isEditMode) formData.customerId = editId;

        fetch(apiUrl, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (isEditMode) {
                    showNotification('Success', data.message, 'success');
                    setTimeout(function () {
                        window.location.href = 'customer-supplier-list.php';
                    }, 1500);
                } else {
                    showNotification('Success', `${data.message} (Customer: ${data.customer_code}, Supplier: ${data.supplier_code})`, 'success');
                    setTimeout(function () {
                        resetFormState();
                        isSubmitting = false;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Customer + Supplier';
                    }, 2000);
                }
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            showNotification('Error', error.message || 'Failed to save', 'error');
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = isEditMode
                ? '<i class="fas fa-save"></i> Update Customer + Supplier'
                : '<i class="fas fa-save"></i> Save Customer + Supplier';
        });
    });

    resetBtn.addEventListener('click', function () {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            resetFormState();
        }
    });
});
