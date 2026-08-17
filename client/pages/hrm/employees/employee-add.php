<?php
require_once '../../../../includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}

// Get base currency symbol
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

// Check if viewing/editing existing employee
$employee = null;
$mode = $_GET['mode'] ?? 'add';
$employee_id = $_GET['id'] ?? null;

if ($employee_id && in_array($mode, ['view', 'edit'])) {
    $stmt = $pdo->prepare("
        SELECT e.*, d.department_name, p.position_title 
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$employee_id, $_SESSION['tenant_id']]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        header('Location: employee-list.php');
        exit();
    }
    
    // Get opening balance from accounting_ledger
    $stmt = $pdo->prepare("
        SELECT account_id, debit, credit 
        FROM accounting_ledger 
        WHERE tenant_id = ? AND transaction_type = 'opening_balance' 
        AND reference_table = 'employees' AND reference_id = ?
    ");
    $stmt->execute([$_SESSION['tenant_id'], $employee_id]);
    $ledgerEntries = $stmt->fetchAll();
    
    $employee['opening_debit'] = 0;
    $employee['opening_credit'] = 0;
    
    foreach ($ledgerEntries as $entry) {
        if ($entry['account_id'] == 31 && $entry['debit'] > 0) {
            $employee['opening_debit'] = $entry['debit'];
        } elseif ($entry['account_id'] == 28 && $entry['credit'] > 0) {
            $employee['opening_credit'] = $entry['credit'];
        }
    }
}

