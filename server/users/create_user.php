<?php
session_start();

include '../config/config.php';
include '../config/functions.php';
// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if the user is an admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo "Access denied. You are not authorized to view this page.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username'])) {
        error_log("POST data: " . print_r($_POST, true));
        
        $username = trim($_POST['username']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $company_settings = isset($_POST['company_settings']) ? $_POST['company_settings'] : [];

        // Debug log
        error_log("Role ID before validation: " . $role_id);

        // Basic validation
        $errors = [];
        if (empty($username)) $errors[] = 'Username is required';
        if (empty($first_name)) $errors[] = 'First name is required';
        if (empty($last_name)) $errors[] = 'Last name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (empty($password)) $errors[] = 'Password is required';

        if (!empty($errors)) {
            error_log("Validation errors: " . implode(", ", $errors));
            header('Location: create_user.php?error=' . urlencode(implode(", ", $errors)));
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            // Check if username already exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("Username already exists");
            }
            
            // Insert the new user
            $user_stmt = $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, password, role_id, is_admin) VALUES (:username, :first_name, :last_name, :email, :password, :role_id, :is_admin)");
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $params = [
                ':username' => $username,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':password' => $hashed_password,
                ':role_id' => $role_id,
                ':is_admin' => $is_admin
            ];
            
            error_log("Executing query with params: " . print_r($params, true));
            
            if (!$user_stmt->execute($params)) {
                $errorInfo = $user_stmt->errorInfo();
                error_log("SQL Error: " . print_r($errorInfo, true));
                throw new Exception("Failed to create user: " . $errorInfo[2]);
            }

            $user_id = $pdo->lastInsertId();
            error_log("User created with ID: " . $user_id);

            // Insert company settings assignments
            if (!empty($company_settings)) {
                $dist_stmt = $pdo->prepare("INSERT INTO distribution_users (user_id, company_setting_id) VALUES (?, ?)");
                foreach ($company_settings as $company_setting_id) {
                    if (!$dist_stmt->execute([$user_id, $company_setting_id])) {
                        $errorInfo = $dist_stmt->errorInfo();
                        error_log("Distribution assignment error: " . print_r($errorInfo, true));
                        throw new Exception("Failed to assign company settings: " . $errorInfo[2]);
                    }
                }
            }

            $pdo->commit();
            header('Location: create_user.php?message=User created successfully');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error creating user: " . $e->getMessage());
            header('Location: create_user.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    } elseif (isset($_POST['new_role'])) {
        $new_role = $_POST['new_role'];

        // Insert the new role
        $role_stmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES (:role_name)");
        $role_stmt->bindParam(':role_name', $new_role);
        $role_stmt->execute();

        header('Location: create_user.php?message=Role created successfully');
        exit;
    } elseif (isset($_POST['delete_role'])) {
        error_log("POST data: " . print_r($_POST, true)); // Debug log
        $role_id = isset($_POST['delete_role']) ? $_POST['delete_role'] : null;
        
        try {
            // Validate role ID
            if (empty($role_id)) {
                error_log("Role ID is empty. POST data: " . print_r($_POST, true));
                throw new Exception('Role ID is missing');
            }

            // Fetch role before deletion
            $role_check = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ?");
            $role_check->execute([$role_id]);
            $role = $role_check->fetch();

            if (!$role) {
                error_log("Role not found for ID: " . $role_id);
                throw new Exception('Role not found');
            }

            // Check if role is in use
            $user_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
            $user_check->execute([$role_id]);
            
            if ($user_check->fetchColumn() > 0) {
                throw new Exception('Cannot delete role that is in use');
            }

            // Delete the role
            $delete = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            if (!$delete->execute([$role_id])) {
                error_log("Failed to delete role: " . implode(", ", $delete->errorInfo()));
                throw new Exception('Failed to delete role');
            }

            header('Location: create_user.php?message=Role deleted successfully');
            exit;

        } catch (Exception $e) {
            error_log("Role deletion error: " . $e->getMessage());
            header('Location: create_user.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Fetch all roles
$role_stmt = $pdo->query("SELECT id, role_name FROM roles");
$roles = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all company settings
$company_stmt = $pdo->query("SELECT id, company_name FROM company_settings ORDER BY company_name");
$company_settings = $company_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 500px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h1 {
            margin-top: 20px;
            margin-bottom: 16px;
            color: #343a40;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        label {
            text-align: left;
            margin-bottom: 4px;
            font-weight: bold;
            color: #495057;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            width: 95%;
            margin-right: auto;
        }
        select {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
        }
        .role-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .role-container select {
            flex: 1;
        }
        .btn-add {
            padding: 6px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            width: 40px;
        }
        .btn-add:hover {
            background-color: #0056b3;
        }
        .btn-delete {
            padding: 6px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            width: 40px;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            gap: 10px;
        }
        .btn {
            padding: 10px;
            width: calc(50% - 5px);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            text-align: center;
            display: inline-block;
            text-decoration: none;
        }
        .btn-create {
            background-color: #007bff;
        }
        .btn-create:hover {
            background-color: #0056b3;
        }
        .btn-back {
            background-color: #6c757d;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: 8px;
            text-align: center;
        }
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .modal-close:hover,
        .modal-close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal h2 {
            margin-top: 0;
            color: #343a40;
        }
        .modal form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .modal input[type="text"] {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            width: 95%;
            margin-right: auto;
        }
        .modal button {
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .modal button:hover {
            background-color: #0056b3;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-delete-confirm {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-delete-confirm:hover {
            background-color: #c82333;
        }
        .admin-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 10px;
            text-align: left;
        }
        .admin-checkbox input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        .admin-checkbox label {
            margin: 0;
            font-weight: normal;
            color: #495057;
        }
        .distributions-container {
            text-align: left;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
        }
        .distribution-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .distribution-item input[type="checkbox"] {
            margin-right: 8px;
            width: auto;
        }
        .distribution-item label {
            margin: 0;
            font-weight: normal;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create User</h1>
        <form method="POST" action="create_user.php">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" required>

            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="role">Role</label>
            <div class="role-container">
                <select id="role" name="role_id" required>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['id']) ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No roles available</option>
                    <?php endif; ?>
                </select>
                <button type="button" class="btn-add" onclick="document.getElementById('roleModal').style.display='flex'">+</button>
                <button type="button" class="btn-delete" onclick="confirmDeleteRole()">-</button>
            </div>

            <label for="company_settings">Company Access</label>
            <div class="distributions-container">
                <?php if (!empty($company_settings)): ?>
                    <?php foreach ($company_settings as $company): ?>
                        <div class="distribution-item">
                            <input type="checkbox" id="company_<?= $company['id'] ?>" name="company_settings[]" value="<?= $company['id'] ?>">
                            <label for="company_<?= $company['id'] ?>"><?= htmlspecialchars($company['company_name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No company settings available</p>
                <?php endif; ?>
            </div>

            <div class="admin-checkbox">
                <input type="checkbox" id="is_admin" name="is_admin" value="1">
                <label for="is_admin">Administrator Access</label>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn btn-create">Create User</button>
                <a href="../admin/admin_panel.php" class="btn btn-back">Back to Admin Panel</a>
            </div>
        </form>
    </div>

    <!-- Role Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="document.getElementById('roleModal').style.display='none'">&times;</span>
            <h2>Create New Role</h2>
            <form method="POST" action="create_user.php">
                <label for="new_role">Role Name</label>
                <input type="text" id="new_role" name="new_role" required>
                <button type="submit">Create Role</button>
            </form>
        </div>
    </div>

    <!-- Delete Role Modal -->
    <div id="deleteRoleModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="document.getElementById('deleteRoleModal').style.display='none'">&times;</span>
            <h2>Delete Role</h2>
            <p>Are you sure you want to delete this role?</p>
            <form method="POST" action="create_user.php">
                <input type="hidden" id="delete_role_id" name="delete_role">
                <div class="modal-buttons">
                    <button type="submit" class="btn-delete-confirm">Delete</button>
                    <button type="button" onclick="document.getElementById('deleteRoleModal').style.display='none'" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDeleteRole() {
            var roleSelect = document.getElementById('role');
            var selectedRole = roleSelect.value;
            var selectedRoleName = roleSelect.options[roleSelect.selectedIndex].text;
            
            if (roleSelect.options.length <= 1) {
                alert('Cannot delete the last remaining role.');
                return;
            }

            var deleteRoleInput = document.getElementById('delete_role_id');
            deleteRoleInput.value = selectedRole;

            var confirmMessage = document.querySelector('#deleteRoleModal p');
            confirmMessage.textContent = 'Are you sure you want to delete the role "' + selectedRoleName + '"?';
            document.getElementById('deleteRoleModal').style.display = 'flex';
        }

        window.onclick = function(event) {
            var roleModal = document.getElementById('roleModal');
            var deleteRoleModal = document.getElementById('deleteRoleModal');
            if (event.target == roleModal) {
                roleModal.style.display = "none";
            }
            if (event.target == deleteRoleModal) {
                deleteRoleModal.style.display = "none";
            }
        }
    </script>

    <?php if (isset($_GET['error'])): ?>
        <script>
            window.addEventListener('load', function() {
                alert('<?= addslashes(htmlspecialchars($_GET['error'])) ?>');
            });
        </script>
    <?php endif; ?>
</body>
</html>