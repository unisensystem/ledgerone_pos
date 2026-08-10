// Get URL parameters
const urlParams = new URLSearchParams(window.location.search);
const mode = urlParams.get('mode') || 'add';
const employeeId = urlParams.get('id');

// Initialize barcode with placeholder
function initializeBarcode() {
    const barcodeValue = document.getElementById('employeeIdDisplay').textContent || 'AUTO-GEN';

    try {
        JsBarcode("#barcode", barcodeValue, {
            format: "CODE128",
            width: 2,
            height: 60,
            displayValue: false,
            background: "transparent",
            lineColor: "#0E1A2B"
        });
    } catch (error) {
        console.log("Barcode generation error:", error);
    }
}



// Show/hide probation duration field
function toggleProbationDetails() {
    const probationYes = document.getElementById('probationYes');
    const probationDetails = document.getElementById('probationDetails');

    probationDetails.style.display = probationYes.checked ? 'flex' : 'none';
}

// Tab Navigation
document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        button.classList.add('active');
        const tabId = button.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// Form Validation and Submission
document.getElementById('employeeForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    // Reset errors
    document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.error-text').forEach(el => el.remove());

    // Required fields validation
    const requiredFields = [
        'firstName', 'gender', 'department', 'position', 'employmentType',
        'baseSalary', 'salaryFrequency'
    ];

    let isValid = true;
    let firstErrorField = null;

    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && !field.value.trim()) {
            field.classList.add('error');
            isValid = false;

            if (!firstErrorField) {
                firstErrorField = field;
            }

            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-text';
            errorDiv.textContent = 'This field is required';
            field.parentNode.insertBefore(errorDiv, field.nextElementSibling);
        }
    });

    // Validate opening balance - only one field should have value
    const openingDebit = parseFloat(document.getElementById('openingDebit').value) || 0;
    const openingCredit = parseFloat(document.getElementById('openingCredit').value) || 0;
    
    if (openingDebit > 0 && openingCredit > 0) {
        document.getElementById('openingDebit').classList.add('error');
        document.getElementById('openingCredit').classList.add('error');
        isValid = false;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text';
        errorDiv.textContent = 'Enter value in either Debit OR Credit field, not both';
        document.getElementById('openingCredit').parentNode.insertBefore(errorDiv, document.getElementById('openingCredit').nextElementSibling);
    }

    if (!isValid) {
        document.getElementById('errorMessage').innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Please fill all required fields correctly</span>';
        document.getElementById('errorMessage').style.display = 'flex';
        document.getElementById('successMessage').style.display = 'none';
        if (firstErrorField) {
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    // Collect form data
    const formData = {
        firstName: document.getElementById('firstName').value,
        lastName: document.getElementById('lastName').value,
        dateOfBirth: document.getElementById('dateOfBirth').value,
        gender: document.getElementById('gender').value,
        nationality: document.getElementById('nationality').value,
        maritalStatus: document.getElementById('maritalStatus').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        emergencyContact: document.getElementById('emergencyContact').value,
        address: document.getElementById('address').value,
        department: document.getElementById('department').value,
        position: document.getElementById('position').value,
        hireDate: document.getElementById('hireDate').value,
        employmentType: document.getElementById('employmentType').value,
        manager: document.getElementById('manager').value,
        probation: document.querySelector('input[name="probation"]:checked')?.value,
        probationMonths: document.getElementById('probationMonths').value,
        workLocation: document.getElementById('workLocation').value,
        workHours: document.getElementById('workHours').value,
        shift: document.getElementById('shiftStart').value + '-' + document.getElementById('shiftEnd').value,
        employeeType: document.getElementById('employeeType').value,
        baseSalary: document.getElementById('baseSalary').value,
        salaryFrequency: document.getElementById('salaryFrequency').value,
        housingAllowance: document.getElementById('housingAllowance').value || 0,
        transportAllowance: document.getElementById('transportAllowance').value || 0,
        medicalAllowance: document.getElementById('medicalAllowance').value || 0,
        mealAllowance: document.getElementById('mealAllowance').value || 0,
        communicationAllowance: document.getElementById('communicationAllowance').value || 0,
        educationAllowance: document.getElementById('educationAllowance').value || 0,
        travelAllowance: document.getElementById('travelAllowance').value || 0,
        otherAllowance: document.getElementById('otherAllowance').value || 0,
        annualBonus: document.getElementById('annualBonus').value || 0,
        providentFund: document.getElementById('providentFund').value || 0,
        gratuity: document.getElementById('gratuity').value,
        healthInsurance: document.getElementById('healthInsurance').checked,
        lifeInsurance: document.getElementById('lifeInsurance').checked,
        pto: document.getElementById('pto').checked,
        paidLeaveBalance: document.getElementById('paidLeaveBalance').value || 21,
        sickLeaveBalance: document.getElementById('sickLeaveBalance').value || 10,
        unpaidLeaveBalance: document.getElementById('unpaidLeaveBalance').value || 0,
        leaveResetPeriod: document.getElementById('leaveResetPeriod').value,
        nextResetDate: document.getElementById('nextResetDate').value,
        carryForward: document.getElementById('carryForward').checked,
        openingDebit: document.getElementById('openingDebit').value || 0,
        openingCredit: document.getElementById('openingCredit').value || 0,
        profilePhotoUrl: uploadedDocuments.profile_picture || null,
        employmentAgreementUrl: uploadedDocuments.employment_agreement || null,
        idProofUrl: uploadedDocuments.id_proof || null,
        resumeUrl: uploadedDocuments.resume || null,
        certificatesUrl: uploadedDocuments.certificates || null,
        medicalCertificateUrl: uploadedDocuments.medical_certificate || null,
        notes: document.getElementById('notes').value
    };

    // Submit to API
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = mode === 'edit' ? '<i class="fas fa-spinner fa-spin"></i> Updating Employee...' : '<i class="fas fa-spinner fa-spin"></i> Adding Employee...';

    try {
        const apiUrl = mode === 'edit' 
            ? '../../../../server/api/hrm/employees/employee-edit.php'
            : '../../../../server/api/hrm/employees/employee-add.php';
        
        const apiMethod = mode === 'edit' ? 'PUT' : 'POST';
        
        if (mode === 'edit') {
            formData.id = employeeId;
        }
        
        const response = await fetch(apiUrl, {
            method: apiMethod,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
            const message = mode === 'edit' 
                ? result.message
                : result.message + ' (ID: ' + result.employeeId + ')';
            document.getElementById('successMessage').innerHTML = '<i class="fas fa-check-circle"></i><span>' + message + '</span>';
            document.getElementById('successMessage').style.display = 'flex';
            document.getElementById('errorMessage').style.display = 'none';
            
            setTimeout(() => {
                window.location.href = 'employee-list.php';
            }, 1500);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        document.getElementById('errorMessage').innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + error.message + '</span>';
        document.getElementById('errorMessage').style.display = 'flex';
        document.getElementById('successMessage').style.display = 'none';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = mode === 'edit' ? '<i class="fas fa-save"></i> Update Employee' : '<i class="fas fa-user-plus"></i> Add Employee';
    }
});

// Load positions based on department
async function loadPositions(departmentId) {
    const positionSelect = document.getElementById('position');
    positionSelect.innerHTML = '<option value="">Loading...</option>';
    
    try {
        const response = await fetch(`../../../../server/api/hrm/employees/get-positions.php?department_id=${departmentId}`);
        const result = await response.json();
        
        if (result.success) {
            positionSelect.innerHTML = '<option value="">Select</option>';
            result.positions.forEach(pos => {
                const option = document.createElement('option');
                option.value = pos.id;
                option.textContent = pos.position_title;
                positionSelect.appendChild(option);
            });
        } else {
            positionSelect.innerHTML = '<option value="">No positions available</option>';
        }
    } catch (error) {
        positionSelect.innerHTML = '<option value="">Error loading positions</option>';
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function () {
    // Initialize barcode
    initializeBarcode();
    
    // Disable all inputs in view mode
    if (mode === 'view') {
        document.querySelectorAll('input, select, textarea, button[type="button"]:not(#cancelBtn)').forEach(el => {
            el.disabled = true;
        });
        document.querySelectorAll('.tab-btn').forEach(btn => btn.disabled = false);
    }
    
    // Load existing documents if in view/edit mode
    if (employeeId && mode !== 'add') {
        // Profile photo is already loaded via PHP
        // Documents are already loaded via PHP
    }

    // Set default dates
    const today = new Date();
    document.getElementById('hireDate').valueAsDate = today;

    // Set date of birth to 25 years ago
    const dob = new Date();
    dob.setFullYear(dob.getFullYear() - 25);
    document.getElementById('dateOfBirth').valueAsDate = dob;

    // Initialize probation details visibility
    toggleProbationDetails();

    // Listen to probation radio buttons
    document.getElementById('probationYes').addEventListener('change', toggleProbationDetails);
    document.getElementById('probationNo').addEventListener('change', toggleProbationDetails);

    // Set default probation months
    document.getElementById('probationMonths').value = 3;
    
    // Add mutual exclusion for opening balance fields
    document.getElementById('openingDebit').addEventListener('input', function() {
        if (this.value > 0) {
            document.getElementById('openingCredit').value = '';
        }
    });
    
    document.getElementById('openingCredit').addEventListener('input', function() {
        if (this.value > 0) {
            document.getElementById('openingDebit').value = '';
        }
    });
    
    // Set default next reset date to next January 1st
    const nextYear = new Date().getFullYear() + 1;
    document.getElementById('nextResetDate').value = `${nextYear}-01-01`;
    
    // Update reset date when period changes
    document.getElementById('leaveResetPeriod').addEventListener('change', function() {
        const hireDate = document.getElementById('hireDate').value;
        const today = new Date();
        let nextReset;
        
        switch(this.value) {
            case 'yearly':
                nextReset = `${today.getFullYear() + 1}-01-01`;
                break;
            case 'monthly':
                const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                nextReset = nextMonth.toISOString().split('T')[0];
                break;
            case 'hire-anniversary':
                if (hireDate) {
                    const hire = new Date(hireDate);
                    nextReset = `${today.getFullYear() + 1}-${String(hire.getMonth() + 1).padStart(2, '0')}-${String(hire.getDate()).padStart(2, '0')}`;
                } else {
                    nextReset = `${today.getFullYear() + 1}-01-01`;
                }
                break;
            case 'fiscal-year':
                nextReset = `${today.getFullYear() + 1}-04-01`;
                break;
            case 'never':
                nextReset = '';
                break;
        }
        
        document.getElementById('nextResetDate').value = nextReset;
    });
    
    // Load positions when department changes
    document.getElementById('department').addEventListener('change', function() {
        if (this.value) {
            loadPositions(this.value);
        } else {
            document.getElementById('position').innerHTML = '<option value="">Select</option>';
        }
    });
    
    // Update reset date when hire date changes
    document.getElementById('hireDate').addEventListener('change', function() {
        const resetPeriod = document.getElementById('leaveResetPeriod').value;
        if (resetPeriod === 'hire-anniversary' && this.value) {
            const hire = new Date(this.value);
            const today = new Date();
            const nextReset = `${today.getFullYear() + 1}-${String(hire.getMonth() + 1).padStart(2, '0')}-${String(hire.getDate()).padStart(2, '0')}`;
            document.getElementById('nextResetDate').value = nextReset;
        }
    });
});

// Document upload variables
let uploadedDocuments = {};

// Avatar Upload
if (document.getElementById('uploadPhoto')) {
    document.getElementById('uploadPhoto').addEventListener('click', function () {
        if (mode === 'view') return;
        uploadDocument('profilePhoto', 'profile_picture');
    });
}

if (document.getElementById('removePhoto')) {
    document.getElementById('removePhoto').addEventListener('click', function () {
        if (mode === 'view') return;
        const avatarPreview = document.getElementById('avatarPreview');
        avatarPreview.innerHTML = '<i class="fas fa-user" style="font-size: 32px; color: #6B7280;"></i>';
        delete uploadedDocuments['profile_picture'];
    });
}

// Document upload function
async function uploadDocument(inputId, documentType) {
    if (mode === 'view') return;
    
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = '.png,.jpg,.jpeg,.gif,.avif,.webp,.pdf';
    
    fileInput.onchange = async function(e) {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            
            // Validate file size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('document_type', documentType);
            
            try {
                const response = await fetch('../../../../server/api/hrm/employees/upload-documents.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    uploadedDocuments[documentType] = result.path;
                    
                    // Update UI
                    const button = document.querySelector(`button[onclick*="${inputId}"]`);
                    if (button) {
                        button.innerHTML = '<i class="fas fa-check"></i> Uploaded';
                        button.classList.add('btn-success');
                        button.classList.remove('btn-secondary');
                    }
                    
                    // Update profile photo preview
                    if (documentType === 'profile_picture') {
                        const avatarPreview = document.getElementById('avatarPreview');
                        avatarPreview.innerHTML = `<img src="${result.path}" alt="Employee Photo">`;
                    }
                    
                    // Show document thumbnail preview
                    showDocumentPreview(documentType, result.path, file.type);
                    
                    alert('Document uploaded successfully!');
                } else {
                    alert('Upload failed: ' + result.message);
                }
            } catch (error) {
                alert('Upload error: ' + error.message);
            }
        }
    };
    
    fileInput.click();
}

// Show document thumbnail preview
function showDocumentPreview(documentType, filePath, fileType) {
    const previewContainer = document.getElementById(`${documentType}_preview`);
    if (!previewContainer) return;
    
    previewContainer.style.display = 'block';
    
    const removeBtn = `<button type="button" class="btn-remove" onclick="removeDocument('${documentType}')" title="Remove"><i class="fas fa-times"></i></button>`;
    
    if (fileType === 'application/pdf') {
        previewContainer.innerHTML = `<div class="pdf-preview"><i class="fas fa-file-pdf"></i></div>${removeBtn}`;
    } else {
        previewContainer.innerHTML = `<img src="${filePath}" alt="Document Preview" onclick="window.open('${filePath}', '_blank')">${removeBtn}`;
    }
}

// Remove document
function removeDocument(documentType) {
    if (mode === 'view') return;
    
    delete uploadedDocuments[documentType];
    
    // Hide preview
    const previewContainer = document.getElementById(`${documentType}_preview`);
    if (previewContainer) {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
    }
    
    // Reset button
    const button = document.querySelector(`button[onclick*="${documentType}"]`);
    if (button) {
        button.innerHTML = '<i class="fas fa-upload"></i> Upload';
        button.classList.remove('btn-success');
        button.classList.add('btn-secondary');
    }
    
    // Reset profile photo if applicable
    if (documentType === 'profile_picture') {
        const avatarPreview = document.getElementById('avatarPreview');
        avatarPreview.innerHTML = '<i class="fas fa-user" style="font-size: 32px; color: #6B7280;"></i>';
    }
}



// Button Actions
document.getElementById('cancelBtn').addEventListener('click', function () {
    if (mode === 'view' || confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
        window.location.href = 'employee-list.php';
    }
});

if (document.getElementById('saveDraftBtn')) {
    document.getElementById('saveDraftBtn').addEventListener('click', function () {
        const btn = this;
        btn.innerHTML = '<i class="fas fa-check"></i> Draft Saved';
        btn.classList.add('btn-primary');
        btn.classList.remove('btn-secondary');

        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }, 2000);

        alert('Draft saved successfully! You can continue later.');
    });
}

