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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Sale Returns</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/sale_return/return-list.css">
    <style>
        /* Searchable customer dropdown */
        .searchable-select { position: relative; width: 100%; }
        .searchable-select-input { width: 100%; box-sizing: border-box; }
        .searchable-select-list {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            max-height: 240px;
            overflow-y: auto;
            background: var(--card-bg, #fff);
            color: var(--text-color, #333);
            border: 1px solid rgba(127, 127, 127, 0.35);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-top: 2px;
        }
        .searchable-select-item,
        .searchable-select-empty { padding: 8px 12px; font-size: 14px; }
        .searchable-select-item { cursor: pointer; }
        .searchable-select-item:hover { background: rgba(127, 127, 127, 0.15); }
        .searchable-select-item.selected { font-weight: 600; background: rgba(67, 97, 238, 0.12); }
        .searchable-select-empty { opacity: 0.6; }

        /* Summary boxes */
        .summary-boxes { display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap; }
        .summary-box {
            flex: 1;
            min-width: 200px;
            padding: 14px 18px;
            border: 1px solid rgba(127, 127, 127, 0.25);
            border-radius: 8px;
            background: rgba(127, 127, 127, 0.08);
        }
        .summary-box-label { font-size: 13px; opacity: 0.75; margin-bottom: 4px; }
        .summary-box-value { font-size: 22px; font-weight: 700; }
    </style>
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Sale Returns</h1>
        </div>

        <div class="card">
            <h2 class="card-title">Filters & Search</h2>
            <div class="filters">
                <div class="form-group">
                    <label for="dateFrom">Date From</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="form-group">
                    <label for="dateTo">Date To</label>
                    <input type="date" id="dateTo">
                </div>
                <div class="form-group">
                    <label for="companyFilter">Company</label>
                    <select id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="customerFilter">Customer</label>
                    <select id="customerFilter">
                        <option value="">All Customers</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="Posted">Posted</option>
                        <option value="Draft">Draft</option>
                        <option value="">All Status</option>
                    </select>
                </div>
            </div>

            <div class="actions" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by invoice number, customer..." value="<?= htmlspecialchars($initialSearch) ?>">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label for="pageSizeSelect" style="margin-right: 6px;">Show</label>
                    <select id="pageSizeSelect">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div style="flex: 1;"></div>
                <button class="btn btn-primary" id="applyFiltersBtn" title="Apply filters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button class="btn btn-secondary" id="refreshBtn" title="Reset all filters">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button class="btn btn-primary" id="newInvoiceBtn">
                    <i class="fas fa-plus"></i> New Return
                </button>
            </div>

            <div class="summary-boxes">
                <div class="summary-box">
                    <div class="summary-box-label">Total Invoices (Filtered)</div>
                    <div class="summary-box-value" id="summaryInvoiceCount">0</div>
                </div>
                <div class="summary-box">
                    <div class="summary-box-label">Total Amount</div>
                    <div class="summary-box-value" id="summaryTotalAmount">0.00</div>
                </div>
            </div>
            
            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="12%">Return No</th>
                            <th width="15%">Date</th>
                            <th width="25%">Customer</th>
                            <th width="12%">Items</th>
                            <th width="15%">Total Amount</th>
                            <th width="21%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">
                    Showing 0 to 0 of 0 entries
                </div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Dynamic pagination buttons will be generated here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h3 class="modal-title">Confirm Delete</h3>
            <p>Are you sure you want to delete this sale return? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

<script src="../../../assets/js/sale/sale_return/return-list.js"></script>
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
</body>
</html>