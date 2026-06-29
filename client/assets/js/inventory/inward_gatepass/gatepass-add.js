let suppliers = [];
let branches = [];
let products = [];
let units = [];
let companies = [];
let containers = [];
let editMode = false;
let editId = null;

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    editId = urlParams.get('id');
    editMode = !!editId;

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('date').value = today;

    fetchCompanies();
    fetchSuppliers();
    fetchBranches();
    fetchProducts();
    fetchUnits();
    fetchContainers();

    if (editMode) {
        setTimeout(() => loadGatepassData(editId), 500);
    }

    document.getElementById('submit-form').addEventListener('click', submitForm);

    document.getElementById('add-row-btn').addEventListener('click', function () {
        const rowCount = document.querySelectorAll('#items-table-body tr').length;
        addItemRow(rowCount + 1);
    });
});

function fetchCompanies() {
    fetch('../../../../server/api/inventory/inward_gatepass/get-companies.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                companies = data.data;
                initDropdown('company', 'company-options', companies, 'company_name');
                if (companies.length === 1) {
                    document.getElementById('company').value = companies[0].company_name;
                    document.getElementById('company').dataset.selectedValue = companies[0].id;
                }
            }
        })
        .catch(error => console.error('Error fetching companies:', error));
}

function fetchSuppliers() {
    fetch('../../../../server/api/inventory/inward_gatepass/get-suppliers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                suppliers = data.data;
                initDropdown('supplier', 'supplier-options', suppliers, 'name');
            }
        })
        .catch(error => console.error('Error fetching suppliers:', error));
}

function fetchBranches() {
    fetch('../../../../server/api/inventory/inward_gatepass/get-branches.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                branches = data.data;
                initDropdown('branch', 'branch-options', branches, 'name');
            }
        })
        .catch(error => console.error('Error fetching branches:', error));
}

function fetchProducts() {
    fetch('../../../../server/api/inventory/inward_gatepass/get-products.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                products = data.data;
                if (!editMode) checkAndAddFirstRow();
            }
        })
        .catch(error => console.error('Error fetching products:', error));
}

function fetchUnits() {
    fetch('../../../../server/api/inventory/inward_gatepass/get-units.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                units = data.data;
                if (!editMode) checkAndAddFirstRow();
            }
        })
        .catch(error => console.error('Error fetching units:', error));
}

function fetchContainers() {
    fetch('../../../../server/api/inventory/inward_gatepass/get-containers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                containers = data.data;
                populateContainerDropdown();
            }
        })
        .catch(error => console.error('Error fetching containers:', error));
}

function populateContainerDropdown() {
    const containerSelect = document.getElementById('container');
    containers.forEach(container => {
        const option = document.createElement('option');
        option.value = container.id;
        option.textContent = container.container_code + ' - ' + container.container_name;
        containerSelect.appendChild(option);
    });
}

function checkAndAddFirstRow() {
    if (products.length > 0 && units.length > 0) {
        const tableBody = document.getElementById('items-table-body');
        if (tableBody.children.length === 0 && !editMode) {
            addItemRow(1);
        }
    }
}

