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
    <title>Branches - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/master_setup/branch_setup/branch-list.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div class="page-title-section">
                <h1 class="page-title">Branches</h1>
                <p class="page-description">Manage all your branches across different locations and types.</p>
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
                <button class="btn btn-primary" onclick="window.location.href='branch-add.php'">
                    <i class="fas fa-plus icon"></i> Add Branch
                </button>
            </div>
        </header>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Branches</h2>
                <div class="filters-section">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search branches..." id="searchInput">
                    </div>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select class="filter-select" id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                    <select class="filter-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="head_office">Head Office</option>
                        <option value="regional_office">Regional Office</option>
                        <option value="branch_office">Branch Office</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="distribution_center">Distribution Center</option>
                        <option value="retail_store">Retail Store</option>
                        <option value="service_center">Service Center</option>
                        <option value="manufacturing_unit">Manufacturing Unit</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table id="branchesTable">
                    <thead>
                        <tr>
                            <th>Branch Code</th>
                            <th>Branch Name</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="branchesTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 1 to 10 of 45 entries</div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevPage" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">4</button>
                    <button class="pagination-btn">5</button>
                    <button class="pagination-btn" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Branch</h2>
                <span class="close">&times;</span>
            </div>
            <form id="editBranchForm">
                <input type="hidden" id="editBranchId">
                <div class="form-group">
                    <label for="editBranchName">Branch Name</label>
                    <input type="text" id="editBranchName" required>
                </div>
                <div class="form-group">
                    <label for="editBranchType">Branch Type</label>
                    <select id="editBranchType" required>
                        <option value="head_office">Head Office</option>
                        <option value="regional_office">Regional Office</option>
                        <option value="branch_office">Branch Office</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="distribution_center">Distribution Center</option>
                        <option value="retail_store">Retail Store</option>
                        <option value="service_center">Service Center</option>
                        <option value="manufacturing_unit">Manufacturing Unit</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editCompany">Company</label>
                    <select id="editCompany" required>
                        <option value="">Select Company</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editParentBranch">Parent Branch</label>
                    <select id="editParentBranch">
                        <option value="">No Parent Branch</option>
                        <option value="1">Head Office - New York</option>
                        <option value="2">Regional Office - Chicago</option>
                        <option value="3">Distribution Center - Texas</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editAddress">Address</label>
                    <textarea id="editAddress"></textarea>
                </div>
                <div class="form-group">
                    <label for="editCountry">Country</label>
                    <input type="text" id="editCountry">
                </div>
                <div class="form-group">
                    <label for="editState">State/Province</label>
                    <input type="text" id="editState">
                </div>
                <div class="form-group">
                    <label for="editCity">City</label>
                    <input type="text" id="editCity">
                </div>
                <div class="form-group">
                    <label for="editZipcode">ZIP/Postal Code</label>
                    <input type="text" id="editZipcode">
                </div>
                <div class="form-group">
                    <label for="editPhone">Phone</label>
                    <input type="tel" id="editPhone">
                </div>
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail">
                </div>
                <div class="form-group">
                    <label for="editManager">Manager</label>
                    <select id="editManager">
                        <option value="">Select Manager</option>
                        <option value="1">John Smith</option>
                        <option value="2">Sarah Johnson</option>
                        <option value="3">Michael Brown</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editIsActive"> Active
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editAllowsSales"> Allows Sales
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editAllowsInventory"> Allows Inventory
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Branch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Branch</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this branch?</p>
                <p><strong id="deleteBranchName"></strong></p>
                <p class="warning">This action cannot be undone.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Branch</button>
            </div>
        </div>
    </div>

    <!-- View Branch Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Branch Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="view-field">
                    <label>Branch Code:</label>
                    <span id="viewBranchCode"></span>
                </div>
                <div class="view-field">
                    <label>Branch Name:</label>
                    <span id="viewBranchName"></span>
                </div>
                <div class="view-field">
                    <label>Branch Type:</label>
                    <span id="viewBranchType"></span>
                </div>
                <div class="view-field">
                    <label>Company:</label>
                    <span id="viewCompany"></span>
                </div>
                <div class="view-field">
                    <label>Address:</label>
                    <span id="viewAddress"></span>
                </div>
                <div class="view-field">
                    <label>Location:</label>
                    <span id="viewLocation"></span>
                </div>
                <div class="view-field">
                    <label>Phone:</label>
                    <span id="viewPhone"></span>
                </div>
                <div class="view-field">
                    <label>Email:</label>
                    <span id="viewEmail"></span>
                </div>
                <div class="view-field">
                    <label>Status:</label>
                    <span id="viewStatus"></span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/master_setup/branch_setup/branch-list.js"></script>
</body>
</html>