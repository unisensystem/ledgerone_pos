document.addEventListener('DOMContentLoaded', function () {
    // Check if editing
    const isEdit = window.adjustmentData !== null;
    
    // Reason hierarchy data structure
    const reasonHierarchy = {
        physical_loss: {
            label: 'Physical Loss/Shrinkage',
            accountingTreatment: { debit: 95, credit: 33 },
            primary: {
                theft: {
                    label: 'Theft',
                    secondary: ['Customer Theft', 'Employee Theft', 'Vendor Theft', 'Burglary/Robbery']
                },
                mystery_shrinkage: {
                    label: 'Mystery Shrinkage',
                    secondary: ['Unaccounted Loss', 'Counting Discrepancy', 'Book-to-Physical Variance']
                },
                pilferage: {
                    label: 'Pilferage',
                    secondary: ['Small-Scale Employee Theft', 'Consumption Staff']
                },
                evaporation: {
                    label: 'Evaporation Loss',
                    secondary: ['Tank Breathing Loss', 'Filling Loss', 'Temperature Expansion/Contraction']
                }
            }
        },
        damage: {
            label: 'Damage/Spoilage',
            accountingTreatment: { debit: 94, credit: 33 },
            primary: {
                handling_damage: {
                    label: 'Handling Damage',
                    secondary: ['Warehouse Mishandling', 'Forklift Damage', 'Loading/Unloading Damage', 'Dropped Items']
                },
                transit_damage: {
                    label: 'Transit Damage',
                    secondary: ['Inbound Shipment', 'Outbound Shipment', 'Inter-Warehouse Transfer']
                },
                environmental_damage: {
                    label: 'Environmental Damage',
                    secondary: ['Water Damage', 'Heat Damage', 'Cold Chain Failure', 'Humidity Damage']
                },
                package_damage: {
                    label: 'Package Damage',
                    secondary: ['Torn Packaging', 'Crushed Boxes', 'Unsealed Containers']
                }
            }
        },
        obsolescence: {
            label: 'Obsolescence/Expiry',
            accountingTreatment: { debit: 96, credit: 33 },
            primary: {
                product_expiry: {
                    label: 'Product Expiry',
                    secondary: ['Past Shelf Life', 'Best Before Date Passed', 'Use-By Date Expired']
                },
                seasonal_obsolescence: {
                    label: 'Seasonal Obsolescence',
                    secondary: ['Holiday Stock Post-Season', 'Fashion Out of Season', 'Seasonal Product Leftover']
                },
                technological_obsolescence: {
                    label: 'Technological Obsolescence',
                    secondary: ['Model Discontinued', 'New Version Released', 'Technology Superseded']
                },
                regulatory_obsolescence: {
                    label: 'Regulatory Obsolescence',
                    secondary: ['Safety Standards Changed', 'Regulatory Ban', 'Certification Lapsed']
                }
            }
        },
        accounting: {
            label: 'Accounting/Control Adjustments',
            accountingTreatment: { debit: 99, credit: 33 },
            primary: {
                system_error: {
                    label: 'System/Data Entry Error',
                    secondary: ['Wrong Quantity Entered', 'Incorrect Cost Posted', 'Duplicate Entry']
                }
            }
        },
        process: {
            label: 'Process-Related Adjustments',
            primary: {
                sampling: {
                    label: 'Sampling/Test Units',
                    accountingTreatment: { debit: 101, credit: 33 },
                    secondary: ['Quality Testing', 'Showroom Samples', 'Demo Units']
                },
                donations: {
                    label: 'Donations/Charity',
                    accountingTreatment: { debit: 102, credit: 33 },
                    secondary: ['Corporate Giving', 'Disaster Relief', 'Community Support']
                }
            }
        },
        natural: {
            label: 'Natural/Extraordinary Loss',
            accountingTreatment: { debit: 96, credit: 33 },
            primary: {
                natural_disaster: {
                    label: 'Natural Disaster',
                    secondary: ['Flood Damage', 'Fire Damage', 'Earthquake Damage']
                },
                accident: {
                    label: 'Accident/Casualty',
                    secondary: ['Vehicle Accident', 'Facility Accident', 'Equipment Failure']
                }
            }
        }
    };

    // Get dropdown elements
    const mainReasonSelect = document.getElementById('mainReason');
    const primaryReasonSelect = document.getElementById('primaryReason');
    const secondaryReasonSelect = document.getElementById('secondaryReason');

    // Main reason change handler
    mainReasonSelect.addEventListener('change', function() {
        const mainValue = this.value;
        
        // Reset and disable dependent dropdowns
        primaryReasonSelect.innerHTML = '<option value="">Select primary reason</option>';
        secondaryReasonSelect.innerHTML = '<option value="">Select secondary reason</option>';
        secondaryReasonSelect.disabled = true;
        
        if (mainValue && reasonHierarchy[mainValue]) {
            // Populate primary reasons
            const primaryReasons = reasonHierarchy[mainValue].primary;
            Object.keys(primaryReasons).forEach(key => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = primaryReasons[key].label;
                primaryReasonSelect.appendChild(option);
            });
            primaryReasonSelect.disabled = false;
        } else {
            primaryReasonSelect.disabled = true;
        }
        
        clearError(this);
    });

    // Primary reason change handler
    primaryReasonSelect.addEventListener('change', function() {
        const mainValue = mainReasonSelect.value;
        const primaryValue = this.value;
        
        // Reset secondary dropdown
        secondaryReasonSelect.innerHTML = '<option value="">Select secondary reason</option>';
        
        if (mainValue && primaryValue && reasonHierarchy[mainValue]?.primary[primaryValue]) {
            // Populate secondary reasons
            const secondaryReasons = reasonHierarchy[mainValue].primary[primaryValue].secondary;
            secondaryReasons.forEach(reason => {
                const option = document.createElement('option');
                option.value = reason;
                option.textContent = reason;
                secondaryReasonSelect.appendChild(option);
            });
            secondaryReasonSelect.disabled = false;
        } else {
            secondaryReasonSelect.disabled = true;
        }
        
        clearError(this);
    });

    // Secondary reason change handler
    secondaryReasonSelect.addEventListener('change', function() {
        clearError(this);
    });

    // Generate adjustment code
    function generateAdjustmentCode() {
        fetch('../../../../server/api/inventory/stock_adjustment/generate-code.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('adjustmentCode').value = data.adjustment_code;
                }
            })
            .catch(error => console.error('Error generating code:', error));
    }

    // Set initial adjustment code
    generateAdjustmentCode();

    // Fetch branches from API
    let branches = [];
    let branchesMap = {};
    let companies = [];
    fetch('../../../../server/api/inventory/stock_adjustment/get-branches.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                branches = data.branches;
                branches.forEach(b => {
                    branchesMap[b.name] = b.id;
                });
                
                // Load edit data after branches are loaded
                if (isEdit && window.adjustmentData) {
                    document.getElementById('adjustmentCode').value = window.adjustmentData.adjustment_code;
                    document.getElementById('branchInput').value = window.adjustmentData.branch_name;
                    document.getElementById('adjustmentType').value = window.adjustmentData.adjustment_type.toLowerCase();
                    document.getElementById('remarks').value = window.adjustmentData.remarks || '';
                    
                    // Set reasons
                    setTimeout(() => {
                        let mainReasonKey = null;
                        for (const [key, value] of Object.entries(reasonHierarchy)) {
                            if (value.label === window.adjustmentData.main_reason) {
                                mainReasonKey = key;
                                break;
                            }
                        }
                        
                        if (mainReasonKey) {
                            mainReasonSelect.value = mainReasonKey;
                            mainReasonSelect.dispatchEvent(new Event('change'));
                            
                            setTimeout(() => {
                                let primaryReasonKey = null;
                                for (const [key, value] of Object.entries(reasonHierarchy[mainReasonKey].primary)) {
                                    if (value.label === window.adjustmentData.primary_reason) {
                                        primaryReasonKey = key;
                                        break;
                                    }
                                }
                                
                                if (primaryReasonKey) {
                                    primaryReasonSelect.value = primaryReasonKey;
                                    primaryReasonSelect.dispatchEvent(new Event('change'));
                                    
                                    setTimeout(() => {
                                        secondaryReasonSelect.value = window.adjustmentData.secondary_reason;
                                    }, 100);
                                }
                            }, 100);
                        }
                    }, 100);
                }
            }
        })
        .catch(error => console.error('Error fetching branches:', error));

    // Fetch companies from API
    fetch('../../../../server/api/inventory/stock_adjustment/get-companies.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                companies = data.data;
                const companySelect = document.getElementById('company');
                data.data.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companySelect.appendChild(option);
                });
                
                // Auto-select if only one company
                if (data.data.length === 1) {
                    companySelect.value = data.data[0].id;
                }
                
                // Set company in edit mode
                if (isEdit && window.adjustmentData && window.adjustmentData.company_id) {
                    companySelect.value = window.adjustmentData.company_id;
                }
            }
        })
        .catch(error => console.error('Error fetching companies:', error));

    // Fetch units from API
    let units = [];
    let unitsLoaded = false;
    fetch('../../../../server/api/inventory/stock_adjustment/get-units.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                units = data.units;
                unitsLoaded = true;
                // Add first row only after units are loaded (and not in edit mode)
                if (!isEdit) {
                    addTableRow();
                }
            }
        })
        .catch(error => console.error('Error fetching units:', error));

    // Fetch products from API
    let products = [];
    let productsMap = {};
    fetch('../../../../server/api/inventory/stock_adjustment/get-products.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                products = data.products;
                products.forEach(p => {
                    productsMap[p.code] = { id: p.id, name: p.name };
                });
                
                // Load items after products are fetched
                if (isEdit && window.adjustmentData) {
                    const tryRender = () => {
                        if (unitsLoaded) {
                            items = [];
                            window.adjustmentData.items.forEach(item => {
                                items.push({
                                    id: itemCounter++,
                                    productCode: `${item.product_code} - ${productsMap[item.product_code]?.name || ''}`,
                                    productId: productsMap[item.product_code]?.id || '',
                                    unitId: item.unit_id || '',
                                    quantity: parseFloat(item.qty),
                                    rate: parseFloat(item.rate),
                                    gross: parseFloat(item.stock_value)
                                });
                            });
                            renderTable();
                        } else {
                            setTimeout(tryRender, 100);
                        }
                    };
                    setTimeout(tryRender, 200);
                }
            }
        })
        .catch(error => console.error('Error fetching products:', error));

    // Initialize items table with one empty row
    let itemCounter = 1;
    let items = [];

    function getUnitOptions(selectedId) {
        return units.map(u => `<option value="${u.id}" ${u.id == selectedId ? 'selected' : ''}>${u.uom_name}</option>`).join('');
    }

    // Branch dropdown functionality
    const branchInput = document.getElementById('branchInput');
    const branchOptions = document.getElementById('branchOptions');

    function populateBranchOptions() {
        branchOptions.innerHTML = '';
        const searchTerm = branchInput.value.toLowerCase();

        branches.forEach(branch => {
            if (branch.name.toLowerCase().includes(searchTerm)) {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.textContent = branch.name;
                
                if (branch.isParent) {
                    option.style.fontWeight = 'bold';
                    option.style.color = '#6b7280';
                    option.style.cursor = 'not-allowed';
                    option.style.backgroundColor = '#f7f9fc';
                } else {
                    option.addEventListener('click', function () {
                        branchInput.value = branch.name;
                        hideDropdown(branchOptions);
                        clearError(branchInput);
                    });
                }
                
                branchOptions.appendChild(option);
            }
        });

        if (branchOptions.children.length > 0) {
            showDropdown(branchOptions);
        } else {
            hideDropdown(branchOptions);
        }
    }

    branchInput.addEventListener('input', populateBranchOptions);
    branchInput.addEventListener('focus', populateBranchOptions);

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!branchInput.contains(e.target) && !branchOptions.contains(e.target)) {
            hideDropdown(branchOptions);
        }
    });

    // Shared floating dropdown for product search
    const floatingDropdown = document.createElement('div');
    floatingDropdown.className = 'product-options-floating';
    document.body.appendChild(floatingDropdown);
    let activeProductInput = null;

    function positionFloatingDropdown() {
        if (!activeProductInput) return;
        const rect = activeProductInput.getBoundingClientRect();
        floatingDropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        floatingDropdown.style.left = (rect.left + window.scrollX) + 'px';
        floatingDropdown.style.width = rect.width + 'px';
    }

    function setupProductDropdown(inputElement, rowIndex) {
        function populateProductOptions() {
            floatingDropdown.innerHTML = '';
            const searchTerm = inputElement.value.toLowerCase();

            products.forEach(product => {
                if (product.code.toLowerCase().includes(searchTerm) ||
                    product.name.toLowerCase().includes(searchTerm)) {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.textContent = `${product.code} - ${product.name}`;
                    option.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        inputElement.value = `${product.code} - ${product.name}`;
                        inputElement.dataset.productId = product.id;
                        floatingDropdown.style.display = 'none';
                        activeProductInput = null;
                        if (product.default_unit_id) {
                            const unitSel = inputElement.closest('tr').querySelector('.unit-select');
                            if (unitSel) unitSel.value = product.default_unit_id;
                        }
                        updateGross(rowIndex);
                        clearError(inputElement);
                    });
                    floatingDropdown.appendChild(option);
                }
            });

            if (floatingDropdown.children.length > 0) {
                activeProductInput = inputElement;
                positionFloatingDropdown();
                floatingDropdown.style.display = 'block';
            } else {
                floatingDropdown.style.display = 'none';
            }
        }

        inputElement.addEventListener('focus', populateProductOptions);
        inputElement.addEventListener('input', populateProductOptions);

        inputElement.addEventListener('blur', function () {
            setTimeout(() => {
                if (activeProductInput === inputElement) {
                    floatingDropdown.style.display = 'none';
                    activeProductInput = null;
                }
            }, 150);
        });
    }

    document.addEventListener('click', function (e) {
        if (activeProductInput && !activeProductInput.contains(e.target) && !floatingDropdown.contains(e.target)) {
            floatingDropdown.style.display = 'none';
            activeProductInput = null;
        }
    });

    window.addEventListener('scroll', positionFloatingDropdown, true);

    // Helper functions for dropdowns
    function showDropdown(dropdown) {
        dropdown.style.display = 'block';
    }

    function hideDropdown(dropdown) {
        dropdown.style.display = 'none';
    }

    // Items table functionality
    function addTableRow() {
        const tableBody = document.getElementById('itemsTableBody');
        const emptyState = document.getElementById('emptyTableState');

        // Hide empty state when adding first row
        if (items.length === 0) {
            emptyState.style.display = 'none';
        }

        const rowIndex = items.length;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${itemCounter}</td>
            <td>
                <div class="searchable-dropdown">
                    <div class="dropdown-input-container">
                        <input type="text" class="table-input product-input" placeholder="Search product" data-product-id="">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </td>
            <td>
                <select class="table-input unit-select">
                    <option value="">Select Unit</option>
                    ${getUnitOptions('')}
                </select>
            </td>
            <td><input type="number" class="table-input quantity-input" min="0" step="0.01" placeholder="0.00"></td>
            <td><input type="number" class="table-input rate-input" min="0" step="0.01" placeholder="0.00"></td>
            <td><input type="text" class="table-input gross-input" readonly value="0.00"></td>
            <td>
                <div class="table-actions">
                    <button class="icon-btn add-btn" title="Add row after"><i class="fas fa-plus"></i></button>
                    <button class="icon-btn remove-btn" title="Remove row"><i class="fas fa-minus"></i></button>
                </div>
            </td>
        `;

        tableBody.appendChild(row);

        // Store row data
        items.push({
            id: itemCounter,
            productCode: '',
            productId: '',
            unitId: '',
            quantity: 0,
            rate: 0,
            gross: 0
        });

        // Setup event listeners for new row
        const productInput = row.querySelector('.product-input');
        const quantityInput = row.querySelector('.quantity-input');
        const rateInput = row.querySelector('.rate-input');
        const grossInput = row.querySelector('.gross-input');
        const addBtn = row.querySelector('.add-btn');
        const removeBtn = row.querySelector('.remove-btn');

        // Setup product dropdown
        setupProductDropdown(productInput, rowIndex);

        // Update gross when quantity or rate changes
        quantityInput.addEventListener('input', () => updateGross(rowIndex));
        rateInput.addEventListener('input', () => updateGross(rowIndex));

        // Add row button
        addBtn.addEventListener('click', function () {
            addTableRow();
        });

        // Remove row button
        removeBtn.addEventListener('click', function () {
            removeTableRow(rowIndex);
        });

        // Validate inputs
        productInput.addEventListener('blur', () => validateRow(rowIndex));
        quantityInput.addEventListener('blur', () => validateRow(rowIndex));
        rateInput.addEventListener('blur', () => validateRow(rowIndex));

        itemCounter++;
        return rowIndex;
    }

    function removeTableRow(rowIndex) {
        if (items.length <= 1) {
            showToast('At least one item is required', 'error');
            return;
        }

        items.splice(rowIndex, 1);
        renderTable();
    }

    function updateGross(rowIndex) {
        if (rowIndex >= items.length) return;

        const quantity = parseFloat(document.querySelectorAll('.quantity-input')[rowIndex].value) || 0;
        const rate = parseFloat(document.querySelectorAll('.rate-input')[rowIndex].value) || 0;
        const gross = quantity * rate;

        document.querySelectorAll('.gross-input')[rowIndex].value = gross.toFixed(2);

        // Update items array
        items[rowIndex].quantity = quantity;
        items[rowIndex].rate = rate;
        items[rowIndex].gross = gross;
    }

    function validateRow(rowIndex) {
        let isValid = true;
        const row = document.querySelectorAll('#itemsTableBody tr')[rowIndex];

        const productInput = row.querySelector('.product-input');
        const quantityInput = row.querySelector('.quantity-input');
        const rateInput = row.querySelector('.rate-input');

        // Check product code
        if (!productInput.value.trim() || !productInput.dataset.productId) {
            productInput.classList.add('field-error');
            isValid = false;
        } else {
            productInput.classList.remove('field-error');
        }

        // Check quantity
        if (!quantityInput.value || parseFloat(quantityInput.value) <= 0) {
            quantityInput.classList.add('field-error');
            isValid = false;
        } else {
            quantityInput.classList.remove('field-error');
        }

        // Check rate
        if (!rateInput.value || parseFloat(rateInput.value) <= 0) {
            rateInput.classList.add('field-error');
            isValid = false;
        } else {
            rateInput.classList.remove('field-error');
        }

        return isValid;
    }

    function renderTable() {
        const tableBody = document.getElementById('itemsTableBody');
        const emptyState = document.getElementById('emptyTableState');

        if (items.length === 0) {
            tableBody.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }
        // Sync unit values before re-render
        document.querySelectorAll('#itemsTableBody tr').forEach((row, i) => {
            if (items[i]) {
                items[i].unitId = row.querySelector('.unit-select')?.value || '';
                items[i].productId = row.querySelector('.product-input')?.dataset.productId || '';
            }
        });

        emptyState.style.display = 'none';
        tableBody.innerHTML = '';

        items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>
                    <div class="searchable-dropdown">
                        <div class="dropdown-input-container">
                            <input type="text" class="table-input product-input" value="${item.productCode}" data-product-id="${item.productId || ''}" placeholder="Search product">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        </div>
                </td>
                <td>
                    <select class="table-input unit-select">
                        <option value="">Select Unit</option>
                        ${getUnitOptions(item.unitId)}
                    </select>
                </td>
                <td><input type="number" class="table-input quantity-input" min="0" step="0.01" value="${item.quantity}" placeholder="0.00"></td>
                <td><input type="number" class="table-input rate-input" min="0" step="0.01" value="${item.rate}" placeholder="0.00"></td>
                <td><input type="text" class="table-input gross-input" readonly value="${item.gross.toFixed(2)}"></td>
                <td>
                    <div class="table-actions">
                        <button class="icon-btn add-btn" title="Add row after">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="icon-btn remove-btn" title="Remove row">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </td>
            `;

            tableBody.appendChild(row);

            // Reattach event listeners
            const productInput = row.querySelector('.product-input');
            const quantityInput = row.querySelector('.quantity-input');
            const rateInput = row.querySelector('.rate-input');
            const addBtn = row.querySelector('.add-btn');
            const removeBtn = row.querySelector('.remove-btn');

            setupProductDropdown(productInput, index);

            quantityInput.addEventListener('input', () => updateGross(index));
            rateInput.addEventListener('input', () => updateGross(index));

            addBtn.addEventListener('click', function () {
                addTableRow();
            });

            removeBtn.addEventListener('click', function () {
                removeTableRow(index);
            });

            productInput.addEventListener('blur', () => validateRow(index));
            quantityInput.addEventListener('blur', () => validateRow(index));
            rateInput.addEventListener('blur', () => validateRow(index));
        });
    }

    // Form validation
    function validateForm() {
        let isValid = true;

        // Check branch
        if (!branchInput.value.trim()) {
            branchInput.classList.add('field-error');
            isValid = false;
        } else {
            branchInput.classList.remove('field-error');
        }

        // Check company
        const company = document.getElementById('company');
        if (!company.value) {
            company.classList.add('field-error');
            isValid = false;
        } else {
            company.classList.remove('field-error');
        }

        // Check adjustment type
        const adjustmentType = document.getElementById('adjustmentType');
        if (!adjustmentType.value) {
            adjustmentType.classList.add('field-error');
            isValid = false;
        } else {
            adjustmentType.classList.remove('field-error');
        }

        // Check main reason
        if (!mainReasonSelect.value) {
            mainReasonSelect.classList.add('field-error');
            isValid = false;
        } else {
            mainReasonSelect.classList.remove('field-error');
        }
        
        // Check primary reason
        if (!primaryReasonSelect.value) {
            primaryReasonSelect.classList.add('field-error');
            isValid = false;
        } else {
            primaryReasonSelect.classList.remove('field-error');
        }
        
        // Check secondary reason
        if (!secondaryReasonSelect.value) {
            secondaryReasonSelect.classList.add('field-error');
            isValid = false;
        } else {
            secondaryReasonSelect.classList.remove('field-error');
        }

        // Check items
        if (items.length === 0) {
            showToast('Please add at least one item', 'error');
            isValid = false;
        } else {
            // Validate each row
            for (let i = 0; i < items.length; i++) {
                if (!validateRow(i)) {
                    isValid = false;
                }
            }
        }

        return isValid;
    }

    // Clear error styling
    function clearError(element) {
        element.classList.remove('field-error');
    }

    // Toast notification
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastIcon = toast.querySelector('.toast-icon i');
        const toastMessage = toast.querySelector('.toast-message');

        // Set toast content and style
        toastMessage.textContent = message;
        toast.className = 'toast';

        if (type === 'success') {
            toast.classList.add('toast-success');
            toastIcon.className = 'fas fa-check-circle';
        } else if (type === 'error') {
            toast.classList.add('toast-error');
            toastIcon.className = 'fas fa-exclamation-circle';
        }

        // Show toast
        toast.classList.add('show');

        // Hide toast after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Event listeners for buttons
    document.getElementById('addItemBtn').addEventListener('click', function () {
        addTableRow();
    });

    document.getElementById('resetBtn').addEventListener('click', function () {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            resetForm();
        }
    });

    document.getElementById('postBtn').addEventListener('click', function () {
        if (validateForm()) {
            const mainReasonKey = mainReasonSelect.value;
            const primaryReasonKey = primaryReasonSelect.value;
            
            // Get accounting treatment
            let accountingTreatment;
            if (mainReasonKey === 'process') {
                accountingTreatment = reasonHierarchy[mainReasonKey].primary[primaryReasonKey].accountingTreatment;
            } else {
                accountingTreatment = reasonHierarchy[mainReasonKey].accountingTreatment;
            }
            
            const adjustmentData = {
                adjustment_code: document.getElementById('adjustmentCode').value,
                branch_id: isEdit ? window.adjustmentData.branch_id : branchesMap[branchInput.value],
                company_id: document.getElementById('company').value,
                adjustment_type: document.getElementById('adjustmentType').value,
                main_reason: reasonHierarchy[mainReasonKey].label,
                primary_reason: reasonHierarchy[mainReasonKey].primary[primaryReasonKey].label,
                secondary_reason: secondaryReasonSelect.value,
                remarks: document.getElementById('remarks').value,
                accounting_treatment: accountingTreatment,
                items: items.map((item, index) => {
                    const productInput = document.querySelectorAll('.product-input')[index];
                    const productId = productInput.dataset.productId;
                    const unitId = document.querySelectorAll('.unit-select')[index]?.value || null;
                    return {
                        product_id: productId,
                        unit_id: unitId,
                        quantity: item.quantity,
                        rate: item.rate,
                        gross: item.gross
                    };
                })
            };
            
            // Add adjustment_id if editing
            if (isEdit) {
                adjustmentData.adjustment_id = window.adjustmentData.id;
            }

            const apiUrl = isEdit 
                ? '../../../../server/api/inventory/stock_adjustment/adjustment-edit.php'
                : '../../../../server/api/inventory/stock_adjustment/adjustment-add.php';

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(adjustmentData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(isEdit ? 'Stock adjustment updated successfully!' : 'Stock adjustment posted successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'adjustment-list.php';
                    }, 2000);
                } else {
                    showToast(data.message || 'Error posting adjustment', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error posting adjustment', 'error');
            });
        } else {
            showToast('Please fix all errors before posting', 'error');
        }
    });

    document.getElementById('helpBtn').addEventListener('click', function () {
        alert('Stock Adjustment Form Help:\n\n1. Fill in all required fields (marked with *)\n2. Select cascading reasons: Main → Primary → Secondary\n3. Add items using the "+" button in the table\n4. Use the searchable dropdowns to select branch and products\n5. Click "Post Adjustment" to save or "Reset Form" to clear all fields');
    });

    // Form reset function
    function resetForm() {
        // Reset main form fields
        generateAdjustmentCode();
        document.getElementById('branchInput').value = '';
        document.getElementById('adjustmentType').value = '';
        mainReasonSelect.value = '';
        primaryReasonSelect.value = '';
        primaryReasonSelect.disabled = true;
        secondaryReasonSelect.value = '';
        secondaryReasonSelect.disabled = true;
        document.getElementById('remarks').value = '';

        // Clear errors
        document.querySelectorAll('.field-error').forEach(el => {
            el.classList.remove('field-error');
        });

        // Reset items table
        items = [];
        renderTable();

        // Add one empty row
        addTableRow();
    }

    // Initialize with one empty row only if not editing — handled inside fetchUnits after units load
    if (false) {
        addTableRow();
    }
});