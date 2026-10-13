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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Lists Management | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/master_setup/rate_list/list-list.css">
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Rate Lists Management</h1>
            <div class="page-actions">
                <button class="btn btn-secondary" id="filterBtn">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button class="btn btn-primary" id="addRateListBtn">
                    <i class="fas fa-plus"></i> New Rate List
                </button>
            </div>
        </div>
        
        <!-- Filter and Search Card -->
        <div class="card">
            <div class="filter-bar">
                <div class="search-container">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search rate lists by code, name, or customer...">
                    <i class="fas fa-search search-icon"></i>
                </div>
                
                <div class="filter-options">
                    <select class="btn btn-secondary" id="statusFilter" style="padding: 0 12px;">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                        <option value="expired">Expired</option>
                    </select>
                    
                    <select class="btn btn-secondary" id="dateFilter" style="padding: 0 12px;">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                    </select>
                </div>
            </div>
            
            <!-- Rate Lists Table -->
            <div class="table-container">
                <table class="data-table" id="rateListsTable">
                    <thead>
                        <tr>
                            <th style="width: 100px;">List Code</th>
                            <th>List Name</th>
                            <th style="width: 180px;">Customers</th>
                            <th style="width: 100px;">Items</th>
                            <th style="width: 120px;">Created Date</th>
                            <th style="width: 120px;">Valid Until</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
                
                <!-- Empty State -->
                <div id="emptyState" class="empty-state" style="display: none;">
                    <div class="empty-state-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="empty-state-title">No rate lists found</div>
                    <p>Try adjusting your search or filter criteria</p>
                    <button class="btn btn-ghost" id="clearFiltersBtn" style="margin-top: 16px;">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">
                    Showing 0 of 0 rate lists
                </div>
                <div class="pagination-controls">
                    <button class="page-btn" id="prevPageBtn" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn" id="nextPageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Rate List Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Rate List Details</h2>
                <button class="modal-close" id="closeViewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-content" id="viewModalContent">
                <!-- Modal content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
                <button class="modal-close" id="closeDeleteModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-content">
                <p>Are you sure you want to delete this rate list? This action cannot be undone.</p>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button class="btn btn-secondary" id="cancelDeleteBtn" style="flex: 1;">
                        Cancel
                    </button>
                    <button class="btn" id="confirmDeleteBtn" style="flex: 1; background-color: var(--error); color: white;">
                        Delete Rate List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/master_setup/rate_list/list-list.js"></script>
</body>
</html>