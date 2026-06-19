<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Projects - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/master_setup/project_setup/project.css">
</head>
<body>
<div class="container">
    <header class="page-header">
        <h1 class="page-title">Projects</h1>
        <p class="page-description">Manage all projects, teams and timelines.</p>
    </header>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Projects</h2>
            <div class="card-actions">
                <a href="project-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Project</a>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name, code, city...">
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <select id="statusFilter" style="height:38px;width:140px;font-size:13px;">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="on_hold">On Hold</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <div class="table-wrapper">
            <table id="projectTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Project Name</th>
                        <th>City</th>
                        <th>Manager</th>
                        <th>Team Leader</th>
                        <th>Team</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="projectTableBody">
                    <tr><td colspan="11" class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="paginationContainer"></div>
    </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Project Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewModalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
            <button class="btn btn-primary" id="viewEditBtn"><i class="fas fa-edit"></i> Edit</button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Project</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:15px;color:var(--body);">Are you sure you want to delete <strong id="deleteProjectName"></strong>? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="../../../assets/js/master_setup/project_setup/project-list.js"></script>
</body>
</html>
