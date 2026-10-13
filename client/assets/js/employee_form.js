// Tab Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons and contents
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            button.classList.add('active');
            document.getElementById(button.dataset.tab + '-tab').classList.add('active');
        });
    });

    // Initialize form validation
    const form = document.getElementById('employee_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (validateForm()) {
                this.submit();
            }
        });
    }
});

function validateForm() {
    const form = document.getElementById('employee_form');
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    // Clear previous error messages
    form.querySelectorAll('.error-message').forEach(msg => msg.remove());
    form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));

    // Check required fields
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            showError(field, 'This field is required');
        }
    });

    // Validate email
    const emailField = form.querySelector('[type="email"]');
    if (emailField && emailField.value && !validateEmail(emailField.value)) {
        isValid = false;
        showError(emailField, 'Please enter a valid email address');
    }

    // Validate CNIC
    const cnicField = form.querySelector('[name="cnic"]');
    if (cnicField && cnicField.value && !validateCNIC(cnicField.value)) {
        isValid = false;
        showError(cnicField, 'Please enter CNIC in format: 12345-1234567-1');
    }

    // Validate dates
    const dateFields = form.querySelectorAll('[type="date"]');
    dateFields.forEach(field => {
        if (field.value && !validateDate(field.value)) {
            isValid = false;
            showError(field, 'Please enter a valid date');
        }
    });

    // Validate salary fields
    const salaryFields = form.querySelectorAll('[type="number"]');
    salaryFields.forEach(field => {
        if (field.value && !validateNumber(field.value)) {
            isValid = false;
            showError(field, 'Please enter a valid number');
        }
    });

    if (!isValid) {
        // Switch to the tab containing the first error
        const firstError = form.querySelector('.error');
        if (firstError) {
            const tabContent = firstError.closest('.tab-content');
            if (tabContent) {
                const tabId = tabContent.id.replace('-tab', '');
                document.querySelector(`[data-tab="${tabId}"]`).click();
            }
        }
    }

    return isValid;
}

// Validation helper functions
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateCNIC(cnic) {
    return /^\d{5}-\d{7}-\d{1}$/.test(cnic);
}

function validateDate(date) {
    const d = new Date(date);
    return d instanceof Date && !isNaN(d);
}

function validateNumber(num) {
    return !isNaN(parseFloat(num)) && isFinite(num) && num >= 0;
}

function showError(field, message) {
    field.classList.add('error');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Form Validation
function initFormValidation() {
    const form = document.getElementById('employee_form');
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                showError(field, 'This field is required');
            } else {
                clearError(field);
            }
        });

        // CNIC validation
        const cnicField = form.querySelector('[name="cnic"]');
        if (cnicField && !validateCNIC(cnicField.value)) {
            isValid = false;
            showError(cnicField, 'Invalid CNIC format (e.g., 12345-1234567-1)');
        }

        // Email validation
        const emailField = form.querySelector('[name="email"]');
        if (emailField && !validateEmail(emailField.value)) {
            isValid = false;
            showError(emailField, 'Invalid email format');
        }

        if (!isValid) {
            e.preventDefault();
            // Switch to the tab containing the first error
            const firstError = form.querySelector('.error-message');
            if (firstError) {
                const tabContent = firstError.closest('.tab-content');
                if (tabContent) {
                    const tabId = tabContent.id.replace('-tab', '');
                    document.querySelector(`[data-tab="${tabId}"]`).click();
                }
            }
        }
    });
}

// Validation Helpers
function validateCNIC(cnic) {
    return /^\d{5}-\d{7}-\d{1}$/.test(cnic);
}

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showError(field, message) {
    clearError(field);
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
    field.classList.add('error');
}

function clearError(field) {
    const error = field.parentNode.querySelector('.error-message');
    if (error) {
        error.remove();
    }
    field.classList.remove('error');
}

