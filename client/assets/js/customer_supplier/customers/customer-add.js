document.addEventListener('DOMContentLoaded', function () {
    // ===== CUSTOMIZE FIELDS FUNCTIONALITY =====
    const CUSTOMIZE_FIELDS_KEY = 'customerFormCustomizeFields';
    
    // Define all customizable fields
    const allFields = [
        { id: 'company', label: 'Company', visible: true },
        { id: 'customerCode', label: 'Customer Code', visible: true },
        { id: 'customerGroup', label: 'Customer Group', visible: true },
        { id: 'customerCategory', label: 'Customer Category', visible: true },
        { id: 'brandName', label: 'Brand Name', visible: true },
        { id: 'customerName', label: 'Customer Name', visible: true },
        { id: 'outStation', label: 'Out Station', visible: true },
        { id: 'shopkeeperName', label: 'Shopkeeper Name', visible: false },
        { id: 'address', label: 'Address', visible: false },
        { id: 'poBoxNo', label: 'P.O. Box No', visible: false },
        { id: 'primaryPhone', label: 'Primary Phone', visible: false },
        { id: 'secondaryPhone', label: 'Secondary Phone', visible: false },
        { id: 'email', label: 'Email Address', visible: false },
        { id: 'identityCard', label: 'Identity Card No', visible: false },
        { id: 'salesOfficer', label: 'Associated Sales Officer', visible: false },
        { id: 'supplierMan', label: 'Supplier Man', visible: false },
        { id: 'project', label: 'Project', visible: false },
        { id: 'country', label: 'Country', visible: false },
        { id: 'region', label: 'Region', visible: false },
        { id: 'city', label: 'City', visible: false },
        { id: 'cityZone', label: 'City Zone', visible: false },
        { id: 'area', label: 'Area', visible: true },
        { id: 'isSalesTaxRegistered', label: 'Is Sales Tax Registered?', visible: false },
        { id: 'strn', label: 'STRN', visible: false },
        { id: 'isFiler', label: 'Is Filer?', visible: false },
        { id: 'ntn', label: 'NTN', visible: false },
        { id: 'advanceIncomeTax', label: 'Advance Income Tax %', visible: false },
        { id: 'defaultDiscount', label: 'Default Discount %', visible: false },
        { id: 'balanceLimit', label: 'Credit Limit', visible: false },
        { id: 'balancePeriodLimit', label: 'Credit Period Limit (Days)', visible: false },
        { id: 'openingDebit', label: 'Opening Debit Amount', visible: false },
        { id: 'openingCredit', label: 'Opening Credit Amount', visible: false },
        { id: 'isWholesaler', label: 'Is Wholesaler?', visible: false },
        { id: 'blacklist', label: 'Blacklist Customer', visible: false },
        // Opening Balance Invoices Section
        { id: 'openingInvoicesSection', label: '📋 Opening Balance Invoices', visible: false },
        // Sub Accounts Section
        { id: 'subAccountsTableBody', label: '👥 Sub Accounts', visible: false }
    ];
    
    // Declare notification elements early for customize field functions to use
    const notification = document.getElementById('notification');
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationClose = document.getElementById('notificationClose');
    
    // Get field visibility settings from localStorage
    function getFieldVisibility() {
        const defaults = getDefaultVisibility();
        const stored = localStorage.getItem(CUSTOMIZE_FIELDS_KEY);
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                // Merge: new fields not in cache get their default value
                return Object.assign({}, defaults, parsed);
            } catch (e) {
                return defaults;
            }
        }
        return defaults;
    }
    
    // Get default visibility for all fields
    function getDefaultVisibility() {
        const defaults = {};
        allFields.forEach(field => {
            defaults[field.id] = field.visible;
        });
        return defaults;
    }
    
    // Save field visibility to localStorage
    function saveFieldVisibility(visibility) {
        localStorage.setItem(CUSTOMIZE_FIELDS_KEY, JSON.stringify(visibility));
    }
    
    // Get form group container for a field
    function getFieldFormGroup(fieldId) {
        const element = document.getElementById(fieldId);
        if (!element) return null;
        
        // Handle special cases like sections (Opening Invoices, Sub Accounts)
        if (fieldId === 'openingInvoicesSection' || fieldId === 'subAccountsSection') {
            return element;
        }
        
        // Find the closest .form-group parent
        let parent = element.closest('.form-group');
        if (parent) return parent;
        
        // For checkboxes, might be in different structure
        parent = element.closest('.checkbox-group');
        if (parent && parent.closest('.form-group')) {
            return parent.closest('.form-group');
        }
        
        return parent;
    }
    
    // Toggle field visibility
    function toggleFieldVisibility(fieldId, isVisible) {
        const formGroup = getFieldFormGroup(fieldId);
        if (formGroup) {
            formGroup.style.display = isVisible ? '' : 'none';
        }
        
        // Hide section heading if all fields in section are hidden
        hideSectionIfEmpty(fieldId);
    }
    
    // Hide section heading if all its fields are hidden
    function hideSectionIfEmpty(fieldId) {
        const element = document.getElementById(fieldId);
        if (!element) return;
        
        const section = element.closest('.form-section');
        if (!section) return;
        
        const formGroups = section.querySelectorAll('.form-group');
        let hasVisibleField = false;
        
        formGroups.forEach(fg => {
            const hasInput = fg.querySelector('input, select, textarea');
            if (hasInput && fg.style.display !== 'none') {
                hasVisibleField = true;
            }
        });
        
        const heading = section.querySelector('.section-title');
        if (heading) {
            heading.style.display = hasVisibleField ? '' : 'none';
        }
    }
    
    // Apply stored visibility settings on page load
    function applyFieldVisibility() {
        const visibility = getFieldVisibility();
        allFields.forEach(field => {
            if (visibility.hasOwnProperty(field.id)) {
                toggleFieldVisibility(field.id, visibility[field.id]);
            }
        });
    }
    
    // Initialize customize fields modal
    const customizeFieldsBtn = document.getElementById('customizeFieldsBtn');
    const customizeFieldsModal = document.getElementById('customizeFieldsModal');
    const customizeFieldsModalClose = document.getElementById('customizeFieldsModalClose');
    const customizeFieldsList = document.getElementById('customizeFieldsList');
    const customizeFieldsSaveBtn = document.getElementById('customizeFieldsSaveBtn');
    const customizeFieldsResetBtn = document.getElementById('customizeFieldsResetBtn');
    const customizeFieldsSelectAllBtn = document.getElementById('customizeFieldsSelectAllBtn');
    const customizeFieldsDeselectAllBtn = document.getElementById('customizeFieldsDeselectAllBtn');
    
    // Define showNotification before using it in customize fields
    function showNotification(title, message, type = 'success') {
        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');

        // Auto hide after 5 seconds
        setTimeout(function () {
            notification.classList.remove('show');
        }, 5000);
    }
    
    // Populate customize fields modal
    function populateCustomizeFieldsList() {
        const visibility = getFieldVisibility();
        customizeFieldsList.innerHTML = '';
        
        allFields.forEach(field => {
            const isVisible = visibility[field.id] !== undefined ? visibility[field.id] : field.visible;
            const checkbox = document.createElement('div');
            checkbox.className = 'customize-field-item';
            checkbox.innerHTML = `
                <label class=\"customize-field-label\">
                    <input type=\"checkbox\" class=\"customize-field-checkbox\" data-field-id=\"${field.id}\" ${isVisible ? 'checked' : ''}>
                    <span>${field.label}</span>
                </label>
            `;
            customizeFieldsList.appendChild(checkbox);
        });
    }
    
    // Open customize fields modal
    customizeFieldsBtn.addEventListener('click', function() {
        populateCustomizeFieldsList();
        customizeFieldsModal.classList.add('show');
    });
    
    // Close customize fields modal
    function closeCustomizeFieldsModal() {
        customizeFieldsModal.classList.remove('show');
    }
    
    customizeFieldsModalClose.addEventListener('click', closeCustomizeFieldsModal);
    customizeFieldsModal.addEventListener('click', function(e) {
        if (e.target === customizeFieldsModal) closeCustomizeFieldsModal();
    });
    
    // Save field preferences
    customizeFieldsSaveBtn.addEventListener('click', function() {
        const checkboxes = customizeFieldsList.querySelectorAll('.customize-field-checkbox');
        const visibility = {};
        
        checkboxes.forEach(checkbox => {
            const fieldId = checkbox.dataset.fieldId;
            visibility[fieldId] = checkbox.checked;
        });
        
        saveFieldVisibility(visibility);
        applyFieldVisibility();
        
        // Hide all empty sections
        document.querySelectorAll('.form-section').forEach(section => {
            const formGroups = section.querySelectorAll('.form-group');
            let hasVisibleField = false;
            
            formGroups.forEach(fg => {
                const hasInput = fg.querySelector('input, select, textarea');
                const hasCheckbox = fg.querySelector('input[type="checkbox"]');
                if ((hasInput || hasCheckbox) && fg.style.display !== 'none') {
                    hasVisibleField = true;
                }
            });
            
            const heading = section.querySelector('.section-title');
            if (heading) {
                heading.style.display = hasVisibleField ? '' : 'none';
            }
            
            // Hide helper-text only form-groups only if section is completely empty
            if (!hasVisibleField) {
                formGroups.forEach(fg => {
                    const hasInput = fg.querySelector('input, select, textarea');
                    const hasCheckbox = fg.querySelector('input[type="checkbox"]');
                    if (!hasInput && !hasCheckbox) {
                        fg.style.display = 'none';
                    }
                });
            } else {
                // Show helper-text if section has visible fields
                formGroups.forEach(fg => {
                    const hasInput = fg.querySelector('input, select, textarea');
                    const hasCheckbox = fg.querySelector('input[type="checkbox"]');
                    if (!hasInput && !hasCheckbox) {
                        fg.style.display = '';
                    }
                });
            }
        });
        
        closeCustomizeFieldsModal();
        showNotification('Success', 'Field preferences saved successfully!', 'success');
    });
    
    // Reset to default visibility
    customizeFieldsResetBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to reset to default field visibility?')) {
            const defaults = getDefaultVisibility();
            saveFieldVisibility(defaults);
            applyFieldVisibility();
            populateCustomizeFieldsList();
            showNotification('Success', 'Field visibility reset to default', 'success');
        }
    });
    
    // Select All button
    customizeFieldsSelectAllBtn.addEventListener('click', function() {
        const checkboxes = customizeFieldsList.querySelectorAll('.customize-field-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
    });
    
    // Deselect All button
    customizeFieldsDeselectAllBtn.addEventListener('click', function() {
        const checkboxes = customizeFieldsList.querySelectorAll('.customize-field-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
    });
    
    // Close notification handler
    notificationClose.addEventListener('click', function() {
        notification.classList.remove('show');
    });
    
    // Apply visibility on page load - MUST BE DONE BEFORE OTHER SETUP
    applyFieldVisibility();
    
    // Hide all empty sections after applying visibility
    document.querySelectorAll('.form-section').forEach(section => {
        const formGroups = section.querySelectorAll('.form-group');
        let hasVisibleField = false;
        
        formGroups.forEach(fg => {
            const hasInput = fg.querySelector('input, select, textarea');
            const hasCheckbox = fg.querySelector('input[type="checkbox"]');
            if ((hasInput || hasCheckbox) && fg.style.display !== 'none') {
                hasVisibleField = true;
            }
        });
        
        const heading = section.querySelector('.section-title');
        if (heading && !hasVisibleField) {
            heading.style.display = 'none';
        }
    });
    
    // Check permissions
    checkPermissions();
    // Set customer code field to show Auto Generated
    document.getElementById('customerCode').value = 'Auto Generated';
    
    // Load customer types and categories
    loadCustomerTypes();
    loadCustomerCategories();
    loadBrands();
    
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;

    // Check for saved theme preference or default to light
    const savedTheme = localStorage.getItem('fuelingsys-theme') || 'light';
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            if (body.classList.contains('dark-mode')) {
                body.classList.remove('dark-mode');
                localStorage.setItem('fuelingsys-theme', 'light');
            } else {
                body.classList.add('dark-mode');
                localStorage.setItem('fuelingsys-theme', 'dark');
            }
        });
    }

    // Form Validation
    const form = document.getElementById('customerForm');
    const customerName = document.getElementById('customerName');
    
    const primaryPhone = document.getElementById('primaryPhone');
    const secondaryPhone = document.getElementById('secondaryPhone');
    const identityCard = document.getElementById('identityCard');
    const email = document.getElementById('email');
    const openingDebit = document.getElementById('openingDebit');
    const openingCredit = document.getElementById('openingCredit');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');

    // Territory dropdowns
    const countrySelect = document.getElementById('country');
    const regionSelect = document.getElementById('region');
    const citySelect = document.getElementById('city');
    const cityZoneSelect = document.getElementById('cityZone');
    const areaSelect = document.getElementById('area');
    const salesOfficerSelect = document.getElementById('salesOfficer');
    const supplierManSelect = document.getElementById('supplierMan');
    const companySelect = document.getElementById('company');

    // Taxation fields
    const isSalesTaxRegistered = document.getElementById('isSalesTaxRegistered');
    const strnGroup = document.getElementById('strnGroup');
    const strn = document.getElementById('strn');
    const isFiler = document.getElementById('isFiler');
    const ntnGroup = document.getElementById('ntnGroup');
    const ntn = document.getElementById('ntn');

    // Opening invoices
    const openingInvoicesSection = document.getElementById('openingInvoicesSection');
    const invoicesTableBody = document.getElementById('invoicesTableBody');
    const addInvoiceBtn = document.getElementById('addInvoiceBtn');
    let invoiceRows = [];
    let suppliers = [];
    let employees = [];

    // Sub accounts
    const subAccountsTableBody = document.getElementById('subAccountsTableBody');
    const addSubAccountBtn = document.getElementById('addSubAccountBtn');
    let subAccountCounter = 0;

    let isSubmitting = false;

    // Load suppliers and employees
    loadSuppliers();
    loadEmployees();

    function loadSuppliers() {
        fetch('../../../../server/api/customer_supplier/customers/get-suppliers.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    suppliers = data.suppliers;
                }
            });
    }

    function loadEmployees() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    employees = data.employees;
                }
            });
    }

    // Validation functions
    function validateName(name) {
        return name.trim().length > 0;
    }

    function validatePhone(phone) {
        if (!phone) return true; // Phone is optional
        const regex = /^[0-9]*$/;
        return regex.test(phone) && phone.length <= 15;
    }

    function validateIdentityCard(card) {
        if (!card) return true; // Identity card is optional
        const regex = /^[0-9]*$/;
        return regex.test(card) && card.length <= 15;
    }

    function validateEmail(email) {
        if (!email) return true; // Email is optional
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email) && email.length <= 300;
    }

    // Real-time validation - only for required fields
    customerName.addEventListener('input', function () {
        const errorElement = document.getElementById('customerNameError');
        if (!validateName(this.value)) {
            this.classList.add('error');
            errorElement.style.display = 'flex';
        } else {
            this.classList.remove('error');
            errorElement.style.display = 'none';
        }
    });

    // Optional field validation (only show errors if field has content)
    primaryPhone.addEventListener('input', function () {
        const errorElement = document.getElementById('primaryPhoneError');
        if (this.value && !validatePhone(this.value)) {
            this.classList.add('error');
            errorElement.style.display = 'flex';
        } else {
            this.classList.remove('error');
            errorElement.style.display = 'none';
        }
    });

    secondaryPhone.addEventListener('input', function () {
        const errorElement = document.getElementById('secondaryPhoneError');
        if (this.value && !validatePhone(this.value)) {
            this.classList.add('error');
            errorElement.style.display = 'flex';
        } else {
            this.classList.remove('error');
            errorElement.style.display = 'none';
        }
    });

    identityCard.addEventListener('input', function () {
        const errorElement = document.getElementById('identityCardError');
        if (this.value && !validateIdentityCard(this.value)) {
            this.classList.add('error');
            errorElement.style.display = 'flex';
        } else {
            this.classList.remove('error');
            errorElement.style.display = 'none';
        }
    });

    email.addEventListener('input', function () {
        const errorElement = document.getElementById('emailError');
        if (this.value && !validateEmail(this.value)) {
            this.classList.add('error');
            errorElement.style.display = 'flex';
        } else {
            this.classList.remove('error');
            errorElement.style.display = 'none';
        }
    });

    // Taxation field visibility
    isSalesTaxRegistered.addEventListener('change', function() {
        strnGroup.style.display = this.checked ? 'flex' : 'none';
        if (!this.checked) strn.value = '';
    });

    isFiler.addEventListener('change', function() {
        ntnGroup.style.display = this.checked ? 'flex' : 'none';
        if (!this.checked) ntn.value = '';
    });

    // Initialize Select2 on territory dropdowns
    $(document).ready(function() {
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
    });

    // Load territory data
    loadCountries();
    loadAllRegions();
    loadAllCities();
    loadAllCityZones();
    loadAllAreas();
    loadSalesOfficers();
    loadSupplierMen();
    loadCompanies();
    loadProjects();

    $('#country').on('change', function() {
        const countryId = this.value;
        if (countryId) {
            loadRegions(countryId);
        } else {
            loadAllRegions();
        }
    });

    $('#region').on('change', function() {
        const regionId = this.value;
        if (regionId) {
            loadRegionHierarchy(regionId);
            loadCities(regionId);
        } else {
            loadAllCities();
        }
    });

    $('#city').on('change', function() {
        const cityId = this.value;
        if (cityId) {
            loadCityHierarchy(cityId);
            loadCityZones(cityId);
        } else {
            loadAllCityZones();
        }
    });

    $('#cityZone').on('change', function() {
        const cityZoneId = this.value;
        if (cityZoneId) {
            loadCityZoneHierarchy(cityZoneId);
            loadAreas(cityZoneId);
        } else {
            loadAllAreas();
        }
    });

    $('#area').on('change', function() {
        const areaId = this.value;
        if (areaId) {
            loadFullHierarchyFromArea(areaId);
        }
    });

    function loadCountries() {
        fetch('../../../../server/api/customer_supplier/customers/get-countries.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.countries.forEach(country => {
                        const option = new Option(country.country_name, country.id);
                        $('#country').append(option);
                    });
                }
            });
    }

    function loadAllRegions() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-regions.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.regions.forEach(region => {
                        const option = new Option(region.region_name, region.id);
                        $('#region').append(option);
                    });
                }
            });
    }

    function loadAllCities() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-cities.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.cities.forEach(city => {
                        const option = new Option(city.city_name, city.id);
                        $('#city').append(option);
                    });
                }
            });
    }

    function loadAllCityZones() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-city-zones.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.city_zones.forEach(zone => {
                        const option = new Option(zone.city_zone_name, zone.id);
                        $('#cityZone').append(option);
                    });
                }
            });
    }

    function loadRegions(countryId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-regions.php?country_id=${countryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#region').empty().append('<option value="">Select Region</option>');
                    data.regions.forEach(region => {
                        const option = new Option(region.region_name, region.id);
                        $('#region').append(option);
                    });
                }
            });
    }

    function loadCities(regionId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-cities.php?region_id=${regionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#city').empty().append('<option value="">Select City</option>');
                    data.cities.forEach(city => {
                        const option = new Option(city.city_name, city.id);
                        $('#city').append(option);
                    });
                }
            });
    }

    function loadCityZones(cityId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-city-zones.php?city_id=${cityId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#cityZone').empty().append('<option value="">Select City Zone</option>');
                    data.city_zones.forEach(zone => {
                        const option = new Option(zone.city_zone_name, zone.id);
                        $('#cityZone').append(option);
                    });
                }
            });
    }

    function loadAreas(cityZoneId) {
        return fetch(`../../../../server/api/customer_supplier/customers/get-areas.php?city_zone_id=${cityZoneId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#area').empty().append('<option value="">Select Area</option>');
                    data.areas.forEach(area => {
                        const option = new Option(area.area_name, area.id);
                        $('#area').append(option);
                    });
                }
            });
    }

    function loadAllAreas() {
        fetch('../../../../server/api/customer_supplier/customers/get-all-areas.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#area').empty().append('<option value="">Select Area</option>');
                    data.areas.forEach(area => {
                        const option = new Option(area.area_name, area.id);
                        $('#area').append(option);
                    });
                }
            });
    }

    function loadRegionHierarchy(regionId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-region-hierarchy.php?region_id=${regionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hierarchy) {
                    $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                }
            });
    }

    function loadCityHierarchy(regionId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-city-hierarchy.php?region_id=${regionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hierarchy) {
                    $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                    $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                }
            });
    }

    function loadCityZoneHierarchy(cityId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-city-zone-hierarchy.php?city_id=${cityId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hierarchy) {
                    $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                    $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                    $('#city').val(data.hierarchy.city_id).trigger('change.select2');
                }
            });
    }

    function loadAreaHierarchy(cityZoneId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-area-hierarchy-from-zone.php?city_zone_id=${cityZoneId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hierarchy) {
                    $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                    $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                    $('#city').val(data.hierarchy.city_id).trigger('change.select2');
                    $('#cityZone').val(data.hierarchy.city_zone_id).trigger('change.select2');
                }
            });
    }

    function loadFullHierarchyFromArea(areaId) {
        fetch(`../../../../server/api/customer_supplier/customers/get-area-hierarchy.php?area_id=${areaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hierarchy) {
                    $('#country').val(data.hierarchy.country_id).trigger('change.select2');
                    $('#region').val(data.hierarchy.region_id).trigger('change.select2');
                    $('#city').val(data.hierarchy.city_id).trigger('change.select2');
                    $('#cityZone').val(data.hierarchy.city_zone_id).trigger('change.select2');
                }
            });
    }

    function loadSalesOfficers() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.employees.forEach(employee => {
                        const option = new Option(employee.full_name, employee.id);
                        $('#salesOfficer').append(option);
                    });
                    $('#salesOfficer').trigger('change');
                }
            });
    }

    function loadSupplierMen() {
        fetch('../../../../server/api/customer_supplier/customers/get-employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.employees.forEach(employee => {
                        const option = new Option(employee.full_name, employee.id);
                        $('#supplierMan').append(option);
                    });
                    $('#supplierMan').trigger('change');
                }
            });
    }

    function loadCompanies() {
        fetch('../../../../server/api/customer_supplier/customers/get-companies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.companies.forEach(company => {
                        const option = new Option(company.company_name, company.id);
                        $('#company').append(option);
                    });
                    // Auto-select if only one company
                    if (data.companies.length === 1) {
                        $('#company').val(data.companies[0].id).trigger('change');
                    }
                }
            });
    }

    function loadProjects() {
        fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.data.forEach(project => {
                        const label = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
                        const option = new Option(label, project.id);
                        $('#project').append(option);
                    });
                    $('#project').trigger('change');
                }
            });
    }

    function loadBrands() {
        fetch('../../../../server/api/customer_supplier/customers/get-brands.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.brands.forEach(brand => {
                        const option = new Option(brand.brand_name, brand.id);
                        $('#brandName').append(option);
                    });
                    $('#brandName').trigger('change');
                }
            });
    }

    // Opening balance mutual exclusion
    openingDebit.addEventListener('click', function() {
        // Allow manual entry by removing readonly temporarily
        if (invoicesTableBody.querySelectorAll('tr').length === 0) {
            this.removeAttribute('readonly');
        }
    });

    openingDebit.addEventListener('blur', function() {
        // If invoices exist, keep it readonly
        if (invoicesTableBody.querySelectorAll('tr').length > 0) {
            this.setAttribute('readonly', 'readonly');
        }
    });

    openingDebit.addEventListener('input', function() {
        if (this.value && parseFloat(this.value) > 0) {
            openingCredit.disabled = true;
            openingCredit.value = '';
        } else {
            openingCredit.disabled = false;
        }
    });

    openingCredit.addEventListener('input', function() {
        if (this.value && parseFloat(this.value) > 0) {
            openingDebit.disabled = true;
            openingDebit.value = '';
            invoiceRows = [];
            invoicesTableBody.innerHTML = '';
        } else {
            openingDebit.disabled = false;
        }
    });

    // Calculate total from invoices
    function calculateInvoiceTotal() {
        const rows = invoicesTableBody.querySelectorAll('tr');
        let total = 0;
        rows.forEach(row => {
            const debit = parseFloat(row.querySelector('.invoice-debit').value) || 0;
            total += debit;
        });
        openingDebit.value = total > 0 ? total.toFixed(2) : '';
        openingDebit.setAttribute('readonly', 'readonly');
        if (total > 0) {
            openingCredit.disabled = true;
        } else {
            openingCredit.disabled = false;
        }
    }

    // Add invoice row
    addInvoiceBtn.addEventListener('click', function() {
        const rowId = Date.now();
        const row = document.createElement('tr');
        row.dataset.rowId = rowId;
        
        row.innerHTML = `
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="invoice-distribution-search" list="suppliers-list-${rowId}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Search distribution...">
                <datalist id="suppliers-list-${rowId}">
                    ${suppliers.map(s => `<option value="${s.supplier_name}" data-id="${s.id}">`).join('')}
                </datalist>
                <input type="hidden" class="invoice-distribution" value="">
            </td>
            <td style="padding: 8px; border: 1px solid var(--border-default);">
                <input type="text" class="invoice-sales-officer-search" list="employees-list-${rowId}" style="width: 100%; padding: 8px; border: 1px solid var(--input-border); border-radius: 6px;" placeholder="Search sales officer...">
                <datalist id="employees-list-${rowId}">
                    ${employees.map(e => `<option value="${e.full_name}" data-id="${e.id}">`).join('')}
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
        
        // Handle distribution search
        const distSearch = row.querySelector('.invoice-distribution-search');
        const distHidden = row.querySelector('.invoice-distribution');
        distSearch.addEventListener('input', function() {
            const supplier = suppliers.find(s => s.supplier_name === this.value);
            distHidden.value = supplier ? supplier.id : '';
        });
        
        // Handle sales officer search
        const salesSearch = row.querySelector('.invoice-sales-officer-search');
        const salesHidden = row.querySelector('.invoice-sales-officer');
        salesSearch.addEventListener('input', function() {
            const employee = employees.find(e => e.full_name === this.value);
            salesHidden.value = employee ? employee.id : '';
        });
        
        // Auto-calculate on debit input
        row.querySelector('.invoice-debit').addEventListener('input', calculateInvoiceTotal);
        
        row.querySelector('.btn-remove-invoice').addEventListener('click', function() {
            row.remove();
            calculateInvoiceTotal();
        });
    });

    // Calculate sub account totals
    function calculateSubAccountTotals() {
        const rows = subAccountsTableBody.querySelectorAll('tr');
        let totalDebit = 0;
        let totalCredit = 0;
        rows.forEach(row => {
            const debit = parseFloat(row.querySelector('.sub-account-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.sub-account-credit').value) || 0;
            totalDebit += debit;
            totalCredit += credit;
        });
        openingDebit.value = totalDebit > 0 ? totalDebit.toFixed(2) : '';
        openingCredit.value = totalCredit > 0 ? totalCredit.toFixed(2) : '';
    }

    // Sub accounts functionality
    addSubAccountBtn.addEventListener('click', function() {
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
        
        debitInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                creditInput.disabled = true;
                creditInput.value = '';
            } else {
                creditInput.disabled = false;
            }
            calculateSubAccountTotals();
        });
        
        creditInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                debitInput.disabled = true;
                debitInput.value = '';
            } else {
                debitInput.disabled = false;
            }
            calculateSubAccountTotals();
        });
        
        row.querySelector('.btn-remove-sub-account').addEventListener('click', function() {
            row.remove();
            renumberSubAccounts();
            calculateSubAccountTotals();
        });
    });

    function renumberSubAccounts() {
        const rows = subAccountsTableBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
        });
        subAccountCounter = rows.length;
    }

    function collectSubAccountData() {
        const rows = subAccountsTableBody.querySelectorAll('tr');
        const subAccounts = [];
        rows.forEach(row => {
            const name = row.querySelector('.sub-account-name').value.trim();
            const debit = parseFloat(row.querySelector('.sub-account-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.sub-account-credit').value) || 0;
            if (name) {
                subAccounts.push({ 
                    sub_account_name: name,
                    debit: debit,
                    credit: credit
                });
            }
        });
        return subAccounts;
    }

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

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isSubmitting) return;

        // Validate only required fields
        const isNameValid = validateName(customerName.value);
        const isCompanyValid = companySelect.value !== '';

        if (!isCompanyValid) {
            showNotification('Validation Error', 'Company is required', 'error');
            companySelect.focus();
            document.getElementById('companyError').style.display = 'flex';
            return;
        }

        if (!isNameValid) {
            showNotification('Validation Error', 'Customer Name is required', 'error');
            customerName.focus();
            return;
        }

        // Validate optional fields only if they have content
        const isPrimaryPhoneValid = !primaryPhone.value || validatePhone(primaryPhone.value);
        const isSecondaryPhoneValid = !secondaryPhone.value || validatePhone(secondaryPhone.value);
        const isIdentityCardValid = !identityCard.value || validateIdentityCard(identityCard.value);
        const isEmailValid = !email.value || validateEmail(email.value);

        if (!isPrimaryPhoneValid || !isSecondaryPhoneValid || !isIdentityCardValid || !isEmailValid) {
            showNotification('Validation Error', 'Please correct the errors in the form', 'error');
            return;
        }

        // Disable submit button and show loading state
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Saving...';

        // Prepare form data
        const formData = {
            companyId: companySelect.value,
            customerTypeId: document.getElementById('customerGroup').value || null,
            customerCategoryId: document.getElementById('customerCategory').value || null,
            brandId: document.getElementById('brandName').value || null,
            customerName: customerName.value.trim(),
            address: document.getElementById('address').value.trim(),
            poBoxNo: document.getElementById('poBoxNo').value.trim(),
            primaryPhone: primaryPhone.value.trim(),
            secondaryPhone: secondaryPhone.value.trim(),
            identityCard: identityCard.value.trim(),
            email: email.value.trim(),
            countryId: countrySelect.value || null,
            regionId: regionSelect.value || null,
            cityId: citySelect.value || null,
            cityZoneId: cityZoneSelect.value || null,
            areaId: areaSelect.value || null,
            projectId: document.getElementById('project').value || null,
            salesOfficerId: salesOfficerSelect.value || null,
            supplierManId: supplierManSelect.value || null,
            isSalesTaxRegistered: isSalesTaxRegistered.checked ? 1 : 0,
            strn: isSalesTaxRegistered.checked && strn.value.trim() ? strn.value.trim() : null,
            isFiler: isFiler.checked ? 1 : 0,
            ntn: isFiler.checked && ntn.value.trim() ? ntn.value.trim() : null,
            advanceIncomeTax: document.getElementById('advanceIncomeTax').value || 0,
            defaultDiscount: document.getElementById('defaultDiscount').value || 0,
            openingDebit: document.getElementById('openingDebit').value || 0,
            openingCredit: document.getElementById('openingCredit').value || 0,
            balanceLimit: document.getElementById('balanceLimit').value || 0,
            balancePeriodLimit: document.getElementById('balancePeriodLimit').value || 0,
            isWholesaler: document.getElementById('isWholesaler').checked ? 1 : 0,
            blacklist: document.getElementById('blacklist').checked ? 1 : 0,
            outStation: document.getElementById('outStation').checked ? 1 : 0,
            openingInvoices: collectInvoiceData(),
            subAccounts: collectSubAccountData()
        };

        // Send to API
        fetch('../../../../server/api/customer_supplier/customers/customer-add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                
                // Reset form after successful submission
                setTimeout(function () {
                    form.reset();
                    document.getElementById('customerCode').value = 'Auto Generated';
                    openingDebit.disabled = false;
                    openingCredit.disabled = false;
                    $('#region').empty().append('<option value="">Select Region</option>').prop('disabled', true);
                    $('#city').empty().append('<option value="">Select City</option>').prop('disabled', true);
                    $('#cityZone').empty().append('<option value="">Select City Zone</option>').prop('disabled', true);
                    loadAllAreas();
                    $('#country').val('').trigger('change');
                    $('#project').val('').trigger('change');
                    $('#salesOfficer').val('').trigger('change');
                    $('#supplierMan').val('').trigger('change');
                    // Reset company if multiple companies exist
                    if ($('#company option').length > 2) {
                        $('#company').val('').trigger('change');
                    }
                    citySelect.disabled = true;
                    cityZoneSelect.disabled = true;
                    areaSelect.disabled = true;
                    isSalesTaxRegistered.checked = false;
                    isFiler.checked = false;
                    strnGroup.style.display = 'none';
                    ntnGroup.style.display = 'none';
                    invoiceRows = [];
                    invoicesTableBody.innerHTML = '';
                    subAccountCounter = 0;
                    subAccountsTableBody.innerHTML = '';
                    isSubmitting = false;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Customer';

                    // Clear any error states
                    document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
                    document.querySelectorAll('.error-text').forEach(el => {
                        el.style.display = 'none';
                    });
                }, 2000);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            showNotification('Error', error.message || 'Failed to save customer', 'error');
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Customer';
        });
    });

    // Reset form
    resetBtn.addEventListener('click', function () {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            form.reset();
            document.getElementById('customerCode').value = 'Auto Generated';
            openingDebit.disabled = false;
            openingCredit.disabled = false;
            countrySelect.value = '';
            $('#region').empty().append('<option value="">Select Region</option>').prop('disabled', true);
            $('#city').empty().append('<option value="">Select City</option>').prop('disabled', true);
            $('#cityZone').empty().append('<option value="">Select City Zone</option>').prop('disabled', true);
            loadAllAreas();
            $('#country').val('').trigger('change');
            $('#project').val('').trigger('change');
            $('#salesOfficer').val('').trigger('change');
            $('#supplierMan').val('').trigger('change');
            // Reset company if multiple companies exist
            if ($('#company option').length > 2) {
                $('#company').val('').trigger('change');
            }
            isSalesTaxRegistered.checked = false;
            isFiler.checked = false;
            strnGroup.style.display = 'none';
            ntnGroup.style.display = 'none';
            invoiceRows = [];
            invoicesTableBody.innerHTML = '';
            subAccountCounter = 0;
            subAccountsTableBody.innerHTML = '';
            // Clear error states
            document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.error-text').forEach(el => {
                el.style.display = 'none';
            });
        }
    });

    // Note: showNotification function is defined earlier in the customize fields section
    
    // Note: notificationClose event listener is handled in the customize fields section
    
    // Check permissions
    function checkPermissions() {
        fetch('../../../../server/api/auth/check-permission.php?category=Customer / Supplier&form_name=New Customer')
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = '../../../../errors/403.php';
                    return;
                }
                if (data.success && data.permissions) {
                    if (!data.permissions.includes('Add')) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-lock"></i> No Permission';
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            showNotification('Access Denied', 'You do not have permission to add customers', 'error');
                        });
                    }
                }
            });
    }
    
    // Customer Types Management
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
    
    // Customer Categories Management
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
    
    function loadCustomerTypes() {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customerTypes = data.types;
                    const select = document.getElementById('customerGroup');
                    select.innerHTML = '<option value="">Select Customer Group</option>';
                    data.types.forEach(type => {
                        const option = new Option(type.type_name, type.id);
                        select.appendChild(option);
                    });
                    $('#customerGroup').trigger('change');
                }
            });
    }
    
    function loadCustomerCategories() {
        fetch('../../../../server/api/customer_supplier/customers/customer-categories.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customerCategories = data.categories;
                    const select = document.getElementById('customerCategory');
                    select.innerHTML = '<option value="">Select Customer Category</option>';
                    data.categories.forEach(category => {
                        const option = new Option(category.category_name, category.id);
                        select.appendChild(option);
                    });
                    $('#customerCategory').trigger('change');
                }
            });
    }
    
    function loadTypesTable() {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customerTypes = data.types;
                    renderTypesTable(data.types);
                }
            });
    }
    
    function renderTypesTable(types) {
        typesTableBody.innerHTML = '';
        types.forEach(type => {
            const isSystem = type.tenant_id == 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default);">
                    ${type.type_name}
                    ${isSystem ? '<span class="system-badge"><i class="fas fa-lock"></i> System</span>' : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default); text-align: center;">
                    <button class="action-btn edit" data-id="${type.id}" ${isSystem ? 'disabled' : ''}>
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete" data-id="${type.id}" ${isSystem ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            typesTableBody.appendChild(row);
        });
    }
    
    manageTypesBtn.addEventListener('click', function() {
        loadTypesTable();
        typesModal.classList.add('show');
    });
    
    function closeTypesModal() {
        typesModal.classList.remove('show');
    }
    
    typesModalClose.addEventListener('click', closeTypesModal);
    typesCloseBtn.addEventListener('click', closeTypesModal);
    typesModal.addEventListener('click', function(e) {
        if (e.target === typesModal) closeTypesModal();
    });
    
    addTypeBtn.addEventListener('click', function() {
        currentEditingTypeId = null;
        typeFormTitle.innerHTML = '<i class="fas fa-plus"></i> Add Customer Type';
        typeName.value = '';
        typeFormModal.classList.add('show');
    });
    
    typesTableBody.addEventListener('click', function(e) {
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
            if (confirm('Are you sure you want to delete this customer type?')) {
                deleteType(typeId);
            }
        }
    });
    
    function closeTypeFormModal() {
        typeFormModal.classList.remove('show');
        currentEditingTypeId = null;
        typeName.value = '';
    }
    
    typeFormModalClose.addEventListener('click', closeTypeFormModal);
    typeFormCancelBtn.addEventListener('click', closeTypeFormModal);
    typeFormModal.addEventListener('click', function(e) {
        if (e.target === typeFormModal) closeTypeFormModal();
    });
    
    typeForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!typeName.value.trim()) {
            showNotification('Error', 'Type name is required', 'error');
            return;
        }
        
        typeFormSaveBtn.disabled = true;
        typeFormSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const url = '../../../../server/api/customer_supplier/customers/customer-types.php';
        const method = currentEditingTypeId ? 'PUT' : 'POST';
        const data = {
            type_name: typeName.value.trim()
        };
        
        if (currentEditingTypeId) {
            data.id = currentEditingTypeId;
        }
        
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                closeTypeFormModal();
                loadTypesTable();
                loadCustomerTypes();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error', 'Failed to save customer type', 'error');
        })
        .finally(() => {
            typeFormSaveBtn.disabled = false;
            typeFormSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
        });
    });
    
    function deleteType(typeId) {
        fetch('../../../../server/api/customer_supplier/customers/customer-types.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: typeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                loadTypesTable();
                loadCustomerTypes();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error', 'Failed to delete customer type', 'error');
        });
    }
    
    function loadCategoriesTable() {
        fetch('../../../../server/api/customer_supplier/customers/customer-categories.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customerCategories = data.categories;
                    renderCategoriesTable(data.categories);
                }
            });
    }
    
    function renderCategoriesTable(categories) {
        categoriesTableBody.innerHTML = '';
        categories.forEach(category => {
            const isSystem = category.tenant_id == 0;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default);">
                    ${category.category_name}
                    ${isSystem ? '<span class="system-badge"><i class="fas fa-lock"></i> System</span>' : ''}
                </td>
                <td style="padding: 12px; border-bottom: 1px solid var(--border-default); text-align: center;">
                    <button class="action-btn edit" data-id="${category.id}" ${isSystem ? 'disabled' : ''}>
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete" data-id="${category.id}" ${isSystem ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            categoriesTableBody.appendChild(row);
        });
    }
    
    manageCategoriesBtn.addEventListener('click', function() {
        loadCategoriesTable();
        categoriesModal.classList.add('show');
    });
    
    function closeCategoriesModal() {
        categoriesModal.classList.remove('show');
    }
    
    categoriesModalClose.addEventListener('click', closeCategoriesModal);
    categoriesCloseBtn.addEventListener('click', closeCategoriesModal);
    categoriesModal.addEventListener('click', function(e) {
        if (e.target === categoriesModal) closeCategoriesModal();
    });
    
    addCategoryBtn.addEventListener('click', function() {
        currentEditingCategoryId = null;
        categoryFormTitle.innerHTML = '<i class="fas fa-plus"></i> Add Customer Category';
        categoryName.value = '';
        categoryFormModal.classList.add('show');
    });
    
    categoriesTableBody.addEventListener('click', function(e) {
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
            if (confirm('Are you sure you want to delete this customer category?')) {
                deleteCategory(categoryId);
            }
        }
    });
    
    function closeCategoryFormModal() {
        categoryFormModal.classList.remove('show');
        currentEditingCategoryId = null;
        categoryName.value = '';
    }
    
    categoryFormModalClose.addEventListener('click', closeCategoryFormModal);
    categoryFormCancelBtn.addEventListener('click', closeCategoryFormModal);
    categoryFormModal.addEventListener('click', function(e) {
        if (e.target === categoryFormModal) closeCategoryFormModal();
    });
    
    categoryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!categoryName.value.trim()) {
            showNotification('Error', 'Category name is required', 'error');
            return;
        }
        categoryFormSaveBtn.disabled = true;
        categoryFormSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        const url = '../../../../server/api/customer_supplier/customers/customer-categories.php';
        const method = currentEditingCategoryId ? 'PUT' : 'POST';
        const data = { category_name: categoryName.value.trim() };
        if (currentEditingCategoryId) data.id = currentEditingCategoryId;
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                closeCategoryFormModal();
                loadCategoriesTable();
                loadCustomerCategories();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error', 'Failed to save customer category', 'error');
        })
        .finally(() => {
            categoryFormSaveBtn.disabled = false;
            categoryFormSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
        });
    });
    
    function deleteCategory(categoryId) {
        fetch('../../../../server/api/customer_supplier/customers/customer-categories.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: categoryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', data.message, 'success');
                loadCategoriesTable();
                loadCustomerCategories();
            } else {
                showNotification('Error', data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error', 'Failed to delete customer category', 'error');
        });
    }
});
