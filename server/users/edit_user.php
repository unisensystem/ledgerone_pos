<?php
session_start();

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

include '../config/config.php'; // Include database connection
include_once '../../client/includes/dashboard.php';
// Fetch user data based on the ID
$user_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch all roles
$role_stmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name");
$roles = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all company settings
$company_stmt = $pdo->query("SELECT id, company_name FROM company_settings ORDER BY company_name");
$company_settings = $company_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's current company settings
$user_company_stmt = $pdo->prepare("SELECT company_setting_id FROM distribution_users WHERE user_id = ?");
$user_company_stmt->execute([$user_id]);
$user_company_settings = $user_company_stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user['password']; // Hash the password if provided
    $role_id = $_POST['role_id'];
    $company_settings_selected = isset($_POST['company_settings']) ? $_POST['company_settings'] : [];

    try {
        $pdo->beginTransaction();

        // Update user
        $stmt = $pdo->prepare("UPDATE users SET username = :username, password = :password, role_id = :role_id WHERE id = :id");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();

        // Update company setting assignments
        // First, delete existing assignments
        $delete_stmt = $pdo->prepare("DELETE FROM distribution_users WHERE user_id = ?");
        $delete_stmt->execute([$user_id]);

        // Then, insert new assignments
        if (!empty($company_settings_selected)) {
            $insert_stmt = $pdo->prepare("INSERT INTO distribution_users (user_id, company_setting_id) VALUES (?, ?)");
            foreach ($company_settings_selected as $company_setting_id) {
                $insert_stmt->execute([$user_id, $company_setting_id]);
            }
        }

        $pdo->commit();
        
        // Redirect back to the admin panel
        header('Location: ../admin/admin_panel.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating user: " . $e->getMessage());
        // Handle error - could redirect with error message
        header('Location: edit_user.php?id=' . $user_id . '&error=Failed to update user');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <!-- Link to the CSS file -->
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }

        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #343a40;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 5px;
            color: #343a40;
        }

        input, select {
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .btn {
            padding: 15px 30px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            text-align: center;
            margin: 10px 0; /* Adjusted margin for separation */
            width: 100%;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .small-btn {
            width: 30px;
            height: 30px;
            margin-left: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            line-height: 1;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
        }

        .role-group {
            display: flex;
            align-items: flex-start;
        }

        .role-group select {
            flex: 1;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
        }

        .modal-header, .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px; /* Add padding below the header */
        }

        .modal-body {
            margin-top: 20px; /* Add margin for spacing */
        }

        .modal-footer {
            border-top: 1px solid #ddd;
            gap: 10px; /* Add space between buttons */
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }

        .close:hover, .close:focus {
            color: black;
        }

        .role-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .role-container select {
            flex: 1;
        }

        .btn-add, .btn-delete {
            padding: 6px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            width: 40px;
            height: 40px;
        }

        .btn-add {
            background-color: #28a745;
        }

        .btn-add:hover {
            background-color: #218838;
        }

        .btn-delete {
            background-color: #dc3545;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .distributions-container {
            text-align: left;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
            margin-bottom: 20px;
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
        <h1>Edit User</h1>
        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            <label for="password">Password (Leave blank to keep current password)</label>
            <input type="password" id="password" name="password">
            <label for="role">Role</label>
            <div class="role-container">
                <select name="role_id" id="role" class="form-control" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>" 
                                <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-add" onclick="document.getElementById('roleModal').style.display='flex'">+</button>
                <button type="button" class="btn-delete" onclick="confirmDeleteRole()">-</button>
            </div>
            
            <label for="company_settings">Company Access</label>
            <div class="distributions-container">
                <?php if (!empty($company_settings)): ?>
                    <?php foreach ($company_settings as $company): ?>
                        <div class="distribution-item">
                            <input type="checkbox" 
                                   id="company_<?= $company['id'] ?>" 
                                   name="company_settings[]" 
                                   value="<?= $company['id'] ?>"
                                   <?= in_array($company['id'], $user_company_settings) ? 'checked' : '' ?>>
                            <label for="company_<?= $company['id'] ?>"><?= htmlspecialchars($company['company_name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No company settings available</p>
                <?php endif; ?>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn">Update User</button>
                <a href="../admin/admin_panel.php" class="btn" style="margin-left: 10px;">Back to Admin Panel</a>
            </div>
        </form>
    </div>

    <!-- The Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Role</h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <label for="new-role">Role Name</label>
                <input type="text" id="new-role" placeholder="Enter new role">
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="addRole()">Add Role</button>
                <button class="btn" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('roleModal').style.display = "block";
        }

        function closeModal() {
            document.getElementById('roleModal').style.display = "none";
        }

        function addRole() {
            var role = document.getElementById('new-role').value;
            if (role) {
                var select = document.getElementById("role");
                var option = document.createElement("option");
                option.value = role.toLowerCase();
                option.text = role;
                select.add(option);
                select.value = role.toLowerCase();
                closeModal();
            }
        }

        function confirmDeleteRole() {
            const roleSelect = document.getElementById('role');
            const selectedRole = roleSelect.value;
            
            if (roleSelect.options.length <= 1) {
                alert('Cannot delete the last remaining role.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete this role?`)) {
                // Send delete request
                window.location.href = `delete_role.php?id=${selectedRole}`;
            }
        }

        // Close modal if the user clicks outside of it
        window.onclick = function(event) {
            if (event.target == document.getElementById('roleModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>