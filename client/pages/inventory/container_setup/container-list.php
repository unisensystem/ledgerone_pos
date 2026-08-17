<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="tenant-id" content="<?php echo htmlspecialchars($tenant_id); ?>">
    <title>Containers - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/inventory/container_setup/container-list.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div class="page-title-section">
                <h1 class="page-title">Containers</h1>
                <p class="page-description">Manage all your containers with container codes and names.</p>
            </div>
            <div class="page-actions">
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" id="exportBtn">
                        <i class="fas fa-download icon"></i> Export
                    </button>
                    <div class="dropdown-menu" id="exportDropdown">
                        <a href="#" class="dropdown-item" onclick="printList()">
                            <i class="fas fa-print"></i> Print List
                        </a>
                        <a href="#" class="dropdown-item" onclick="exportExcel()">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </a>
                        <a href="#" class="dropdown-item" onclick="exportJSON()">
                            <i class="fas fa-file-code"></i> Export JSON
                        </a>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus icon"></i> Add Container
                </button>
            </div>
        </header>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Containers</h2>
                <div class="filters-section">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search containers..." id="searchInput">
                    </div>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table id="containersTable">
                    <thead>
                        <tr>
                            <th>Container Code</th>
                            <th>Container Name</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="containersTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 1 to 10 of 0 entries</div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevPage" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div id="pageButtons"></div>
                    <button class="pagination-btn" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Container Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Container</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form id="addContainerForm">
                <div class="form-group">
                    <label for="addContainerCode">Container Code</label>
                    <input type="text" id="addContainerCode" placeholder="Auto-generated" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small style="color: #999; font-size: 12px; margin-top: 4px; display: block;">Auto-generated code</small>
                </div>
                <div class="form-group">
                    <label for="addContainerName">Container Name <span style="color: red;">*</span></label>
                    <input type="text" id="addContainerName" placeholder="e.g., Large Box" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="addIsActive" checked> Active
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Container</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Container Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Container</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editContainerForm">
                <input type="hidden" id="editContainerId">
                <div class="form-group">
                    <label for="editContainerCode">Container Code</label>
                    <input type="text" id="editContainerCode" required>
                </div>
                <div class="form-group">
                    <label for="editContainerName">Container Name</label>
                    <input type="text" id="editContainerName" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editIsActive"> Active
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Container</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Container</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this container?</p>
                <p><strong id="deleteContainerName"></strong></p>
                <p class="warning">This action cannot be undone.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Container</button>
            </div>
        </div>
    </div>

    <!-- View Container Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Container Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="view-field">
                    <label>Container Code:</label>
                    <span id="viewContainerCode"></span>
                </div>
                <div class="view-field">
                    <label>Container Name:</label>
                    <span id="viewContainerName"></span>
                </div>
                <div class="view-field">
                    <label>Status:</label>
                    <span id="viewStatus"></span>
                </div>
                <div class="view-field">
                    <label>Created Date:</label>
                    <span id="viewCreatedDate"></span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/inventory/container_setup/container-list.js?v=<?php echo time(); ?>"></script>
</body>
</html>