function loadGatepassData(id) {
    fetch(`../../../../server/api/inventory/inward_gatepass/gatepass-get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const gatepass = data.data;
                
                document.getElementById('igp-code').value = gatepass.gatepass_code;
                document.getElementById('date').value = gatepass.date;
                
                const company = companies.find(c => c.id == gatepass.company_id);
                if (company) {
                    document.getElementById('company').value = company.company_name;
                    document.getElementById('company').dataset.selectedValue = company.id;
                }
                
                const supplier = suppliers.find(s => s.id == gatepass.supplier_id);
                if (supplier) {
                    document.getElementById('supplier').value = supplier.name;
                    document.getElementById('supplier').dataset.selectedValue = supplier.id;
                }
                
                const branch = branches.find(b => b.id == gatepass.branch_id);
                if (branch) {
                    document.getElementById('branch').value = branch.name;
                    document.getElementById('branch').dataset.selectedValue = branch.id;
                }
                
                if (gatepass.container_id) {
                    document.getElementById('container').value = gatepass.container_id;
                }
                
                gatepass.items.forEach((item, index) => {
                    const row = addItemRow(index + 1);
                    
                    const product = products.find(p => p.id == item.product_id);
                    if (product) {
                        const productSelect = row.querySelector('.product-select');
                        productSelect.value = product.name;
                        productSelect.dataset.selectedValue = product.id;
                    }
                    
                    const unit = units.find(u => u.id == item.unit_id);
                    if (unit) {
                        const unitSelect = row.querySelector('.unit-select');
                        unitSelect.value = unit.name;
                    }
                    
                    row.querySelector('.quantity-input').value = item.quantity;
                });
                
                document.getElementById('submit-form').innerHTML = '<i class="fas fa-save"></i> Update Gatepass';
            } else {
                alert('Error loading gatepass: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load gatepass data');
        });
}

function initDropdown(inputId, optionsContainerId, items, displayProperty) {
    const input = document.getElementById(inputId);
    const optionsContainer = document.getElementById(optionsContainerId);
    const dropdownToggle = input.parentElement;
    const dropdownMenu = dropdownToggle.nextElementSibling;
    const searchInput = dropdownMenu.querySelector('input[type="text"]');

    optionsContainer.innerHTML = '';

    items.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.className = 'dropdown-item';
        itemElement.textContent = item[displayProperty];
        itemElement.dataset.value = item.id;
        itemElement.dataset.display = item[displayProperty];

        itemElement.addEventListener('click', function () {
            input.value = this.dataset.display;
            input.dataset.selectedValue = this.dataset.value;

            optionsContainer.querySelectorAll('.dropdown-item').forEach(el => {
                el.classList.remove('selected');
            });
            this.classList.add('selected');

            dropdownMenu.classList.remove('show');
        });

        optionsContainer.appendChild(itemElement);
    });

    dropdownToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');

        if (dropdownMenu.classList.contains('show')) {
            searchInput.value = '';
            searchInput.focus();

            optionsContainer.querySelectorAll('.dropdown-item').forEach(item => {
                item.style.display = 'block';
            });
        }
    });

    searchInput.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        optionsContainer.querySelectorAll('.dropdown-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(searchTerm) ? 'block' : 'none';
        });
    });

    document.addEventListener('click', function (e) {
        if (!dropdownMenu.contains(e.target) && !dropdownToggle.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    });
}

function addItemRow(rowNumber) {
    const tableBody = document.getElementById('items-table-body');
    const row = document.createElement('tr');
    row.dataset.rowId = Date.now();

    row.innerHTML = `
                <td>${rowNumber}</td>
                <td>
                    <div class="searchable-dropdown">
                        <div class="dropdown-toggle">
                            <input 
                                type="text" 
                                class="table-input product-select" 
                                placeholder="Select product" 
                                readonly
                                required
                            >
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="dropdown-menu">
                            <div class="dropdown-search">
                                <input type="text" placeholder="Search products..." class="product-search">
                            </div>
                            <div class="dropdown-items product-options">
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <select class="table-select unit-select" required>
                        <option value="">Select unit</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="table-input quantity-input" min="1" value="1" required>
                </td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="action-btn add" title="Add row below">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="action-btn delete" title="Delete row">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </td>
            `;

    tableBody.appendChild(row);

    const unitSelect = row.querySelector('.unit-select');
    units.forEach(unit => {
        const option = document.createElement('option');
        option.value = unit.name;
        option.textContent = unit.name;
        option.dataset.unitId = unit.id;
        unitSelect.appendChild(option);
    });

    const productDropdown = row.querySelector('.searchable-dropdown');
    const productToggle = row.querySelector('.dropdown-toggle');
    const productSelect = row.querySelector('.product-select');
    const productMenu = productToggle.nextElementSibling;
    const productSearch = productMenu.querySelector('.product-search');
    const productOptions = productMenu.querySelector('.product-options');

    productOptions.innerHTML = '';
    products.forEach(product => {
        const option = document.createElement('div');
        option.className = 'dropdown-item';
        option.textContent = product.name;
        option.dataset.value = product.id;
        option.dataset.display = product.name;
        option.dataset.unit = product.unit;

        option.addEventListener('click', function () {
            productSelect.value = this.dataset.display;
            productSelect.dataset.selectedValue = this.dataset.value;

            const unitSelect = row.querySelector('.unit-select');
            if (this.dataset.unit) {
                unitSelect.value = this.dataset.unit;
            }

            productOptions.querySelectorAll('.dropdown-item').forEach(el => {
                el.classList.remove('selected');
            });
            this.classList.add('selected');

            productMenu.classList.remove('show');
        });

        productOptions.appendChild(option);
    });

    productToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        productMenu.classList.toggle('show');

        if (productMenu.classList.contains('show')) {
            productSearch.value = '';
            productSearch.focus();

            productOptions.querySelectorAll('.dropdown-item').forEach(item => {
                item.style.display = 'block';
            });
        }
    });

    productSearch.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        productOptions.querySelectorAll('.dropdown-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(searchTerm) ? 'block' : 'none';
        });
    });

    row.querySelector('.action-btn.add').addEventListener('click', function () {
        const currentRowNumber = parseInt(row.cells[0].textContent);
        const newRow = addItemRow(currentRowNumber + 1);

        tableBody.insertBefore(newRow, row.nextSibling);

        updateRowNumbers();
    });

    row.querySelector('.action-btn.delete').addEventListener('click', function () {
        if (tableBody.children.length > 1) {
            row.remove();
            updateRowNumbers();
        } else {
            alert("At least one item is required");
        }
    });

    document.addEventListener('click', function (e) {
        if (!productMenu.contains(e.target) && !productToggle.contains(e.target)) {
            productMenu.classList.remove('show');
        }
    });

    return row;
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('#items-table-body tr');
    rows.forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
}

function submitForm() {
    const requiredFields = [
        { id: 'date', name: 'Date' },
        { id: 'supplier', name: 'Supplier' },
        { id: 'branch', name: 'Branch' }
    ];

    let isValid = true;
    let errorMessage = '';

    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (!element.value.trim()) {
            isValid = false;
            element.classList.add('error');
            errorMessage += `${field.name} is required.\n`;
        } else {
            element.classList.remove('error');
        }
    });

    const companyElement = document.getElementById('company');
    if (!companyElement.value.trim()) {
        isValid = false;
        companyElement.classList.add('error');
        errorMessage += 'Company is required.\n';
    } else {
        companyElement.classList.remove('error');
    }

    const itemRows = document.querySelectorAll('#items-table-body tr');
    if (itemRows.length === 0) {
        isValid = false;
        errorMessage += "At least one item is required.\n";
    }

    itemRows.forEach((row, index) => {
        const productSelect = row.querySelector('.product-select');
        const unitSelect = row.querySelector('.unit-select');
        const quantityInput = row.querySelector('.quantity-input');

        if (!productSelect.value.trim()) {
            isValid = false;
            productSelect.classList.add('error');
            errorMessage += `Product is required for item ${index + 1}.\n`;
        } else {
            productSelect.classList.remove('error');
        }

        if (!unitSelect.value) {
            isValid = false;
            unitSelect.classList.add('error');
            errorMessage += `Unit is required for item ${index + 1}.\n`;
        } else {
            unitSelect.classList.remove('error');
        }

        if (!quantityInput.value || quantityInput.value <= 0) {
            isValid = false;
            quantityInput.classList.add('error');
            errorMessage += `Valid quantity is required for item ${index + 1}.\n`;
        } else {
            quantityInput.classList.remove('error');
        }
    });

    if (!isValid) {
        alert("Please fix the following errors:\n\n" + errorMessage);
        return;
    }

    const formData = {
        date: document.getElementById('date').value,
        company_id: document.getElementById('company').dataset.selectedValue,
        supplier_id: document.getElementById('supplier').dataset.selectedValue,
        branch_id: document.getElementById('branch').dataset.selectedValue,
        container_id: document.getElementById('container').value || null,
        items: []
    };

    itemRows.forEach(row => {
        const unitSelect = row.querySelector('.unit-select');
        const selectedOption = unitSelect.options[unitSelect.selectedIndex];
        
        formData.items.push({
            product_id: row.querySelector('.product-select').dataset.selectedValue,
            unit_id: selectedOption.dataset.unitId,
            quantity: row.querySelector('.quantity-input').value
        });
    });

    const apiUrl = editMode 
        ? '../../../../server/api/inventory/inward_gatepass/gatepass-update.php'
        : '../../../../server/api/inventory/inward_gatepass/gatepass-add.php';
    
    if (editMode) {
        formData.id = editId;
    }
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const message = editMode 
                ? 'Inward gatepass updated successfully!'
                : 'Inward gatepass created successfully!\nGatepass Code: ' + data.gatepass_code;
            
            alert(message);
            
            window.location.href = 'gatepass-list.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to submit gatepass. Please try again.');
    });
}
