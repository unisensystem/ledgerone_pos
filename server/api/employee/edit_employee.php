<?php
session_start();
require_once('../../config/config.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get employee data
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            die('Employee not found');
        }
        
        // Get salary components
        $salary_stmt = $pdo->prepare("SELECT * FROM salary_components WHERE employee_id = ?");
        $salary_stmt->execute([$_GET['id']]);
        $salary = $salary_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    try {
        $pdo->beginTransaction();

        // First, update employee details
        $sql = "UPDATE employees SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone_primary = :phone_primary,
                phone_secondary = :phone_secondary,
                address = :address,
                date_of_birth = :date_of_birth,
                department_id = :department_id,
                position = :position,
                blood_group = :blood_group,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                cnic = :cnic,
                hire_date = :hire_date,
                status = :status,
                gender = :gender,
                marital_status = :marital_status,
                religion = :religion,
                nationality = :nationality
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $update_result = $stmt->execute([
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone_primary' => $_POST['phone_primary'] ?? '',
            'phone_secondary' => $_POST['phone_secondary'] ?? '',
            'address' => $_POST['address'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'department_id' => $_POST['department_id'] ?? '',
            'position' => $_POST['position'] ?? '',
            'blood_group' => $_POST['blood_group'] ?? '',
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? '',
            'cnic' => $_POST['cnic'] ?? '',
            'hire_date' => $_POST['hire_date'] ?? '',
            'status' => $_POST['status'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'marital_status' => $_POST['marital_status'] ?? '',
            'religion' => $_POST['religion'] ?? '',
            'nationality' => $_POST['nationality'] ?? '',
            'id' => $_POST['id'] ?? ''
        ]);

        if (!$update_result) {
            throw new Exception("Failed to update employee details");
        }

        $pdo->commit();
        
        // Redirect or send response based on request type
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Employee updated successfully'
            ]);
        } else {
            // Regular form submit
            header('Location: ../../../client/pages/HR/Entry/manage_employees.php?success=2');
        }
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error_message]);
        } else {
            // Error will be displayed in the form
        }
    }
}

// Prepare the edit form
$employee_id = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$employee_id) {
    die('Employee ID not provided');
}

