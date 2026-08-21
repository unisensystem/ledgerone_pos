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
    <title>LedgerOne ERP - Purchase Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_reports/purchase-reports.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-title">
                <h1>Purchase Reports</h1>
                <p>Analyze and track purchasing activities across different dimensions</p>
            </div>
            <div class="export-dropdown">
                <button class="btn btn-primary" id="exportBtn">
                    <i class="fas fa-download"></i> Export All Reports <i class="fas fa-chevron-down"></i>
                </button>
                <div class="export-menu" id="exportMenu">
                    <button class="export-option" data-type="print"><i class="fas fa-print"></i> Print Report</button>
                    <button class="export-option" data-type="excel"><i class="fas fa-file-excel"></i> Export to Excel</button>
                    <button class="export-option" data-type="json"><i class="fas fa-file-code"></i> Export JSON</button>
                </div>
            </div>
        </header>

        <!-- Controls -->
        <section class="controls">
            <div class="date-filter">
                <label class="filter-label">Date Range</label>
                <select class="filter-select" id="dateRange">
                    <option value="last7">Last 7 Days</option>
                    <option value="last30" selected>Last 30 Days</option>
                    <option value="last90">Last 90 Days</option>
                    <option value="ytd">Year to Date</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="custom-date-range" id="customDateRange" style="display: none;">
                <div>
                    <label class="filter-label">Start Date</label>
                    <input type="date" class="filter-input" id="startDate">
                </div>
                <div>
                    <label class="filter-label">End Date</label>
                    <input type="date" class="filter-input" id="endDate">
                </div>
            </div>
            <div class="report-type">
                <label class="filter-label">Company</label>
                <select class="filter-select" id="company">
                    <option value="">All Companies</option>
                </select>
            </div>
            <div class="report-type">
                <label class="filter-label">Project</label>
                <select class="filter-select" id="project">
                    <option value="">All Projects</option>
                </select>
            </div>
            <div class="report-type">
                <label class="filter-label">Supplier</label>
                <select class="filter-select" id="supplier">
                    <option value="">All Suppliers</option>
                </select>
            </div>
            <div class="report-type">
                <label class="filter-label">Report Type</label>
                <select class="filter-select" id="reportType">
                    <option value="all" selected>All Reports</option>
                    <option value="item">Item-wise Report</option>
                    <option value="invoice">Invoice-wise Report</option>
                    <option value="category">Category-wise Report</option>
                    <option value="supplier">Supplier-wise Report</option>
                </select>
            </div>
            <div class="search-filter">
                <label class="filter-label">Search</label>
                <input type="text" class="filter-input" id="searchInput" placeholder="Search...">
            </div>
            <div class="filter-actions">
                <button class="btn btn-secondary" id="applyFilters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button class="btn btn-ghost" id="resetFilters">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </section>

        <!-- Reports Summary Cards -->
        <section class="reports-grid">
            <!-- Item-wise Report Card -->
            <div class="report-card" id="itemReportCard">
                <div class="report-header">
                    <div class="report-title">
                        <h3>Item-wise Report</h3>
                        <p>Analysis by purchased items</p>
                    </div>
                    <div class="report-icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Items Purchased</span>
                        <span class="stat-value">142</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Quantity</span>
                        <span class="stat-value">1,850 units</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Avg. Unit Price</span>
                        <span class="stat-value">$45.20</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Value</span>
                        <span class="stat-value">$83,654</span>
                    </div>
                </div>
                <div class="report-actions">
                    <button class="btn btn-ghost btn-sm view-details" data-report="item">
                        <i class="fas fa-chart-bar"></i> View Details
                    </button>
                </div>
            </div>

            <!-- Invoice-wise Report Card -->
            <div class="report-card" id="invoiceReportCard">
                <div class="report-header">
                    <div class="report-title">
                        <h3>Invoice-wise Report</h3>
                        <p>Analysis by purchase invoices</p>
                    </div>
                    <div class="report-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                </div>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Invoices</span>
                        <span class="stat-value">38</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Avg. Invoice Value</span>
                        <span class="stat-value">$2,201</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Pending Invoices</span>
                        <span class="stat-value">4</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Invoice Value</span>
                        <span class="stat-value">$83,654</span>
                    </div>
                </div>
                <div class="report-actions">
                    <button class="btn btn-ghost btn-sm view-details" data-report="invoice">
                        <i class="fas fa-chart-bar"></i> View Details
                    </button>
                </div>
            </div>

            <!-- Category-wise Report Card -->
            <div class="report-card" id="categoryReportCard">
                <div class="report-header">
                    <div class="report-title">
                        <h3>Category-wise Report</h3>
                        <p>Analysis by product categories</p>
                    </div>
                    <div class="report-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Categories</span>
                        <span class="stat-value">8</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Top Category</span>
                        <span class="stat-value">Electronics</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">% of Total Spend</span>
                        <span class="stat-value">32%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Category Spend</span>
                        <span class="stat-value">$26,769</span>
                    </div>
                </div>
                <div class="report-actions">
                    <button class="btn btn-ghost btn-sm view-details" data-report="category">
                        <i class="fas fa-chart-bar"></i> View Details
                    </button>
                </div>
            </div>

            <!-- Supplier-wise Report Card -->
            <div class="report-card" id="supplierReportCard">
                <div class="report-header">
                    <div class="report-title">
                        <h3>Supplier-wise Report</h3>
                        <p>Analysis by suppliers</p>
                    </div>
                    <div class="report-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Active Suppliers</span>
                        <span class="stat-value">12</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Items</span>
                        <span class="stat-value">1,850 units</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Avg. Price</span>
                        <span class="stat-value">$45.20</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Spend</span>
                        <span class="stat-value">$83,654</span>
                    </div>
                </div>
                <div class="report-actions">
                    <button class="btn btn-ghost btn-sm view-details" data-report="supplier">
                        <i class="fas fa-chart-bar"></i> View Details
                    </button>
                </div>
            </div>
        </section>

        <!-- Detailed Report Table -->
        <section class="report-table-container" id="detailedTable">
    <div class="table-header" style="display:flex; justify-content:space-between; align-items:center; position:relative;">
        <h3 id="tableTitle">Item-wise Purchase Details</h3>
        <div style="position:relative;">
            <button class="btn btn-ghost btn-sm" id="columns-btn"><i class="fas fa-columns"></i> Columns</button>
            <div id="columns-menu" style="display:none; position:absolute; right:0; top:110%; background:white; border:1px solid #e1e6ee; border-radius:6px; padding:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:10; min-width:180px;"></div>
        </div>
    </div>
    <table>
        
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Invoice Name</th>
                        <th>Category</th>
                        <th>Items</th>
                        <th>Due Date</th>
                        <th>Total Value</th>
                        <th>Supplier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Table rows will be populated by JavaScript -->
                </tbody>
            </table>
        </section>
    </div>

    <script src="../../../assets/js/purchase/purchase_reports/purchase-reports.js"></script>
</body>

</html>