// Real-time validation for email
document.getElementById('email').addEventListener('blur', function () {
    const email = this.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (email && !emailRegex.test(email)) {
        this.classList.add('error');
        if (!this.nextElementSibling.classList.contains('error-text')) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-text';
            errorDiv.textContent = 'Please enter a valid email address';
            this.parentNode.insertBefore(errorDiv, this.nextElementSibling);
        }
    }
});

// Remove error class when user starts typing
document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('input', function () {
        if (this.classList.contains('error')) {
            this.classList.remove('error');
            const errorText = this.nextElementSibling;
            if (errorText && errorText.classList.contains('error-text')) {
                errorText.remove();
            }
        }
    });
});


// Department Modal Functions
async function openDepartmentModal() {
    document.getElementById('departmentModal').classList.add('active');
    await loadDepartmentsList();
}

function closeDepartmentModal() {
    document.getElementById('departmentModal').classList.remove('active');
    document.getElementById('newDepartmentName').value = '';
}

async function loadDepartmentsList() {
    try {
        const response = await fetch('../../../../server/api/hrm/employees/manage-departments.php');
        const result = await response.json();
        
        if (result.success) {
            const list = document.getElementById('departmentList');
            list.innerHTML = '';
            
            result.departments.forEach(dept => {
                const canDelete = dept.tenant_id !== 0;
                list.innerHTML += `
                    <div class="dept-item">
                        <span>${dept.department_name}</span>
                        ${canDelete ? `<button onclick="deleteDepartment(${dept.id})"><i class="fas fa-trash"></i> Delete</button>` : '<span style="font-size: 11px; color: #6b7280;">System</span>'}
                    </div>
                `;
            });
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

async function saveDepartment() {
    const name = document.getElementById('newDepartmentName').value.trim();
    
    if (!name) {
        alert('Please enter department name');
        return;
    }
    
    try {
        const response = await fetch('../../../../server/api/hrm/employees/manage-departments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', name })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('newDepartmentName').value = '';
            await loadDepartmentsList();
            
            // Reload department dropdown
            const deptSelect = document.getElementById('department');
            const option = document.createElement('option');
            option.value = result.id;
            option.textContent = name;
            option.selected = true;
            deptSelect.appendChild(option);
            
            alert('Department added successfully');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving department');
    }
}

async function deleteDepartment(id) {
    if (!confirm('Are you sure you want to delete this department?')) return;
    
    try {
        const response = await fetch('../../../../server/api/hrm/employees/manage-departments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await loadDepartmentsList();
            
            // Remove from dropdown
            const deptSelect = document.getElementById('department');
            const option = deptSelect.querySelector(`option[value="${id}"]`);
            if (option) option.remove();
            
            alert('Department deleted successfully');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting department');
    }
}

// Position Modal Functions
async function openPositionModal() {
    document.getElementById('positionModal').classList.add('active');
    await loadDepartmentsForPosition();
}

function closePositionModal() {
    document.getElementById('positionModal').classList.remove('active');
    document.getElementById('newPositionName').value = '';
}

async function loadDepartmentsForPosition() {
    try {
        const response = await fetch('../../../../server/api/hrm/employees/manage-departments.php');
        const result = await response.json();
        
        if (result.success) {
            const select = document.getElementById('positionDepartment');
            select.innerHTML = '<option value="">Select Department</option>';
            
            result.departments.forEach(dept => {
                select.innerHTML += `<option value="${dept.id}">${dept.department_name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

async function loadPositionsList() {
    const deptId = document.getElementById('positionDepartment').value;
    
    if (!deptId) {
        document.getElementById('positionList').innerHTML = '';
        return;
    }
    
    try {
        const response = await fetch(`../../../../server/api/hrm/employees/manage-positions.php?department_id=${deptId}`);
        const result = await response.json();
        
        if (result.success) {
            const list = document.getElementById('positionList');
            list.innerHTML = '';
            
            result.positions.forEach(pos => {
                const canDelete = pos.tenant_id !== 0;
                list.innerHTML += `
                    <div class="pos-item">
                        <span>${pos.position_title}</span>
                        ${canDelete ? `<button onclick="deletePosition(${pos.id})"><i class="fas fa-trash"></i> Delete</button>` : '<span style="font-size: 11px; color: #6b7280;">System</span>'}
                    </div>
                `;
            });
        }
    } catch (error) {
        console.error('Error loading positions:', error);
    }
}

async function savePosition() {
    const name = document.getElementById('newPositionName').value.trim();
    const deptId = document.getElementById('positionDepartment').value;
    
    if (!name || !deptId) {
        alert('Please enter position name and select department');
        return;
    }
    
    try {
        const response = await fetch('../../../../server/api/hrm/employees/manage-positions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', name, department_id: deptId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('newPositionName').value = '';
            await loadPositionsList();
            
            // Reload position dropdown if same department
            const currentDept = document.getElementById('department').value;
            if (currentDept == deptId) {
                const posSelect = document.getElementById('position');
                const option = document.createElement('option');
                option.value = result.id;
                option.textContent = name;
                option.selected = true;
                posSelect.appendChild(option);
            }
            
            alert('Position added successfully');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error saving position');
    }
}

async function deletePosition(id) {
    if (!confirm('Are you sure you want to delete this position?')) return;
    
    try {
        const response = await fetch('../../../../server/api/hrm/employees/manage-positions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await loadPositionsList();
            
            // Remove from dropdown
            const posSelect = document.getElementById('position');
            const option = posSelect.querySelector(`option[value="${id}"]`);
            if (option) option.remove();
            
            alert('Position deleted successfully');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting position');
    }
}
