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
    <title>LedgerOne ERP - Purchase Invoices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_invoice/purchase-list.css?v=<?php echo time(); ?>">
    <script>
        // Force reload JS file
        const timestamp = new Date().getTime();
    </script>
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Purchase Tax Invoices</h1>
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
                    <label for="supplierFilter">Supplier</label>
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" autocomplete="off" placeholder="All Suppliers" id="supplierFilterSearch">
                        <div class="dropdown-options" id="supplierFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="supplierFilter">
                    </div>
                </div>
                <div class="form-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
            </div>
            
            <div class="actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by invoice number, supplier...">
                </div>
                <button class="btn btn-primary" id="newInvoiceBtn">
                    <i class="fas fa-plus"></i> New Invoice
                </button>
            </div>
            
            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="12%">Invoice No</th>
                            <th width="15%">Date</th>
                            <th width="25%">Supplier</th>
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
            <p>Are you sure you want to delete this purchase invoice? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/purchase/purchase_tax_invoice/purchase-list.js?v=<?php echo time(); ?>"></script>
</body>
</html>