<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine Setup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/machine_setup/list.css">
</head>
<body>
    <div class="main-content">
        <div class="list-container">
            <div id="successMessage" style="display:none;" class="success-message">
                <i class="las la-check-circle"></i>
                <span>Machine saved successfully!</span>
            </div>
            <div class="card">
                <div class="list-header">
                    <h2>Machine Setup</h2>
                    <button class="btn btn-primary" onclick="openAddModal()"><i class="las la-plus"></i> Add Machine</button>
                </div>

                <div class="filter-bar">
                    <div class="search-wrapper">
                        <i class="las la-search"></i>
                        <input type="search" placeholder="Search machines..." id="searchInput">
                    </div>
                    <div class="filter-group">
                        <select class="filter-select" id="statusFilter">
                            <option value="all">All status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <button class="btn btn-secondary" id="resetFiltersBtn"><i class="las la-redo-alt"></i> Reset</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Machine Name</th>
                                <th>Branch</th>
                                <th>Capacity/Speed</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="6" style="text-align:center; padding:32px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="machineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Machine</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="machineId">
                <div class="field-group">
                    <label class="form-label">Code <span style="color:#E34F4F;">*</span></label>
                    <input type="text" class="form-control" id="machineCode" readonly required>
                    <div class="helper-text">Auto-generated</div>
                </div>
                <div class="field-group">
                    <label class="form-label">Machine Name <span style="color:#E34F4F;">*</span></label>
                    <input type="text" class="form-control" id="machineName" required>
                </div>
                <div class="field-group">
                    <label class="form-label">Branch <span style="color:#E34F4F;">*</span></label>
                    <select class="form-control" id="branchId" required>
                        <option value="">Select branch</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="form-label">Capacity / Speed</label>
                    <input type="text" class="form-control" id="capacitySpeed" placeholder="e.g., 100 units/hour">
                </div>
                <div class="field-group">
                    <label class="form-label">Notes / Description</label>
                    <textarea class="form-control" id="notes" rows="3"></textarea>
                </div>
                <div class="field-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="isActive" checked>
                        <label for="isActive">Active</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveMachine()">Save Machine</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Machine Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
        </div>
    </div>

    <script src="../../../assets/js/manufacturing/machine_setup/list.js"></script>
</body>
</html>
