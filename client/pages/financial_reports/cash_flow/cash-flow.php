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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Cash Flow Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/cash_flow/cash-flow.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1 class="page-title">Cash Flow Report</h1>
                <p class="page-subtitle">Track all financial transactions with detailed breakdown</p>
            </div>
            <div class="actions">
                <button class="btn btn-secondary" id="toggleViewBtn">
                    <i class="fas fa-chart-bar"></i> Graph View
                </button>
                <button class="btn btn-primary" id="printBtn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card mb-24">
            <h2 class="card-title"><i class="fas fa-filter"></i> Search Filters</h2>
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Company</label>
                    <select class="filter-input" id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Date Range</label>
                    <select class="filter-input" id="dateRange">
                        <option value="all">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="filter-group" id="customDateGroup" style="display: none;">
                    <label class="filter-label">From Date</label>
                    <input type="date" class="filter-input" id="fromDate">
                </div>
                <div class="filter-group" id="customDateGroup2" style="display: none;">
                    <label class="filter-label">To Date</label>
                    <input type="date" class="filter-input" id="toDate">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Description</label>
                    <input type="text" class="filter-input" id="descriptionFilter" placeholder="Search description...">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Account</label>
                    <input type="text" class="filter-input" id="accountFilter" placeholder="Search account...">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Method</label>
                    <select class="filter-input" id="methodFilter">
                        <option value="all">All Methods</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Bank Account</label>
                    <select class="filter-input" id="bankAccountFilter">
                        <option value="all">All Bank Accounts</option>
                        <option value="main">Main Business Account</option>
                        <option value="operating">Operating Account</option>
                        <option value="savings">Savings Account</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Transaction Type</label>
                    <select class="filter-input" id="typeFilter">
                        <option value="all">All Types</option>
                        <option value="inflow">Inflow Only</option>
                        <option value="outflow">Outflow Only</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Currency</label>
                    <select class="filter-input" id="currencyFilter">
                        <option value="">Loading...</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-ghost" id="resetFiltersBtn">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
                <button class="btn btn-primary" id="applyFiltersBtn">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
            </div>
        </div>

        <!-- Balances Summary -->
        <h3 class="section-title">Balance Summary</h3>
        <div class="balances-section mb-24">
            <div class="balance-card opening-balance">
                <div>
                    <div class="balance-label">Opening Balance</div>
                    <div class="balance-value">Rs 12,450.00</div>
                </div>
                <i class="fas fa-wallet" style="color: var(--primary); font-size: 24px;"></i>
            </div>
            <div class="balance-card total-inflow">
                <div>
                    <div class="balance-label">Total Inflow</div>
                    <div class="balance-value">Rs 3,280.50</div>
                </div>
                <i class="fas fa-arrow-down" style="color: var(--success); font-size: 24px;"></i>
            </div>
            <div class="balance-card total-outflow">
                <div>
                    <div class="balance-label">Total Outflow</div>
                    <div class="balance-value">Rs 0.00</div>
                </div>
                <i class="fas fa-arrow-up" style="color: var(--error); font-size: 24px;"></i>
            </div>
            <div class="balance-card closing-balance">
                <div>
                    <div class="balance-label">Closing Balance</div>
                    <div class="balance-value">Rs 15,730.50</div>
                </div>
                <i class="fas fa-chart-line" style="color: var(--success); font-size: 24px;"></i>
            </div>
        </div>

        <!-- Graph View -->
        <div class="card" id="graphView" style="display: none;">
            <h2 class="card-title"><i class="fas fa-chart-bar"></i> Cash Flow Graph</h2>
            <canvas id="cashFlowChart" style="max-height: 400px;"></canvas>
        </div>

        <!-- Table Card -->
        <div class="card" id="tableView">
            <h2 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Cash Flow Transactions</h2>
            <div class="table-container">
                <table id="cashFlowTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Account</th>
                            <th>Method</th>
                            <th>Bank Account</th>
                            <th class="text-right">Inflow</th>
                            <th class="text-right">Outflow</th>
                            <th class="text-right">Cash Balance</th>
                            <th class="text-right">Bank Balance</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td colspan="5"><strong>TOTALS</strong></td>
                            <td class="text-right positive"><strong>Rs 12,840.75</strong></td>
                            <td class="text-right negative"><strong>Rs 9,560.25</strong></td>
                            <td class="text-right"><strong>Rs 6,320.00</strong></td>
                            <td class="text-right"><strong>Rs 9,410.50</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="form-actions mt-24">
                <div style="color: var(--text-subtle); font-size: 13px;">
                    <i class="fas fa-info-circle"></i> Showing 15 transactions
                </div>
                <div>
                    <button class="btn btn-ghost" id="prevPageBtn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="btn btn-ghost" id="nextPageBtn">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/financial_reports/cash_flow/cash-flow.js"></script>
</body>
</html>