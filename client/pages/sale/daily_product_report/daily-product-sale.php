<?php
// Path convention: pages/sales/daily_product_sale_report/index.php
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
    <title>Daily Product Sale Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Reuses the same table/card/form classes as the Stock Position module -->
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_position/stock-position.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daily Product Sale Report</h2>
                <div class="action-buttons">
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="printReport()"><i class="fas fa-print"></i> Print</a>
                            <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer">Customer</label>
                    <input type="text" class="form-control" id="customer" list="customer-list" placeholder="All Customers">
                    <datalist id="customer-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="branch">Branch</label>
                    <input type="text" class="form-control" id="branch" list="branch-list" placeholder="All Branches">
                    <datalist id="branch-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="product">Product</label>
                    <input type="text" class="form-control" id="product" list="product-list" placeholder="All Products">
                    <datalist id="product-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="from-date">From Date</label>
                    <input type="date" class="form-control" id="from-date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="to-date">To Date</label>
                    <input type="date" class="form-control" id="to-date">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                    <button class="btn btn-primary" id="applyFilters">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button class="btn btn-secondary" id="clearFilters">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sr#</th>
                            <th>Product Code</th>
                            <th>Details</th>
                            <!-- <th class="col-customer">Customer</th> -->
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="pagination-container">
                    <div class="pagination-info">
                        <span id="pagination-info">Showing 0 - 0 of 0 entries</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="btn btn-secondary" id="prev-btn" disabled>Previous</button>
                        <span id="page-info">Page 1 of 1</span>
                        <button class="btn btn-secondary" id="next-btn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

<script src="../../../assets/js/sale/daily_product_sale_report/daily-product-sale-report.js?v=<?= time() ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>