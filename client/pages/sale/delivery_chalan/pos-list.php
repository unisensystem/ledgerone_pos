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
// 👇 ADD KARO
$initialSearch = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Delivery Chalans</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_invoice/purchase-list.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Delivery Chalans</h1>
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
                    <label for="customerFilter">Customer</label>
                    <div class="searchable-dropdown">
<input type="text" id="searchInput" placeholder="Search by invoice number, customer..." value="<?= htmlspecialchars($initialSearch) ?>">
                        <div class="dropdown-options" id="customerFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="customerFilter">
                    </div>
                </div>

            </div>
            
            <div class="actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by invoice number, customer...">
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                    <button class="btn btn-primary" id="newInvoiceBtn">
                        <i class="fas fa-plus"></i> New Chalan
                    </button>
                </div>
            </div>
            
            <div class="summary-section">
                <div class="summary-card">
                    <div class="summary-item">
                        <span class="summary-label">Total Chalans</span>
                        <span class="summary-value" id="totalInvoiceCount">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Items</span>
                        <span class="summary-value" id="totalAmountSum">0</span>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="15%">Chalan No</th>
                            <th width="15%">Delivery Date</th>
                            <th width="28%">Customer</th>
                            <th width="10%">Items</th>
                            <th width="12%">Status</th>
                            <th width="15%">Actions</th>
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
            <p>Are you sure you want to delete this delivery chalan? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

<script src="../../../assets/js/sale/delivery_chalan/pos-list.js"></script>
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