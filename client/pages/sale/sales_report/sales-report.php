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
    <title>LedgerOne ERP | Sales Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../../../assets/css/sale/sales_report/sales-report.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Sales Reports Dashboard</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary" id="refresh-btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Date Filter -->
        <div class="date-filter">
            <div class="filter-group">
                <label for="date-from">Date From</label>
                <input type="date" id="date-from" class="form-input" value="2024-01-01">
            </div>
            <div class="filter-group">
                <label for="date-to">Date To</label>
                <input type="date" id="date-to" class="form-input" value="2024-03-31">
            </div>
            <div class="filter-group">
                <label for="company">Company</label>
                <select id="company" class="form-input">
                    <option value="">All Companies</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="project">Project</label>
                <select id="project" class="form-input">
                    <option value="">All Projects</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="report-type">Report Type</label>
                <select id="report-type" class="form-input">
                    <option value="all">All Reports</option>
                    <option value="officer">Sales Officer Wise</option>
                    <option value="product">Product Wise</option>
                    <option value="branch">Branch Wise</option>
                    <option value="territory">Territory Wise</option>
                    <option value="invoice">Invoice Wise</option>
                    <option value="vendor">Vendor Wise</option>
                    <option value="customer">Customer Wise</option>
                    <option value="supplier_man">Supplier Man Wise</option>
                    <option value="category">Category Wise</option>
                </select>
            </div>
            <div class="filter-group">
                <button class="btn btn-primary" id="apply-filter">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="reports-grid">
            <div class="report-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Sales</div>
                        <div class="card-value">$0</div>
                    </div>
                    <div class="card-icon icon-meter">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            
            <div class="report-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Customers</div>
                        <div class="card-value">0</div>
                    </div>
                    <div class="card-icon icon-product">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="report-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Transactions</div>
                        <div class="card-value">0</div>
                    </div>
                    <div class="card-icon icon-branch">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
            
            <div class="report-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Average Sale</div>
                        <div class="card-value">$0</div>
                    </div>
                    <div class="card-icon icon-category">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Sales Trend (Last 6 Months)</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Sales Distribution by Category</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Top 5 Sales Officers</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="topOfficersChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Top 5 Products</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Top 5 Vendors</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="topVendorsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Branch Sales Comparison</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="branchComparisonChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Monthly Sales Comparison</div>
                    <button class="btn btn-ghost">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="chart-container">
                    <canvas id="monthlyComparisonChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Sales Officer Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Sales Officer Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-officer" class="form-input" placeholder="Search by Employee ID or Officer Name..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Officer Name</th>
                            <th>Total Invoices</th>
                            <th>Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="officerTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Supplier Man Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Supplier Man Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-supplier-man" class="form-input" placeholder="Search by Employee ID or Supplier Man Name..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Supplier Man Name</th>
                            <th>Total Invoices</th>
                            <th>Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="supplierManTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Product Wise Sales Table -->
      <div class="table-card">
    <div class="table-title">Product Wise Sales Details</div>
    <div style="margin-bottom: 16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <input type="text" id="search-product" class="form-input" placeholder="Search by Product ID, Name or Category..." style="max-width: 400px;">
        <div style="position:relative;">
            <button type="button" class="btn btn-secondary" id="product-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            <div id="product-columns-menu" style="display:none; position:absolute; top:110%; left:0; background:#fff; border:1px solid #e1e6ee; border-radius:8px; padding:10px; box-shadow:0 4px 14px rgba(0,0,0,0.1); z-index:20; min-width:190px;"></div>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th data-col="product_id">Product ID</th>
                    <th data-col="product_name">Product Name</th>
                    <th data-col="category">Category</th>
                    <th data-col="batch_no">Batch No</th>
                    <th data-col="expiry_date">Expiry Date</th>
                    <th data-col="quantity_sold">Quantity Sold</th>
                    <th data-col="revenue">Revenue (<?php echo $currency_symbol; ?>)</th>
                    <th data-col="growth">Growth</th>
                </tr>
            </thead>
            <tbody id="productTableBody"></tbody>
        </table>
    </div>
</div>
        
        <!-- Branch Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Branch Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-branch" class="form-input" placeholder="Search by Branch ID or Name..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Branch ID</th>
                            <th>Branch Name</th>
                            <th>Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th>Transactions</th>
                            <th>Avg Sale (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="branchTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Category Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Category Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-category" class="form-input" placeholder="Search by Category..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total Revenue (<?php echo $currency_symbol; ?>)</th>
                            <th>Percentage</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Territory Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Territory Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-territory" class="form-input" placeholder="Search by Country, Region, City, Zone or Area..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Region</th>
                            <th>City</th>
                            <th>Zone</th>
                            <th>Area</th>
                            <th>Total Invoices</th>
                            <th>Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="territoryTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Invoice Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Invoice Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-invoice" class="form-input" placeholder="Search by Invoice #, Customer or Officer..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Sales Officer</th>
                            <th>Total Bill (<?php echo $currency_symbol; ?>)</th>
                            <th>Discount (<?php echo $currency_symbol; ?>)</th>
                            <th>Net Amount (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Vendor Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Vendor Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-vendor" class="form-input" placeholder="Search by Vendor ID or Name..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Vendor ID</th>
                            <th>Vendor Name</th>
                            <th>Total Invoices</th>
                            <th>Total Quantity</th>
                            <th>Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="vendorTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Customer Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Customer Wise Sales Details</div>
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-customer" class="form-input" placeholder="Search by Customer ID or Name..." style="max-width: 400px;">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Customer Name</th>
                            <th>Total Invoices</th>
                            <th>Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>LedgerOne ERP Sales Reports • Data as of <span id="current-date"></span> • Confidential</p>
        </div>
    </div>
    
    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/sale/sales_report/sales-report.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/sale/sales_report/sales-report.js') ?>"></script>
</body>
</html>