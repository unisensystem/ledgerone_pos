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
    <title>Customer Aging Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/customer_aging/customer-aging.css">
</head>
<style>
    /* Append to customer-aging.css — styles for the searchable customer combobox */

.combo-select {
    position: relative;
}

.combo-select .form-select {
    width: 100%;
}

.combo-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 50;
    max-height: 220px;
    overflow-y: auto;
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    margin-top: 4px;
}

.combo-dropdown.open {
    display: block;
}

.combo-dropdown .combo-option {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
}

.combo-dropdown .combo-option:hover,
.combo-dropdown .combo-option.active {
    background: var(--hover, #f1f5f9);
}

.combo-dropdown .combo-empty {
    padding: 8px 12px;
    font-size: 13px;
    color: var(--subtext, #94a3b8);
}

select.form-select:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
</style>
<body>
    <div class="container">
        <!-- Header -->
        <div class="mb-4">
            <h1>Customer Aging Report</h1>
            <p class="subtitle">Track overdue invoices and customer payment patterns</p>
        </div>
        
        <!-- Filter Bar -->
        <div class="card">
            <div class="card-header">
                <h3>Report Filters</h3>
                <div class="filter-actions">
                    <button class="btn btn-secondary" onclick="printReport()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-secondary" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button class="btn btn-secondary" onclick="exportToJSON()">
                        <i class="fas fa-file-code"></i> JSON
                    </button>
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

                <!-- Customer: dropdown + type-to-search combobox in one control -->
                <div class="form-group">
                    <label class="form-label">Customer</label>
                    <div class="combo-select" id="customerCombo">
                        <input type="text" class="form-select" id="customerFilterInput"
                               placeholder="All Customers" autocomplete="off">
                        <input type="hidden" id="customerFilter" value="">
                        <div class="combo-dropdown" id="customerDropdown"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Country</label>
                    <select class="form-select" id="countryFilter">
                        <option value="">All Countries</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Region</label>
                    <select class="form-select" id="regionFilter" disabled>
                        <option value="">All Regions</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">City</label>
                    <select class="form-select" id="cityFilter" disabled>
                        <option value="">All Cities</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Zone</label>
                    <select class="form-select" id="zoneFilter" disabled>
                        <option value="">All Zones</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Area</label>
                    <select class="form-select" id="areaFilter" disabled>
                        <option value="">All Areas</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date Range</label>
                    <select class="form-select">
                        <option value="all">All Time</option>
                        <option value="30days" selected>Last 30 Days</option>
                        <option value="90days">Last 90 Days</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Customer Type</label>
                    <select class="form-select">
                        <option value="all">All Customers</option>
                        <option value="regular">Regular</option>
                        <option value="vip">VIP</option>
                        <option value="new">New</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Overdue Bucket</label>
                    <select class="form-select">
                        <option value="all">All Buckets</option>
                        <option value="15+">15+ Days</option>
                        <option value="25+">25+ Days</option>
                        <option value="35+">35+ Days</option>
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
            <div class="bucket-card">
                <div class="bucket-value" id="totalOverdueValue">$0</div>
                <div class="bucket-label">Total Overdue</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);">Across all customers</div>
            </div>
            
            <div class="bucket-card">
                <div class="bucket-value" id="bucket15Value">$0</div>
                <div class="bucket-label">15+ Days Overdue</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);"><span id="bucket15Count">0</span> invoices</div>
            </div>
            
            <div class="bucket-card warning">
                <div class="bucket-value" id="bucket25Value">$0</div>
                <div class="bucket-label">25+ Days Overdue</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);"><span id="bucket25Count">0</span> invoices</div>
            </div>
            
            <div class="bucket-card error">
                <div class="bucket-value" id="bucket45Value">$0</div>
                <div class="bucket-label">45+ Days Overdue</div>
                <div class="mt-2" style="font-size: 12px; color: var(--subtext);"><span id="bucket45Count">0</span> invoices</div>
            </div>
        </div>
        
        <!-- Report Table -->
        <div class="card">
            <div class="card-header">
                <h3>Aging Report Details</h3>
                <div class="d-flex align-center">
                    <div class="pagination-info" id="paginationInfoTop">Showing 0 entries</div>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>S#</th>
                            <th>Customer Code</th>
                            <th>Customer Name</th>
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
                <div class="pagination-info" id="paginationInfoBottom">Page 1 of 1</div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Buttons will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/financial_reports/customer_aging/customer-aging.js"></script>
</body>
</html>