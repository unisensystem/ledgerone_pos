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
    <title>Trial Balance Report | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/trial_balance/trial-balance.css">
</head>
<body>
    <div class="container">
        <!-- Header Card -->
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Trial Balance Report</h1>
                <div class="d-flex align-center gap-2">
                    <button class="btn btn-secondary" id="printBtn">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-secondary" id="exportBtn">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-primary" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <p class="mb-3">View and validate your company's financial position with hierarchical Chart of Accounts.</p>
            
            <!-- Validation Status -->
            <div id="validationStatus" class="validation-status validation-success">
                <i class="fas fa-check-circle text-success"></i>
                <div>
                    <strong>Trial Balance is in balance!</strong>
                    <div>Total Debits (<?php echo $currency_symbol; ?>1,250,450.00) = Total Credits (<?php echo $currency_symbol; ?>1,250,450.00)</div>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label class="form-label">Date Range</label>
                    <select class="form-control" id="dateRange">
                        <option value="select">Select Range</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                
                <div class="filter-group hidden" id="customDateRange">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" id="fromDate">
                </div>
                
                <div class="filter-group hidden" id="customDateRange2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" id="toDate">
                </div>
                
                <div class="filter-group">
                    <label class="form-label">Account Level</label>
                    <select class="form-control" id="accountLevel">
                        <option value="all">All Accounts</option>
                        <option value="summary">Summary Only (Level 0-1)</option>
                        <option value="detailed">Detailed (All Levels)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="form-label">Show Zero Balances</label>
                    <select class="form-control" id="zeroBalances">
                        <option value="show">Show All Accounts</option>
                        <option value="hide">Hide Zero Balances</option>
                    </select>
                </div>
                
                <button class="btn btn-secondary" id="applyFilters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </div>
        
        <!-- Trial Balance Table Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Trial Balance</h2>
                <div class="d-flex align-center gap-2">
                    <span class="subtext">As of April 30, 2024</span>
                </div>
            </div>
            
            <div class="table-container">
                <table id="trialBalanceTable">
                    <thead>
                        <tr>
                            <th width="120">Account Code</th>
                            <th>Account Name</th>
                            <th width="150" class="text-right">Debit (<?php echo $currency_symbol; ?>)</th>
                            <th width="150" class="text-right">Credit (<?php echo $currency_symbol; ?>)</th>
                            <th width="120" class="text-right">Balance (<?php echo $currency_symbol; ?>)</th>
                        </tr>
                    </thead>
                    <tbody id="trialBalanceBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL</strong></td>
                            <td class="debit-amount"><strong>1,250,450.00</strong></td>
                            <td class="credit-amount"><strong>1,250,450.00</strong></td>
                            <td class="text-right"><strong>0.00</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="d-flex justify-between align-center mt-3">
                <div class="d-flex align-center gap-2">
                    <div class="d-flex align-center gap-1">
                        <div style="width: 12px; height: 12px; background-color: var(--surface-1); border: 1px solid var(--border-default);"></div>
                        <span>Parent Account</span>
                    </div>
                    <div class="d-flex align-center gap-1">
                        <div style="width: 12px; height: 12px; background-color: var(--surface-0); border: 1px solid var(--border-default);"></div>
                        <span>Child Account</span>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-ghost" id="expandAll">
                        <i class="fas fa-expand-alt"></i> Expand All
                    </button>
                    <button class="btn btn-ghost" id="collapseAll">
                        <i class="fas fa-compress-alt"></i> Collapse All
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Help Section Card -->
        <div class="card">
            <div class="help-section">
                <div class="help-title">
                    <i class="fas fa-life-ring"></i>
                    <span>How to Use Trial Balance Report</span>
                </div>
                
                <p class="mb-3">This report helps you verify that your books are balanced and understand your financial position.</p>
                
                <div class="help-item">
                    <div class="help-item-title">1. Check if Debits = Credits</div>
                    <p>At the bottom of the report, ensure "Total Debits" equals "Total Credits". If they don't match, there may be data entry errors.</p>
                </div>
                
                <div class="help-item">
                    <div class="help-item-title">2. Understand Account Hierarchy</div>
                    <p>Accounts are organized in a tree structure. Parent accounts (like "Assets") show the sum of their child accounts (like "Cash", "Inventory").</p>
                </div>
                
                <div class="help-item">
                    <div class="help-item-title">3. Identify Unusual Balances</div>
                    <p>Review accounts with unusually high or negative balances that don't make sense for that account type.</p>
                </div>
                
                <div class="help-item">
                    <div class="help-item-title">4. Use Filters for Analysis</div>
                    <p>Filter by date range or hide zero-balance accounts to focus on active accounts. View summary levels for management reporting.</p>
                </div>
                
                <div class="help-item">
                    <div class="help-item-title">5. Export for Sharing</div>
                    <p>Export the report to share with your accountant or for audit purposes. Print a clean version for meetings.</p>
                </div>
                
                <div class="validation-status validation-warning mt-3">
                    <i class="fas fa-lightbulb"></i>
                    <div>
                        <strong>Tip for Non-Accountants:</strong>
                        <div>Think of Debits as "Where money came from" and Credits as "Where money went to". The Trial Balance ensures all money movements are properly recorded.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/trial_balance/trial-balance.js"></script>
</body>
</html>