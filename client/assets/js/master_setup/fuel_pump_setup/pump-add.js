let branches = [];
let products = [];

// Load branches and products on page load
document.addEventListener('DOMContentLoaded', () => {
    loadBranches();
    loadProducts();
});

async function loadBranches() {
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/get-branches.php');
        const result = await response.json();
        
        if (result.success) {
            branches = result.branches;
            setupSearchableDropdown();
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

async function loadProducts() {
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/get-products.php');
        const result = await response.json();
        
        if (result.success) {
            products = result.products;
            populatePumpTypeDropdown();
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function populatePumpTypeDropdown() {
    setupPumpTypeSearchableDropdown();
}

function setupPumpTypeSearchableDropdown() {
    const input = document.getElementById('pumpType');
    const dropdown = document.getElementById('pumpTypeDropdown');
    const hiddenInput = document.getElementById('pumpTypeId');
    
    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        const filtered = products.filter(product => 
            product.name.toLowerCase().includes(query)
        );
        showPumpTypeDropdown(filtered);
    });
    
    input.addEventListener('focus', () => showPumpTypeDropdown(products));
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.searchable-select')) {
            dropdown.style.display = 'none';
        }
    });
    
    function showPumpTypeDropdown(items) {
        dropdown.innerHTML = '';
        if (items.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        items.forEach(product => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = product.name;
            item.addEventListener('click', () => {
                input.value = product.name;
                hiddenInput.value = product.id;
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(item);
        });
        
        dropdown.style.display = 'block';
    }
}

function setupSearchableDropdown() {
    const input = document.getElementById('parentBranch');
    const dropdown = document.getElementById('branchDropdown');
    const hiddenInput = document.getElementById('parentBranchId');
    
    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        const filtered = branches.filter(branch => 
            branch.branch_name.toLowerCase().includes(query) || 
            branch.branch_type.toLowerCase().includes(query)
        );
        showDropdown(filtered);
    });
    
    input.addEventListener('focus', () => showDropdown(branches));
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.searchable-select')) {
            dropdown.style.display = 'none';
        }
    });
    
    function showDropdown(items) {
        dropdown.innerHTML = '';
        if (items.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        items.forEach(branch => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            const displayText = branch.parent_branch_name 
                ? `${branch.parent_branch_name} → ${branch.branch_name} (${branch.branch_type})`
                : `${branch.branch_name} (${branch.branch_type})`;
            item.textContent = displayText;
            item.addEventListener('click', () => {
                input.value = displayText;
                hiddenInput.value = branch.id;
                dropdown.style.display = 'none';
                input.classList.remove('error');
            });
            dropdown.appendChild(item);
        });
        
        dropdown.style.display = 'block';
    }
}

// Form validation and submission
const form = document.getElementById('stationForm');
const parentBranch = document.getElementById('parentBranch');
const parentBranchId = document.getElementById('parentBranchId');
const pumpName = document.getElementById('pumpName');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    let isValid = true;

    // Validate required fields
    if (!parentBranchId.value) {
        parentBranch.classList.add('error');
        isValid = false;
    } else {
        parentBranch.classList.remove('error');
    }

    if (!pumpName.value.trim()) {
        pumpName.classList.add('error');
        isValid = false;
    } else {
        pumpName.classList.remove('error');
    }

    if (!isValid) {
        alert('Please fill in all required fields.');
        return;
    }

    // Prepare form data
    const formData = {
        station_name: pumpName.value.trim(),
        branch_id: parentBranchId.value,
        fuel_type_id: document.getElementById('pumpTypeId').value || null,
        manufacturer: document.getElementById('manufacturer').value.trim() || null,
        model: document.getElementById('model').value.trim() || null,
        serial_number: document.getElementById('serialNumber').value.trim() || null,
        flow_rate: document.getElementById('flowRate').value || null,
        location_description: document.getElementById('locationDescription').value.trim() || null,
        status: document.getElementById('pumpStatus').value,
        installation_date: document.getElementById('installationDate').value || null,
        last_maintenance_date: document.getElementById('lastService').value || null
    };

    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/pump-add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
            alert('Station information saved successfully!');
            form.reset();
            // Redirect to list page
            window.location.href = 'pump-list.php';
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving station information. Please try again.');
        console.error('Error:', error);
    }
});



// Clear error states when user starts typing/selecting
parentBranch.addEventListener('input', () => {
    if (parentBranchId.value) {
        parentBranch.classList.remove('error');
    }
});

pumpName.addEventListener('input', () => {
    if (pumpName.value.trim()) {
        pumpName.classList.remove('error');
    }
});