window.addEventListener('load', function () {
    console.log('QRious available:', typeof QRious !== 'undefined');
    console.log('JsBarcode available:', typeof JsBarcode !== 'undefined');
    
    loadFieldPreferences();
    
    document.getElementById('customizeFieldsBtn').addEventListener('click', openCustomizeModal);
    document.getElementById('closeCustomizeModal').addEventListener('click', closeCustomizeModal);
    document.getElementById('cancelCustomize').addEventListener('click', closeCustomizeModal);
    document.getElementById('saveCustomize').addEventListener('click', saveFieldPreferences);
    document.getElementById('selectAllFields').addEventListener('change', function() {
        document.querySelectorAll('.field-toggle').forEach(cb => cb.checked = this.checked);
    });
    
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');
    
    if (productId) {
        // Edit mode
        document.querySelector('h1').textContent = 'Edit Product';
        document.getElementById('submitBtn').textContent = 'Update Product';
        loadProductForEdit(productId);
    }
    
    // Load units from database
    loadUnits();
    
    // Load UOM groups
    loadUomGroups();
    
    // UOM Type change handler
    const uomTypeRadios = document.querySelectorAll('input[name="uomType"]');
    console.log('UOM Type radios found:', uomTypeRadios.length);
    uomTypeRadios.forEach(radio => {
        radio.addEventListener('change', handleUomTypeChange);
    });
    
    // Initial call to set correct state
    handleUomTypeChange();
    
    // Default Unit change handler
    document.getElementById('defaultUnit').addEventListener('change', handleDefaultUnitChange);
    
    // UOM Group change handler
    document.getElementById('uomGroup').addEventListener('change', handleUomGroupChange);

    // Price Entry Unit change handler (enter Trade Price / MRP by Piece, Carton, etc.)
    document.getElementById('priceEntryUnit').addEventListener('change', handlePriceEntryUnitChange);

    // Self-service toggle for the "Enter Trade Price / MRP By" feature — lets each
    // business turn it on/off for themselves; default is off/unchanged for everyone.
    document.getElementById('enableCartonPriceEntryToggle').addEventListener('change', function() {
        const checkbox = this;
        const enabled = checkbox.checked;
        fetch('../../../../server/api/inventory/products/toggle-carton-price-entry.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.ENABLE_CARTON_PRICE_ENTRY = enabled;
                populatePriceEntryUnitDropdown(window.currentGroupUnits || []);
            } else {
                alert('Could not save this setting: ' + (data.message || 'unknown error'));
                checkbox.checked = !enabled;
            }
        })
        .catch(() => {
            alert('Could not save this setting — please try again.');
            checkbox.checked = !enabled;
        });
    });
    
    // Load categories and subcategories
    loadCategories();
    
    // Load inventory accounts
    loadInventoryAccounts();

    // Load vendors
    loadVendors();
    
    // Load branches
    loadBranches();
    
    // Initialize parent product dropdown
    initParentProductDropdown();
    
    // Load companies
    loadCompanies();
    
    // Load tax regimes
    loadTaxRegimes();
    

    
    // Category change handler to load subcategories
    document.getElementById('category').addEventListener('change', function() {
        const categoryId = this.value;
        loadSubcategories(categoryId);
    });

    // Product type change handler
    const productTypeRadios = document.querySelectorAll('input[name="productType"]');
    productTypeRadios.forEach(radio => {
        radio.addEventListener('change', handleProductTypeChange);
    });

    // Form submission
    const form = document.getElementById('productForm');
    form.addEventListener('submit', handleFormSubmit);
    
    // Prevent form submission on Enter key
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });

    // Cancel button
    document.getElementById('cancelBtn').addEventListener('click', function () {
        if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
            window.location.href = 'product-list.php'; // Redirect to products list
        }
    });

    // Photo upload
    document.getElementById('photoUpload').addEventListener('click', function () {
        document.getElementById('photo').click();
    });

    document.getElementById('photo').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
                const preview = document.getElementById('photoPreview');
                const removeBtn = document.getElementById('removePhoto');
                preview.src = event.target.result;
                preview.style.display = 'block';
                removeBtn.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Remove photo button
    document.getElementById('removePhoto').addEventListener('click', function () {
        const preview = document.getElementById('photoPreview');
        const photoInput = document.getElementById('photo');
        const removeBtn = document.getElementById('removePhoto');
        
        preview.style.display = 'none';
        preview.src = '';
        photoInput.value = '';
        removeBtn.style.display = 'none';
    });

    // Plus/Minus buttons for dropdowns
    document.getElementById('addUnit').addEventListener('click', function () {
        openUnitModal();
    });

    // Unit modal functionality
    document.getElementById('closeUnitModal').addEventListener('click', closeUnitModal);
    document.getElementById('cancelUnit').addEventListener('click', closeUnitModal);
    document.getElementById('unitForm').addEventListener('submit', handleUnitSubmit);
    
    // Unit scope radio handler
    document.querySelectorAll('input[name="unitScope"]').forEach(radio => {
        radio.addEventListener('change', handleUnitScopeChange);
    });
    
    // Base unit checkbox handler
    document.getElementById('isBaseUnit').addEventListener('change', function() {
        handleUnitScopeChange();
    });
    
    // Unit type change handler to load base units
    document.getElementById('unitType').addEventListener('change', loadBaseUnits);
    
    // Close modal on outside click
    document.getElementById('unitModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUnitModal();
        }
    });

    document.getElementById('removeUnit').addEventListener('click', function () {
        const select = document.getElementById('defaultUnit');
        if (select.selectedIndex > 0) {
            const unitId = select.value;
            const unitName = select.options[select.selectedIndex].text;
            editUnit(unitId, unitName);
        } else {
            alert('Please select a unit to edit');
        }
    });

    // UOM Group modal functionality
    document.getElementById('addUomGroup').addEventListener('click', openUomGroupModal);
    document.getElementById('removeUomGroup').addEventListener('click', function () {
        const select = document.getElementById('uomGroup');
        if (select.selectedIndex > 0) {
            const groupId = select.value;
            const groupName = select.options[select.selectedIndex].text;
            editUomGroup(groupId, groupName);
        } else {
            alert('Please select a UOM group to edit');
        }
    });
    
    document.getElementById('closeUomGroupModal').addEventListener('click', closeUomGroupModal);
    document.getElementById('cancelUomGroup').addEventListener('click', closeUomGroupModal);
    document.getElementById('uomGroupForm').addEventListener('submit', handleUomGroupSubmit);
    
    document.getElementById('uomGroupModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUomGroupModal();
        }
    });

    // Category modal functionality
    document.getElementById('addCategory').addEventListener('click', function () {
        document.getElementById('categoryModal').style.display = 'flex';
        document.getElementById('categoryName').focus();
    });
    
    document.getElementById('removeCategory').addEventListener('click', function () {
        const select = document.getElementById('category');
        if (select.selectedIndex > 0) {
            const categoryId = select.value;
            const categoryName = select.options[select.selectedIndex].text;
            editCategory(categoryId, categoryName);
        } else {
            alert('Please select a category to edit');
        }
    });
    
    document.getElementById('closeCategoryModal').addEventListener('click', function() {
        document.getElementById('categoryModal').style.display = 'none';
        document.getElementById('categoryForm').reset();
    });
    
    document.getElementById('cancelCategory').addEventListener('click', function() {
        document.getElementById('categoryModal').style.display = 'none';
        document.getElementById('categoryForm').reset();
    });
    
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(document.getElementById('categoryForm'));
        
        fetch('../../../../server/api/inventory/products/category-add.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('category');
                const option = document.createElement('option');
                option.value = data.category.id;
                option.textContent = data.category.name;
                select.appendChild(option);
                select.value = option.value;
                document.getElementById('categoryModal').style.display = 'none';
                document.getElementById('categoryForm').reset();
                alert('Category added successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the category');
        });
    });

    // Subcategory modal functionality
    document.getElementById('addSubcategory').addEventListener('click', function () {
        loadCategoriesForSubcategory();
        document.getElementById('subcategoryModal').style.display = 'flex';
        document.getElementById('subcategoryName').focus();
    });
    
    document.getElementById('removeSubcategory').addEventListener('click', function () {
        const select = document.getElementById('subcategory');
        if (select.selectedIndex > 0) {
            const subcategoryId = select.value;
            const subcategoryName = select.options[select.selectedIndex].text;
            editSubcategory(subcategoryId, subcategoryName);
        } else {
            alert('Please select a subcategory to edit');
        }
    });
    
    document.getElementById('closeSubcategoryModal').addEventListener('click', function() {
        document.getElementById('subcategoryModal').style.display = 'none';
        document.getElementById('subcategoryForm').reset();
    });
    
    document.getElementById('cancelSubcategory').addEventListener('click', function() {
        document.getElementById('subcategoryModal').style.display = 'none';
        document.getElementById('subcategoryForm').reset();
    });
    
    document.getElementById('subcategoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(document.getElementById('subcategoryForm'));
        
        fetch('../../../../server/api/inventory/products/subcategory-add.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('subcategory');
                const option = document.createElement('option');
                option.value = data.subcategory.id;
                option.textContent = data.subcategory.name;
                select.appendChild(option);
                select.value = option.value;
                document.getElementById('subcategoryModal').style.display = 'none';
                document.getElementById('subcategoryForm').reset();
                alert('Subcategory added successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the subcategory');
        });
    });

    // QR Code and Barcode generation
    document.getElementById('qrInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('manualQR').click();
        }
    });
    
    document.getElementById('manualQR').addEventListener('click', function () {
        const code = document.getElementById('qrInput').value;
        if (code) {
            generateQRCode(code);
        } else {
            alert('Please enter a QR code value');
        }
    });

    document.getElementById('autoQR').addEventListener('click', function () {
        let productCode = document.getElementById('code').value;
        if (!productCode) {
            productCode = 'PROD-' + Date.now();
        }
        document.getElementById('qrInput').value = productCode;
        generateQRCode(productCode);
    });

    document.getElementById('barcodeInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('manualBarcode').click();
        }
    });
    
    document.getElementById('manualBarcode').addEventListener('click', function () {
        const code = document.getElementById('barcodeInput').value;
        if (code) {
            generateBarcode(code);
        } else {
            alert('Please enter a barcode value');
        }
    });

    document.getElementById('autoBarcode').addEventListener('click', function () {
        let productCode = document.getElementById('code').value;
        if (!productCode) {
            productCode = 'PROD-' + Date.now();
        }
        document.getElementById('barcodeInput').value = productCode;
        generateBarcode(productCode);
    });

    // Clear buttons
    document.getElementById('clearQR').addEventListener('click', function () {
        document.getElementById('qrDisplay').style.display = 'none';
        document.getElementById('qrValue').textContent = '';
        document.getElementById('qrInput').value = '';
        document.getElementById('qrCodeHidden').value = '';
    });

    document.getElementById('clearBarcode').addEventListener('click', function () {
        document.getElementById('barcodeDisplay').style.display = 'none';
        document.getElementById('barcodeValue').textContent = '';
        document.getElementById('barcodeInput').value = '';
        document.getElementById('barcodeHidden').value = '';
    });

    // Stock entry management
    document.getElementById('addStockEntry').addEventListener('click', addStockEntry);
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-stock-entry')) {
            removeStockEntry(e.target);
        }
    });
    
    // Auto-calculate totals when stock values change
    document.addEventListener('input', function(e) {
        if (e.target.name === 'openingQty[]' || e.target.name === 'openingPrice[]' || e.target.name.startsWith('openingQty[')) {
            calculateTotalStock();
        }
    });

    // Initial call to set form state based on default product type
    handleProductTypeChange();
});

