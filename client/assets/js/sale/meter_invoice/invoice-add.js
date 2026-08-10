document.addEventListener('DOMContentLoaded', function () {
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date').value = today;

    // Sample data for dropdowns
    // Branches will be fetched from API
    let branches = [];

    // Stations will be fetched from API
    let stations = [];

    // Products will be fetched from API
    let products = [];

    // Units will be fetched from API
    let units = [];

    // Fetch and initialize dropdowns
    fetchBranches();
    fetchStations();
    fetchProducts();
    fetchUnits();

    // Form submission
    const form = document.getElementById('meterReadingForm');
    const saveBtn = document.getElementById('saveBtn');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (validateForm()) {
            // Lock the save button
            saveBtn.disabled = true;
            saveBtn.classList.add('btn-loading');

            // Submit form data to API
            submitFormData();
        }
    });

    // Reset button
    document.getElementById('resetBtn').addEventListener('click', function () {
        resetForm();
        showNotification('Form has been reset', 'success');
    });

    // Form validation function
    function validateForm() {
        let isValid = true;

        // Validate required fields
        const requiredFields = [
            'date', 'openingReading'
        ];

        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + 'Error');

            if (field && !field.value.trim()) {
                field.classList.add('error');
                if (errorElement) errorElement.classList.add('show');
                isValid = false;
            } else if (field) {
                field.classList.remove('error');
                if (errorElement) errorElement.classList.remove('show');
            }
        });

        // Validate dropdowns
        const dropdowns = ['branch', 'station', 'product', 'unit'];

        dropdowns.forEach(dropdownId => {
            const toggle = document.getElementById(dropdownId + 'Toggle');
            const errorElement = document.getElementById(dropdownId + 'Error');

            if (toggle.textContent.includes('Select')) {
                toggle.classList.add('error');
                errorElement.classList.add('show');
                isValid = false;
            } else {
                toggle.classList.remove('error');
                errorElement.classList.remove('show');
            }
        });

        // Validate numeric fields
        const openingReading = document.getElementById('openingReading');

        if (openingReading.value && parseFloat(openingReading.value) < 0) {
            openingReading.classList.add('error');
            document.getElementById('openingReadingError').classList.add('show');
            isValid = false;
        }

        return isValid;
    }

    // Get form data
    function getFormData() {
        const branchId = document.getElementById('branchToggle').dataset.selectedId;
        const stationId = document.getElementById('stationToggle').dataset.selectedId;
        const productId = document.getElementById('productToggle').dataset.selectedId;
        const unitId = document.getElementById('unitToggle').dataset.selectedId;
        
        return {
            date: document.getElementById('date').value,
            branch_id: branchId,
            station_id: stationId,
            product_id: productId,
            unit_id: unitId,
            opening_reading: document.getElementById('openingReading').value
        };
    }

    // Submit form data to API
    async function submitFormData() {
        try {
            const formData = getFormData();
            const response = await fetch('../../../../server/api/sale/meter_invoice/invoice-add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'success');
                resetForm();
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            showNotification('Error saving data', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.classList.remove('btn-loading');
        }
    }

    // Reset form
    function resetForm() {
        form.reset();

        // Reset dropdowns
        const dropdowns = ['branch', 'station', 'product', 'unit'];
        dropdowns.forEach(id => {
            document.getElementById(id + 'Toggle').textContent = 'Select ' + id.charAt(0).toUpperCase() + id.slice(1);
            document.getElementById(id + 'Toggle').classList.remove('error');
        });

        // Set default date to today
        document.getElementById('date').value = today;

        // Clear error states
        document.querySelectorAll('.form-input.error').forEach(el => {
            el.classList.remove('error');
        });

        document.querySelectorAll('.form-error.show').forEach(el => {
            el.classList.remove('show');
        });
    }

    // Initialize dropdown
    function initializeDropdown(type, items) {
        const toggle = document.getElementById(type + 'Toggle');
        const menu = document.getElementById(type + 'Menu');
        const search = document.getElementById(type + 'Search');
        const itemsContainer = menu.querySelector('.dropdown-items');

        // Populate dropdown items
        items.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'dropdown-item';
            itemElement.textContent = item.code ? `${item.code} - ${item.name}` : item.name;
            itemElement.dataset.id = item.id;

            itemElement.addEventListener('click', function () {
                toggle.textContent = item.code ? `${item.code} - ${item.name}` : item.name;
                toggle.classList.remove('open');
                menu.classList.remove('show');

                // Auto-populate unit if product is selected
                if (type === 'product' && item.default_unit_id) {
                    const unit = units.find(u => u.id == item.default_unit_id);
                    if (unit) {
                        const unitToggle = document.getElementById('unitToggle');
                        unitToggle.textContent = unit.name;
                        unitToggle.dataset.selectedId = unit.id;
                    }
                }

                // Auto-populate products and branch if station is selected
                if (type === 'station') {
                    fetchProductsByStation(item.id);
                    fetchBranchByStation(item.id);
                    fetchLastReading(item.id);
                }

                // Store selected ID
                toggle.dataset.selectedId = item.id;

                // Clear search
                search.value = '';
                filterDropdownItems(type, '');
            });

            itemsContainer.appendChild(itemElement);
        });

        // Toggle dropdown
        toggle.addEventListener('click', function () {
            const isOpen = menu.classList.contains('show');

            // Close all dropdowns first
            document.querySelectorAll('.dropdown-menu.show').forEach(el => {
                el.classList.remove('show');
            });
            document.querySelectorAll('.dropdown-toggle.open').forEach(el => {
                el.classList.remove('open');
            });

            if (!isOpen) {
                menu.classList.add('show');
                toggle.classList.add('open');
                search.focus();
            }
        });

        // Filter items based on search
        search.addEventListener('input', function () {
            filterDropdownItems(type, this.value);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
                toggle.classList.remove('open');
            }
        });
    }

    // Filter dropdown items
    function filterDropdownItems(type, query) {
        const menu = document.getElementById(type + 'Menu');
        const items = menu.querySelectorAll('.dropdown-item');

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(query.toLowerCase())) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Fetch products from API
    async function fetchProducts() {
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/get-products.php');
            const result = await response.json();
            
            if (result.success) {
                products = result.data;
                initializeDropdown('product', products);
            } else {
                showNotification('Failed to load products', 'error');
            }
        } catch (error) {
            showNotification('Error loading products', 'error');
        }
    }

    // Fetch products by station
    async function fetchProductsByStation(stationId) {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-products-by-station.php?station_id=${stationId}`);
            const result = await response.json();
            
            if (result.success) {
                products = result.data;
                // Clear existing product dropdown
                const productToggle = document.getElementById('productToggle');
                const unitToggle = document.getElementById('unitToggle');
                productToggle.textContent = 'Select Product';
                productToggle.dataset.selectedId = '';
                unitToggle.textContent = 'Select Unit';
                unitToggle.dataset.selectedId = '';
                
                // Re-initialize product dropdown with filtered products
                const productMenu = document.getElementById('productMenu');
                const itemsContainer = productMenu.querySelector('.dropdown-items');
                itemsContainer.innerHTML = '';
                
                // Re-populate dropdown items for products
                products.forEach(product => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'dropdown-item';
                    itemElement.textContent = product.code ? `${product.code} - ${product.name}` : product.name;
                    itemElement.dataset.id = product.id;

                    itemElement.addEventListener('click', function () {
                        productToggle.textContent = product.code ? `${product.code} - ${product.name}` : product.name;
                        productToggle.dataset.selectedId = product.id;
                        productToggle.classList.remove('open');
                        productMenu.classList.remove('show');

                        // Auto-populate unit if product is selected
                        if (product.default_unit_id) {
                            const unit = units.find(u => u.id == product.default_unit_id);
                            if (unit) {
                                unitToggle.textContent = unit.name;
                                unitToggle.dataset.selectedId = unit.id;
                            }
                        }
                    });

                    itemsContainer.appendChild(itemElement);
                });
                
                // Auto-select first product if only one available
                if (products.length === 1) {
                    const product = products[0];
                    productToggle.textContent = product.code ? `${product.code} - ${product.name}` : product.name;
                    productToggle.dataset.selectedId = product.id;
                    
                    // Auto-populate unit for the selected product
                    if (product.default_unit_id) {
                        const unit = units.find(u => u.id == product.default_unit_id);
                        if (unit) {
                            unitToggle.textContent = unit.name;
                            unitToggle.dataset.selectedId = unit.id;
                        }
                    }
                }
            } else {
                // Clear product dropdown if no products found
                const productToggle = document.getElementById('productToggle');
                const unitToggle = document.getElementById('unitToggle');
                productToggle.textContent = 'Select Product';
                productToggle.dataset.selectedId = '';
                unitToggle.textContent = 'Select Unit';
                unitToggle.dataset.selectedId = '';
                
                const productMenu = document.getElementById('productMenu');
                const itemsContainer = productMenu.querySelector('.dropdown-items');
                itemsContainer.innerHTML = '';
                
                showNotification('No products found for this station', 'error');
            }
        } catch (error) {
            showNotification('Error loading products for station', 'error');
        }
    }

    // Fetch branch by station
    async function fetchBranchByStation(stationId) {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-branch-by-station.php?station_id=${stationId}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                const branch = result.data;
                const branchToggle = document.getElementById('branchToggle');
                branchToggle.textContent = `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type.replace('_', ' ')})`;
                branchToggle.dataset.selectedId = branch.id;
            }
        } catch (error) {
            showNotification('Error loading branch for station', 'error');
        }
    }

    // Fetch branches from API
    async function fetchBranches() {
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/get-branches.php');
            const result = await response.json();
            
            if (result.success) {
                branches = result.data.map(branch => ({ 
                    id: branch.id, 
                    code: branch.branch_code, 
                    name: `${branch.branch_name} (${branch.branch_type.replace('_', ' ')})` 
                }));
                initializeDropdown('branch', branches);
            } else {
                showNotification('Failed to load branches', 'error');
            }
        } catch (error) {
            showNotification('Error loading branches', 'error');
        }
    }

    // Fetch stations from API
    async function fetchStations() {
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/get-stations.php');
            const result = await response.json();
            
            if (result.success) {
                stations = result.data.map(station => ({ 
                    id: station.id, 
                    code: station.station_code, 
                    name: station.station_name 
                }));
                initializeDropdown('station', stations);
            } else {
                showNotification('Failed to load stations', 'error');
            }
        } catch (error) {
            showNotification('Error loading stations', 'error');
        }
    }

    // Fetch units from API
    async function fetchUnits() {
        try {
            const response = await fetch('../../../../server/api/sale/meter_invoice/get-units.php');
            const result = await response.json();
            
            if (result.success) {
                units = result.data.map(unit => ({ id: unit.id, name: unit.uom_name }));
                initializeDropdown('unit', units);
            } else {
                showNotification('Failed to load units', 'error');
            }
        } catch (error) {
            showNotification('Error loading units', 'error');
        }
    }

    // Fetch last reading for station
    async function fetchLastReading(stationId) {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-last-reading.php?station_id=${stationId}`);
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('openingReading').value = parseFloat(result.last_reading).toFixed(2);
            }
        } catch (error) {
            console.error('Error loading last reading:', error);
        }
    }

    // Show notification
    function showNotification(message, type) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type} show`;

        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }
});