// Education History
function addEducationEntry() {
    const tbody = document.getElementById('education-entries');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <select name="education_level[]" required>
                <option value="">Select Level</option>
                ${educationLevels.map(level => 
                    `<option value="${level.id}">${level.name}</option>`
                ).join('')}
            </select>
        </td>
        <td><input type="text" name="institution[]" required></td>
        <td><input type="text" name="field_of_study[]" required></td>
        <td><input type="date" name="edu_start_date[]" required></td>
        <td><input type="date" name="edu_end_date[]"></td>
        <td><input type="text" name="grade_gpa[]"></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeHistoryEntry(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
}

// Work History
function addWorkEntry() {
    const tbody = document.getElementById('work-entries');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="company_name[]" required></td>
        <td><input type="text" name="work_position[]" required></td>
        <td><input type="date" name="work_start_date[]" required></td>
        <td><input type="date" name="work_end_date[]"></td>
        <td>
            <input type="text" name="reference_name[]" placeholder="Reference Name">
            <input type="text" name="reference_contact[]" placeholder="Contact">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeHistoryEntry(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
}

function removeHistoryEntry(button) {
    button.closest('tr').remove();
}

// Salary Calculations
function calculateTotalSalary() {
    const baseSalary = parseFloat(document.getElementsByName('base_salary')[0].value) || 0;
    const monthlyBonus = parseFloat(document.getElementsByName('monthly_bonus')[0].value) || 0;
    const housingAllowance = parseFloat(document.getElementsByName('housing_allowance')[0].value) || 0;
    const transportAllowance = parseFloat(document.getElementsByName('transport_allowance')[0].value) || 0;
    const medicalAllowance = parseFloat(document.getElementsByName('medical_allowance')[0].value) || 0;

    const totalMonthly = baseSalary + monthlyBonus + housingAllowance + transportAllowance + medicalAllowance;
    
    // Update total display
    const totalElement = document.getElementById('total_monthly');
    if (totalElement) {
        totalElement.textContent = totalMonthly.toFixed(2);
    }

    // Calculate annual package
    const quarterlyBonus = parseFloat(document.getElementsByName('quarterly_bonus')[0].value) || 0;
    const yearlyBonus = parseFloat(document.getElementsByName('yearly_bonus')[0].value) || 0;
    const annualPackage = (totalMonthly * 12) + (quarterlyBonus * 4) + yearlyBonus;
    
    const annualElement = document.getElementById('annual_package');
    if (annualElement) {
        annualElement.textContent = annualPackage.toFixed(2);
    }
}

// Photo Preview
function previewPhoto(input) {
    const preview = document.getElementById('photo_preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Employee Photo">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Toggle Detail Fields
document.addEventListener('DOMContentLoaded', function() {
    // Disease details toggle
    const hasDiseasesCheckbox = document.getElementById('has_diseases');
    if (hasDiseasesCheckbox) {
        hasDiseasesCheckbox.addEventListener('change', function() {
            document.getElementById('disease_details_div').style.display = 
                this.checked ? 'block' : 'none';
        });
    }

    // Disability details toggle
    const hasDisabilityCheckbox = document.getElementById('has_disability');
    if (hasDisabilityCheckbox) {
        hasDisabilityCheckbox.addEventListener('change', function() {
            document.getElementById('disability_details_div').style.display = 
                this.checked ? 'block' : 'none';
        });
    }

    // Add listeners for salary calculations
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', calculateTotalSalary);
    });
});

// Employee ID and Barcode Generation
let educationLevels = [
    { id: 1, name: 'High School' },
    { id: 2, name: 'Bachelor' },
    { id: 3, name: 'Master' },
    { id: 4, name: 'PhD' }
];

async function generateEmployeeId() {
    const departmentSelect = document.getElementById('department_id');
    const employeeIdInput = document.getElementById('employee_id');
    const barcodePreview = document.getElementById('barcodePreview');
    
    if (!departmentSelect.value) {
        employeeIdInput.value = '';
        if (barcodePreview) {
            barcodePreview.innerHTML = '';
        }
        return;
    }

    const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];
    const deptCode = selectedOption.getAttribute('data-code');
    
    try {
        // Get the next sequence number from server
        const response = await fetch('get_next_sequence.php?dept_code=' + deptCode);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error);
        }

        const year = new Date().getFullYear();
        const sequence = data.sequence.toString().padStart(4, '0');
        const employeeId = `${year}-${deptCode}-${sequence}`;
        
        // Generate unique barcode
        const timestamp = Math.floor(Date.now() / 1000);
        const random = Math.floor(Math.random() * 9000) + 1000;
        const barcode = `EMP-${deptCode}${timestamp}${random}`;

        // Verify uniqueness
        const verifyResponse = await fetch('verify_unique.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                employee_id: employeeId,
                barcode: barcode
            })
        });
        
        const verifyData = await verifyResponse.json();
        if (!verifyData.success) {
            // If not unique, try again
            return generateEmployeeId();
        }

        // Update form values
        employeeIdInput.value = employeeId;

        // Generate barcode if JsBarcode is available
        if (typeof JsBarcode !== 'undefined' && barcodePreview) {
            JsBarcode("#employeeBarcode", barcode, {
                format: "CODE128",
                width: 2,
                height: 50,
                displayValue: false
            });
            const barcodeText = document.getElementById('barcodeText');
            if (barcodeText) {
                barcodeText.innerHTML = barcode;
            }
        }

        // Add hidden input for barcode
        let barcodeInput = document.getElementById('barcode_input');
        if (!barcodeInput) {
            barcodeInput = document.createElement('input');
            barcodeInput.type = 'hidden';
            barcodeInput.name = 'barcode';
            barcodeInput.id = 'barcode_input';
            document.getElementById('employee_form').appendChild(barcodeInput);
        }
        barcodeInput.value = barcode;

    } catch (error) {
        console.error('Error generating employee ID:', error);
        alert('Error generating employee ID. Please try again.');
    }
}