function loadFieldPreferences() {
    const prefs = JSON.parse(localStorage.getItem('fieldPreferences') || '{}');
    document.querySelectorAll('[data-field]').forEach(el => {
        const field = el.dataset.field;
        el.style.display = prefs[field] === false ? 'none' : '';
    });
}

function openCustomizeModal() {
    const prefs = JSON.parse(localStorage.getItem('fieldPreferences') || '{}');
    document.querySelectorAll('.field-toggle').forEach(cb => {
        cb.checked = prefs[cb.dataset.field] !== false;
    });
    document.getElementById('customizeFieldsModal').style.display = 'flex';
}

function closeCustomizeModal() {
    document.getElementById('customizeFieldsModal').style.display = 'none';
}

function saveFieldPreferences() {
    const prefs = {};
    document.querySelectorAll('.field-toggle').forEach(cb => {
        prefs[cb.dataset.field] = cb.checked;
    });
    localStorage.setItem('fieldPreferences', JSON.stringify(prefs));
    loadFieldPreferences();
    closeCustomizeModal();
}

function handleUomTypeChange() {
    console.log('handleUomTypeChange called');
    const isUnit = document.getElementById('uomTypeUnit').checked;
    console.log('Is Unit selected:', isUnit);
    const defaultUnitGroup = document.getElementById('defaultUnitGroup');
    const uomGroupField = document.getElementById('uomGroupField');
    const defaultUnitSelect = document.getElementById('defaultUnit');
    const uomGroupSelect = document.getElementById('uomGroup');
    
    if (isUnit) {
        // Show Default Unit, hide UOM Group
        console.log('Showing Default Unit');
        defaultUnitGroup.style.display = '';
        uomGroupField.style.display = 'none';
        defaultUnitSelect.setAttribute('required', '');
        uomGroupSelect.removeAttribute('required');
        uomGroupSelect.value = '';
        clearGroupConversionFactors();
        window.currentGroupUnits = [];
    } else {
        // Show UOM Group, hide Default Unit
        console.log('Showing UOM Group');
        defaultUnitGroup.style.display = 'none';
        uomGroupField.style.display = '';
        defaultUnitSelect.removeAttribute('required');
        defaultUnitSelect.value = '';
        uomGroupSelect.setAttribute('required', '');
        
        // Hide single product conversion factor when switching to group
        const conversionFactorGroup = document.getElementById('productConversionFactorGroup');
        conversionFactorGroup.style.display = 'none';
        document.getElementById('productConversionFactor').removeAttribute('required');
    }
    rebuildStockEntries();
}

function handleUnitScopeChange() {
    const isPerProduct = document.getElementById('perProduct').checked;
    const baseUnitGroup = document.getElementById('baseUnitGroup');
    const conversionFactorGroup = document.getElementById('conversionFactorGroup');
    const isBaseUnitCheckbox = document.getElementById('isBaseUnit');
    
    if (isPerProduct) {
        // For Per Product: only hide Conversion Factor
        conversionFactorGroup.style.display = 'none';
        document.getElementById('conversionFactor').removeAttribute('required');
        // Base Unit visibility depends on Is Base Unit checkbox
        if (isBaseUnitCheckbox.checked) {
            baseUnitGroup.style.display = 'none';
            document.getElementById('baseUnit').removeAttribute('required');
        } else {
            baseUnitGroup.style.display = 'block';
            document.getElementById('baseUnit').setAttribute('required', '');
        }
    } else {
        // For Universal: show both, but hide if Is Base Unit is checked
        if (isBaseUnitCheckbox.checked) {
            baseUnitGroup.style.display = 'none';
            conversionFactorGroup.style.display = 'none';
            document.getElementById('baseUnit').removeAttribute('required');
            document.getElementById('conversionFactor').removeAttribute('required');
        } else {
            baseUnitGroup.style.display = 'block';
            conversionFactorGroup.style.display = 'block';
            document.getElementById('baseUnit').setAttribute('required', '');
            document.getElementById('conversionFactor').setAttribute('required', '');
        }
    }
}

function handleProductTypeChange() {
    const isService = document.getElementById('service').checked;

    // Elements to show/hide based on product type
    const elementsToToggle = [
        'defaultUnitGroup',

        'qrCodeGroup',
        'barcodeGroup',
        'purchasePriceGroup',
        'stockSection',
        'additionalInfoSection'
    ];

    elementsToToggle.forEach(id => {
        const element = document.getElementById(id);
        if (isService) {
            element.classList.add('hidden');
            // Clear required attribute for hidden fields
            const inputs = element.querySelectorAll('[required]');
            inputs.forEach(input => {
                input.removeAttribute('required');
            });
        } else {
            element.classList.remove('hidden');
            // Add back required attribute for visible required fields
            if (id === 'defaultUnitGroup') {
                document.getElementById('defaultUnit').setAttribute('required', '');
            }
        }
    });
}

