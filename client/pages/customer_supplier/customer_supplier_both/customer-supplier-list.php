<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Customer + Supplier (Both) List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customers/customer-list.css">
</head>
<body class="light-mode">
    <div class="container">
        <div class="header">
            <div class="header-actions">
                <a href="customer-supplier-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Customer + Supplier
                </a>
            </div>
        </div>

        <div class="page-title">
            <h1>
                <i class="fas fa-user-friends"></i>
                Customer + Supplier (Both) Management
            </h1>
            <div class="pagination-info" id="paginationInfo">Showing 0 records</div>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-value" id="statTotal">0</div>
                <div class="stat-label">Total (Both)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value" id="statActive">0</div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-value" id="statBlacklisted">0</div>
                <div class="stat-label">Blacklisted</div>
            </div>
        </div>

        <div class="card">
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name, code, phone, email...">
                </div>
                <div class="filter-options">
                    <select class="filter-select" id="rowsPerPage">
                        <option value="10">Show 10</option>
                        <option value="25">Show 25</option>
                        <option value="50">Show 50</option>
                        <option value="100">Show 100</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Sr#</th>
                            <th>Customer Code</th>
                            <th>Supplier Code</th>
                            <th>Party Name</th>
                            <th>Company</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Customer Bal</th>
                            <th>Supplier Bal</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bothTable">
                        <tr><td colspan="11" style="text-align:center; padding:24px;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="tableInfo">Showing 0 records</div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>
                    <button class="pagination-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> View Customer + Supplier</h3>
                <button class="modal-close" id="viewModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-grid" id="viewFieldsGrid"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="viewModalCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <div class="notification" id="notification">
        <div class="notification-icon"><i class="fas fa-check"></i></div>
        <div class="notification-content">
            <div class="notification-title" id="notificationTitle">Success</div>
            <div class="notification-message" id="notificationMessage">Done</div>
        </div>
        <button class="notification-close" id="notificationClose"><i class="fas fa-times"></i></button>
    </div>

    <script src="../../../assets/js/customer_supplier/customer_supplier_both/customer-supplier-list.js?v=<?php echo time(); ?>"></script>
</body>
</html>
