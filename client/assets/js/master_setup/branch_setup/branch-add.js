document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('branchForm');
    const resetBtn = document.getElementById('resetBtn');
    const cancelBtn = document.querySelector('.btn-secondary[type="button"]');

    let parentBranches = [];

    // Load parent branches
    async function loadParentBranches() {
        try {
            const response = await fetch('../../../../server/api/master_setup/branch_setup/parent-branches.php');
            const result = await response.json();
            
            if (result.success) {
                parentBranches = result.data;
                setupSearchableDropdown();
            }
        } catch (error) {
            console.error('Error loading parent branches:', error);
        }
    }

    function setupSearchableDropdown() {
        const input = document.getElementById('parentBranch');
        const dropdown = document.getElementById('parentBranchDropdown');
        const hiddenInput = document.getElementById('parentBranchId');

        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filtered = parentBranches.filter(branch => 
                branch.branch_name.toLowerCase().includes(searchTerm) ||
                branch.branch_code.toLowerCase().includes(searchTerm)
            );
            
            if (searchTerm && filtered.length > 0) {
                dropdown.innerHTML = '';
                filtered.forEach(branch => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item';
                    item.textContent = `${branch.branch_name} - ${branch.branch_code}`;
                    item.onclick = () => {
                        input.value = `${branch.branch_name} - ${branch.branch_code}`;
                        hiddenInput.value = branch.id;
                        dropdown.style.display = 'none';
                    };
                    dropdown.appendChild(item);
                });
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        });

        input.addEventListener('focus', function() {
            if (parentBranches.length > 0) {
                dropdown.innerHTML = '';
                parentBranches.forEach(branch => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item';
                    item.textContent = `${branch.branch_name} - ${branch.branch_code}`;
                    item.onclick = () => {
                        input.value = `${branch.branch_name} - ${branch.branch_code}`;
                        hiddenInput.value = branch.id;
                        dropdown.style.display = 'none';
                    };
                    dropdown.appendChild(item);
                });
                dropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    loadParentBranches();
    loadManagers();
    loadCompanies();

    let managers = [];

    // Load managers
    async function loadManagers() {
        try {
            const response = await fetch('../../../../server/api/master_setup/branch_setup/managers.php');
            const result = await response.json();
            
            if (result.success) {
                managers = result.data;
                setupManagerDropdown();
            }
        } catch (error) {
            console.error('Error loading managers:', error);
        }
    }

    function setupManagerDropdown() {
        const input = document.getElementById('manager');
        const dropdown = document.getElementById('managerDropdown');
        const hiddenInput = document.getElementById('managerId');

        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filtered = managers.filter(manager => 
                manager.full_name.toLowerCase().includes(searchTerm)
            );
            
            if (searchTerm && filtered.length > 0) {
                dropdown.innerHTML = '';
                filtered.forEach(manager => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item';
                    item.textContent = manager.full_name;
                    item.onclick = () => {
                        input.value = manager.full_name;
                        hiddenInput.value = manager.id;
                        dropdown.style.display = 'none';
                    };
                    dropdown.appendChild(item);
                });
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        });

        input.addEventListener('focus', function() {
            if (managers.length > 0) {
                dropdown.innerHTML = '';
                managers.forEach(manager => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-item';
                    item.textContent = manager.full_name;
                    item.onclick = () => {
                        input.value = manager.full_name;
                        hiddenInput.value = manager.id;
                        dropdown.style.display = 'none';
                    };
                    dropdown.appendChild(item);
                });
                dropdown.style.display = 'block';
            }
        });
    }

    // Load companies
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/companies/get-companies.php');
            const result = await response.json();
            
            if (result.success && result.data) {
                const companySelect = document.getElementById('company');
                companySelect.innerHTML = '<option value="">Select Company</option>';
                
                result.data.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companySelect.appendChild(option);
                });
                
                if (result.data.length === 1) {
                    companySelect.value = result.data[0].id;
                }
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    // Form validation function
    function validateField(field, errorElement) {
        if (!field.value.trim()) {
            field.classList.add('input-error');
            errorElement.style.display = 'block';
            return false;
        } else {
            field.classList.remove('input-error');
            errorElement.style.display = 'none';
            return true;
        }
    }

    // Real-time validation for required fields
    const requiredFields = [
        { field: document.getElementById('branchName'), error: document.getElementById('branchNameError') },
        { field: document.getElementById('branchType'), error: document.getElementById('branchTypeError') },
        { field: document.getElementById('company'), error: document.getElementById('companyError') }
    ];

    requiredFields.forEach(item => {
        item.field.addEventListener('blur', () => {
            validateField(item.field, item.error);
        });
    });

    // Form submission handler
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        // Validate all required fields
        let isValid = true;
        requiredFields.forEach(item => {
            if (!validateField(item.field, item.error)) {
                isValid = false;
            }
        });

        if (!isValid) {
            // Scroll to first error
            const firstError = document.querySelector('.input-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const formData = {
            branch_name: document.getElementById('branchName').value,
            branch_type: document.getElementById('branchType').value,
            company_id: document.getElementById('company').value,
            parent_branch_id: document.getElementById('parentBranchId').value || null,
            address: document.getElementById('address').value,
            country: document.getElementById('country').value,
            state: document.getElementById('state').value,
            city: document.getElementById('city').value,
            zipcode: document.getElementById('zipcode').value,
            phone: document.getElementById('phone').value,
            email: document.getElementById('email').value,
            manager_id: document.getElementById('managerId').value || null,
            is_active: document.getElementById('isActive').checked ? 1 : 0,
            allows_sales: document.getElementById('allowsSales').checked ? 1 : 0,
            allows_inventory: document.getElementById('allowsInventory').checked ? 1 : 0,
            is_default: document.getElementById('isDefault').checked ? 1 : 0
        };

        try {
            const response = await fetch('../../../../server/api/master_setup/branch_setup/branch-add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                // Update branch code field with generated code
                const branchCodeField = document.getElementById('branchCode');
                if (branchCodeField) {
                    branchCodeField.value = result.branch_code;
                }
                
                // Show success message and redirect
                if (confirm('Branch created successfully! Branch Code: ' + result.branch_code + '\nDo you want to go to the branches list?')) {
                    window.location.href = 'branch-list.php';
                } else {
                    form.reset();
                    if (branchCodeField) {
                        branchCodeField.value = result.branch_code;
                    }
                }
            } else {
                throw new Error(result.message || 'Failed to create branch');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error creating branch: ' + error.message);
        }
    });

    // Reset form handler
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                form.reset();
                // Clear validation errors
                requiredFields.forEach(item => {
                    item.field.classList.remove('input-error');
                    item.error.style.display = 'none';
                });
            }
        });
    }

    // Cancel button handler
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
                window.location.href = 'branch-list.php';
            }
        });
    }

    // Add focus styles to form elements
    const formElements = form.querySelectorAll('input, select, textarea');
    formElements.forEach(element => {
        element.addEventListener('focus', function () {
            this.style.boxShadow = '0 0 0 2px rgba(31, 123, 255, 0.2)';
        });

        element.addEventListener('blur', function () {
            this.style.boxShadow = 'none';
        });
    });
});