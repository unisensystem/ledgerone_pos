<?php
session_start(); // Ensure this is at the very top

// Include the database connection
include '../config/config.php';
include_once '../../client/includes/dashboard.php';
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

// Function to update the last activity timestamp for the logged-in user
function updateLastActivity($user_id, $pdo) {
    $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
}

// Function to check if a user is online based on last activity
function isUserOnline($last_activity) {
    $current_time = new DateTime();
    $last_activity_time = new DateTime($last_activity);
    $interval = $current_time->diff($last_activity_time);
    
    // Consider user online if last activity was within the last 5 minutes
    return ($interval->i < 5 && $interval->h == 0 && $interval->d == 0);
}

// Call the function to update the last activity timestamp
updateLastActivity($_SESSION['user_id'], $pdo);

// Initialize users array
$users = [];

// Fetch users based on search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
try {
    $user_stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            first_name,
            last_name,
            blocked,
            last_activity,
            is_active,
            is_admin,
            role_id
        FROM users
        WHERE username LIKE :search 
        OR email LIKE :search 
        OR first_name LIKE :search 
        OR last_name LIKE :search
        ORDER BY created_at DESC
    ");
    
    $user_stmt->bindValue(':search', "%$search%");
    if ($user_stmt->execute()) {
        $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch role names separately
        $role_stmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ?");
        foreach ($users as &$user) {
            $role_stmt->execute([$user['role_id']]);
            $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
            $user['role_name'] = $role ? $role['role_name'] : 'Unknown';
        }
    } else {
        error_log("Query failed: " . implode(" ", $user_stmt->errorInfo()));
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Add debugging if needed
if (empty($users)) {
    error_log("No users found or query failed");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="admin_panel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1>Admin Panel</h1>
        <div class="btn-container">
            <a href="../../client/pages/dashboard/dashboard.php" class="btn btn-primary">Dashboard</a>
            <div class="right-buttons">
                <a href="../network_requests/incoming_connections.php" class="btn btn-info">
                    <i class="fas fa-network-wired"></i> Incoming Connections
                </a>
                <a href="role_access.php" class="btn btn-warning">
                    <i class="fas fa-user-lock"></i> Role Access
                </a>
                <button class="btn btn-danger" onclick="confirmFormat()">
                    <i class="fas fa-database"></i> Format Database
                </button>
                <a href="../users/create_user.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Create New User
                </a>
            </div>
        </div>

        <h2>Search and Filter Users</h2>
        <div class="search-container">
            <form method="GET" action="admin_panel.php">
                <input type="text" name="search" placeholder="Search by username" value="<?= isset($search) ? htmlspecialchars($search) : '' ?>">
                <button type="submit" class="search-btn">Search</button>
                <a href="admin_panel.php" class="btn btn-grey">Cancel</a>
            </form>
        </div>

        <h2>Manage Users</h2>
        <div class="user-details-heading">
            <span class="name-heading">Full Name</span>
            <span class="username-heading">Username</span>
            <span class="email-heading">Email</span>
            <span class="role-heading">Role</span>
            <span class="status-heading">Status</span>
            <span class="actions-heading">Actions</span>
        </div>
        <ul>
            <?php foreach ($users as $user_item): ?>
                <li class="user-details" data-user-id="<?= htmlspecialchars($user_item['id']) ?>">
                    <span class="name">
                        <?= htmlspecialchars($user_item['first_name'] . ' ' . $user_item['last_name']) ?>
                    </span>
                    <span class="username">
                        <?= htmlspecialchars($user_item['username']) ?>
                        <?= $user_item['is_admin'] ? ' (Admin)' : '' ?>
                    </span>
                    <span class="email"><?= htmlspecialchars($user_item['email']) ?></span>
                    <span class="role"><?= htmlspecialchars($user_item['role_name']) ?></span>
                    <span class="status <?= $user_item['is_active'] ? 'status-online' : 'status-offline' ?>">
                        <?= $user_item['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <div class="actions">
                        <a href="../users/edit_user.php?id=<?= htmlspecialchars($user_item['id']) ?>" class="btn btn-green small-btn">Edit</a>

                        <button class="btn btn-danger small-btn" onclick="confirmDelete(<?= htmlspecialchars($user_item['id']) ?>)">Delete</button>
                        <?php if ($user_item['blocked']): ?>
                            <button class="btn btn-green small-btn" onclick="confirmUnblock(<?= $user_item['id'] ?>)">Unblock</button>
                        <?php else: ?>
                            <button class="btn btn-dark-red small-btn" onclick="confirmBlock(<?= $user_item['id'] ?>)">Block</button>
                        <?php endif; ?>
                        <?php /* Temporarily disabled
                        <a href="../view_user_logs.php?id=<?= htmlspecialchars($user_item['id']) ?>" class="btn btn-purple small-btn">View Logs</a>
                        */ ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="export-btn-container">
            <a href="../users/export_users_pdf.php" class="btn btn-purple">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
        </div>
    </div>

    <!-- Block Confirmation Modal -->
    <div id="blockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Block</h2>
                <button class="close" onclick="closeModal('blockModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to block this user?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-confirm btn-confirm-block" id="confirmBlockBtn">Block</button>
                <button class="btn btn-cancel" onclick="closeModal('blockModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Unblock Confirmation Modal -->
    <div id="unblockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Unblock</h2>
                <button class="close" onclick="closeModal('unblockModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to unblock this user?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-confirm btn-confirm-unblock" id="confirmUnblockBtn">Unblock</button>
                <button class="btn btn-cancel" onclick="closeModal('unblockModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Delete</h2>
                <button class="close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-confirm btn-confirm-delete" id="confirmDeleteBtn">Delete</button>
                <button class="btn btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Format Database Modal -->
    <div id="formatModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Format Database</h2>
                <button class="close" onclick="closeModal('formatModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p class="warning-text">Warning: This action will permanently delete all data. This cannot be undone!</p>
                <div class="confirmation-input">
                    <label>Type "CONFIRM" to proceed:</label>
                    <input type="text" id="formatConfirmation" />
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" id="confirmFormatBtn" disabled>Format Database</button>
                <button class="btn btn-secondary" onclick="closeModal('formatModal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let userIdToBlock = null;
        let userIdToUnblock = null;
        let userIdToDelete = null;

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            // Trigger reflow
            modal.offsetHeight;
            modal.classList.add('show');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function confirmBlock(userId) {
            userIdToBlock = userId;
            showModal('blockModal');
        }

        function confirmUnblock(userId) {
            userIdToUnblock = userId;
            showModal('unblockModal');
        }

        function confirmDelete(userId) {
            userIdToDelete = userId;
            showModal('deleteModal');
        }

        document.getElementById('confirmBlockBtn').onclick = function() {
            if (userIdToBlock !== null) {
                window.location.href = '../users/block_user.php?id=' + userIdToBlock;
            }
        };

        document.getElementById('confirmUnblockBtn').onclick = function() {
            if (userIdToUnblock !== null) {
                window.location.href = '../users/unblock_user.php?id=' + userIdToUnblock;
            }
        };

        document.getElementById('confirmDeleteBtn').onclick = function() {
            if (userIdToDelete !== null) {
                window.location.href = '../users/delete_user.php?id=' + userIdToDelete;
            }
        };

        window.onclick = function(event) {
            if (event.target == document.getElementById('blockModal')) {
                closeModal('blockModal');
            }
            if (event.target == document.getElementById('unblockModal')) {
                closeModal('unblockModal');
            }
            if (event.target == document.getElementById('deleteModal')) {
                closeModal('deleteModal');
            }
        };

        // Add status polling function
        function updateUserStatuses() {
            fetch('../users/check_status.php')
                .then(response => {
                    if (response.status === 403) {
                        // Session expired or unauthorized - redirect to login
                        window.location.href = '../auth/login.php';
                        return;
                    }
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // Skip if no data
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    document.querySelectorAll('.user-details').forEach(userRow => {
                        const userId = userRow.dataset.userId;
                        if (userId && data.hasOwnProperty(userId)) {
                            const statusSpan = userRow.querySelector('.status');
                            if (statusSpan) {
                                const isActive = data[userId].is_active === 1;
                                statusSpan.textContent = isActive ? 'Active' : 'Inactive';
                                statusSpan.className = `status ${isActive ? 'status-online' : 'status-offline'}`;
                            }
                        }
                    });
                })
                .catch(error => {
                    if (!error.message.includes('403')) {  // Don't log 403 errors
                        console.error('Error updating statuses:', error.message);
                    }
                });
        }

        // Update statuses every 2 seconds
        const statusInterval = setInterval(updateUserStatuses, 2000);

        // Clear interval when page is unloaded
        window.onunload = function() {
            clearInterval(statusInterval);
        };

        // Initial update
        updateUserStatuses();

        function confirmFormat() {
            showModal('formatModal');
            document.getElementById('formatConfirmation').value = '';
            document.getElementById('confirmFormatBtn').disabled = true;
        }

        // Add Format Database confirmation logic
        document.getElementById('formatConfirmation').addEventListener('input', function() {
            document.getElementById('confirmFormatBtn').disabled = this.value !== 'CONFIRM';
        });

        document.getElementById('confirmFormatBtn').onclick = function() {
            window.location.href = '../database/format_database.php';
        };
    </script>
</body>
</html>