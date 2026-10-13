<?php
session_start();
require_once('config.php');
require_once('includes/barcode_generator.php');

// Fetch all active employees for the "Hired By" dropdown
$stmt = $conn->query("SELECT id, first_name, last_name FROM employees WHERE status = 'Active'");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Update Employee
if (isset($_POST['update_employee'])) {
    try {
        $sql = "UPDATE employees SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                department = :department,
                position = :position
                WHERE id = :id";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'first_name' => $_POST['edit_first_name'],
            'last_name' => $_POST['edit_last_name'],
            'email' => $_POST['edit_email'],
            'department' => $_POST['edit_department'],
            'position' => $_POST['edit_position'],
            'id' => $_POST['edit_id']
        ]);
        
        $_SESSION['success_message'] = "Employee updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating employee: " . $e->getMessage();
    }
}

// Handle Delete Employee
if (isset($_POST['delete_employee'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        
        $_SESSION['success_message'] = "Employee deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting employee: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_employee']) && !isset($_POST['delete_employee'])) {
    try {
        $conn->beginTransaction();

        // Generate unique barcode
        $barcode = generateUniqueBarcode($conn, $_POST['employee_id']);

        // Handle photo upload
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/employee_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed.');
            }

            $photo_path = $upload_dir . $_POST['employee_id'] . '.' . $file_extension;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                throw new Exception('Failed to upload photo.');
            }
        }

        // Insert employee data
        $sql = "INSERT INTO employees (
            employee_id, first_name, last_name, email, phone, 
            photo_path, address, date_of_birth, hire_date, 
            department, position, salary, hired_by, barcode
        ) VALUES (
            :employee_id, :first_name, :last_name, :email, :phone,
            :photo_path, :address, :date_of_birth, :hire_date,
            :department, :position, :salary, :hired_by, :barcode
        )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'employee_id' => $_POST['employee_id'],
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'photo_path' => $photo_path,
            'address' => $_POST['address'],
            'date_of_birth' => $_POST['date_of_birth'],
            'hire_date' => $_POST['hire_date'],
            'department' => $_POST['department'],
            'position' => $_POST['position'],
            'salary' => $_POST['salary'],
            'hired_by' => $_POST['hired_by'],
            'barcode' => $barcode
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Employee added successfully!";
        header("Location: employees.php");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Employee</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .actions {
            white-space: nowrap;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0 2px;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        .btn-danger {
            background-color: #dc3545;
            color: #fff;
        }
        .btn-info {
            background-color: #17a2b8;
            color: #fff;
        }
        .btn-primary {
            background-color: #007bff;
            color: #fff;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        .status-badge.active {
            background-color: #28a745;
            color: white;
        }
        .status-badge.inactive {
            background-color: #dc3545;
            color: white;
        }
        /* Add Font Awesome */
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Employee</h2>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Employee ID: <span class="required">*</span></label>
                <input type="text" name="employee_id" id="employee_id" readonly required>
                <small>Format: YYYY-DD-XXXX (e.g., 2025-IT-0001)</small>
            </div>

            <div class="form-group">
                <label>First Name: <span class="required">*</span></label>
                <input type="text" name="first_name" required>
            </div>

            <div class="form-group">
                <label>Last Name: <span class="required">*</span></label>
                <input type="text" name="last_name" required>
            </div>

            <div class="form-group">
                <label>Email: <span class="required">*</span></label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Phone:</label>
                <input type="tel" name="phone">
            </div>

            <div class="form-group">
                <label>Photo:</label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png">
                <small>Allowed formats: JPG, JPEG, PNG</small>
            </div>

            <div class="form-group">
                <label>Address:</label>
                <textarea name="address"></textarea>
            </div>

            <div class="form-group">
                <label>Date of Birth: <span class="required">*</span></label>
                <input type="date" name="date_of_birth" required>
            </div>

            <div class="form-group">
                <label>Hire Date: <span class="required">*</span></label>
                <input type="date" name="hire_date" required>
            </div>

            <div class="form-group">
                <label>Department: <span class="required">*</span></label>
                <select name="department" id="department" required>
                    <option value="">Select Department</option>
                    <option value="IT">Information Technology</option>
                    <option value="HR">Human Resources</option>
                    <option value="FN">Finance</option>
                    <option value="MK">Marketing</option>
                    <option value="SL">Sales</option>
                    <option value="OP">Operations</option>
                </select>
            </div>

            <div class="form-group">
                <label>Position: <span class="required">*</span></label>
                <input type="text" name="position" required>
            </div>

            <div class="form-group">
                <label>Salary: <span class="required">*</span></label>
                <input type="number" name="salary" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Hired By: <span class="required">*</span></label>
                <select name="hired_by" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <button type="submit">Add Employee</button>
                <a href="employees.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="employee-list-section">
        <h3>Employee List</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $list_sql = "SELECT * FROM employees ORDER BY created_at DESC";
                $list_stmt = $conn->query($list_sql);
                while ($row = $list_stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                    <td><?php echo htmlspecialchars($row['position']); ?></td>
                    <td>
                        <span class="status-badge <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span>
                    </td>
                    <td class="actions">
                        <button onclick="openEditModal(<?php echo $row['id']; ?>)" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="openDeleteModal(<?php echo $row['id']; ?>)" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                        <a href="get_employee_card.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-id-card"></i> ID Card
                        </a>
                        <a href="attendance.php?employee_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-clock"></i> Attendance
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
    function generateEmployeeId() {
        const departmentSelect = document.getElementById('department');
        const employeeIdInput = document.getElementById('employee_id');
        
        if (!departmentSelect.value) {
            employeeIdInput.value = '';
            return;
        }

        // Get current date components
        const currentDate = new Date();
        const year = currentDate.getFullYear();
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const sequence = '0001'; // You can modify this based on your needs
        
        // Format: YYYY-DD-XXXX (e.g., 2025-IT-0001)
        employeeIdInput.value = `${year}-${departmentSelect.value}-${sequence}`;
    }

    // Add event listener when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        const departmentSelect = document.getElementById('department');
        if (departmentSelect) {
            departmentSelect.addEventListener('change', generateEmployeeId);
        }
    });
    </script>
</body>
</html>