function handleFormSubmit(e) {
    e.preventDefault();

    // Basic validation
    if (!validateForm()) {
        return;
    }

    // Disable submit button and show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    submitBtn.textContent = 'Saving...';

    // Prepare form data
    const formData = new FormData(document.getElementById('productForm'));
    const editId = document.getElementById('productForm').dataset.editId;

    // If Trade Price / MRP were entered against a non-Piece unit (e.g. Carton),
    // send BOTH: (1) the Piece-equivalent in tradePrice/mrp — the backend and every
    // downstream page (Sale Invoice, reports, stock valuation) always expect these
    // fields to be per-Piece — and (2) the exact raw value + unit the user typed, so
    // re-opening this product for edit can show back exactly "2000" instead of a
    // rounded recomputation like 1999.992.
    const priceEntryUnitGroup = document.getElementById('priceEntryUnitGroup');
    if (window.ENABLE_CARTON_PRICE_ENTRY && priceEntryUnitGroup && priceEntryUnitGroup.style.display !== 'none' && currentPriceEntryUnitId) {
        const units = window.currentGroupUnits || [];
        const unit = units.find(u => String(u.id) === String(currentPriceEntryUnitId));
        if (unit && unit.is_base_unit != 1) {
            const factor = getUnitFactorValue(unit);
            if (!factor) {
                alert(`Please enter a valid Conversion Factor for ${unit.uom_name} before saving.`);
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.textContent = editId ? 'Update Product' : 'Save Product';
                return;
            }
            formData.set('priceEntryUnitId', unit.id);
            const rawTrade = parseFloat(formData.get('tradePrice'));
            if (!isNaN(rawTrade) && rawTrade > 0) {
                formData.set('tradePriceEntryValue', rawTrade.toFixed(3));
                formData.set('tradePrice', (rawTrade / factor).toFixed(3));
            }
            const rawMrp = parseFloat(formData.get('mrp'));
            if (!isNaN(rawMrp) && rawMrp > 0) {
                formData.set('mrpEntryValue', rawMrp.toFixed(3));
                formData.set('mrp', (rawMrp / factor).toFixed(3));
            }

            // Opening Price / Unit (Branch-wise Opening Stock) follows the same
            // entry unit — convert every branch row's price down to per-Piece too,
            // and remember each row's exact raw value (like tradePriceEntryValue)
            // so editing shows the original number back instead of re-dividing an
            // already-converted value a second time.
            const rawOpeningPrices = formData.getAll('openingPrice[]');
            if (rawOpeningPrices.length > 0) {
                formData.delete('openingPrice[]');
                formData.delete('openingPriceEntryValue[]');
                rawOpeningPrices.forEach(raw => {
                    const rawVal = parseFloat(raw);
                    if (!isNaN(rawVal) && rawVal > 0) {
                        formData.append('openingPriceEntryValue[]', rawVal.toFixed(3));
                        formData.append('openingPrice[]', (rawVal / factor).toFixed(3));
                    } else {
                        formData.append('openingPriceEntryValue[]', '');
                        formData.append('openingPrice[]', raw);
                    }
                });
            }
        }
    }

    // Collect schemes from modal
    const schemes = collectAllSchemes();
    console.log('Schemes collected in handleFormSubmit:', schemes);
    if (schemes.length > 0) {
        schemes.forEach((scheme, index) => {
            const schemeJson = JSON.stringify(scheme);
            console.log(`Adding scheme ${index}:`, schemeJson);
            formData.append('schemes[]', schemeJson);
        });
    }
    console.log('FormData entries:');
    for (let [key, value] of formData.entries()) {
        if (key.includes('scheme')) {
            console.log(key + ':', value);
        }
    }
    
    if (editId) {
        formData.append('id', editId);
    }
    
    // DEBUG: Log ALL form data
    console.log('=== FORM DATA DEBUG ===');
    console.log('Edit ID:', editId);
    
    // Log stock opening data specifically
    const stockEntries = document.querySelectorAll('.stock-entry');
    console.log('Stock entries count:', stockEntries.length);
    stockEntries.forEach((entry, index) => {
        const branchInput = entry.querySelector('input[type="hidden"][name="branch[]"]');
        const qtyInputs = entry.querySelectorAll('input[name^="openingQty"]');
        const priceInput = entry.querySelector('input[name="openingPrice[]"]');
        
        console.log(`Stock Entry ${index}:`, {
            branch: branchInput ? branchInput.value : 'empty',
            qty: qtyInputs.length > 0 ? Array.from(qtyInputs).map(i => i.value) : 'N/A',
            price: priceInput ? priceInput.value : 'N/A'
        });
    });
    
    // Log all form entries
    for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
    }
    console.log('======================');
    
    const endpoint = editId ? 'product-edit.php' : 'product-add.php';

    // Submit to API
    fetch(`../../../../server/api/inventory/products/${endpoint}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = editId ? 'Product updated successfully!' : 'Product saved successfully!';
            if (data.debug) {
                console.log('=== DEBUG INFO ===');
                console.log('Branch isset:', data.debug.branch_isset);
                console.log('Branch is array:', data.debug.branch_is_array);
                console.log('Branch count:', data.debug.branch_count);
                console.log('UOM Type:', data.debug.uom_type);
                console.log('UOM Group ID:', data.debug.uom_group_id);
                console.log('Qty keys:', data.debug.qty_keys);
                console.log('Stock entries:', data.debug.stock_entries);
                console.log('==================');
            }
            alert(message);
            window.location.href = 'product-list.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the product');
    })
    .finally(() => {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        submitBtn.textContent = editId ? 'Update Product' : 'Save Product';
    });
}

function validateForm() {
    let isValid = true;
    const form = document.getElementById('productForm');

    // Clear previous errors
    const errorElements = form.querySelectorAll('.error, .error-text');
    errorElements.forEach(el => {
        el.classList.remove('error', 'error-text');
    });

    // Check required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');

            // Add error message
            let errorMsg = field.parentNode.querySelector('.error-text');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'helper-text error-text';
                field.parentNode.appendChild(errorMsg);
            }
            errorMsg.textContent = 'This field is required';
        }
    });

    // Additional validation for numeric fields
    const numericFields = form.querySelectorAll('input[type="number"]');
    numericFields.forEach(field => {
        if (field.value && field.min && parseFloat(field.value) < parseFloat(field.min)) {
            isValid = false;
            field.classList.add('error');

            let errorMsg = field.parentNode.querySelector('.error-text');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'helper-text error-text';
                field.parentNode.appendChild(errorMsg);
            }
            errorMsg.textContent = `Value must be at least ${field.min}`;
        }

        if (field.value && field.max && parseFloat(field.value) > parseFloat(field.max)) {
            isValid = false;
            field.classList.add('error');

            let errorMsg = field.parentNode.querySelector('.error-text');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'helper-text error-text';
                field.parentNode.appendChild(errorMsg);
            }
            errorMsg.textContent = `Value must be at most ${field.max}`;
        }
    });

    // Validate branch-wise opening stock entries
    const stockSection = document.getElementById('stockSection');
    if (stockSection && !stockSection.classList.contains('hidden')) {
        const stockEntries = document.querySelectorAll('.stock-entry');
        let hasValidEntry = false;

        stockEntries.forEach((entry, index) => {
            const branchHiddenInput = entry.querySelector('input[type="hidden"][name="branch[]"]');
            const branchSearchInput = entry.querySelector('.branch-search');
            const qtyInputs = entry.querySelectorAll('input[name^="openingQty"]');
            const priceInput = entry.querySelector('input[name="openingPrice[]"]');

            let branchId = branchHiddenInput ? branchHiddenInput.value : '';
            let hasQty = false;

            // Check if at least one qty field has a value
            qtyInputs.forEach(input => {
                if (input.value && parseFloat(input.value) > 0) {
                    hasQty = true;
                }
            });

            // If there's any data in this entry (qty or price), branch must be selected
            if (hasQty || (priceInput && priceInput.value)) {
                if (!branchId) {
                    isValid = false;
                    if (branchSearchInput) {
                        branchSearchInput.classList.add('error');
                        let errorMsg = entry.querySelector('.branch-error-text');
                        if (!errorMsg) {
                            errorMsg = document.createElement('div');
                            errorMsg.className = 'helper-text error-text branch-error-text';
                            branchSearchInput.parentNode.appendChild(errorMsg);
                        }
                        errorMsg.textContent = 'Branch selection is required';
                    }
                } else {
                    hasValidEntry = true;
                    // Clear error if any
                    if (branchSearchInput) {
                        branchSearchInput.classList.remove('error');
                        const errorMsg = entry.querySelector('.branch-error-text');
                        if (errorMsg) errorMsg.remove();
                    }
                }
            }

            // If quantity is provided, branch must be selected
            if (hasQty && !branchId) {
                isValid = false;
                if (branchSearchInput) {
                    branchSearchInput.classList.add('error');
                }
            }
        });
    }

    return isValid;
}

function addStockEntry() {
    const stockEntries = document.getElementById('stockEntries');
    const newEntry = document.createElement('div');
    newEntry.className = 'stock-entry';
    
    const uomType = document.querySelector('input[name="uomType"]:checked').value;
    const groupId = document.getElementById('uomGroup').value;
    
    const batchFields = `
        <div class="form-group">
            <label>Batch No</label>
            <input type="text" name="batchNo[]" placeholder="e.g., B-2026-001">
        </div>
        <div class="form-group">
            <label>Batch Expiry Date</label>
            <input type="date" name="batchExpiryDate[]">
        </div>
    `;
    
    if (uomType === 'group' && groupId && window.currentGroupUnits && window.currentGroupUnits.length > 0) {
        let unitColumns = '';
        window.currentGroupUnits.forEach(unit => {
            unitColumns += `
                <div class="form-group">
                    <label>${unit.uom_name}</label>
                    <input type="number" name="openingQty[${unit.id}][]" step="0.01" min="0" placeholder="Qty">
                </div>
            `;
        });
        
        newEntry.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label>Branch</label>
                    <div class="custom-dropdown">
                        <input type="text" class="branch-search" placeholder="Search branches..." autocomplete="off">
                        <input type="hidden" name="branch[]">
                        <div class="dropdown-list" style="display: none;"></div>
                    </div>
                </div>
                ${unitColumns}
                <div class="form-group">
                    <label class="opening-price-label">Opening Price / Unit</label>
                    <input type="number" name="openingPrice[]" step="0.01" min="0">
                </div>
                ${batchFields}
                <div class="form-group">
                    <button type="button" class="btn btn-danger remove-stock-entry" style="margin-top: 24px;">Remove</button>
                </div>
            </div>
        `;
    } else {
        newEntry.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label>Branch</label>
                    <div class="custom-dropdown">
                        <input type="text" class="branch-search" placeholder="Search branches..." autocomplete="off">
                        <input type="hidden" name="branch[]">
                        <div class="dropdown-list" style="display: none;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Opening Qty</label>
                    <input type="number" name="openingQty[]" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="opening-price-label">Opening Price / Unit</label>
                    <input type="number" name="openingPrice[]" step="0.01" min="0">
                </div>
                ${batchFields}
                <div class="form-group">
                    <button type="button" class="btn btn-danger remove-stock-entry" style="margin-top: 24px;">Remove</button>
                </div>
            </div>
        `;
    }
    
    stockEntries.appendChild(newEntry);
    initBranchDropdown(newEntry.querySelector('.custom-dropdown'));
    calculateTotalStock();

    // Keep this new entry's "Opening Price" label consistent with the
    // current Trade Price / MRP entry-unit selection (Carton/Dozen/etc.)
    if (window.ENABLE_CARTON_PRICE_ENTRY && typeof currentPriceEntryUnitId !== 'undefined' && currentPriceEntryUnitId) {
        const units = window.currentGroupUnits || [];
        const unit = units.find(u => String(u.id) === String(currentPriceEntryUnitId));
        if (unit && unit.is_base_unit != 1) {
            const label = newEntry.querySelector('.opening-price-label');
            if (label) label.textContent = `Opening Price / ${escProdText(unit.uom_name)}`;
        }
    }
}

function removeStockEntry(button) {
    const stockEntries = document.getElementById('stockEntries');
    if (stockEntries.children.length > 1) {
        button.closest('.stock-entry').remove();
        calculateTotalStock();
    } else {
        alert('At least one stock entry is required');
    }
}

function calculateTotalStock() {
    const uomType = document.querySelector('input[name="uomType"]:checked').value;
    const qtyInputs = document.querySelectorAll('input[name="openingQty[]"]');
    const priceInputs = document.querySelectorAll('input[name="openingPrice[]"]');
    
    let totalQtyInBaseUnits = 0;
    let totalValue = 0;

    // If Trade Price / MRP are being entered against a non-Piece unit (e.g.
    // Carton), Opening Price / Unit follows the same unit — convert it down
    // to a per-Piece rate before valuing the row, same as everywhere else.
    let openingPriceUnitFactor = 1;
    if (window.ENABLE_CARTON_PRICE_ENTRY && typeof currentPriceEntryUnitId !== 'undefined' && currentPriceEntryUnitId) {
        const priceUnit = (window.currentGroupUnits || []).find(u => String(u.id) === String(currentPriceEntryUnitId));
        if (priceUnit && priceUnit.is_base_unit != 1) {
            const factor = getUnitFactorValue(priceUnit);
            if (factor) openingPriceUnitFactor = factor;
        }
    }

    if (uomType === 'group' && window.currentGroupUnits && window.currentGroupUnits.length > 0) {
        // For UOM Group, convert all quantities to base units
        for (let i = 0; i < priceInputs.length; i++) {
            const price = (parseFloat(priceInputs[i].value) || 0) / openingPriceUnitFactor;
            let rowQtyInBaseUnits = 0;
            
            window.currentGroupUnits.forEach(unit => {
                const unitQtyInputs = document.querySelectorAll(`input[name="openingQty[${unit.id}][]"]`);
                if (unitQtyInputs[i]) {
                    const qty = parseFloat(unitQtyInputs[i].value) || 0;
                    
                    // Convert to base units
                    if (unit.is_base_unit == 1) {
                        rowQtyInBaseUnits += qty;
                    } else if (unit.unit_scope === 'per_product') {
                        // Get conversion factor from the form
                        const conversionInput = document.querySelector(`input[name="groupConversionFactor[${unit.id}]"]`);
                        const conversionFactor = conversionInput ? parseFloat(conversionInput.value) || 1 : 1;
                        rowQtyInBaseUnits += qty * conversionFactor;
                    } else if (unit.unit_scope === 'universal' && unit.conversion_factor) {
                        // Use universal conversion factor
                        rowQtyInBaseUnits += qty * parseFloat(unit.conversion_factor);
                    } else {
                        rowQtyInBaseUnits += qty;
                    }
                }
            });
            
            totalQtyInBaseUnits += rowQtyInBaseUnits;
            totalValue += rowQtyInBaseUnits * price;
        }
    } else {
        // For Default Unit, use single qty column
        for (let i = 0; i < qtyInputs.length; i++) {
            const qty = parseFloat(qtyInputs[i].value) || 0;
            const price = parseFloat(priceInputs[i].value) || 0;
            
            totalQtyInBaseUnits += qty;
            totalValue += qty * price;
        }
    }
    
    const avgPrice = totalQtyInBaseUnits > 0 ? totalValue / totalQtyInBaseUnits : 0;
    
    document.getElementById('totalQty').textContent = totalQtyInBaseUnits.toFixed(2);
    document.getElementById('avgPrice').textContent = avgPrice.toFixed(2);
    document.getElementById('totalValue').textContent = totalValue.toFixed(2);
}

function rebuildStockEntries() {
    const stockEntries = document.getElementById('stockEntries');
    if (!stockEntries || stockEntries.children.length === 0) return;
    
    // Save existing data
    const existingData = [];
    Array.from(stockEntries.children).forEach(entry => {
        const branchSearch = entry.querySelector('.branch-search');
        const branchHidden = entry.querySelector('input[name="branch[]"]');
        const priceInput = entry.querySelector('input[name="openingPrice[]"]');
        
        const data = {
            branchName: branchSearch ? branchSearch.value : '',
            branchId: branchHidden ? branchHidden.value : '',
            price: priceInput ? priceInput.value : ''
        };
        // paste yahan ↓
data.batchNo = entry.querySelector('input[name="batchNo[]"]') ? entry.querySelector('input[name="batchNo[]"]').value : '';
data.batchExpiryDate = entry.querySelector('input[name="batchExpiryDate[]"]') ? entry.querySelector('input[name="batchExpiryDate[]"]').value : '';
        
        // Save qty data based on current mode
        const uomType = document.querySelector('input[name="uomType"]:checked').value;
        if (uomType === 'group' && window.currentGroupUnits) {
            data.unitQtys = {};
            window.currentGroupUnits.forEach(unit => {
                const qtyInput = entry.querySelector(`input[name="openingQty[${unit.id}][]"]`);
                if (qtyInput) {
                    data.unitQtys[unit.id] = qtyInput.value;
                }
            });
        } else {
            const qtyInput = entry.querySelector('input[name="openingQty[]"]');
            data.qty = qtyInput ? qtyInput.value : '';
        }
        
        existingData.push(data);
    });
    
    // Clear and rebuild
    stockEntries.innerHTML = '';
    
    if (existingData.length === 0) {
        addStockEntry();
    } else {
        existingData.forEach(data => {
            addStockEntry();
            const entry = stockEntries.lastElementChild;
            
            // Restore branch data
            const branchSearch = entry.querySelector('.branch-search');
            const branchHidden = entry.querySelector('input[name="branch[]"]');
            if (branchSearch) branchSearch.value = data.branchName;
            if (branchHidden) branchHidden.value = data.branchId;
            
            // Restore price
            const priceInput = entry.querySelector('input[name="openingPrice[]"]');
            if (priceInput) priceInput.value = data.price;

const batchNoInput = entry.querySelector('input[name="batchNo[]"]');
if (batchNoInput) batchNoInput.value = data.batchNo || '';
const batchExpiryInput = entry.querySelector('input[name="batchExpiryDate[]"]');
if (batchExpiryInput) batchExpiryInput.value = data.batchExpiryDate || '';
            
            // Restore qty data (won't work across mode changes, but preserves within same mode)
            const uomType = document.querySelector('input[name="uomType"]:checked').value;
            if (uomType === 'group' && data.unitQtys && window.currentGroupUnits) {
                window.currentGroupUnits.forEach(unit => {
                    const qtyInput = entry.querySelector(`input[name="openingQty[${unit.id}][]"]`);
                    if (qtyInput && data.unitQtys[unit.id]) {
                        qtyInput.value = data.unitQtys[unit.id];
                    }
                });
            } else if (data.qty) {
                const qtyInput = entry.querySelector('input[name="openingQty[]"]');
                if (qtyInput) qtyInput.value = data.qty;
            }
        });
    }
    
    calculateTotalStock();
}

function generateQRCode(value) {
    const canvas = document.getElementById('qrCanvas');
    
    if (typeof QRious !== 'undefined') {
        new QRious({
            element: canvas,
            value: value,
            size: 150
        });
    } else {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#f0f0f0';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#666';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('QR Code: ' + value, canvas.width/2, canvas.height/2);
    }
    
    document.getElementById('qrValue').textContent = value;
    document.getElementById('qrCodeHidden').value = value;
    document.getElementById('qrDisplay').style.display = 'flex';
}

function generateBarcode(value) {
    const canvas = document.getElementById('barcodeCanvas');
    
    if (typeof JsBarcode !== 'undefined') {
        JsBarcode(canvas, value, {
            format: "CODE128",
            width: 2,
            height: 50,
            displayValue: true,
            fontSize: 12,
            margin: 10
        });
    } else {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#f0f0f0';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#666';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Barcode: ' + value, canvas.width/2, canvas.height/2);
    }
    
    document.getElementById('barcodeValue').textContent = value;
    document.getElementById('barcodeHidden').value = value;
    document.getElementById('barcodeDisplay').style.display = 'flex';
}

function openUnitModal() {
    document.getElementById('unitModalTitle').textContent = 'Add New Unit';
    document.getElementById('saveUnit').textContent = 'Save Unit';
    document.getElementById('unitModal').style.display = 'flex';
    document.getElementById('unitName').focus();
    document.getElementById('universal').checked = true;
    handleUnitScopeChange();
    loadBaseUnits();
}

function editUnit(unitId, unitName) {
    showConfirmModal(
        'Unit Action',
        `What would you like to do with "${unitName}"?`,
        'Edit',
        'Delete',
        () => {
            // Edit unit
            document.getElementById('unitModalTitle').textContent = 'Edit Unit';
            document.getElementById('saveUnit').textContent = 'Update Unit';
            document.getElementById('unitName').value = unitName;
            document.getElementById('unitForm').dataset.editId = unitId;
            document.getElementById('unitModal').style.display = 'flex';
            document.getElementById('unitName').focus();
            loadBaseUnits();
        },
        () => {
            // Delete unit - show second confirmation
            showConfirmModal(
                'Delete Unit',
                `Are you sure you want to delete "${unitName}"? This action cannot be undone.`,
                'Cancel',
                'Delete',
                () => {}, // Cancel - do nothing
                () => deleteUnit(unitId) // Delete
            );
        }
    );
}

function deleteUnit(unitId) {
    fetch('../../../../server/api/inventory/products/unit-delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${unitId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove from dropdown
            const select = document.getElementById('defaultUnit');
            const option = select.querySelector(`option[value="${unitId}"]`);
            if (option) {
                option.remove();
                select.selectedIndex = 0;
            }
            alert('Unit deleted successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the unit');
    });
}

function showConfirmModal(title, message, cancelText, okText, onCancel, onOk) {
    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalMessage').textContent = message;
    document.getElementById('confirmCancel').textContent = cancelText;
    document.getElementById('confirmOk').textContent = okText;
    
    const modal = document.getElementById('confirmModal');
    modal.style.display = 'flex';
    
    // Remove previous event listeners
    const newCancelBtn = document.getElementById('confirmCancel').cloneNode(true);
    const newOkBtn = document.getElementById('confirmOk').cloneNode(true);
    document.getElementById('confirmCancel').parentNode.replaceChild(newCancelBtn, document.getElementById('confirmCancel'));
    document.getElementById('confirmOk').parentNode.replaceChild(newOkBtn, document.getElementById('confirmOk'));
    
    // Add new event listeners
    newCancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        if (onCancel) onCancel();
    });
    
    newOkBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        if (onOk) onOk();
    });
    
    // Close button
    document.getElementById('closeConfirmModal').onclick = () => {
        modal.style.display = 'none';
        if (onCancel) onCancel();
    };
    
    // Close on outside click
    modal.onclick = (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            if (onCancel) onCancel();
        }
    };
}

function loadCategoriesForSubcategory() {
    const parentCategorySelect = document.getElementById('parentCategory');
    const categorySelect = document.getElementById('category');
    
    // Clear existing options
    parentCategorySelect.innerHTML = '<option value="">Select Category</option>';
    
    // Copy categories from main dropdown
    for (let i = 1; i < categorySelect.options.length; i++) {
        const option = document.createElement('option');
        option.value = categorySelect.options[i].value;
        option.textContent = categorySelect.options[i].textContent;
        parentCategorySelect.appendChild(option);
    }
}

function editCategory(categoryId, categoryName) {
    showConfirmModal(
        'Category Action',
        `What would you like to do with "${categoryName}"?`,
        'Edit',
        'Delete',
        () => {
            // Edit category
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            document.getElementById('saveCategory').textContent = 'Update Category';
            document.getElementById('categoryName').value = categoryName;
            document.getElementById('categoryForm').dataset.editId = categoryId;
            document.getElementById('categoryModal').style.display = 'flex';
            document.getElementById('categoryName').focus();
        },
        () => {
            // Delete category
            showConfirmModal(
                'Delete Category',
                `Are you sure you want to delete "${categoryName}"? This action cannot be undone.`,
                'Cancel',
                'Delete',
                () => {},
                () => deleteCategory(categoryId)
            );
        }
    );
}

function editSubcategory(subcategoryId, subcategoryName) {
    showConfirmModal(
        'Subcategory Action',
        `What would you like to do with "${subcategoryName}"?`,
        'Edit',
        'Delete',
        () => {
            // Edit subcategory
            document.getElementById('subcategoryModalTitle').textContent = 'Edit Subcategory';
            document.getElementById('saveSubcategory').textContent = 'Update Subcategory';
            document.getElementById('subcategoryName').value = subcategoryName;
            document.getElementById('subcategoryForm').dataset.editId = subcategoryId;
            loadCategoriesForSubcategory();
            document.getElementById('subcategoryModal').style.display = 'flex';
            document.getElementById('subcategoryName').focus();
        },
        () => {
            // Delete subcategory
            showConfirmModal(
                'Delete Subcategory',
                `Are you sure you want to delete "${subcategoryName}"? This action cannot be undone.`,
                'Cancel',
                'Delete',
                () => {},
                () => deleteSubcategory(subcategoryId)
            );
        }
    );
}

function deleteCategory(categoryId) {
    fetch('../../../../server/api/inventory/products/category-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${categoryId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('category');
            const option = select.querySelector(`option[value="${categoryId}"]`);
            if (option) {
                option.remove();
                select.selectedIndex = 0;
            }
            alert('Category deleted successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the category');
    });
}

function deleteSubcategory(subcategoryId) {
    fetch('../../../../server/api/inventory/products/subcategory-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${subcategoryId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('subcategory');
            const option = select.querySelector(`option[value="${subcategoryId}"]`);
            if (option) {
                option.remove();
                select.selectedIndex = 0;
            }
            alert('Subcategory deleted successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the subcategory');
    });
}

function loadBaseUnits() {
    const unitType = document.getElementById('unitType').value;
    const baseUnitSelect = document.getElementById('baseUnit');
    
    // Clear existing options
    baseUnitSelect.innerHTML = '<option value="">Select Base Unit</option>';
    
    // Load base units of same type via API
    fetch(`../../../../server/api/inventory/products/unit-list.php?type=${unitType}&base_only=1`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.units) {
            data.units.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = unit.uom_name;
                baseUnitSelect.appendChild(option);
            });
            // Select first base unit by default
            if (data.units.length > 0) {
                baseUnitSelect.selectedIndex = 1;
            }
        }
    })
    .catch(error => console.error('Error loading base units:', error));
}

function loadUnits() {
    fetch('../../../../server/api/inventory/products/unit-list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.units) {
            window.unitsData = data.units; // Store for later use
            const select = document.getElementById('defaultUnit');
            data.units.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = unit.uom_name;
                option.dataset.unitScope = unit.unit_scope || 'universal';
                option.dataset.isBaseUnit = unit.is_base_unit || 0;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error loading units:', error));
}

function handleDefaultUnitChange() {
    const select = document.getElementById('defaultUnit');
    const selectedOption = select.options[select.selectedIndex];
    const conversionFactorGroup = document.getElementById('productConversionFactorGroup');
    const conversionFactorInput = document.getElementById('productConversionFactor');
    const uomGroupSelect = document.getElementById('uomGroup');
    
    if (selectedOption && selectedOption.value) {
        const unitScope = selectedOption.dataset.unitScope;
        const isBaseUnit = selectedOption.dataset.isBaseUnit;
        const selectedUnitId = selectedOption.value;
        
        // Show conversion factor if unit_scope is per_product AND is_base_unit is 0
        if (unitScope === 'per_product' && isBaseUnit == 0) {
            conversionFactorGroup.style.display = 'block';
            conversionFactorInput.setAttribute('required', '');
        } else {
            conversionFactorGroup.style.display = 'none';
            conversionFactorInput.removeAttribute('required');
            conversionFactorInput.value = '';
        }
        
        // Filter UOM Groups based on selected unit
        filterUomGroupsByUnit(selectedUnitId);
    } else {
        conversionFactorGroup.style.display = 'none';
        conversionFactorInput.removeAttribute('required');
        conversionFactorInput.value = '';
        
        // Show all UOM groups when no unit selected
        showAllUomGroups();
    }
}

function filterUomGroupsByUnit(unitId) {
    const uomGroupSelect = document.getElementById('uomGroup');
    const currentValue = uomGroupSelect.value;
    
    uomGroupSelect.innerHTML = '<option value="">Select UOM Group</option>';
    
    if (window.uomGroupsData && window.uomGroupsData.length > 0) {
        window.uomGroupsData.forEach(group => {
            // Check if this group contains the selected unit
            if (group.unit_ids && group.unit_ids.includes(unitId)) {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.group_name;
                option.dataset.unitIds = JSON.stringify(group.unit_ids);
                uomGroupSelect.appendChild(option);
            }
        });
    }
    
    // Restore previous selection if still valid
    if (currentValue) {
        const optionExists = Array.from(uomGroupSelect.options).some(opt => opt.value === currentValue);
        if (optionExists) {
            uomGroupSelect.value = currentValue;
        }
    }
}

function showAllUomGroups() {
    const uomGroupSelect = document.getElementById('uomGroup');
    const currentValue = uomGroupSelect.value;
    
    uomGroupSelect.innerHTML = '<option value="">Select UOM Group</option>';
    
    if (window.uomGroupsData && window.uomGroupsData.length > 0) {
        window.uomGroupsData.forEach(group => {
            const option = document.createElement('option');
            option.value = group.id;
            option.textContent = group.group_name;
            option.dataset.unitIds = JSON.stringify(group.unit_ids);
            uomGroupSelect.appendChild(option);
        });
    }
    
    // Restore previous selection if exists
    if (currentValue) {
        uomGroupSelect.value = currentValue;
    }
}

function loadCategories() {
    fetch('../../../../server/api/inventory/products/category-list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.categories) {
            const select = document.getElementById('category');
            data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.category_name;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error loading categories:', error));
}

function loadSubcategories(categoryId = '') {
    const select = document.getElementById('subcategory');
    
    // Clear existing options
    select.innerHTML = '<option value="">Select Sub-category</option>';
    
    if (!categoryId) return;
    
    fetch(`../../../../server/api/inventory/products/subcategory-list.php?category_id=${categoryId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.subcategories) {
            data.subcategories.forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.subcategory_name;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error loading subcategories:', error));
}

function loadInventoryAccounts() {
    fetch('../../../../server/api/inventory/products/account-list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.accounts) {
            const select = document.getElementById('inventoryAccount');
            data.accounts.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = account.name;
                select.appendChild(option);
            });
            select.value = '33';
        }
    })
    .catch(error => console.error('Error loading accounts:', error));
}

