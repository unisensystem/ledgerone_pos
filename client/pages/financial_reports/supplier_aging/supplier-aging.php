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
    <title>Supplier Aging Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/supplier_aging/supplier-aging.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="mb-4">
            <h1>Supplier Aging Report</h1>
            <p class="subtitle">Track overdue invoices and supplier payment patterns</p>
        </div>
        
        <!-- Filter Bar -->
        <div class="card">
            <div class="card-header">
                <h3>Report Filters</h3>
                <div class="filter-actions">
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div class="dropdown-menu">
                            <a href="#" class="dropdown-item" onclick="printReport(); return false;">
                                <i class="fas fa-print"></i> Print Report
                            </a>
                            <a href="#" class="dropdown-item" onclick="exportToExcel(); return false;">
                                <i class="fas fa-file-excel"></i> Export To Excel
                            </a>
                            <a href="#" class="dropdown-item" onclick="exportToJSON(); return false;">
                                <i class="fas fa-file-code"></i> Export JSON
                            </a>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="applyFiltersBtn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
            
            <div class="filter-bar">
                <div class="form-group">
                    <label class="form-label">Company</label>
                    <select class="form-select" id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date Range</label>
                    <select class="form-select" id="dateRangeFilter">
                        <option value="all">All Time</option>
                        <option value="30days" selected>Last 30 Days</option>
                        <option value="90days">Last 90 Days</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <div class="form-group" id="customDateGroup" style="display:none;">
                    <label class="form-label">From</label>
                    <input type="date" class="form-select" id="dateFrom">
                </div>

                <div class="form-group" id="customDateGroupTo" style="display:none;">
                    <label class="form-label">To</label>
                    <input type="date" class="form-select" id="dateTo">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Overdue Bucket</label>
                    <select class="form-select" id="bucketFilter">
                        <option value="all">All Buckets</option>
                        <option value="current">Current (Today)</option>
                        <option value="1-15">1 - 15 Days</option>
                        <option value="16-30">16 - 30 Days</option>
                        <option value="31-45">31 - 45 Days</option>
                        <option value="45+">45+ Days</option>
                    </select>
                </div>
                
                <button class="btn btn-ghost" id="resetFiltersBtn">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
            </div>
        </div>
        
        <!-- Buckets Summary -->
        <h2>Overdue Summary</h2>
        <div class="buckets-summary">
            <div class="bucket-card" data-bucket="all">
                <div class="bucket-value">$0.00</div>
                <div class="bucket-label">Total Outstanding</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">Across all suppliers</div>
            </div>
            <div class="bucket-card" data-bucket="current">
                <div class="bucket-value">$0.00</div>
                <div class="bucket-label">Current (Today)</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">0 invoices</div>
            </div>
            <div class="bucket-card" data-bucket="1-15">
                <div class="bucket-value">$0.00</div>
                <div class="bucket-label">1 - 15 Days</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">0 invoices</div>
            </div>
            <div class="bucket-card warning" data-bucket="16-30">
                <div class="bucket-value">$0.00</div>
                <div class="bucket-label">16 - 30 Days</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">0 invoices</div>
            </div>
            <div class="bucket-card warning" data-bucket="31-45">
                <div class="bucket-value">$0.00</div>
                <div class="bucket-label">31 - 45 Days</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">0 invoices</div>
            </div>
            <div class="bucket-card error" data-bucket="45+">
                <div class="bucket-value">$0.00</div>
                <div class="bucket-label">45+ Days</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">0 invoices</div>
            </div>
        </div>
        
        <!-- Report Table -->
        <div class="card">
            <div class="card-header">
                <h3>Aging Report Details</h3>
                <div class="d-flex align-center">
                    <div class="pagination-info">Showing 1-10 of 45 entries</div>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>S#</th>
                            <th>Supplier Code</th>
                            <th>Supplier Name</th>
                            <th>Address</th>
                            <th>Invoice No</th>
                            <th>Invoice Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Days Overdue</th>
                            <th>Bucket</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info">Page 1 of 5</div>
                <div class="pagination-controls">
                    <!-- Buttons will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/financial_reports/supplier_aging/supplier-aging.js"></script>
</body>
</html>