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
<style>
    #round-off-toggle {
  appearance: none;
  width: 42px;
  height: 24px;
  border: 1px solid var(--border-default, #e1e6ee);
  border-radius: 999px;
  background: var(--border-default, #e1e6ee);
  cursor: pointer;
  position: relative;
  transition: background 0.2s ease;
}
#round-off-toggle::before {
  content: '';
  position: absolute;
  top: 2px; left: 2px;
  width: 18px; height: 18px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s ease;
}
#round-off-toggle:checked { background: #1F7BFF; }
#round-off-toggle:checked::before { transform: translateX(18px); }

.table-toolbar { margin-bottom: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.columns-btn { white-space: nowrap; }

.col-modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    z-index: 1000; align-items: center; justify-content: center;
}
.col-modal-overlay.open { display: flex; }
.col-modal {
    background: var(--card-bg, #fff); color: var(--text-color, #222);
    border-radius: 10px; width: 340px; max-width: 92vw; max-height: 80vh;
    display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.25);
}
.col-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid #e1e6ee;
}
.col-modal-header h3 { font-size: 16px; margin: 0; }
.col-modal-close { background: none; border: none; font-size: 22px; line-height: 1; cursor: pointer; color: inherit; }
.col-modal-body { padding: 10px 18px; overflow-y: auto; flex: 1; }
.col-modal-body label {
    display: flex; align-items: center; gap: 10px; padding: 7px 2px; font-size: 14px; cursor: pointer;
}
.col-modal-footer {
    padding: 12px 18px; border-top: 1px solid #e1e6ee;
    display: flex; justify-content: space-between; gap: 10px;
}
</style>

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
                    <option value="item_company">Item Company Wise</option>
                    <option value="category">Category Wise</option>
                </select>
            </div>
            <div class="filter-group">
                <button class="btn btn-primary" id="apply-filter">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </div>
            <div class="filter-group">
    <label for="round-off-toggle" style="display:flex; align-items:center; gap:8px;">
        Round Off
    </label>
    <input type="checkbox" id="round-off-toggle" aria-label="Round off sales report amounts">
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
            <div class="table-toolbar">
                <input type="text" id="search-officer" class="form-input" placeholder="Search by Employee ID or Officer Name..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="officer-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="employee_id">Employee ID</th>
                            <th data-col="officer_name">Officer Name</th>
                            <th data-col="total_invoices">Total Invoices</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
                            <th data-col="status">Status</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-supplier-man" class="form-input" placeholder="Search by Employee ID or Supplier Man Name..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="supplier_man-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="employee_id">Employee ID</th>
                            <th data-col="supplier_man_name">Supplier Man Name</th>
                            <th data-col="total_invoices">Total Invoices</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
                            <th data-col="status">Status</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-product" class="form-input" placeholder="Search by Product ID, Name or Category..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="product-columns-btn"><i class="fas fa-columns"></i> Columns</button>
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
                            <th data-col="item_company">Item Company</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-branch" class="form-input" placeholder="Search by Branch ID or Name..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="branch-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="branch_id">Branch ID</th>
                            <th data-col="branch_name">Branch Name</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="transactions">Transactions</th>
                            <th data-col="avg_sale">Avg Sale (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-category" class="form-input" placeholder="Search by Category..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="category-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="category">Category</th>
                            <th data-col="total_revenue">Total Revenue (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="percentage">Percentage</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-territory" class="form-input" placeholder="Search by Country, Region, City, Zone or Area..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="territory-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="country">Country</th>
                            <th data-col="region">Region</th>
                            <th data-col="city">City</th>
                            <th data-col="zone">Zone</th>
                            <th data-col="area">Area</th>
                            <th data-col="total_invoices">Total Invoices</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-invoice" class="form-input" placeholder="Search by Invoice #, Customer or Officer..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="invoice-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="bill_no">Invoice #</th>
                            <th data-col="sale_date">Date</th>
                            <th data-col="customer_name">Customer</th>
                            <th data-col="officer_name">Sales Officer</th>
                            <th data-col="total_bill">Total Bill (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="discount">Discount (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="net_amount">Net Amount (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Item Company Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Item Company Wise Sales Details</div>
            <div class="table-toolbar">
                <input type="text" id="search-item-company" class="form-input" placeholder="Search by Item Company Name..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="item_company-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="item_company">Item Company</th>
                            <th data-col="total_invoices">Total Invoices</th>
                            <th data-col="total_quantity">Total Quantity</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody id="itemCompanyTableBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Vendor Wise Sales Table -->
        <div class="table-card">
            <div class="table-title">Vendor Wise Sales Details</div>
            <div class="table-toolbar">
                <input type="text" id="search-vendor" class="form-input" placeholder="Search by Vendor ID or Name..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="vendor-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="vendor_id">Vendor ID</th>
                            <th data-col="vendor_name">Vendor Name</th>
                            <th data-col="total_invoices">Total Invoices</th>
                            <th data-col="total_quantity">Total Quantity</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
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
            <div class="table-toolbar">
                <input type="text" id="search-customer" class="form-input" placeholder="Search by Customer ID or Name..." style="max-width: 400px;">
                <button type="button" class="btn btn-secondary columns-btn" id="customer-columns-btn"><i class="fas fa-columns"></i> Columns</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th data-col="customer_id">Customer ID</th>
                            <th data-col="customer_name">Customer Name</th>
                            <th data-col="total_invoices">Total Invoices</th>
                            <th data-col="total_sales">Total Sales (<?php echo $currency_symbol; ?>)</th>
                            <th data-col="batch_no">Batch No</th>
                            <th data-col="expiry_date">Expiry Date</th>
                            <th data-col="item_company">Item Company</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shared Show/Hide Columns Modal (reused by every report table) -->
        <div id="columns-modal" class="col-modal-overlay">
            <div class="col-modal">
                <div class="col-modal-header">
                    <h3 id="col-modal-title">Show / Hide Columns</h3>
                    <button type="button" class="col-modal-close" id="col-modal-close">&times;</button>
                </div>
                <div class="col-modal-body" id="col-modal-body"></div>
                <div class="col-modal-footer">
                    <button type="button" class="btn btn-secondary" id="col-modal-reset">Reset to Default</button>
                    <button type="button" class="btn btn-primary" id="col-modal-done">Done</button>
                </div>
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