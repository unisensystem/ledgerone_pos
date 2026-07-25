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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inward Gatepass List - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/inward_gatepass/gatepass-list.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="title-section">
                <h1>Inward Gatepass List</h1>
                <p>View and manage all inward gatepass entries</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn btn-secondary" id="refresh-btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" id="new-gatepass-btn">
                    <i class="fas fa-plus"></i> New Gatepass
                </button>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="search-filter-bar">
            <div class="search-box">
<input type="text" id="search-input" placeholder="Search by IGP code, supplier, or product..." value="<?= htmlspecialchars($initialSearch) ?>">
                <i class="fas fa-search search-icon"></i>
            </div>

            <div class="filter-group">
                <input type="date" class="filter-select" id="date-from">
                <input type="date" class="filter-select" id="date-to">
                <select class="filter-select" id="company-filter">
                    <option value="">All Companies</option>
                </select>

                <button type="button" class="btn btn-ghost" id="clear-filters-btn">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <table class="data-table" id="gatepass-table">
                <thead>
                    <tr>
                        <th width="120">IGP Code</th>
                        <th width="100">Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th width="120">Items</th>
                        <th width="100">Total Qty</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    <!-- Table rows will be populated by JavaScript -->
                </tbody>
            </table>

            <!-- Empty State -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <i class="fas fa-clipboard-list"></i>
                <h3>No gatepass entries found</h3>
                <p>Try adjusting your search or filters, or create a new gatepass entry.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <div class="pagination-info" id="pagination-info">
                Showing 0 of 0 entries
            </div>
            <div class="pagination-controls">
                <button class="page-btn" id="first-page" disabled>
                    <i class="fas fa-angle-double-left"></i>
                </button>
                <button class="page-btn" id="prev-page" disabled>
                    <i class="fas fa-angle-left"></i>
                </button>

                <span style="margin: 0 8px; color: var(--subtext); font-size: 14px;">Page</span>
                <input type="number" id="page-input" min="1" value="1"
                    style="width: 50px; height: 36px; text-align: center; border: 1px solid var(--border-default); border-radius: var(--radius-sm);">
                <span style="margin: 0 8px; color: var(--subtext); font-size: 14px;" id="total-pages">of 1</span>

                <button class="page-btn" id="next-page" disabled>
                    <i class="fas fa-angle-right"></i>
                </button>
                <button class="page-btn" id="last-page" disabled>
                    <i class="fas fa-angle-double-right"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/inventory/inward_gatepass/gatepass-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
</body>

</html>