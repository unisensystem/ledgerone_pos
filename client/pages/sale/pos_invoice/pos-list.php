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
    <title>LedgerOne ERP - Sale Invoices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_invoice/purchase-list.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Sale Invoices</h1>
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
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" placeholder="Search company..." id="companyFilterSearch" autocomplete="off">
                        <div class="dropdown-options" id="companyFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="companyFilter">
                    </div>
                </div>
                <div class="form-group">
                    <label for="customerFilter">Customer</label>
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" placeholder="Search customer..." id="customerFilterSearch" autocomplete="off">
                        <div class="dropdown-options" id="customerFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="customerFilter">
                    </div>
                </div>
                <div class="form-group">
                    <label for="saleOfficerFilter">Sales Officer</label>
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" placeholder="Search sales officer..." id="saleOfficerFilterSearch" autocomplete="off">
                        <div class="dropdown-options" id="saleOfficerFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="saleOfficerFilter">
                    </div>
                </div>
                <div class="form-group">
                    <label for="supplierManFilter">Supplier Man</label>
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" placeholder="Search supplier man..." id="supplierManFilterSearch" autocomplete="off">
                        <div class="dropdown-options" id="supplierManFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="supplierManFilter">
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
<input type="text" id="searchInput" placeholder="Search by invoice number, customer..." value="<?= htmlspecialchars($initialSearch) ?>">
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                    <button class="btn btn-success" id="printSelectedBtn" style="display:none;">
                        <i class="fas fa-print"></i> Print Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-primary" id="newInvoiceBtn">
                        <i class="fas fa-plus"></i> New Invoice
                    </button>
                </div>
            </div>
            
            <div class="summary-section">
                <div class="summary-card">
                    <div class="summary-item">
                        <span class="summary-label">Total Invoices</span>
                        <span class="summary-value" id="totalInvoiceCount">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value" id="totalAmountSum">₹ 0.00</span>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="selectAllCheckbox" title="Select All"></th>
                            <th width="12%">Invoice No</th>
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
            <p>Are you sure you want to delete this sale invoice? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/sale/pos_invoice/pos-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    // Global search se aane par existing search box ko auto-trigger karo
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