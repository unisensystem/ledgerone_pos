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
    <title>Stock Transfer List | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_transfer/transfer-list.css">
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Stock Transfers</h1>
                <p>Manage and track all stock transfers between branches</p>
            </div>
            <div class="header-actions">
                <button id="new-transfer-btn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Transfer
                </button>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">Filters</div>
                <button id="clear-filters" class="btn btn-ghost">
                    <i class="fas fa-times"></i> Clear All
                </button>
            </div>
            <div class="filters-grid">
                <!-- Search -->
                <div class="search-container">
                    <div class="form-group">
                        <label class="form-label" for="search">Search Transfers</label>
                        <div class="search-input-container">
<input type="text" id="search" class="form-input search-input" placeholder="Search by transfer code, branch..." value="<?= htmlspecialchars($initialSearch) ?>">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="form-group">
                    <label class="form-label" for="date-from">From Date</label>
                    <input type="date" id="date-from" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="date-to">To Date</label>
                    <input type="date" id="date-to" class="form-input">
                </div>

                <!-- Company Filter -->
                <div class="form-group">
                    <label class="form-label" for="company-filter">Company</label>
                    <select id="company-filter" class="form-input">
                        <option value="">All Companies</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="content-card">
            <!-- Table Container -->
            <div class="table-container">
                <table class="transfers-table">
                    <thead>
                        <tr>
                            <th width="10%">Transfer Code</th>
                            <th width="12%">Date</th>
                            <th width="15%">Company</th>
                            <th width="15%">From Branch</th>
                            <th width="15%">To Branch</th>
                            <th width="8%">Items</th>
                            <th width="10%">Total Value</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transfers-table-body">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State (hidden by default) -->
            <div id="empty-state" class="empty-state" style="display: none;">
                <i class="fas fa-exchange-alt"></i>
                <h3>No Stock Transfers Found</h3>
                <p>No stock transfers match your current filters. Try adjusting your search criteria or create a new transfer.</p>
                <button id="empty-new-transfer" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Transfer
                </button>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <div class="pagination-info" id="pagination-info">
                    Showing 0 of 0 transfers
                </div>
                <div class="pagination-controls">
                    <button id="first-page" class="pagination-btn" disabled>
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button id="prev-page" class="pagination-btn" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <!-- Page numbers will be added by JavaScript -->
                    
                    <button id="next-page" class="pagination-btn" disabled>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button id="last-page" class="pagination-btn" disabled>
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Delete Transfer</div>
                <button class="modal-close" id="close-delete-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete transfer <strong id="delete-transfer-code"></strong>?</p>
                <p class="helper-text" style="margin-top: 8px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button id="cancel-delete" class="btn btn-secondary">Cancel</button>
                <button id="confirm-delete" class="btn btn-primary" style="background-color: #E34F4F;">Delete</button>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Transfer Details</div>
                <button class="modal-close" id="close-view-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="transfer-details">
                    <!-- Details will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-view" class="btn btn-secondary">Close</button>
                <button id="print-transfer" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/inventory/stock_transfer/transfer-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
    <script>
        // Pass currency symbol to JavaScript
        window.currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
</body>
</html>