function loadVendors() {
    fetch('../../../../server/api/inventory/products/supplier-list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.suppliers) {
            const select = document.getElementById('vendor');
            data.suppliers.forEach(supplier => {
                const option = document.createElement('option');
                option.value = supplier.id;
                option.textContent = supplier.supplier_name;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error loading vendors:', error));
}

function loadUomGroups() {
    fetch('../../../../server/api/inventory/products/uom-group-by-unit.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.groups) {
            window.uomGroupsData = data.groups;
            const select = document.getElementById('uomGroup');
            select.innerHTML = '<option value="">Select UOM Group</option>';
            data.groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.group_name;
                option.dataset.unitIds = JSON.stringify(group.unit_ids);
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error loading UOM groups:', error));
}

function openUomGroupModal() {
    document.getElementById('uomGroupModalTitle').textContent = 'Manage UOM Groups';
    document.getElementById('saveUomGroup').textContent = 'Save UOM Group';
    document.getElementById('uomGroupModal').style.display = 'flex';
    document.getElementById('uomGroupName').focus();
    
    // Initialize with one dropdown
    const container = document.getElementById('unitDropdownContainer');
    container.innerHTML = '';
    addUnitDropdownRow();
    
    // Load existing groups list
    loadUomGroupsList();
}

function addUnitDropdownRow() {
    const container = document.getElementById('unitDropdownContainer');
    const row = document.createElement('div');
    row.className = 'unit-dropdown-row';
    row.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px;';
    
    const select = document.createElement('select');
    select.className = 'unit-select';
    select.style.cssText = 'flex: 1; height: 40px; padding: 0 12px; border: 1.5px solid var(--border-default); border-radius: 6px;';
    select.required = true;
    
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Select Unit';
    select.appendChild(defaultOption);
    
    if (window.unitsData && window.unitsData.length > 0) {
        window.unitsData.forEach(unit => {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = `${unit.uom_name} (${unit.uom_type})`;
            select.appendChild(option);
        });
    }
    
    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn btn-secondary';
    addBtn.textContent = '+';
    addBtn.style.cssText = 'width: 40px; height: 40px; padding: 0;';
    addBtn.onclick = addUnitDropdownRow;
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-danger';
    removeBtn.textContent = '-';
    removeBtn.style.cssText = 'width: 40px; height: 40px; padding: 0;';
    removeBtn.onclick = function() { removeUnitDropdownRow(this); };
    
    row.appendChild(select);
    row.appendChild(addBtn);
    row.appendChild(removeBtn);
    container.appendChild(row);
}

function removeUnitDropdownRow(btn) {
    const container = document.getElementById('unitDropdownContainer');
    if (container.children.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('At least one unit is required');
    }
}

function closeUomGroupModal() {
    document.getElementById('uomGroupModal').style.display = 'none';
    document.getElementById('uomGroupForm').reset();
    delete document.getElementById('uomGroupForm').dataset.editId;
}

function handleUomGroupSubmit(e) {
    e.preventDefault();
    
    const groupName = document.getElementById('uomGroupName').value;
    const selectedUnits = Array.from(document.querySelectorAll('.unit-select'))
        .map(select => select.value)
        .filter(value => value !== '');
    
    if (selectedUnits.length === 0) {
        alert('Please select at least one unit');
        return;
    }
    
    // Check for duplicates
    const uniqueUnits = [...new Set(selectedUnits)];
    if (uniqueUnits.length !== selectedUnits.length) {
        alert('Please select different units. Duplicates are not allowed.');
        return;
    }
    
    const formData = new FormData();
    formData.append('groupName', groupName);
    uniqueUnits.forEach(unitId => {
        formData.append('unitIds[]', unitId);
    });
    
    fetch('../../../../server/api/inventory/products/uom-group-add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('uomGroup');
            const option = document.createElement('option');
            option.value = data.group.id;
            option.textContent = data.group.name;
            select.appendChild(option);
            select.value = option.value;
            
            // Reload UOM groups to update the filter data
            loadUomGroups();
            
            // Reload the list
            loadUomGroupsList();
            
            // Clear form
            document.getElementById('uomGroupForm').reset();
            const container = document.getElementById('unitDropdownContainer');
            container.innerHTML = '';
            addUnitDropdownRow();
            
            alert('UOM Group added successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the UOM group');
    });
}

function loadUomGroupsList() {
    const listContainer = document.getElementById('uomGroupList');
    listContainer.innerHTML = '<p style="color: var(--subtext); text-align: center; padding: 20px;">Loading...</p>';
    
    fetch('../../../../server/api/inventory/products/uom-group-list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.groups && data.groups.length > 0) {
            listContainer.innerHTML = '';
            
            data.groups.forEach(group => {
                const groupItem = document.createElement('div');
                groupItem.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid var(--border-default); border-radius: 6px; margin-bottom: 8px; background: var(--surface-1);';
                
                const groupInfo = document.createElement('div');
                groupInfo.style.cssText = 'flex: 1;';
                
                const groupName = document.createElement('div');
                groupName.style.cssText = 'font-weight: 500; color: var(--heading); margin-bottom: 4px;';
                groupName.textContent = group.group_name + (group.tenant_id == 0 ? ' 🔒' : '');
                
                const unitNames = document.createElement('div');
                unitNames.style.cssText = 'font-size: 12px; color: var(--subtext);';
                unitNames.textContent = group.unit_names || 'No units';
                
                groupInfo.appendChild(groupName);
                groupInfo.appendChild(unitNames);
                
                groupItem.appendChild(groupInfo);
                
                // Only show delete button for tenant-owned groups
                if (group.tenant_id != 0) {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'btn btn-danger';
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.style.cssText = 'height: 32px; padding: 0 12px; font-size: 13px;';
                    deleteBtn.onclick = () => confirmDeleteUomGroup(group.id, group.group_name);
                    groupItem.appendChild(deleteBtn);
                }
                
                listContainer.appendChild(groupItem);
            });
        } else {
            listContainer.innerHTML = '<p style="color: var(--subtext); text-align: center; padding: 20px;">No UOM groups found</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        listContainer.innerHTML = '<p style="color: var(--error); text-align: center; padding: 20px;">Error loading groups</p>';
    });
}

function confirmDeleteUomGroup(groupId, groupName) {
    if (confirm(`Are you sure you want to delete "${groupName}"? This action cannot be undone.`)) {
        deleteUomGroupFromList(groupId);
    }
}

function deleteUomGroupFromList(groupId) {
    fetch('../../../../server/api/inventory/products/uom-group-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${groupId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadUomGroupsList();
            const select = document.getElementById('uomGroup');
            const option = select.querySelector(`option[value="${groupId}"]`);
            if (option) {
                option.remove();
                select.selectedIndex = 0;
            }
            loadUomGroups();
            alert('UOM Group deleted successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the UOM group');
    });
}

function editUomGroup(groupId, groupName) {
    showConfirmModal(
        'UOM Group Action',
        `What would you like to do with "${groupName}"?`,
        'Edit',
        'Delete',
        () => {
            alert('Edit functionality will be implemented');
        },
        () => {
            showConfirmModal(
                'Delete UOM Group',
                `Are you sure you want to delete "${groupName}"? This action cannot be undone.`,
                'Cancel',
                'Delete',
                () => {},
                () => deleteUomGroup(groupId)
            );
        }
    );
}

function deleteUomGroup(groupId) {
    fetch('../../../../server/api/inventory/products/uom-group-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${groupId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('uomGroup');
            const option = select.querySelector(`option[value="${groupId}"]`);
            if (option) {
                option.remove();
                select.selectedIndex = 0;
            }
            alert('UOM Group deleted successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the UOM group');
    });
}

