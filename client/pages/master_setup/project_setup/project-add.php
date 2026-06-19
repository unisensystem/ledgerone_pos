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
    <title>New Project - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/master_setup/project_setup/project.css">
</head>
<body>
<div class="container">
    <header class="page-header">
        <h1 class="page-title">New Project</h1>
        <p class="page-description">Create a new project, assign a team and set timelines.</p>
    </header>

    <form id="projectForm" class="card">
        <div class="card-header">
            <h2 class="card-title">Project Information</h2>
            <div class="card-actions">
                <a href="project-list.php" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> All Projects</a>
            </div>
        </div>

        <div class="form-grid">

            <!-- Basic Info -->
            <div class="form-group">
                <label>Project Code</label>
                <input type="text" id="projectCode" readonly placeholder="Auto-generated">
            </div>

            <div class="form-group">
                <label for="projectName" class="required">Project Name</label>
                <div class="input-wrapper">
                    <input type="text" id="projectName" name="project_name" placeholder="Enter project name">
                    <div class="error-message" id="projectNameError">Project name is required</div>
                </div>
            </div>

            <div class="form-group">
                <label for="city" class="required">City</label>
                <div class="input-wrapper">
                    <select id="city" name="city_id">
                        <option value="">Select City</option>
                    </select>
                    <div class="error-message" id="cityError">Please select a city</div>
                </div>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active">Active</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-group">
                <label for="startDate" class="required">Start Date</label>
                <div class="input-wrapper">
                    <input type="date" id="startDate" name="start_date">
                    <div class="error-message" id="startDateError">Start date is required</div>
                </div>
            </div>

            <div class="form-group">
                <label for="endDate" class="required">End Date</label>
                <div class="input-wrapper">
                    <input type="date" id="endDate" name="end_date">
                    <div class="error-message" id="endDateError">End date is required</div>
                </div>
            </div>

            <!-- Roles -->
            <div class="form-group">
                <label>Project Manager</label>
                <div class="input-wrapper searchable-select">
                    <input type="text" id="projectManager" placeholder="Search employee..." autocomplete="off">
                    <input type="hidden" id="projectManagerId">
                    <div class="dropdown-list" id="managerDropdown"></div>
                </div>
            </div>

            <div class="form-group">
                <label>Team Leader</label>
                <div class="input-wrapper searchable-select">
                    <input type="text" id="teamLeader" placeholder="Search employee..." autocomplete="off">
                    <input type="hidden" id="teamLeaderId">
                    <div class="dropdown-list" id="leaderDropdown"></div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="form-group full-width">
                <label>Assign Team Members</label>
                <div class="team-members-container">
                    <div class="team-search-box">
                        <input type="text" id="teamSearch" placeholder="Search employees to add...">
                    </div>
                    <div class="team-list" id="teamList">
                        <div class="dropdown-empty">Loading employees...</div>
                    </div>
                </div>
                <div class="selected-tags" id="selectedTags"></div>
            </div>

            <!-- Description -->
            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Brief project description (optional)"></textarea>
            </div>

        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="resetBtn"><i class="fas fa-undo"></i> Reset</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Project</button>
        </div>
    </form>
</div>

<div class="toast-container" id="toastContainer"></div>

<script src="../../../assets/js/master_setup/project_setup/project-add.js"></script>
</body>
</html>
