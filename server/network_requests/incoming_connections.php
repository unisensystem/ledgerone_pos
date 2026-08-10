<?php
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include '../config/config.php';

// Check admin access
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize variables
$connections = [];
$total_pages = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 7;

try {
    // First check if the connections table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'connections'")->rowCount() > 0;
    
    if (!$table_exists) {
        // Create connections table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS connections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            city VARCHAR(100),
            country VARCHAR(100),
            connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    // Clean up old connections (older than 7 days)
    $cleanup_stmt = $pdo->prepare("
        DELETE FROM connections 
        WHERE connected_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $cleanup_stmt->execute();

    // Fetch connections with pagination
    $stmt = $pdo->prepare("
        SELECT * FROM connections 
        WHERE connected_at >= DATE_SUB(NOW(), INTERVAL :duration DAY)
        ORDER BY connected_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':duration', $duration, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $total = $pdo->prepare("
        SELECT COUNT(*) FROM connections 
        WHERE connected_at >= DATE_SUB(NOW(), INTERVAL :duration DAY)
    ");
    $total->bindValue(':duration', $duration, PDO::PARAM_INT);
    $total->execute();
    $total_pages = ceil($total->fetchColumn() / $per_page);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please check the error logs.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Connections</title>
    <link rel="stylesheet" href="incoming_connections.css">
</head>
<body>
    <div class="container">
        <h1>Incoming Connections</h1>
        
        <div class="btn-container">
            <a href="../admin/admin_panel.php" class="btn btn-primary">Back to Admin Panel</a>
            <div class="duration-filter">
                <a href="?duration=7" class="btn <?= $duration === 7 ? 'btn-primary' : 'btn-secondary' ?>">7 Days</a>
                <a href="?duration=5" class="btn <?= $duration === 5 ? 'btn-primary' : 'btn-secondary' ?>">5 Days</a>
                <a href="?duration=3" class="btn <?= $duration === 3 ? 'btn-primary' : 'btn-secondary' ?>">3 Days</a>
                <a href="?duration=1" class="btn <?= $duration === 1 ? 'btn-primary' : 'btn-secondary' ?>">1 Day</a>
            </div>
        </div>

        <div class="connections-table">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>Connected At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $connection): ?>
                        <tr>
                            <td><?= htmlspecialchars($connection['username']) ?></td>
                            <td><?= htmlspecialchars($connection['ip_address']) ?></td>
                            <td><?= htmlspecialchars($connection['city'] . ', ' . $connection['country']) ?></td>
                            <td><?= htmlspecialchars($connection['connected_at']) ?></td>
                            <td>
                                <button class="btn btn-warning" onclick="showResetModal(<?= $connection['user_id'] ?>, '<?= htmlspecialchars($connection['username']) ?>')">Suspicious Login?</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&duration=<?= $duration ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <!-- Password Reset Modal -->
        <div id="resetModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Security Check</h2>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Reset password for suspicious login by: <span id="resetUsername"></span></p>
                    <form id="resetForm" method="POST" action="../../../server/users/reset_password.php">
                        <input type="hidden" id="resetUserId" name="user_id">
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                            <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function showResetModal(userId, username) {
                document.getElementById('resetUserId').value = userId;
                document.getElementById('resetUsername').textContent = username;
                document.getElementById('resetModal').style.display = 'block';
            }

            function closeResetModal() {
                document.getElementById('resetModal').style.display = 'none';
                document.getElementById('resetForm').reset();
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('resetModal');
                if (event.target == modal) {
                    closeResetModal();
                }
            }

            // Close modal when clicking X
            document.querySelector('.close').onclick = closeResetModal;

            // Validate passwords match
            document.getElementById('resetForm').onsubmit = function(e) {
                const pass1 = document.getElementById('newPassword').value;
                const pass2 = document.getElementById('confirmPassword').value;
                
                if (pass1 !== pass2) {
                    alert('Passwords do not match!');
                    e.preventDefault();
                    return false;
                }
                return true;
            };
        </script>

        <style>
            /* Add to existing styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }

            .modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 500px;
                border-radius: 5px;
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .close {
                color: #aaa;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                display: block;
                margin-bottom: 5px;
            }

            .form-group input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .modal-buttons {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 20px;
            }

            .btn-warning {
                background-color: #e74c3c;  /* Changed from #ffc107 */
                color: #ffffff;  /* Changed from #000 */
            }

            .btn-warning:hover {
                background-color: #c0392b;  /* Changed from #e0a800 */
            }

            .duration-filter {
                display: flex;
                gap: 10px;
                margin-left: auto;
            }

            .btn-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2rem;
            }
        </style>
    </div>
</body>
</html>