try {
    // Fetch employee data if not already fetched
    if (!isset($employee)) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            die('Employee not found');
        }
    }
    
    // Fetch departments for dropdown
    $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY dept_name");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        .required {
            color: red;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            padding: 10px 20px;
            margin-right: 5px;
            border: none;
            background: #f8f9fa;
            cursor: pointer;
        }
        .tab-button.active {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Edit Employee</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($employee_id); ?>">
            <input type="hidden" name="update" value="1">

            <div class="tab-buttons mb-3">
                <button type="button" class="tab-button active" data-tab="basic">Basic Information</button>
                <button type="button" class="tab-button" data-tab="personal">Personal Details</button>
                <button type="button" class="tab-button" data-tab="contact">Contact Information</button>
                <button type="button" class="tab-button" data-tab="employment">Employment Details</button>
                <button type="button" class="tab-button" data-tab="education">Education & Work History</button>
                <button type="button" class="tab-button" data-tab="salary">Salary & Benefits</button>
                <button type="button" class="tab-button" data-tab="health">Health Information</button>
                <button type="button" class="tab-button" data-tab="additional">Additional Information</button>
            </div>

            <div class="tab-content active" id="basic-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name: <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name: <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email: <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>CNIC: <span class="required">*</span></label>
                        <input type="text" name="cnic" class="form-control" value="<?php echo htmlspecialchars($employee['cnic'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="personal-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date of Birth: <span class="required">*</span></label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Gender: <span class="required">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($employee['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($employee['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($employee['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marital Status:</label>
                        <select name="marital_status" class="form-control">
                            <option value="">Select Status</option>
                            <option value="Single" <?php echo ($employee['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo ($employee['marital_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                            <option value="Divorced" <?php echo ($employee['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo ($employee['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="contact-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Primary Phone: <span class="required">*</span></label>
                        <input type="tel" name="phone_primary" class="form-control" value="<?php echo htmlspecialchars($employee['phone_primary'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Secondary Phone:</label>
                        <input type="tel" name="phone_secondary" class="form-control" value="<?php echo htmlspecialchars($employee['phone_secondary'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address:</label>
                        <textarea name="address" class="form-control"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="employment-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Department: <span class="required">*</span></label>
                        <select name="department_id" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['id']); ?>" 
                                    <?php echo ($employee['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position: <span class="required">*</span></label>
                        <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hire Date: <span class="required">*</span></label>
                        <input type="date" name="hire_date" class="form-control" value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php echo ($employee['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($employee['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="On Leave" <?php echo ($employee['status'] ?? '') === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Education & Work History Tab -->
            <div class="tab-content" id="education-tab">
                <h3>Education History</h3>
                <div id="education-history">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Institution</th>
                                <th>Field of Study</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Grade/GPA</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="education-entries">
                            <!-- PHP code for education entries -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary mt-2" onclick="addEducationEntry()">Add Education</button>
                </div>

                <h3 class="mt-4">Work History</h3>
                <div id="work-history">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Reference Name</th>
                                <th>Reference Contact</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="work-entries">
                            <!-- PHP code for work entries -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary mt-2" onclick="addWorkEntry()">Add Work History</button>
                </div>
            </div>

            <!-- Salary & Benefits Tab -->
            <div class="tab-content" id="salary-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Base Salary:</label>
                        <input type="number" name="basic_salary" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['basic_salary'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Monthly Bonus:</label>
                        <input type="number" name="monthly_bonus" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['monthly_bonus'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Quarterly Bonus:</label>
                        <input type="number" name="quarterly_bonus" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['quarterly_bonus'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Yearly Bonus:</label>
                        <input type="number" name="yearly_bonus" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['yearly_bonus'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Sales Commission (%):</label>
                        <input type="number" name="sales_commission_percentage" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['sales_commission_percentage'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Housing Allowance:</label>
                        <input type="number" name="housing_allowance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['housing_allowance'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Transport Allowance:</label>
                        <input type="number" name="transport_allowance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['transport_allowance'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Medical Allowance:</label>
                        <input type="number" name="medical_allowance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($salary['medical_allowance'] ?? '0'); ?>">
                    </div>
                </div>

                <div class="salary-summary mt-3">
                    <h4>Salary Summary</h4>
                    <p>Monthly Total: <span class="salary-total" id="total_monthly">0.00</span></p>
                    <p>Annual Package: <span class="salary-total" id="annual_package">0.00</span></p>
                </div>
            </div>

            <div class="tab-content" id="health-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Blood Group:</label>
                        <select name="blood_group" class="form-control">
                            <option value="">Select Blood Group</option>
                            <?php
                            $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($blood_groups as $bg):
                            ?>
                                <option value="<?php echo $bg; ?>" <?php echo ($employee['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Name:</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Phone:</label>
                        <input type="tel" name="emergency_contact_phone" class="form-control" value="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Additional Information Tab -->
            <div class="tab-content" id="additional-tab">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Skills:</label>
                        <textarea name="skills" class="form-control" rows="3"><?php echo htmlspecialchars($employee['skills'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Languages Known:</label>
                        <textarea name="languages" class="form-control" rows="3"><?php echo htmlspecialchars($employee['languages'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Additional Remarks:</label>
                        <textarea name="remarks" class="form-control" rows="3"><?php echo htmlspecialchars($employee['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-group mt-3">
                <button type="submit" class="btn btn-primary">Update Employee</button>
                <a href="../../../client/pages/HR/Entry/manage_employees.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Tab switching functionality
        $('.tab-button').click(function() {
            $('.tab-button').removeClass('active');
            $('.tab-content').removeClass('active');
            $(this).addClass('active');
            $('#' + $(this).data('tab') + '-tab').addClass('active');
        });

        // Form validation
        $('form').on('submit', function(e) {
            let isValid = true;
            const requiredFields = $(this).find('[required]');
            
            requiredFields.each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    });

    // Salary calculations
    function calculateSalary() {
        const basicSalary = parseFloat($('input[name="basic_salary"]').val()) || 0;
        const monthlyBonus = parseFloat($('input[name="monthly_bonus"]').val()) || 0;
        const quarterlyBonus = parseFloat($('input[name="quarterly_bonus"]').val()) || 0;
        const yearlyBonus = parseFloat($('input[name="yearly_bonus"]').val()) || 0;
        const salesCommission = parseFloat($('input[name="sales_commission_percentage"]').val()) || 0;
        const housingAllowance = parseFloat($('input[name="housing_allowance"]').val()) || 0;
        const transportAllowance = parseFloat($('input[name="transport_allowance"]').val()) || 0;
        const medicalAllowance = parseFloat($('input[name="medical_allowance"]').val()) || 0;

        // Calculate monthly total
        const monthlyTotal = basicSalary + 
                           monthlyBonus + 
                           (quarterlyBonus / 3) + 
                           (yearlyBonus / 12) +
                           housingAllowance +
                           transportAllowance +
                           medicalAllowance;

        // Calculate annual package
        const annualPackage = (basicSalary * 12) + 
                            (monthlyBonus * 12) +
                            (quarterlyBonus * 4) +
                            yearlyBonus +
                            (housingAllowance * 12) +
                            (transportAllowance * 12) +
                            (medicalAllowance * 12);

        // Update the display
        $('#total_monthly').text(monthlyTotal.toLocaleString('en-US', {
            style: 'currency',
            currency: 'PKR'
        }));
        $('#annual_package').text(annualPackage.toLocaleString('en-US', {
            style: 'currency',
            currency: 'PKR'
        }));
    }

    // Add event listeners to salary inputs
    $(document).ready(function() {
        const salaryInputs = [
            'basic_salary', 'monthly_bonus', 'quarterly_bonus', 'yearly_bonus',
            'sales_commission_percentage', 'housing_allowance', 
            'transport_allowance', 'medical_allowance'
        ];

        salaryInputs.forEach(input => {
            $(`input[name="${input}"]`).on('input', calculateSalary);
        });

        // Calculate initial values
        calculateSalary();
    });
    </script>
</body>
</html>