function loadCompanies() {
    fetch('../../../../server/api/inventory/products/get-companies.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const companySelect = document.getElementById('company');
            companySelect.innerHTML = '<option value="">Select Company</option>';
            data.companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = `${company.company_code} - ${company.company_name}`;
                companySelect.appendChild(option);
            });
            if (data.companies.length === 1) {
                companySelect.value = data.companies[0].id;
            }
        }
    })
    .catch(error => console.error('Error loading companies:', error));
}

function initParentProductDropdown() {
    const searchInput = document.getElementById('parentProductSearch');
    const hiddenInput = document.getElementById('parentProduct');
    const dropdown = document.getElementById('parentProductDropdown');
    let debounceTimer;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const searchTerm = this.value.trim();
        
        // Clear hidden input if search is cleared
        if (searchTerm === '') {
            hiddenInput.value = '';
        }
        
        debounceTimer = setTimeout(() => {
            const editId = document.getElementById('productForm').dataset.editId || '';
            fetch(`../../../../server/api/inventory/products/parent-product-list.php?search=${encodeURIComponent(searchTerm)}&exclude_id=${editId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products) {
                    dropdown.innerHTML = '';
                    
                    if (data.products.length === 0) {
                        dropdown.innerHTML = '<div class="dropdown-item" style="color: var(--subtext);">No products found</div>';
                    } else {
                        data.products.forEach(product => {
                            const item = document.createElement('div');
                            item.className = 'dropdown-item';
                            item.textContent = `${product.code} - ${product.name}`;
                            item.dataset.id = product.id;
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                searchInput.value = `${product.code} - ${product.name}`;
                                hiddenInput.value = product.id;
                                dropdown.style.display = 'none';
                            });
                            dropdown.appendChild(item);
                        });
                    }
                    
                    dropdown.style.display = 'block';
                }
            })
            .catch(error => console.error('Error loading parent products:', error));
        }, 300);
    });
    
    searchInput.addEventListener('focus', function() {
        const editId = document.getElementById('productForm').dataset.editId || '';
        fetch(`../../../../server/api/inventory/products/parent-product-list.php?search=&exclude_id=${editId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products) {
                dropdown.innerHTML = '';
                
                if (data.products.length === 0) {
                    dropdown.innerHTML = '<div class="dropdown-item" style="color: var(--subtext);">No products found</div>';
                } else {
                    data.products.forEach(product => {
                        const item = document.createElement('div');
                        item.className = 'dropdown-item';
                        item.textContent = `${product.code} - ${product.name}`;
                        item.dataset.id = product.id;
                        item.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            searchInput.value = `${product.code} - ${product.name}`;
                            hiddenInput.value = product.id;
                            dropdown.style.display = 'none';
                        });
                        dropdown.appendChild(item);
                    });
                }
                
                dropdown.style.display = 'block';
            }
        })
        .catch(error => console.error('Error loading parent products:', error));
    });
    
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

