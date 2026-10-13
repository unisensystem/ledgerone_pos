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
    <title>FuelingSys - Petrol Tank Stock Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_position/worksheet-stock.css">
</head>
<body>
    <div class="container">
        <!-- Report Header -->
        <div class="header">
            <div class="title-section">
                <h1>Petrol Tank Stock Report</h1>
                <p>Stock movement tracking with supplier/customer details</p>
            </div>
            
            <div class="report-meta">
                <div class="meta-item">
                    <span class="meta-label">Report Period</span>
                    <span class="meta-value" id="reportPeriod">01 Jan 2024 - 31 Jan 2024</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Generated On</span>
                    <span class="meta-value" id="generatedDate">15 Feb 2024, 14:30</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Total Transactions</span>
                    <span class="meta-value" id="totalTransactions">45</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Current Balance</span>
                    <span class="meta-value" id="currentBalance">1,980.00 L</span>
                </div>
            </div>
        </div>

        <!-- Report Actions -->
        <div class="report-actions">
            <div class="filters">
                <select class="select-field" id="periodFilter">
                    <option value="current_month">Current Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="last_quarter">Last Quarter</option>
                    <option value="custom">Custom Period</option>
                </select>
                
                <input type="date" class="select-field" id="customFromDate" style="display: none;">
                <input type="date" class="select-field" id="customToDate" style="display: none;">
                
                <select class="select-field" id="transactionTypeFilter">
                    <option value="all">All Transactions</option>
                    <option value="in">IN Transactions Only</option>
                    <option value="out">OUT Transactions Only</option>
                </select>
                
                <button class="btn btn-secondary" id="applyFilterBtn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
            
            <div class="actions">
                <button class="btn btn-ghost" id="exportBtn">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
                <button class="btn btn-primary" id="printReportBtn">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Report Table -->
        <div class="report-container">
            <table class="report-table" id="reportTable">
                <thead>
                    <tr>
                        <th class="date-col">Date</th>
                        <th class="details-col">Details (Supplier/Customer)</th>
                        <th class="in-col">IN (Liters)</th>
                        <th class="out-col">OUT (Liters)</th>
                        <th class="balance-col">Balance</th>
                    </tr>
                </thead>
                <tbody id="reportBody">
                    <!-- Report data will be populated by JavaScript -->
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="pagination">
                <button class="btn btn-secondary" id="prevPageBtn" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="pagination-info" id="paginationInfo">Page 1 of 3</span>
                <button class="btn btn-secondary" id="nextPageBtn">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <!-- Summary Section -->
            <div class="summary-section">
                <div class="summary-title">Stock Summary</div>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="label">Opening Stock</div>
                        <div class="value" id="summaryOpening">1,250.50 L</div>
                        <div class="subtext">At beginning of period</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Total IN</div>
                        <div class="value" id="summaryTotalIn">4,850.25 L</div>
                        <div class="subtext">From Suppliers</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Total OUT</div>
                        <div class="value" id="summaryTotalOut">4,120.75 L</div>
                        <div class="subtext">To Customers</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Closing Stock</div>
                        <div class="value" id="summaryClosing">1,980.00 L</div>
                        <div class="subtext">At end of period</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Net Movement</div>
                        <div class="value" id="summaryNet">+729.50 L</div>
                        <div class="subtext">IN - OUT</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Top Supplier</div>
                        <div class="value" id="topSupplier">Shell Corp</div>
                        <div class="subtext" id="supplierAmount">1,250.00 L</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Top Customer</div>
                        <div class="value" id="topCustomer">ABC Transport</div>
                        <div class="subtext" id="customerAmount">850.00 L</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hardcoded values
        window.HARDCODED_PRODUCT_ID = 4;
        window.HARDCODED_BRANCH_ID = 5;
        window.HARDCODED_UNIT_ID = 7;
        window.HARDCODED_STATION_ID = 3;
    </script>
    <script src="../../../assets/js/inventory/stock_position/worksheet-stock.js"></script>
</body>
</html>