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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/system_setup/admin_panel/panel.css">
</head>

<body>
    <div class="app-container">
        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- CONTENT AREA -->
            <div class="content-area">
                <!-- USER STATS CARD -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">User Statistics</div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-primary" onclick="openCreateUserModal()">
                                <i class="fas fa-plus"></i> Create User
                            </button>
                            <button class="btn btn-secondary" onclick="openAccessModal()">
                                <i class="fas fa-key"></i> Manage Access
                            </button>
                        </div>
                    </div>
                    <div class="d-flex gap-16">
                        <div
                            style="flex: 1; padding: 16px; border-radius: var(--radius); background-color: rgba(31, 123, 255, 0.05);">
                            <div style="font-size: 28px; font-weight: 700; color: var(--primary);">42</div>
                            <div class="text-muted">Total Users</div>
                        </div>
                        <div
                            style="flex: 1; padding: 16px; border-radius: var(--radius); background-color: rgba(47, 191, 113, 0.05);">
                            <div style="font-size: 28px; font-weight: 700; color: var(--success);">38</div>
                            <div class="text-muted">Active Users</div>
                        </div>
                        <div
                            style="flex: 1; padding: 16px; border-radius: var(--radius); background-color: rgba(227, 79, 79, 0.05);">
                            <div style="font-size: 28px; font-weight: 700; color: var(--error);">3</div>
                            <div class="text-muted">Blocked Users</div>
                        </div>
                        <div
                            style="flex: 1; padding: 16px; border-radius: var(--radius); background-color: rgba(232, 178, 63, 0.05);">
                            <div style="font-size: 28px; font-weight: 700; color: var(--warning);">1</div>
                            <div class="text-muted">Pending</div>
                        </div>
                    </div>
                </div>

                <!-- USERS TABLE CARD -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Users List</div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-secondary btn-sm">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-secondary btn-sm">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="usersTable">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Users will be populated by JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-between align-center mt-24">
                        <div class="text-muted">Showing 1-10 of 42 users</div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-ghost btn-sm">Previous</button>
                            <button class="btn btn-ghost btn-sm">Next</button>
                        </div>
                    </div>
                </div>

                <!-- ROLES MANAGEMENT CARD -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Roles Management</div>
                        <button class="btn btn-primary" onclick="openCreateRoleModal()">
                            <i class="fas fa-plus"></i> Create Role
                        </button>
                    </div>
                    <div class="table-container">
                        <table id="rolesTable">
                            <thead>
                                <tr>
                                    <th>Role ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>System Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rolesTableBody">
                                <!-- Roles will be populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SYSTEM MANAGEMENT CARD -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">System Management</div>
                    </div>
                    <div class="form-grid">
                        <div>
                            <h4 style="margin-bottom: 16px; color: var(--heading);">Database Operations</h4>
                            <div class="d-flex flex-column gap-12">
                                <button class="btn btn-warning" onclick="openFormatModal()">
                                    <i class="fas fa-eraser"></i> Format Database
                                </button>
                                <button class="btn btn-secondary" onclick="openBackupModal()">
                                    <i class="fas fa-download"></i> Backup Database
                                </button>
                                <button class="btn btn-secondary" onclick="openRestoreModal()">
                                    <i class="fas fa-upload"></i> Restore Database
                                </button>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 16px; color: var(--heading);">Login Activity</h4>
                            <div class="d-flex flex-column gap-12">
                                <button class="btn btn-secondary" onclick="openLoginHistoryModal()">
                                    <i class="fas fa-history"></i> View Login History
                                </button>
                                <button class="btn btn-secondary" onclick="openFailedLoginsModal()">
                                    <i class="fas fa-exclamation-triangle"></i> Failed Logins
                                </button>
                                <button class="btn btn-secondary" onclick="openLoginLocationsModal()">
                                    <i class="fas fa-map-marker-alt"></i> Login Locations
                                </button>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 16px; color: var(--heading);">Access Management</h4>
                            <div class="d-flex flex-column gap-12">
                                <button class="btn btn-secondary" onclick="openAccessModal()">
                                    <i class="fas fa-user-shield"></i> Role Permissions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- CREATE USER MODAL -->
    <div id="createUserModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Create New User</div>
                <button class="modal-close" onclick="closeCreateUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createUserForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-input" placeholder="John Doe" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" placeholder="john@example.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-input" placeholder="+1234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" class="form-select">
                                <option value="">Select Employee (Optional)</option>
                                <!-- Will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <!-- Will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-input" placeholder="Enter password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-input" accept="image/*" onchange="previewImage(this)">
                            <div id="imagePreview" style="margin-top: 10px; display: none; position: relative; width: 80px;">
                                <img id="preview" src="" alt="Preview" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-default);">
                                <button type="button" onclick="removeImage()" style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; border-radius: 50%; background: var(--error); color: white; border: none; cursor: pointer; font-size: 12px;">&times;</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <select name="language_code" class="form-select">
                                <option value="en" selected>English</option>
                                <option value="es">Spanish</option>
                                <option value="fr">French</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-select">
                                <option value="Asia/Karachi" selected>Asia/Karachi</option>
                                <option value="Asia/Lahore">Asia/Lahore</option>
                                <option value="Asia/Shanghai">Asia/Shanghai</option>
                                <option value="Asia/Beijing">Asia/Beijing</option>
                                <option value="Asia/Tokyo">Asia/Tokyo</option>
                                <option value="Asia/Dubai">Asia/Dubai</option>
                                <option value="Europe/London">Europe/London</option>
                                <option value="Europe/Paris">Europe/Paris</option>
                                <option value="Europe/Berlin">Europe/Berlin</option>
                                <option value="America/New_York">America/New_York</option>
                                <option value="America/Chicago">America/Chicago</option>
                                <option value="America/Los_Angeles">America/Los_Angeles</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="form-label">Branch Access</label>
                            <div style="position: relative;">
                                <input type="text" id="createBranchSearch" class="form-input" placeholder="Search branches..." autocomplete="off">
                                <div id="createBranchDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--surface-card); border:1px solid var(--border-default); border-radius:var(--radius); max-height:200px; overflow-y:auto; z-index:1050; box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
                            </div>
                            <div id="createSelectedBranches" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>
                            <input type="hidden" id="createBranchIds" value="">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
                <button class="btn btn-primary" onclick="createUser()">Create User</button>
            </div>
        </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div id="editUserModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Edit User</div>
                <button class="modal-close" onclick="closeEditUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Content will be populated by JS -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveUser()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ACCESS MANAGEMENT MODAL -->
    <div id="accessModal" class="modal-overlay">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header">
                <div class="modal-title">Access Management</div>
                <button class="modal-close" onclick="closeAccessModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Role</label>
                    <select class="form-select" id="roleSelect" onchange="loadRolePermissions()">
                        <option value="">Select a role</option>
                    </select>
                </div>
                <div id="permissionsTree" style="margin-top: 20px;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAccessModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveAccessSettings()">Save Permissions</button>
            </div>
        </div>
    </div>

    <!-- FORMAT DATABASE MODAL -->
    <div id="formatModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Format Database</div>
                <button class="modal-close" onclick="closeFormatModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 48px; color: var(--warning); margin-bottom: 16px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 style="margin-bottom: 12px; color: var(--heading);">Warning: Irreversible Action</h3>
                    <p class="text-muted" style="margin-bottom: 24px;">
                        This will permanently delete all data from the database. This action cannot be undone.
                        Please ensure you have a recent backup before proceeding.
                    </p>
                    <div class="form-group">
                        <label class="form-label">Type "CONFIRM" to proceed</label>
                        <input type="text" class="form-input" id="confirmFormat" placeholder="CONFIRM">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeFormatModal()">Cancel</button>
                <button class="btn btn-error" onclick="formatDatabase()" id="formatBtn" disabled>Format
                    Database</button>
            </div>
        </div>
    </div>

    <!-- CREATE ROLE MODAL -->
    <div id="createRoleModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Create New Role</div>
                <button class="modal-close" onclick="closeCreateRoleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createRoleForm">
                    <div class="form-group">
                        <label class="form-label">Role Name</label>
                        <input type="text" name="name" class="form-input" placeholder="Manager" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" placeholder="Role description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCreateRoleModal()">Cancel</button>
                <button class="btn btn-primary" onclick="createRole()">Create Role</button>
            </div>
        </div>
    </div>

    <!-- EDIT ROLE MODAL -->
    <div id="editRoleModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Edit Role</div>
                <button class="modal-close" onclick="closeEditRoleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Content will be populated by JS -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditRoleModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveRole()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- BACKUP DATABASE MODAL -->
    <div id="backupModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Backup Database</div>
                <button class="modal-close" onclick="closeBackupModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Backup Name</label>
                    <input type="text" class="form-input" id="backupName" placeholder="backup_2023_10_15"
                        value="backup_">
                </div>
                <div class="form-group">
                    <label class="form-label">Backup Location</label>
                    <select class="form-select">
                        <option value="external">External Drive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeBackupModal()">Cancel</button>
                <button class="btn btn-primary" onclick="createBackup()">Create Backup</button>
            </div>
        </div>
    </div>

    <!-- RESTORE DATABASE MODAL -->
    <div id="restoreModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Restore Database</div>
                <button class="modal-close" onclick="closeRestoreModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 48px; color: var(--warning); margin-bottom: 16px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 style="margin-bottom: 12px; color: var(--heading);">Warning: Data Will Be Overwritten</h3>
                    <p class="text-muted" style="margin-bottom: 24px;">
                        Restoring a backup will replace all current data with the backup data. This action cannot be undone.
                    </p>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Backup File (.sql)</label>
                    <input type="file" class="form-input" id="restoreFile" accept=".sql">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeRestoreModal()">Cancel</button>
                <button class="btn btn-warning" onclick="restoreDatabase()">Restore Database</button>
            </div>
        </div>
    </div>

    <!-- LOGIN HISTORY MODAL -->
    <div id="loginHistoryModal" class="modal-overlay">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <div class="modal-title">Login History</div>
                <button class="modal-close" onclick="closeLoginHistoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Login Time</th>
                                <th>IP Address</th>
                                <th>Device</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="loginHistoryBody">
                            <!-- Will be populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeLoginHistoryModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- FAILED LOGINS MODAL -->
    <div id="failedLoginsModal" class="modal-overlay">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <div class="modal-title">Failed Login Attempts</div>
                <button class="modal-close" onclick="closeFailedLoginsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Attempt Time</th>
                                <th>IP Address</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody id="failedLoginsBody">
                            <!-- Will be populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeFailedLoginsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- LOGIN LOCATIONS MODAL -->
    <div id="loginLocationsModal" class="modal-overlay">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <div class="modal-title">Login Locations</div>
                <button class="modal-close" onclick="closeLoginLocationsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Location</th>
                                <th>IP Address</th>
                                <th>Last Login</th>
                                <th>Login Count</th>
                            </tr>
                        </thead>
                        <tbody id="loginLocationsBody">
                            <!-- Will be populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeLoginLocationsModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/admin_panel/panel.js?v=<?php echo time(); ?>"></script>
</body>

</html>