// Helper function to safely set element values
function safeSetValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.value = value || '';
    }
}

// Helper function to safely set element checked state
function safeSetChecked(elementId, checked) {
    const element = document.getElementById(elementId);
    if (element) {
        element.checked = checked;
    }
}

function loadProductForEdit(productId) {
    fetch(`../../../../server/api/inventory/products/product-get.php?id=${productId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const product = data.product;
            
            // Populate form fields using safe helpers to avoid null errors
            safeSetValue('code', product.code);
            safeSetValue('name', product.name);
            safeSetValue('company', product.company_id || '');
            safeSetValue('description', product.description || '');
            safeSetValue('weightValue', product.weight_value || '');
            safeSetValue('weightUnit', product.weight_unit || '');
            safeSetValue('purchasePrice', product.purchase_price || '');
            safeSetValue('tradePrice', product.trade_price || '');
            safeSetValue('wholesalePrice', product.wholesale_price || '');
            safeSetValue('mrp', product.mrp || '');
            safeSetValue('defaultDiscount', product.default_discount || '');
            safeSetValue('tradeOfferDiscount', product.trade_offer_discount || '');
            safeSetValue('defaultFoc', product.default_foc || '');
            safeSetValue('cartonConversion', product.carton_conversion || '');
            safeSetValue('minStock', product.min_stock_level || '');
            safeSetValue('maxStock', product.max_stock_level || '');
            safeSetValue('manufacturingDate', (product.manufacturing_date && product.manufacturing_date !== '0000-00-00') ? product.manufacturing_date : '');
            safeSetValue('expiryDate', (product.expiry_date && product.expiry_date !== '0000-00-00') ? product.expiry_date : '');
            safeSetChecked('active', product.is_active == 1);
            safeSetChecked('stockAffects', product.stock_affects == 1);
            safeSetChecked('invoiceAffects', product.invoice_affects == 1);
            
            // Handle product_type with null check - default to 'physical' if null or invalid
            const productTypeValue = (product.product_type || 'physical').toLowerCase();
            const productTypeElement = document.querySelector(`input[name="productType"][value="${productTypeValue}"]`);
            if (productTypeElement) {
                productTypeElement.checked = true;
            } else {
                // Fallback to physical if value not found
                const fallbackElement = document.querySelector(`input[name="productType"][value="physical"]`);
                if (fallbackElement) {
                    fallbackElement.checked = true;
                }
            }
            
            // Set dropdown values after they are loaded
            setTimeout(() => {
                if (product.company_id) {
                    safeSetValue('company', product.company_id);
                }
                
                // Handle UOM Type selection based on what's saved
                let uomReadyPromise = Promise.resolve();
                if (product.uom_type === 'group' && product.uom_group_id) {
                    // UOM Group is selected
                    safeSetChecked('uomTypeGroup', true);
                    handleUomTypeChange();
                    safeSetValue('uomGroup', product.uom_group_id);
                    uomReadyPromise = handleUomGroupChange().then(() => {
                        applyStoredPriceEntry(product);
                        loadGroupConversionFactorsForEdit(productId);
                    });
                } else {
                    // Default Unit is selected (default case)
                    safeSetChecked('uomTypeUnit', true);
                    handleUomTypeChange();
                    if (product.default_unit_id) {
                        safeSetValue('defaultUnit', product.default_unit_id);
                        // Store unit data in option for handleDefaultUnitChange
                        const defaultUnitElement = document.getElementById('defaultUnit');
                        if (defaultUnitElement) {
                            const selectedOption = defaultUnitElement.options[defaultUnitElement.selectedIndex];
                            if (selectedOption && product.unit_scope) {
                                selectedOption.dataset.unitScope = product.unit_scope;
                                selectedOption.dataset.isBaseUnit = product.is_base_unit || 0;
                            }
                        }
                        handleDefaultUnitChange();
                    }
                }
                
                if (product.product_conversion_factor) {
                    safeSetValue('productConversionFactor', product.product_conversion_factor);
                }
                if (product.category_id) {
                    safeSetValue('category', product.category_id);
                    // Load subcategories for selected category
                    loadSubcategories(product.category_id);
                    setTimeout(() => {
                        if (product.subcategory_id) {
                            safeSetValue('subcategory', product.subcategory_id);
                        }
                    }, 500);
                }
                if (product.inventory_account_id) {
                    safeSetValue('inventoryAccount', product.inventory_account_id);
                }
                if (product.vendor_id) {
                    safeSetValue('vendor', product.vendor_id);
                }
                if (product.parent_product_id) {
                    loadParentProductForEdit(product.parent_product_id);
                }
                // Populate Sales Tax Type (custom dropdown with search)
                if (product.tax_regime_id && product.regime_name) {
                    const searchInput = document.getElementById('salesTaxTypeSearch');
                    const hiddenInput = document.getElementById('salesTaxType');
                    if (searchInput) searchInput.value = product.regime_name;
                    if (hiddenInput) hiddenInput.value = product.tax_regime_id;
                }
                    // Wait for the UOM Group's per-unit Opening Qty columns (Carton,
                    // Dozen, etc.) to actually exist in the DOM before trying to
                    // populate them — handleUomGroupChange() above builds them
                    // asynchronously and isn't guaranteed to have finished yet.
                    uomReadyPromise.then(() => loadStockOpeningData(productId));

            }, 1000);
            

            // Set form to edit mode
            const productForm = document.getElementById('productForm');
            if (productForm) {
                productForm.dataset.editId = productId;
            }
            
            // Load existing photo if available
            if (product.photo) {
                const preview = document.getElementById('photoPreview');
                const removeBtn = document.getElementById('removePhoto');
                if (preview) {
                    preview.src = `../../../assets/uploads/products/${product.photo}`;
                    preview.style.display = 'block';
                }
                if (removeBtn) {
                    removeBtn.style.display = 'block';
                }
            }
            
            // Load existing QR code if available
            if (product.qr_code) {
                const qrInput = document.getElementById('qrInput');
                if (qrInput) {
                    qrInput.value = product.qr_code;
                    generateQRCode(product.qr_code);
                }
            }
            
            // Load existing barcode if available
            if (product.barcode) {
                const barcodeInput = document.getElementById('barcodeInput');
                if (barcodeInput) {
                    barcodeInput.value = product.barcode;
                    generateBarcode(product.barcode);
                }
            }
            
            
            
            // Load existing schemes
            loadExistingSchemesForEdit(productId);
            
            handleProductTypeChange();
        } else {
            alert('Error loading product: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while loading the product');
    });
}

function loadStockOpeningData(productId) {
    setTimeout(() => {
        const uomType = document.querySelector('input[name="uomType"]:checked').value;
        
        // First, load conversion factors if UOM Group
        const loadConversionFactorsPromise = uomType === 'group' ? 
            fetch(`../../../../server/api/inventory/products/product-uom-conversions.php?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.conversions) {
                        data.conversions.forEach(conv => {
                            const input = document.querySelector(`input[name="groupConversionFactor[${conv.uom_id}]"]`);
                            if (input) {
                                input.value = conv.conversion_factor;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error loading conversion factors:', error))
            : Promise.resolve();
        
        // Then load stock opening data after conversion factors are ready
        loadConversionFactorsPromise.then(() => {
            fetch(`../../../../server/api/inventory/products/stock-opening-get.php?product_id=${productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.stock_entries && data.stock_entries.length > 0) {
                    const stockEntries = data.stock_entries;
                    console.log('Loaded stock entries:', stockEntries);
                    
                    // Group entries by branch. For 'group' UOM products, several
                    // stock_opening rows (one per unit) together represent ONE opening
                    // stock event for that branch, so they belong in the same row. For
                    // 'unit' products, each row is an independent historical entry
                    // (different batch/date) — grouping those by branch_id alone would
                    // only ever render the first one and silently hide the rest, so key
                    // each one uniquely instead.
                    const entriesByBranch = {};
                stockEntries.forEach(entry => {
                    const groupKey = uomType === 'group' ? entry.branch_id : `${entry.branch_id}_${entry.id}`;
                    if (!entriesByBranch[groupKey]) {
                        entriesByBranch[groupKey] = [];
                    }
                    entriesByBranch[groupKey].push(entry);
                });
                
                // Clear existing stock entry rows (except first empty template)
                const stockEntriesContainer = document.getElementById('stockEntries');
                while (stockEntriesContainer.children.length > 1) {
                    stockEntriesContainer.removeChild(stockEntriesContainer.lastChild);
                }
                
                // Populate first row with first entry data
                if (stockEntries.length > 0) {
                    const firstBranchId = Object.keys(entriesByBranch)[0];
                    const branchEntries = entriesByBranch[firstBranchId];
                    const firstEntry = branchEntries[0];
                    
                    const firstRow = stockEntriesContainer.querySelector('.stock-entry');
                    
                    // Set branch
                    const branchHidden = firstRow.querySelector('input[type="hidden"][name="branch[]"]');
                    const branchSearch = firstRow.querySelector('.branch-search');
                    if (branchHidden && branchSearch && firstEntry.branch_display) {
                        branchHidden.value = firstEntry.branch_id;
                        branchSearch.value = firstEntry.branch_display;
                    }
                    
                    // Set price (same for all units in this branch) — prefer the exact
                    // raw value the user originally typed (e.g. Carton price 2000) over
                    // the stored per-Piece rate, so it doesn't get divided again on save.
                    const priceInput = firstRow.querySelector('input[name="openingPrice[]"]');
                    if (priceInput && (firstEntry.opening_price_entry_value || firstEntry.opening_price)) {
                        priceInput.value = firstEntry.opening_price_entry_value || firstEntry.opening_price;
                    }
                    // paste yahan ↓
const batchNoInput = firstRow.querySelector('input[name="batchNo[]"]');
if (batchNoInput && firstEntry.batch_no) {
    batchNoInput.value = firstEntry.batch_no;
}
const batchExpiryInput = firstRow.querySelector('input[name="batchExpiryDate[]"]');
if (batchExpiryInput && firstEntry.expiry_date && firstEntry.expiry_date !== '0000-00-00') {
    batchExpiryInput.value = firstEntry.expiry_date;
}
                    
                    if (uomType === 'group') {
                        // For UOM groups, populate individual unit quantities
                        branchEntries.forEach(entry => {
                            const unitInput = firstRow.querySelector(`input[name="openingQty[${entry.unit_id}][]"]`);
                            if (unitInput && entry.unit_id) {
                                unitInput.value = entry.opening_qty;
                            }
                        });
                    } else {
                        // For single unit mode
                        const qtyInput = firstRow.querySelector('input[name="openingQty[]"]');
                        if (qtyInput && firstEntry.opening_qty) {
                            qtyInput.value = firstEntry.opening_qty;
                        }
                    }
                }
                
                // Add additional rows for other branches
                let branchIndex = 0;
                for (const branchId of Object.keys(entriesByBranch)) {
                    if (branchIndex === 0) {
                        branchIndex++;
                        continue; // Skip first, already populated
                    }
                    
                    const branchEntries = entriesByBranch[branchId];
                    addStockEntry();
                    
                    const newRow = stockEntriesContainer.lastElementChild;
                    
                    // Set branch
                    const branchHidden = newRow.querySelector('input[type="hidden"][name="branch[]"]');
                    const branchSearch = newRow.querySelector('.branch-search');
                    if (branchHidden && branchSearch && branchEntries[0].branch_display) {
                        branchHidden.value = branchEntries[0].branch_id;
                        branchSearch.value = branchEntries[0].branch_display;
                    }
                    
                    // Set price — prefer the exact raw entered value (see comment above).
                    const priceInput = newRow.querySelector('input[name="openingPrice[]"]');
                    if (priceInput && (branchEntries[0].opening_price_entry_value || branchEntries[0].opening_price)) {
                        priceInput.value = branchEntries[0].opening_price_entry_value || branchEntries[0].opening_price;
                    }
                    const batchNoInput = newRow.querySelector('input[name="batchNo[]"]');
if (batchNoInput && branchEntries[0].batch_no) {
    batchNoInput.value = branchEntries[0].batch_no;
}
const batchExpiryInput = newRow.querySelector('input[name="batchExpiryDate[]"]');
if (batchExpiryInput && branchEntries[0].expiry_date && branchEntries[0].expiry_date !== '0000-00-00') {
    batchExpiryInput.value = branchEntries[0].expiry_date;
}
                    
                    if (uomType === 'group') {
                        // For UOM groups, populate individual unit quantities
                        branchEntries.forEach(entry => {
                            const unitInput = newRow.querySelector(`input[name="openingQty[${entry.unit_id}][]"]`);
                            if (unitInput && entry.unit_id) {
                                unitInput.value = entry.opening_qty;
                            }
                        });
                    } else {
                        // For single unit mode
                        const qtyInput = newRow.querySelector('input[name="openingQty[]"]');
                        if (qtyInput && branchEntries[0].opening_qty) {
                            qtyInput.value = branchEntries[0].opening_qty;
                        }
                    }
                    
                    branchIndex++;
                }
                
                calculateTotalStock();
            } else {
                console.log('No stock entries found');
            }
            })
            .catch(error => console.error('Error loading stock opening data:', error));
        })
        .catch(error => console.error('Error in stock opening load process:', error));
    }, 1000);
}

function loadParentProductForEdit(parentProductId) {
    fetch(`../../../../server/api/inventory/products/product-get.php?id=${parentProductId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.product) {
            const product = data.product;
            document.getElementById('parentProductSearch').value = `${product.code} - ${product.name}`;
            document.getElementById('parentProduct').value = product.id;
        }
    })
    .catch(error => console.error('Error loading parent product:', error));
}

function loadBranches() {
    window.branchesData = [];
    const branchDropdowns = document.querySelectorAll('.custom-dropdown');
    console.log('[Branch] loadBranches: found', branchDropdowns.length, 'custom-dropdown elements');
    branchDropdowns.forEach(dropdown => {
        if (dropdown.querySelector('.branch-search')) {
            console.log('[Branch] Initializing branch dropdown from loadBranches');
            initBranchDropdown(dropdown);
        }
    });

    fetch('../../../../server/api/inventory/products/branch-list.php')
    .then(response => response.json())
    .then(data => {
        console.log('[Branch] API response:', data);
        if (data.success && Array.isArray(data.branches)) {
            window.branchesData = data.branches;
            console.log('[Branch] branchesData set:', window.branchesData.length, 'branches');
        } else {
            console.warn('[Branch] fetch returned no data:', data);
        }
    })
    .catch(error => console.error('[Branch] fetch error:', error));
}

function initBranchDropdown(container) {
    const searchInput = container.querySelector('.branch-search');
    const hiddenInput = container.querySelector('input[type="hidden"]');
    const dropdownList = container.querySelector('.dropdown-list');

    console.log('[Branch] initBranchDropdown - searchInput:', !!searchInput, 'hiddenInput:', !!hiddenInput, 'dropdownList:', !!dropdownList);

    if (!searchInput || !hiddenInput || !dropdownList) {
        console.warn('[Branch] initBranchDropdown: missing elements, aborting');
        return;
    }

    searchInput.addEventListener('focus', () => {
        console.log('[Branch] focus fired, branchesData:', window.branchesData ? window.branchesData.length + ' items' : 'undefined');
        renderBranchList('', dropdownList, searchInput, hiddenInput);
        dropdownList.style.display = 'block';
    });

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        renderBranchList(query, dropdownList, searchInput, hiddenInput);
    });

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdownList.style.display = 'none';
        }
    });
}

