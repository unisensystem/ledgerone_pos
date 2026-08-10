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
$initialSearch = $_GET['search'] ?? '';

// Get base currency symbol
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Stock Adjustments List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_adjustment/adjustment-list.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Stock Adjustments</h1>
            <div class="header-actions">
                <button class="btn btn-primary" id="newAdjustmentBtn">
                    <i class="fas fa-plus"></i> New Adjustment
                </button>
            </div>
        </div>
        
        <!-- Action Bar -->
        <div class="action-bar">
            <div class="filters">
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="posted">Posted</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Type</label>
                    <select class="filter-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="increase">Increase</option>
                        <option value="decrease">Decrease</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Company</label>
                    <select class="filter-select" id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date Range</label>
                    <input type="date" class="filter-input" id="dateFromFilter">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <input type="date" class="filter-input" id="dateToFilter">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button class="btn btn-secondary" id="applyFiltersBtn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button class="btn btn-ghost" id="clearFiltersBtn">
                        Clear All
                    </button>
                </div>
            </div>
            
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
<input type="text" class="search-input" id="searchInput" placeholder="Search by code, branch, or reason..." value="<?= htmlspecialchars($initialSearch) ?>">
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Total Adjustments</div>
                    <div class="stat-value" id="totalAdjustments">0</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon increase">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Increase Adjustments</div>
                    <div class="stat-value" id="increaseAdjustments">0</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon decrease">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Decrease Adjustments</div>
                    <div class="stat-value" id="decreaseAdjustments">0</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Pending</div>
                    <div class="stat-value" id="pendingAdjustments">0</div>
                </div>
            </div>
        </div>
        
        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Recent Adjustments</h2>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="adjustmentsTable">
                    <thead>
                        <tr>
                            <th data-sort="code">Adjustment Code <span class="sort-icon">↕</span></th>
                            <th data-sort="date">Date <span class="sort-icon">↕</span></th>
                            <th data-sort="branch">Branch <span class="sort-icon">↕</span></th>
                            <th data-sort="type">Type <span class="sort-icon">↕</span></th>
                            <th data-sort="items">Items <span class="sort-icon">↕</span></th>
                            <th data-sort="total">Total Value <span class="sort-icon">↕</span></th>
                            <th data-sort="status">Status <span class="sort-icon">↕</span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Empty State -->
            <div class="empty-state" id="emptyState">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No adjustments found</h3>
                <p>Try adjusting your filters or create a new adjustment.</p>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">
                    Showing 0 of 0 adjustments
                </div>
                <div class="pagination-controls">
                    <button class="page-btn" id="firstPageBtn" disabled>
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button class="page-btn" id="prevPageBtn" disabled>
                        <i class="fas fa-angle-left"></i>
                    </button>
                    
                    <div id="pageNumbers">
                        <!-- Page numbers will be generated by JavaScript -->
                    </div>
                    
                    <button class="page-btn" id="nextPageBtn" disabled>
                        <i class="fas fa-angle-right"></i>
                    </button>
                    <button class="page-btn" id="lastPageBtn" disabled>
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Details Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Adjustment Details</h3>
                <button class="modal-close" id="closeViewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="adjustment-details" id="modalDetails">
                    <!-- Details will be populated by JavaScript -->
                </div>
                
                <div class="modal-items">
                    <h4 class="modal-items-title">Adjustment Items</h4>
                    <table class="modal-table" id="modalItemsTable">
                        <thead>
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Unit</th>
                                <th>Quantity</th>
                                <th>Rate</th>
                                <th>Gross</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsBody">
                            <!-- Items will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="closeModalBtn">Close</button>
                <button class="btn btn-primary" id="printDetailsBtn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="modal-close" id="closeDeleteModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete adjustment <strong id="deleteAdjustmentCode"></strong>?</p>
                <p class="detail-value" style="color: var(--error); margin-top: var(--spacing-md);">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-primary" id="confirmDeleteBtn" style="background-color: var(--error);">
                    <i class="fas fa-trash"></i> Delete Adjustment
                </button>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="toast-message">Operation completed successfully!</div>
    </div>

  <script src="../../../assets/js/inventory/stock_adjustment/adjustment-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
<script>
    window.currencySymbol = '<?php echo $currency_symbol; ?>';
</script>
</body>
</html>