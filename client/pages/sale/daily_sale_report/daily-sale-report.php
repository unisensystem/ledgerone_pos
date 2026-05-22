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
    <title>LedgerOne ERP - Daily Sales Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/daily_sale_report/daily-sale-report.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="page-title">Daily Sales Report (DSR)</h1>
            <p class="page-subtitle">Analyze sales performance with item-wise and bill-wise reports</p>
        </div>

        <!-- Report Type Toggle -->
        <div class="report-toggle">
            <div class="toggle-option active" id="item-wise-toggle">
                <i class="fas fa-boxes"></i> Item-wise DSR
            </div>
            <div class="toggle-option" id="bill-wise-toggle">
                <i class="fas fa-file-invoice-dollar"></i> Bill-wise DSR
            </div>
        </div>

        <!-- Reference Number -->
        <div class="reference-card" id="reference-card" style="display: none;">
            <div class="reference-label">Reference #:</div>
            <div class="reference-value" id="reference-number">-</div>
        </div>

        <!-- Summary Card -->
        <div class="summary-card" id="summary-card">
            <div class="summary-item">
                <div class="summary-value" id="total-sales"><?php echo $currency_symbol; ?>0</div>
                <div class="summary-label">Total Sales</div>
            </div>
            <div class="summary-item" id="items-sold-item">
                <div class="summary-value" id="total-items">0</div>
                <div class="summary-label">Items Sold</div>
            </div>
            <div class="summary-item" id="bills-generated-item">
                <div class="summary-value" id="total-bills">0</div>
                <div class="summary-label">Bills Generated</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" id="net-amount"><?php echo $currency_symbol; ?>0</div>
                <div class="summary-label">Net Amount</div>
            </div>
        </div>

        <!-- Search Filters Card -->
        <div class="card">
            <h2 class="card-title">Search Filters</h2>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-input" id="invoice-type">
                        <option value="">All Types</option>
                        <option value="Cash">Cash</option>
                        <option value="Credit">Credit</option>
                        <option value="OutStation">Out Station</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Sales Officer</label>
                    <div class="multi-select-dropdown" id="sales-officer-dropdown">
                        <div class="multi-select-trigger" id="sales-officer-trigger">
                            <span id="sales-officer-label">All Sales Officers</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="sales-officer-options">
                            <!-- Options loaded dynamically -->
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Supplier Man</label>
                    <select class="form-input" id="supplier-man">
                        <option value="">All Supplier Men</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Vendor</label>
                    <select class="form-input" id="vendor">
                        <option value="">All Vendors</option>
                        <option value="v1">Vendor A</option>
                        <option value="v2">Vendor B</option>
                        <option value="v3">Vendor C</option>
                        <option value="v4">Vendor D</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">City</label>
                    <select class="form-input" id="city-filter">
                        <option value="">All Cities</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">City Zone</label>
                    <select class="form-input" id="city-zone-filter">
                        <option value="">All City Zones</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Area</label>
                    <select class="form-input" id="area-filter">
                        <option value="">All Areas</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-input" id="date-from" value="2023-10-01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-input" id="date-to" value="2023-10-31">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Company</label>
                    <select class="form-input" id="company">
                        <option value="">All Companies</option>
                    </select>
                </div>
            </div>
            
            <div class="button-group">
                <button class="btn btn-primary" id="search-btn">
                    <i class="fas fa-search"></i> Search Report
                </button>
                <button class="btn btn-secondary" id="reset-btn">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
                <button class="btn btn-ghost" id="export-btn">
                    <i class="fas fa-download"></i> Export as CSV
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Loading report data...</p>
        </div>

        <!-- Item-wise Report Table -->
        <div class="card" id="item-wise-report">
            <h2 class="card-title">Item-wise Sales Report</h2>
            <div class="table-container">
                <table class="table" id="item-wise-table">
                    <thead>
                        <tr>
                            <th>S#</th>
                            <th>Product</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">FOC Qty</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="item-wise-data">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bill-wise Report Table -->
        <div class="card" id="bill-wise-report" style="display: none;">
            <h2 class="card-title">Bill-wise Sales Report</h2>
            <div class="table-container">
                <table class="table" id="bill-wise-table">
                    <thead>
                        <tr>
                            <th>S#</th>
                            <th>Invoice No</th>
                            <th>Customer Code</th>
                            <th>Customer Name</th>
                            <th>Address</th>
                            <th class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody id="bill-wise-data">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="footer-actions">
            <div>
                <span id="report-count">0 records found</span>
            </div>
            <div class="pagination" id="pagination">
                <!-- Pagination will be inserted here -->
            </div>
            <div class="button-group">
                <button class="btn btn-ghost" id="print-btn">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/sale/daily_sale_report/daily-sale-report.js"></script>
</body>
</html>