function renderBranchList(query, dropdownList, searchInput, hiddenInput) {
    dropdownList.innerHTML = '';

    if (!window.branchesData || window.branchesData.length === 0) {
        console.log('[Branch] renderBranchList: no data available');
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">No branches available</div>';
        return;
    }

    const filtered = window.branchesData.filter(b =>
        query === '' || b.branch_name.toLowerCase().includes(query)
    );

    console.log('[Branch] renderBranchList: filtered', filtered.length, 'branches for query:', JSON.stringify(query));

    if (filtered.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">No branches found</div>';
        return;
    }

    filtered.forEach(branch => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = branch.parent_branch_name
            ? `${branch.branch_name} (${branch.branch_type}) — ${branch.parent_branch_name}`
            : `${branch.branch_name} (${branch.branch_type})`;
        item.dataset.id = branch.id;

        item.addEventListener('click', () => {
            searchInput.value = `${branch.branch_name} (${branch.branch_type})`;
            hiddenInput.value = branch.id;
            dropdownList.style.display = 'none';
        });

        dropdownList.appendChild(item);
    });
}

function closeUnitModal() {
    document.getElementById('unitModal').style.display = 'none';
    document.getElementById('unitForm').reset();
    delete document.getElementById('unitForm').dataset.editId;
    document.getElementById('universal').checked = true;
    handleUnitScopeChange();
}

function handleUnitSubmit(e) {
    e.preventDefault();
    
    const form = document.getElementById('unitForm');
    const formData = new FormData(form);
    const editId = form.dataset.editId;
    
    if (editId) {
        formData.append('id', editId);
    }
    
    // Set default conversion factor if empty
    if (!formData.get('conversionFactor') || formData.get('conversionFactor') === '') {
        formData.set('conversionFactor', '1');
    }
    
    const endpoint = editId ? 'unit-edit.php' : 'unit-add.php';
    
    fetch(`../../../../server/api/inventory/products/${endpoint}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (editId) {
                // Update existing option
                const select = document.getElementById('defaultUnit');
                const option = select.querySelector(`option[value="${editId}"]`);
                if (option) {
                    option.textContent = data.unit.name;
                }
                alert('Unit updated successfully!');
            } else {
                // Add new option
                const select = document.getElementById('defaultUnit');
                const option = document.createElement('option');
                option.value = data.unit.id;
                option.textContent = data.unit.name;
                select.appendChild(option);
                select.value = option.value;
                alert('Unit added successfully!');
            }
            closeUnitModal();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the unit');
    });
}


function handleUomGroupChange() {
    const groupSelect = document.getElementById('uomGroup');
    const groupId = groupSelect.value;
    
    if (!groupId) {
        clearGroupConversionFactors();
        window.currentGroupUnits = [];
        rebuildStockEntries();
        handleUomGroupChangeWithScheme();
        return Promise.resolve();
    }
    
    return fetch(`../../../../server/api/inventory/products/uom-group-units.php?group_id=${groupId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.units) {
            window.currentGroupUnits = data.units;
            console.log('Loaded group units:', data.units);
            displayGroupConversionFactors(data.units);
            rebuildStockEntries();
            handleUomGroupChangeWithScheme();
        }
    })
    .catch(error => console.error('Error loading group units:', error));
}

