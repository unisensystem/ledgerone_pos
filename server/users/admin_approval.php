<?php
session_start();
include 'config.php';
include 'auth.php';
requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_SANITIZE_NUMBER_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    if ($request_id && ($action === 'approved' || $action === 'rejected')) {
        try {
            if ($action === 'approved') {
                // Fetch request details
                $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = :id");
                $stmt->bindParam(':id', $request_id);
                $stmt->execute();
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($request) {
                    // Insert user into users table
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'user')");
                    $stmt->bindParam(':username', $request['username']);
                    $stmt->bindParam(':password', $request['password']);
                    $stmt->execute();

                    // Delete request from registration_requests table
                    $stmt = $conn->prepare("DELETE FROM registration_requests WHERE id = :id");
                    $stmt->bindParam(':id', $request_id);
                    $stmt->execute();
                }
            } else {
                // Delete request from registration_requests table
                $stmt = $conn->prepare("DELETE FROM registration_requests WHERE id = :id");
                $stmt->bindParam(':id', $request_id);
                $stmt->execute();
            }

            // Log admin action
            $stmt = $conn->prepare("INSERT INTO admin_approval (request_id, admin_id, action) VALUES (:request_id, :admin_id, :action)");
            $stmt->bindParam(':request_id', $request_id);
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':action', $action);
            $stmt->execute();

            echo "Action completed successfully.";
        } catch (PDOException $e) {
            echo "Error: Could not complete the action.";
        }
    } else {
        echo "Invalid action.";
    }
}

try {
    // Fetch all registration requests
    $stmt = $conn->prepare("SELECT * FROM registration_requests");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching registration requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Approval</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .table-container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        .form-container {
            display: flex;
            justify-content: space-between;
        }
        .form-container form {
            display: inline;
        }
        .form-container button {
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="table-container">
        <h1>Admin Approval</h1>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gmail</th>
                    <th>Cell No</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Username</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($requests)): ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= htmlspecialchars($request['id']) ?></td>
                            <td><?= htmlspecialchars($request['gmail']) ?></td>
                            <td><?= htmlspecialchars($request['cell_no']) ?></td>
                            <td><?= htmlspecialchars($request['first_name']) ?></td>
                            <td><?= htmlspecialchars($request['last_name']) ?></td>
                            <td><?= htmlspecialchars($request['username']) ?></td>
                            <td class="form-container">
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <input type="hidden" name="action" value="approved">
                                    <button type="submit">Approve</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <input type="hidden" name="action" value="rejected">
                                    <button type="submit">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No registration requests found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>