$readonly = ($mode === 'view') ? 'readonly disabled' : '';
$page_title = $mode === 'view' ? 'View Employee' : ($mode === 'edit' ? 'Edit Employee' : 'Add Employee');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Barcode library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <link rel="stylesheet" href="../../../assets/css/hrm/employees/employee-add.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><?php echo $page_title; ?></h1>
                <p><?php echo $mode === 'view' ? 'View employee details' : ($mode === 'edit' ? 'Update employee information' : 'Add new employee to LedgerOne ERP system. Fill required fields marked with *'); ?></p>
            </div>
            <div class="barcode-preview">
                <svg id="barcode"></svg>
                <div class="employee-id-display" id="employeeIdDisplay"><?php echo $employee['employee_id'] ?? 'AUTO-GEN'; ?></div>
            </div>
        </div>

        <div class="tabs" id="tabs">
            <button class="tab-btn active" data-tab="personal">Personal Details</button>
            <button class="tab-btn" data-tab="employment">Employment</button>
            <button class="tab-btn" data-tab="compensation">Compensation</button>
            <button class="tab-btn" data-tab="documents">Documents</button>
        </div>

        <div class="status-message success" id="successMessage" style="display: none;">
            <i class="fas fa-check-circle"></i>
            <span>Employee added successfully!</span>
        </div>

        <div class="status-message error" id="errorMessage" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span>Please fill all required fields correctly.</span>
        </div>

        <form id="employeeForm">
            <!-- Personal Details Tab -->
            <div class="tab-content active" id="personal">
                <div class="form-section">
                    <h3 class="section-title">Basic Information <span class="section-required">* Required</span></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName" class="required">First Name</label>
                            <input type="text" id="firstName" name="firstName" placeholder="John" value="<?php echo htmlspecialchars(explode(' ', $employee['full_name'] ?? '')[0] ?? ''); ?>" <?php echo $readonly; ?>>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" placeholder="Doe" value="<?php echo htmlspecialchars(trim(str_replace(explode(' ', $employee['full_name'] ?? '')[0] ?? '', '', $employee['full_name'] ?? ''))); ?>" <?php echo $readonly; ?>>
                        </div>
                        <div class="form-group">
                            <label for="dateOfBirth">Date of Birth</label>
                            <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php echo $employee['date_of_birth'] ?? ''; ?>" <?php echo $readonly; ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender" class="required">Gender</label>
                            <select id="gender" name="gender" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="male" <?php echo ($employee['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($employee['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($employee['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nationality">Nationality</label>
                            <input type="text" id="nationality" name="nationality" placeholder="e.g., American" value="<?php echo htmlspecialchars($employee['nationality'] ?? ''); ?>" <?php echo $readonly; ?>>
                        </div>
                        <div class="form-group">
                            <label for="maritalStatus">Marital Status</label>
                            <select id="maritalStatus" name="maritalStatus" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="single" <?php echo ($employee['marital_status'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
                                <option value="married" <?php echo ($employee['marital_status'] ?? '') === 'married' ? 'selected' : ''; ?>>Married</option>
                                <option value="divorced" <?php echo ($employee['marital_status'] ?? '') === 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="widowed" <?php echo ($employee['marital_status'] ?? '') === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Contact Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="john.doe@company.com" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" <?php echo $readonly; ?>>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="+1 (123) 456-7890" value="<?php echo htmlspecialchars($employee['phone_number'] ?? ''); ?>" <?php echo $readonly; ?>>
                        </div>
                        <div class="form-group">
                            <label for="emergencyContact">Emergency Contact</label>
                            <input type="text" id="emergencyContact" name="emergencyContact" placeholder="Name & phone" value="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>" <?php echo $readonly; ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Full residential address" <?php echo $readonly; ?>><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="profilePhoto">Profile Photo</label>
                            <div class="avatar-upload">
                                <div class="avatar-preview" id="avatarPreview">
                                    <?php if ($employee['profile_photo_url'] ?? ''): ?>
                                        <img src="<?php echo htmlspecialchars($employee['profile_photo_url']); ?>" alt="Employee Photo">
                                    <?php else: ?>
                                        <i class="fas fa-user" style="font-size: 32px; color: #6B7280;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-upload-controls">
                                    <button type="button" class="btn btn-secondary avatar-upload-btn" id="uploadPhoto" <?php echo $readonly; ?>>
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                    <button type="button" class="btn btn-ghost avatar-upload-btn" id="removePhoto" <?php echo $readonly; ?>>
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Tab -->
            <div class="tab-content" id="employment">
                <div class="form-section">
                    <h3 class="section-title">Employment Details <span class="section-required">* Required</span></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employeeId">Employee ID</label>
                            <input type="text" id="employeeId" name="employeeId" placeholder="Auto-generated" 
                                   value="<?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?>" readonly disabled>
                            <div class="helper-text">Auto-generated based on department</div>
                        </div>
                        <div class="form-group">
                            <label for="department" class="required">Department</label>
                            <div style="display: flex; gap: 8px;">
                                <select id="department" name="department" <?php echo $readonly; ?> style="flex: 1;">
                                    <option value="">Select</option>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE (tenant_id = 0 OR tenant_id = ?) AND is_active = 1 ORDER BY department_name");
                                    $stmt->execute([$_SESSION['tenant_id']]);
                                    while ($dept = $stmt->fetch()) {
                                        $selected = ($employee['department_id'] ?? '') == $dept['id'] ? 'selected' : '';
                                        echo '<option value="' . $dept['id'] . '" ' . $selected . '>' . htmlspecialchars($dept['department_name']) . '</option>';
                                    }
                                    ?>
                                </select>
                                <button type="button" class="btn btn-secondary" onclick="openDepartmentModal()" <?php echo $readonly; ?> style="width: 40px; padding: 0;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="position" class="required">Position/Title</label>
                            <div style="display: flex; gap: 8px;">
                                <select id="position" name="position" <?php echo $readonly; ?> style="flex: 1;">
                                    <option value="">Select</option>
                                    <?php if ($employee): ?>
                                    <option value="<?php echo $employee['position_id']; ?>" selected><?php echo htmlspecialchars($employee['position_title']); ?></option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-secondary" onclick="openPositionModal()" <?php echo $readonly; ?> style="width: 40px; padding: 0;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="hireDate">Hire Date</label>
                            <input type="date" id="hireDate" name="hireDate" value="<?php echo $employee['hire_date'] ?? ''; ?>" <?php echo $readonly; ?>>
                        </div>
                        <div class="form-group">
                            <label for="employmentType" class="required">Employment Type</label>
                            <select id="employmentType" name="employmentType" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="full-time" <?php echo ($employee['employment_type'] ?? '') === 'full-time' ? 'selected' : ''; ?>>Full-time Permanent</option>
                                <option value="part-time" <?php echo ($employee['employment_type'] ?? '') === 'part-time' ? 'selected' : ''; ?>>Part-time</option>
                                <option value="contract" <?php echo ($employee['employment_type'] ?? '') === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="intern" <?php echo ($employee['employment_type'] ?? '') === 'intern' ? 'selected' : ''; ?>>Intern/Trainee</option>
                                <option value="probation" <?php echo ($employee['employment_type'] ?? '') === 'probation' ? 'selected' : ''; ?>>Probationary</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="manager">Reporting Manager</label>
                            <select id="manager" name="manager" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, full_name, employee_id FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
                                $stmt->execute([$_SESSION['tenant_id']]);
                                while ($emp = $stmt->fetch()) {
                                    $selected = ($employee['reporting_manager_id'] ?? '') == $emp['id'] ? 'selected' : '';
                                    echo '<option value="' . $emp['id'] . '" ' . $selected . '>' . htmlspecialchars($emp['full_name']) . ' (' . htmlspecialchars($emp['employee_id']) . ')</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Probation & Work Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Probation Period</label>
                            <div class="probation-container">
                                <div class="probation-option">
                                    <input type="radio" id="probationYes" name="probation" value="yes" <?php echo ($employee['probation_end_date'] ?? '') ? 'checked' : ''; ?> <?php echo $readonly; ?>>
                                    <label for="probationYes" style="margin-bottom: 0;">Yes</label>
                                </div>
                                <div class="probation-option">
                                    <input type="radio" id="probationNo" name="probation" value="no" <?php echo !($employee['probation_end_date'] ?? '') ? 'checked' : ''; ?> <?php echo $readonly; ?>>
                                    <label for="probationNo" style="margin-bottom: 0;">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="probationDetails">
                            <label for="probationMonths">Probation Duration</label>
                            <div class="field-with-unit">
                                <input type="number" id="probationMonths" name="probationMonths" placeholder="6" min="1" max="12" value="<?php if($employee['probation_end_date'] ?? '') { $diff = (new DateTime($employee['hire_date']))->diff(new DateTime($employee['probation_end_date'])); echo $diff->m + ($diff->y * 12); } ?>" <?php echo $readonly; ?>>
                                <span class="field-unit">months</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="workLocation">Work Location</label>
                            <select id="workLocation" name="workLocation" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="office" <?php echo ($employee['work_location'] ?? '') === 'office' ? 'selected' : ''; ?>>Office</option>
                                <option value="remote" <?php echo ($employee['work_location'] ?? '') === 'remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="hybrid" <?php echo ($employee['work_location'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                <option value="site" <?php echo ($employee['work_location'] ?? '') === 'site' ? 'selected' : ''; ?>>Site/Field</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="workHours">Work Hours/Week</label>
                            <div class="field-with-unit">
                                <input type="number" id="workHours" name="workHours" placeholder="40" min="10" max="80" value="<?php echo $employee['work_hours_per_week'] ?? ''; ?>" <?php echo $readonly; ?>>
                                <span class="field-unit">hours</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="shiftStart">Shift Timing</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="time" id="shiftStart" name="shiftStart" value="<?php echo explode('-', $employee['shift_timing'] ?? '09:00-17:00')[0]; ?>" <?php echo $readonly; ?>>
                                <span>to</span>
                                <input type="time" id="shiftEnd" name="shiftEnd" value="<?php echo explode('-', $employee['shift_timing'] ?? '09:00-17:00')[1]; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="employeeType">Employee Category</label>
                            <select id="employeeType" name="employeeType" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="regular" <?php echo ($employee['employee_category'] ?? '') === 'regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="temporary" <?php echo ($employee['employee_category'] ?? '') === 'temporary' ? 'selected' : ''; ?>>Temporary</option>
                                <option value="consultant" <?php echo ($employee['employee_category'] ?? '') === 'consultant' ? 'selected' : ''; ?>>Consultant</option>
                                <option value="expatriate" <?php echo ($employee['employee_category'] ?? '') === 'expatriate' ? 'selected' : ''; ?>>Expatriate</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compensation Tab -->
            <div class="tab-content" id="compensation">
                <div class="form-section">
                    <h3 class="section-title">Salary Structure <span class="section-required">* Required</span></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="baseSalary" class="required">Base Salary</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="currencySymbol"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="baseSalary" name="baseSalary" placeholder="50000" min="0" step="100" value="<?php echo $employee['base_salary'] ?? ''; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="salaryFrequency" class="required">Payment Frequency</label>
                            <select id="salaryFrequency" name="salaryFrequency" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="daily" <?php echo ($employee['salary_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="monthly" <?php echo ($employee['salary_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="bi-weekly" <?php echo ($employee['salary_frequency'] ?? '') === 'bi-weekly' ? 'selected' : ''; ?>>Bi-weekly</option>
                                <option value="weekly" <?php echo ($employee['salary_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="semi-monthly" <?php echo ($employee['salary_frequency'] ?? '') === 'semi-monthly' ? 'selected' : ''; ?>>Semi-monthly</option>
                                <option value="yearly" <?php echo ($employee['salary_frequency'] ?? '') === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Allowances & Benefits</h3>
                    <div class="allowances-grid">
                        <div class="allowance-item">
                            <label for="housingAllowance">Housing Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="housingCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="housingAllowance" name="housingAllowance" placeholder="0" min="0" value="<?php echo $employee['housing_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="transportAllowance">Transport Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="transportCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="transportAllowance" name="transportAllowance" placeholder="0" min="0" value="<?php echo $employee['transport_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="medicalAllowance">Medical Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="medicalCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="medicalAllowance" name="medicalAllowance" placeholder="0" min="0" value="<?php echo $employee['medical_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="mealAllowance">Meal Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="mealCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="mealAllowance" name="mealAllowance" placeholder="0" min="0" value="<?php echo $employee['meal_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="communicationAllowance">Communication Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="commCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="communicationAllowance" name="communicationAllowance" placeholder="0" min="0" value="<?php echo $employee['communication_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="educationAllowance">Education Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="eduCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="educationAllowance" name="educationAllowance" placeholder="0" min="0" value="<?php echo $employee['education_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="travelAllowance">Travel Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="travelCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="travelAllowance" name="travelAllowance" placeholder="0" min="0" value="<?php echo $employee['travel_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                        <div class="allowance-item">
                            <label for="otherAllowance">Other Allowance</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="otherCurrency"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="otherAllowance" name="otherAllowance" placeholder="0" min="0" value="<?php echo $employee['other_allowance'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Additional Benefits</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="annualBonus">Annual Bonus (%)</label>
                            <div class="field-with-unit">
                                <input type="number" id="annualBonus" name="annualBonus" placeholder="10" min="0" max="100" step="0.5" value="<?php echo $employee['annual_bonus_percentage'] ?? '0'; ?>" <?php echo $readonly; ?>>
                                <span class="field-unit">%</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="providentFund">Provident Fund (%)</label>
                            <div class="field-with-unit">
                                <input type="number" id="providentFund" name="providentFund" placeholder="12" min="0" max="20" step="0.5" value="<?php echo $employee['provident_fund_percentage'] ?? '0'; ?>" <?php echo $readonly; ?>>
                                <span class="field-unit">%</span>
                            </div>
                            <div class="helper-text">Employee contribution</div>
                        </div>
                        <div class="form-group">
                            <label for="gratuity">Gratuity Eligibility</label>
                            <select id="gratuity" name="gratuity" <?php echo $readonly; ?>>
                                <option value="">Select</option>
                                <option value="yes" <?php echo ($employee['gratuity_eligibility'] ?? 0) == 1 ? 'selected' : ''; ?>>Yes</option>
                                <option value="no" <?php echo ($employee['gratuity_eligibility'] ?? 0) == 0 ? 'selected' : ''; ?>>No</option>
                                <option value="after-5-years">After 5 years</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Health Insurance</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="healthInsurance" name="healthInsurance" <?php echo ($employee['health_insurance'] ?? 1) ? 'checked' : ''; ?> <?php echo $readonly; ?>>
                                <label for="healthInsurance" style="margin-bottom: 0;">Include health insurance</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Life Insurance</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="lifeInsurance" name="lifeInsurance" <?php echo ($employee['life_insurance'] ?? 0) ? 'checked' : ''; ?> <?php echo $readonly; ?>>
                                <label for="lifeInsurance" style="margin-bottom: 0;">Include life insurance</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Paid Time Off</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="pto" name="pto" <?php echo ($employee['paid_time_off'] ?? 1) ? 'checked' : ''; ?> <?php echo $readonly; ?>>
                                <label for="pto" style="margin-bottom: 0;">Include PTO</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Leave Balances</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="paidLeaveBalance">Paid Leave Balance</label>
                            <div class="field-with-unit">
                                <input type="number" id="paidLeaveBalance" name="paidLeaveBalance" value="<?php echo max(0, $employee['leave_balance_paid'] ?? 21); ?>" min="0" max="365" <?php echo $readonly; ?>>
                                <span class="field-unit">days</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="sickLeaveBalance">Sick Leave Balance</label>
                            <div class="field-with-unit">
                                <input type="number" id="sickLeaveBalance" name="sickLeaveBalance" value="<?php echo max(0, $employee['leave_balance_sick'] ?? 10); ?>" min="0" max="365" <?php echo $readonly; ?>>
                                <span class="field-unit">days</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="unpaidLeaveBalance">Unpaid Leave Balance</label>
                            <div class="field-with-unit">
                                <input type="number" id="unpaidLeaveBalance" name="unpaidLeaveBalance" value="<?php echo max(0, $employee['leave_balance_unpaid'] ?? 0); ?>" min="0" max="365" <?php echo $readonly; ?>>
                                <span class="field-unit">days</span>
                            </div>
                            <div class="helper-text">Usually unlimited (0 = unlimited)</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leaveResetPeriod">Leave Reset Period</label>
                            <select id="leaveResetPeriod" name="leaveResetPeriod" <?php echo $readonly; ?>>
                                <option value="yearly" <?php echo ($employee['leave_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : ''; ?>>Yearly (January 1st)</option>
                                <option value="monthly" <?php echo ($employee['leave_reset_period'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly (1st of each month)</option>
                                <option value="hire-anniversary" <?php echo ($employee['leave_reset_period'] ?? '') === 'hire-anniversary' ? 'selected' : ''; ?>>Hire Date Anniversary</option>
                                <option value="fiscal-year" <?php echo ($employee['leave_reset_period'] ?? '') === 'fiscal-year' ? 'selected' : ''; ?>>Fiscal Year</option>
                                <option value="never" <?php echo ($employee['leave_reset_period'] ?? '') === 'never' ? 'selected' : ''; ?>>Never Reset</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nextResetDate">Next Reset Date</label>
                            <input type="date" id="nextResetDate" name="nextResetDate" value="<?php echo $employee['next_reset_date'] ?? ''; ?>" <?php echo $readonly; ?>>
                            <div class="helper-text">When balances will next reset</div>
                        </div>
                        <div class="form-group">
                            <label>Carry Forward</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="carryForward" name="carryForward" <?php echo ($employee['carry_forward_allowed'] ?? 0) ? 'checked' : ''; ?> <?php echo $readonly; ?>>
                                <label for="carryForward" style="margin-bottom: 0;">Allow unused leave carry forward</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Opening Balance</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="openingDebit">Opening Debit Amount</label>
                            <div class="currency-input">
                                <span class="currency-symbol"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="openingDebit" name="openingDebit" placeholder="0" min="0" step="0.01" value="<?php echo $employee['opening_debit'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                            <div class="helper-text">Amount employee owes to company</div>
                        </div>
                        <div class="form-group">
                            <label for="openingCredit">Opening Credit Amount</label>
                            <div class="currency-input">
                                <span class="currency-symbol"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="openingCredit" name="openingCredit" placeholder="0" min="0" step="0.01" value="<?php echo $employee['opening_credit'] ?? '0'; ?>" <?php echo $readonly; ?>>
                            </div>
                            <div class="helper-text">Amount company owes to employee</div>
                        </div>
                        <div class="form-group">
                            <div class="helper-text" style="color: #e34f4f; margin-top: 20px;">Note: Enter value in either Debit OR Credit field, not both</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div class="tab-content" id="documents">
                <div class="form-section">                    
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <div>
                                <div style="font-weight: 500;">Employment Agreement</div>
                                <div style="font-size: 12px; color: #6B7280;">Signed contract with terms & conditions</div>
                                <div class="document-preview" id="employment_agreement_preview" style="<?php echo ($employee['employment_agreement_url'] ?? '') ? 'display: block;' : 'display: none;'; ?>">
                                    <?php if ($employee['employment_agreement_url'] ?? ''): ?>
                                        <?php if (pathinfo($employee['employment_agreement_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                            <div class="pdf-preview" onclick="window.open('<?php echo htmlspecialchars($employee['employment_agreement_url']); ?>', '_blank')"><i class="fas fa-file-pdf"></i></div>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($employee['employment_agreement_url']); ?>" alt="Document" onclick="window.open('<?php echo htmlspecialchars($employee['employment_agreement_url']); ?>', '_blank')">
                                        <?php endif; ?>
                                        <button type="button" class="btn-remove" onclick="removeDocument('employment_agreement')" title="Remove" <?php echo $readonly; ?>><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <input type="file" id="employmentAgreement" name="employmentAgreement" accept=".png,.jpg,.jpeg,.gif,.avif,.webp,.pdf" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="uploadDocument('employmentAgreement', 'employment_agreement')" <?php echo $readonly; ?>>
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>

                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div>
                                <div style="font-weight: 500;">ID Proof</div>
                                <div style="font-size: 12px; color: #6B7280;">Passport, Driver's License, or National ID</div>
                                <div class="document-preview" id="id_proof_preview" style="<?php echo ($employee['id_proof_url'] ?? '') ? 'display: block;' : 'display: none;'; ?>">
                                    <?php if ($employee['id_proof_url'] ?? ''): ?>
                                        <?php if (pathinfo($employee['id_proof_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                            <div class="pdf-preview" onclick="window.open('<?php echo htmlspecialchars($employee['id_proof_url']); ?>', '_blank')"><i class="fas fa-file-pdf"></i></div>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($employee['id_proof_url']); ?>" alt="Document" onclick="window.open('<?php echo htmlspecialchars($employee['id_proof_url']); ?>', '_blank')">
                                        <?php endif; ?>
                                        <button type="button" class="btn-remove" onclick="removeDocument('id_proof')" title="Remove" <?php echo $readonly; ?>><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <input type="file" id="idProof" name="idProof" accept=".png,.jpg,.jpeg,.gif,.avif,.webp,.pdf" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="uploadDocument('idProof', 'id_proof')" <?php echo $readonly; ?>>
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>

                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <div style="font-weight: 500;">Resume / CV</div>
                                <div style="font-size: 12px; color: #6B7280;">Updated curriculum vitae</div>
                                <div class="document-preview" id="resume_preview" style="<?php echo ($employee['resume_url'] ?? '') ? 'display: block;' : 'display: none;'; ?>">
                                    <?php if ($employee['resume_url'] ?? ''): ?>
                                        <?php if (pathinfo($employee['resume_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                            <div class="pdf-preview" onclick="window.open('<?php echo htmlspecialchars($employee['resume_url']); ?>', '_blank')"><i class="fas fa-file-pdf"></i></div>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($employee['resume_url']); ?>" alt="Document" onclick="window.open('<?php echo htmlspecialchars($employee['resume_url']); ?>', '_blank')">
                                        <?php endif; ?>
                                        <button type="button" class="btn-remove" onclick="removeDocument('resume')" title="Remove" <?php echo $readonly; ?>><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <input type="file" id="resume" name="resume" accept=".png,.jpg,.jpeg,.gif,.avif,.webp,.pdf" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="uploadDocument('resume', 'resume')" <?php echo $readonly; ?>>
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>

                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <div style="font-weight: 500;">Educational Certificates</div>
                                <div style="font-size: 12px; color: #6B7280;">Degree/Diploma certificates</div>
                                <div class="document-preview" id="certificates_preview" style="<?php echo ($employee['certificates_url'] ?? '') ? 'display: block;' : 'display: none;'; ?>">
                                    <?php if ($employee['certificates_url'] ?? ''): ?>
                                        <?php if (pathinfo($employee['certificates_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                            <div class="pdf-preview" onclick="window.open('<?php echo htmlspecialchars($employee['certificates_url']); ?>', '_blank')"><i class="fas fa-file-pdf"></i></div>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($employee['certificates_url']); ?>" alt="Document" onclick="window.open('<?php echo htmlspecialchars($employee['certificates_url']); ?>', '_blank')">
                                        <?php endif; ?>
                                        <button type="button" class="btn-remove" onclick="removeDocument('certificates')" title="Remove" <?php echo $readonly; ?>><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <input type="file" id="certificates" name="certificates" accept=".png,.jpg,.jpeg,.gif,.avif,.webp,.pdf" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="uploadDocument('certificates', 'certificates')" <?php echo $readonly; ?>>
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>

                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas fa-briefcase-medical"></i>
                            </div>
                            <div>
                                <div style="font-weight: 500;">Medical Certificate</div>
                                <div style="font-size: 12px; color: #6B7280;">Fitness certificate from physician</div>
                                <div class="document-preview" id="medical_certificate_preview" style="<?php echo ($employee['medical_certificate_url'] ?? '') ? 'display: block;' : 'display: none;'; ?>">
                                    <?php if ($employee['medical_certificate_url'] ?? ''): ?>
                                        <?php if (pathinfo($employee['medical_certificate_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                            <div class="pdf-preview" onclick="window.open('<?php echo htmlspecialchars($employee['medical_certificate_url']); ?>', '_blank')"><i class="fas fa-file-pdf"></i></div>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($employee['medical_certificate_url']); ?>" alt="Document" onclick="window.open('<?php echo htmlspecialchars($employee['medical_certificate_url']); ?>', '_blank')">
                                        <?php endif; ?>
                                        <button type="button" class="btn-remove" onclick="removeDocument('medical_certificate')" title="Remove" <?php echo $readonly; ?>><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <input type="file" id="medicalCertificate" name="medicalCertificate" accept=".png,.jpg,.jpeg,.gif,.avif,.webp,.pdf" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="uploadDocument('medicalCertificate', 'medical_certificate')" <?php echo $readonly; ?>>
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Additional Information</h3>
                    <div class="form-row full-width">
                        <div class="form-group">
                            <label for="notes">Notes for HR/Administration</label>
                            <textarea id="notes" name="notes" placeholder="Any additional information, special conditions, or remarks about the employee..." <?php echo $readonly; ?>><?php echo htmlspecialchars($employee['notes'] ?? ''); ?></textarea>
                            <div class="helper-text">Internal notes only, not visible to the employee</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" id="cancelBtn">
                    <i class="fas fa-times"></i> <?php echo $mode === 'view' ? 'Back' : 'Cancel'; ?>
                </button>
                <?php if ($mode !== 'view'): ?>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-<?php echo $mode === 'edit' ? 'save' : 'user-plus'; ?>"></i> <?php echo $mode === 'edit' ? 'Update Employee' : 'Add Employee'; ?>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Department Modal -->
    <div class="modal" id="departmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Departments</h3>
                <button class="modal-close" onclick="closeDepartmentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="newDepartmentName">Department Name</label>
                    <input type="text" id="newDepartmentName" placeholder="Enter department name">
                </div>
                <button class="btn btn-primary" onclick="saveDepartment()">
                    <i class="fas fa-save"></i> Save Department
                </button>
                
                <div style="margin-top: 24px;">
                    <h4 style="font-size: 14px; margin-bottom: 12px;">Existing Departments</h4>
                    <div id="departmentList" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Position Modal -->
    <div class="modal" id="positionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Positions</h3>
                <button class="modal-close" onclick="closePositionModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="positionDepartment">Department</label>
                    <select id="positionDepartment" onchange="loadPositionsList()">
                        <option value="">Select Department</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="newPositionName">Position Title</label>
                    <input type="text" id="newPositionName" placeholder="Enter position title">
                </div>
                <button class="btn btn-primary" onclick="savePosition()">
                    <i class="fas fa-save"></i> Save Position
                </button>
                
                <div style="margin-top: 24px;">
                    <h4 style="font-size: 14px; margin-bottom: 12px;">Existing Positions</h4>
                    <div id="positionList" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/hrm/employees/employee-add.js"></script>
</body>
</html>