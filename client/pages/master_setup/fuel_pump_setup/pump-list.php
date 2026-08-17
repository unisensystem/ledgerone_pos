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
    <title>FuelingSys ERP - Stations List</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/master_setup/fuel_pump_setup/pump-list.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Fueling Stations</h1>
            <div class="header-actions">
                <button class="add-btn" id="addStationBtn">
                    <i class="fas fa-plus"></i>
                    <span>Add Station</span>
                </button>
            </div>
        </div>

        <div class="card">
            <div class="filters">
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search stations...">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Branch</label>
                    <select class="filter-select" id="branchFilter">
                        <option value="">All Branches</option>
                        <option value="branch1">Downtown Station</option>
                        <option value="branch2">Westside Fuel Center</option>
                        <option value="branch3">Northgate Petroleum</option>
                        <option value="branch4">East End Fuels</option>
                        <option value="branch5">Central City Station</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Pump Type</label>
                    <select class="filter-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="regular">Regular Gasoline</option>
                        <option value="premium">Premium Gasoline</option>
                        <option value="diesel">Diesel</option>
                        <option value="cng">CNG</option>
                        <option value="electric">Electric Charger</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table class="stations-table">
                    <thead>
                        <tr>
                            <th>Pump Name / No</th>
                            <th>Parent Branch</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Last Service</th>
                            <th>Installation Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="stationsTableBody">
                        <!-- Stations will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 0-0 of 0 stations</div>
                <div class="pagination-controls">
                    <!-- Dynamic pagination buttons will be inserted here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Fuel Station</h2>
                <span class="close" id="closeModal">&times;</span>
            </div>
            <form id="editForm">
                <input type="hidden" id="editStationId">
                <div class="form-row">
                    <div class="form-group">
                        <label for="editPumpName" class="form-label required">Fuel Pump Name / No</label>
                        <input type="text" id="editPumpName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="editPumpType" class="form-label">Pump Type</label>
                        <div class="searchable-select">
                            <input type="text" id="editPumpType" class="form-input" placeholder="Search pump types..." autocomplete="off">
                            <input type="hidden" id="editPumpTypeId" name="editPumpTypeId">
                            <div class="dropdown-list" id="editPumpTypeDropdown"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editStatus" class="form-label">Status</label>
                        <select id="editStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editManufacturer" class="form-label">Manufacturer</label>
                        <input type="text" id="editManufacturer" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="editModel" class="form-label">Model</label>
                        <input type="text" id="editModel" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="editSerialNumber" class="form-label">Serial Number</label>
                        <input type="text" id="editSerialNumber" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editParentBranch" class="form-label required">Parent Branch</label>
                        <div class="searchable-select">
                            <input type="text" id="editParentBranch" class="form-input" placeholder="Search branches..." autocomplete="off" required>
                            <input type="hidden" id="editParentBranchId" name="editParentBranchId">
                            <div class="dropdown-list" id="editBranchDropdown"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editFlowRate" class="form-label">Flow Rate (L/Min)</label>
                        <input type="number" id="editFlowRate" class="form-input" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="editLocationDescription" class="form-label">Location Description</label>
                        <input type="text" id="editLocationDescription" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editInstallationDate" class="form-label">Installation Date</label>
                        <input type="date" id="editInstallationDate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="editLastService" class="form-label">Last Service Date</label>
                        <input type="date" id="editLastService" class="form-input">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Station</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Fuel Station</h2>
                <span class="close" id="closeDeleteModal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this fuel station?</p>
                <p><strong id="deleteStationName"></strong></p>
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/master_setup/fuel_pump_setup/pump-list.js"></script>
</body>
</html>