function displayGroupConversionFactors(units) {
    clearGroupConversionFactors();

    const perProductUnits = units.filter(u => u.unit_scope === 'per_product' && u.is_base_unit == 0);

    if (perProductUnits.length > 0) {
        const conversionFactorGroup = document.getElementById('productConversionFactorGroup');
        const formRow = conversionFactorGroup.closest('.form-row');

        perProductUnits.forEach(unit => {
            const fieldGroup = document.createElement('div');
            fieldGroup.className = 'form-group group-conversion-factor';
            fieldGroup.innerHTML = `
                <label class="required">${unit.uom_name} Conversion Factor</label>
                <input type="number" name="groupConversionFactor[${unit.id}]" step="0.000001" min="0" placeholder="e.g., 1000" required>
                <div class="helper-text">Conversion factor for ${unit.uom_name}</div>
            `;
            formRow.insertBefore(fieldGroup, conversionFactorGroup.nextSibling);
        });
    }

    populatePriceEntryUnitDropdown(units);
}

function clearGroupConversionFactors() {
    document.querySelectorAll('.group-conversion-factor').forEach(el => el.remove());
    populatePriceEntryUnitDropdown([]);
}

// --- Price Entry Unit (enter Trade Price / MRP by Piece, Carton, Dozen, etc.) ---
let currentPriceEntryUnitId = null;

function escProdText(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function getUnitFactorValue(unit) {
    if (!unit) return null;
    if (unit.is_base_unit == 1) return 1;
    if (unit.unit_scope === 'per_product') {
        const input = document.querySelector(`input[name="groupConversionFactor[${unit.id}]"]`);
        const val = input ? parseFloat(input.value) : NaN;
        return (!isNaN(val) && val > 0) ? val : null;
    }
    const val = parseFloat(unit.conversion_factor);
    return (!isNaN(val) && val > 0) ? val : null;
}

function updatePriceFieldLabels(unitName) {
    const tradeLabel = document.getElementById('tradePriceLabel');
    const mrpLabel = document.getElementById('mrpLabel');
    const suffix = unitName ? ` / ${escProdText(unitName)}` : ' / Selected Unit';
    if (tradeLabel) tradeLabel.textContent = `Trade Price (TP)${suffix}`;
    if (mrpLabel) mrpLabel.textContent = `Maximum Retail Price (MRP)${suffix}`;

    document.querySelectorAll('.opening-price-label').forEach(label => {
        label.textContent = `Opening Price${suffix}`;
    });
}

function populatePriceEntryUnitDropdown(units) {
    const group = document.getElementById('priceEntryUnitGroup');
    const select = document.getElementById('priceEntryUnit');
    if (!group || !select) return;

    if (!window.ENABLE_CARTON_PRICE_ENTRY || !units || units.length <= 1) {
        group.style.display = 'none';
        select.innerHTML = '<option value="">Select Unit</option>';
        currentPriceEntryUnitId = null;
        updatePriceFieldLabels(null);
        return;
    }

    group.style.display = '';
    select.innerHTML = units.map(u =>
        `<option value="${u.id}">${escProdText(u.uom_name)}</option>`
    ).join('');

    const baseUnit = units.find(u => u.is_base_unit == 1) || units[0];
    const preferredName = localStorage.getItem('priceEntryUnitPreference');
    const preferredUnit = preferredName ? units.find(u => u.uom_name === preferredName) : null;
    const initialUnit = preferredUnit || baseUnit;

    select.value = initialUnit.id;
    currentPriceEntryUnitId = String(initialUnit.id);
    updatePriceFieldLabels(initialUnit.uom_name);
}

function handlePriceEntryUnitChange() {
    const select = document.getElementById('priceEntryUnit');
    const newUnitId = select.value;
    const units = window.currentGroupUnits || [];
    const newUnit = units.find(u => String(u.id) === String(newUnitId));
    if (!newUnit) return;

    const newFactor = getUnitFactorValue(newUnit);
    if (newFactor === null) {
        alert(`Please enter the Conversion Factor for ${newUnit.uom_name} first.`);
        select.value = currentPriceEntryUnitId || '';
        return;
    }

    const oldUnit = units.find(u => String(u.id) === String(currentPriceEntryUnitId));
    const oldFactor = oldUnit ? getUnitFactorValue(oldUnit) : 1;

    if (oldFactor) {
        ['tradePrice', 'mrp'].forEach(fieldId => {
            const input = document.getElementById(fieldId);
            const val = parseFloat(input.value);
            if (!isNaN(val) && val > 0) {
                input.value = ((val * newFactor) / oldFactor).toFixed(3);
            }
        });

        // Opening Price / Unit (Branch-wise Opening Stock) follows the same
        // entry unit — live-convert any already-typed values too.
        document.querySelectorAll('input[name="openingPrice[]"]').forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val) && val > 0) {
                input.value = ((val * newFactor) / oldFactor).toFixed(3);
            }
        });
    }

    currentPriceEntryUnitId = String(newUnitId);
    updatePriceFieldLabels(newUnit.uom_name);
    localStorage.setItem('priceEntryUnitPreference', newUnit.uom_name);
    calculateTotalStock();
}

// Re-apply an edited product's remembered price-entry unit + its EXACT originally-
// typed Trade Price / MRP values (not a recomputed multiply-back, which would drift
// from rounding — e.g. 2000 -> 83.333 -> *24 = 1999.992, not 2000).
function applyStoredPriceEntry(product) {
    if (!window.ENABLE_CARTON_PRICE_ENTRY) return;
    if (!product.price_entry_unit_id) return; // stored in Piece terms — dropdown already defaults to Piece

    const units = window.currentGroupUnits || [];
    const unit = units.find(u => String(u.id) === String(product.price_entry_unit_id));
    if (!unit) return; // unit no longer part of this group — fall back to the raw Piece values already shown

    const select = document.getElementById('priceEntryUnit');
    if (select) select.value = unit.id;
    currentPriceEntryUnitId = String(unit.id);
    updatePriceFieldLabels(unit.uom_name);

    if (product.trade_price_entry_value !== null && product.trade_price_entry_value !== undefined && product.trade_price_entry_value !== '') {
        document.getElementById('tradePrice').value = product.trade_price_entry_value;
    }
    if (product.mrp_entry_value !== null && product.mrp_entry_value !== undefined && product.mrp_entry_value !== '') {
        document.getElementById('mrp').value = product.mrp_entry_value;
    }
}

function loadGroupConversionFactorsForEdit(productId) {
    return fetch(`../../../../server/api/inventory/products/product-uom-conversions.php?product_id=${productId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.conversions) {
            data.conversions.forEach(conv => {
                const input = document.querySelector(`input[name="groupConversionFactor[${conv.uom_id}]"]`);
                if (input) {
                    input.value = conv.conversion_factor;
                }
            });
        }
    })
    .catch(error => console.error('Error loading conversion factors:', error));
}


// Scheme Modal Handlers
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const closeSchemeBtn = document.getElementById('closeSchemeModal');
        const cancelSchemeBtn = document.getElementById('cancelScheme');
        const addSchemeRowBtn = document.getElementById('addSchemeRow');
        const saveSchemeBtn = document.getElementById('saveScheme');
        const schemeModal = document.getElementById('schemeModal');
        
        if (closeSchemeBtn) closeSchemeBtn.addEventListener('click', closeSchemeModal);
        if (cancelSchemeBtn) cancelSchemeBtn.addEventListener('click', closeSchemeModal);
        if (addSchemeRowBtn) addSchemeRowBtn.addEventListener('click', addSchemeRow);
        if (saveSchemeBtn) saveSchemeBtn.addEventListener('click', saveSchemes);
        if (schemeModal) {
            schemeModal.addEventListener('click', function(e) {
                if (e.target === this) closeSchemeModal();
            });
        }
    }, 1000);
});

// Update Default Unit change handler to include scheme button
const originalHandleDefaultUnitChange = handleDefaultUnitChange;
handleDefaultUnitChange = function() {
    originalHandleDefaultUnitChange.call(this);
    handleDefaultUnitChangeWithScheme();
};

// Remove old scheme handlers - they're now in scheme-functions.js

function loadExistingSchemesForEdit(productId) {
    console.log('Loading existing schemes for edit mode, product:', productId);
    
    fetch(`../../../../server/api/inventory/products/scheme-get.php?product_id=${productId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.schemes && data.schemes.length > 0) {
            console.log('Schemes loaded for edit:', data.schemes);
            window.existingSchemesData = data.schemes;
        }
    })
    .catch(error => console.error('Error loading schemes:', error));
}

function loadTaxRegimes() {
    fetch('../../../../server/api/inventory/products/tax-regimes-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.regimes) {
                // Store data globally for use by dropdown
                window.taxRegimesData = data.regimes;
                // Initialize the dropdown
                initTaxRegimeDropdown();
            }
        })
        .catch(error => console.error('Error loading tax regimes:', error));
}

function initTaxRegimeDropdown() {
    const searchInput = document.getElementById('salesTaxTypeSearch');
    const hiddenInput = document.getElementById('salesTaxType');
    const dropdownList = document.getElementById('taxRegimeDropdown');
    
    if (!searchInput || !hiddenInput || !dropdownList) return;
    
    searchInput.addEventListener('focus', () => {
        renderTaxRegimeList('', dropdownList, searchInput, hiddenInput);
        dropdownList.style.display = 'block';
    });
    
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        renderTaxRegimeList(query, dropdownList, searchInput, hiddenInput);
    });
    
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !dropdownList.contains(e.target)) {
            dropdownList.style.display = 'none';
        }
    });
}

function renderTaxRegimeList(query, dropdownList, searchInput, hiddenInput) {
    dropdownList.innerHTML = '';
    
    if (!window.taxRegimesData || window.taxRegimesData.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">No tax types available</div>';
        return;
    }
    
    const filtered = window.taxRegimesData.filter(regime => 
        regime.regime_name.toLowerCase().includes(query) ||
        (regime.description && regime.description.toLowerCase().includes(query))
    );
    
    if (filtered.length === 0) {
        dropdownList.innerHTML = '<div class="dropdown-item" style="color: var(--subtext); cursor: default;">No tax types found</div>';
    } else {
        filtered.forEach(regime => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = regime.regime_name;
            if (regime.description) {
                item.title = regime.description;
            }
            item.dataset.id = regime.id;
            
            item.addEventListener('click', () => {
                searchInput.value = regime.regime_name;
                hiddenInput.value = regime.id;
                dropdownList.style.display = 'none';
            });
            
            dropdownList.appendChild(item);
        });
    }
}