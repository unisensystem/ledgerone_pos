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
    <title>LedgerOne ERP - General Ledger Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/general_ledger/general-ledger.css">
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="header">
            <h1 class="page-title">General Ledger Report</h1>
            <p class="page-subtitle">Track all financial transactions, validate accounting entries, and generate compliance reports</p>
        </div>
        
        <!-- Validation Summary -->
        <div id="validationSummary" class="validation-summary">
            <div class="validation-title">
                <i class="fas fa-exclamation-circle"></i>
                <span>Accounting Validation Issues</span>
            </div>
            <ul id="validationList"></ul>
        </div>
        
        <!-- Help Panel -->
        <div id="helpPanel" class="help-panel">
            <h3 class="help-title"><i class="fas fa-life-ring"></i> Understanding General Ledger Reports</h3>
            
            <div class="help-item">
                <div class="help-item-title">What is a General Ledger?</div>
                <p>The General Ledger is the master accounting record of all financial transactions in your business. It's organized by accounts (like Cash, Revenue, Expenses) and provides the data needed to create financial statements.</p>
            </div>
            
            <div class="help-item">
                <div class="help-item-title">Debits vs. Credits (Plain English)</div>
                <p>Don't let accounting terminology confuse you! Think of it this way:
                <ul>
                    <li><strong>Debit</strong> = Adding to an asset or expense account</li>
                    <li><strong>Credit</strong> = Adding to a liability, equity, or revenue account</li>
                    <li>Every transaction must have equal debits and credits (this is called "double-entry accounting")</li>
                </ul>
                </p>
            </div>
            
            <div class="help-item">
                <div class="help-item-title">Why Validation Matters</div>
                <p>The system checks that your ledger follows accounting rules. If debits don't equal credits, or if accounts don't balance, you'll see warnings here. This helps prevent accounting errors before they affect your financial statements.</p>
            </div>
            
            <div class="help-item">
                <div class="help-item-title">How to Use This Report</div>
                <p>1. Set your date range to focus on specific periods<br>
                2. Filter by account type to see only relevant transactions<br>
                3. Search for specific transactions or accounts<br>
                4. Check validation status to ensure accuracy<br>
                5. Export for your records or accountant review</p>
            </div>
        </div>
        
        <!-- Action Bar -->
        <div class="action-bar">
            <div class="d-flex align-center gap-1">
                <button id="toggleHelp" class="btn btn-secondary">
                    <i class="fas fa-question-circle"></i> Toggle Help
                </button>
                <button id="validateReport" class="btn btn-secondary">
                    <i class="fas fa-check-circle"></i> Validate Report
                </button>
            </div>
            
            <div class="d-flex align-center gap-1">
                <button class="btn btn-ghost">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-ghost" id="printBtn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-refresh"></i> Generate Report
                </button>
            </div>
        </div>
        
        <!-- Search & Filters Card -->
        <div class="card">
            <h3 class="card-title"><i class="fas fa-filter"></i> Report Filters</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Company</label>
                    <select class="form-control" id="company">
                        <option value="">All Companies</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date Range <span class="help-trigger" data-help="date-range">?</span></label>
                    <div class="d-flex gap-1">
                        <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-01-01'); ?>">
                        <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Account Type <span class="help-trigger" data-help="account-type">?</span></label>
                    <select class="form-control" id="accountType">
                        <option value="">All Account Types</option>
                        <option value="asset">Assets (Cash, Inventory, Equipment)</option>
                        <option value="liability">Liabilities (Loans, Accounts Payable)</option>
                        <option value="equity">Equity (Owner's Capital, Retained Earnings)</option>
                        <option value="revenue">Revenue (Sales, Services)</option>
                        <option value="expense">Expenses (Rent, Salaries, Utilities)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Account Number</label>
                    <input type="text" class="form-control" id="accountNumber" placeholder="e.g., 1010, 4010">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Transaction Type <span class="help-trigger" data-help="transaction-type">?</span></label>
                    <select class="form-control" id="transactionType">
                        <option value="">All Transactions</option>
                        <option value="debit">Debit Entries Only</option>
                        <option value="credit">Credit Entries Only</option>
                        <option value="adjusting">Adjusting Entries</option>
                        <option value="closing">Closing Entries</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Minimum Amount</label>
                    <input type="number" class="form-control" id="minAmount" placeholder="0.00">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Maximum Amount</label>
                    <input type="number" class="form-control" id="maxAmount" placeholder="10000.00">
                </div>
                
                <div class="form-group">
                </div>
                
                <div class="form-group">
                </div>
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label">Search Description or Reference</label>
                <div class="d-flex gap-1">
                    <input type="text" class="form-control" id="searchText" placeholder="Search transaction descriptions...">
                    <button class="btn btn-secondary" id="clearFilters">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Report Summary Card -->
        <div class="card">
            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Report Summary</h3>
            
            <div class="grid">
                <div class="col-3">
                    <div class="card" style="margin-bottom: 0; padding: var(--spacing-md);">
                        <div style="font-size: 12px; color: var(--subtext);">Total Debits</div>
                        <div style="font-size: 24px; font-weight: 600; color: var(--heading);">$147,850.00</div>
                        <div class="badge badge-balanced">Balanced</div>
                    </div>
                </div>
                
                <div class="col-3">
                    <div class="card" style="margin-bottom: 0; padding: var(--spacing-md);">
                        <div style="font-size: 12px; color: var(--subtext);">Total Credits</div>
                        <div style="font-size: 24px; font-weight: 600; color: var(--heading);">$147,850.00</div>
                        <div class="badge badge-balanced">Balanced</div>
                    </div>
                </div>
                
                <div class="col-3">
                    <div class="card" style="margin-bottom: 0; padding: var(--spacing-md);">
                        <div style="font-size: 12px; color: var(--subtext);">Transactions</div>
                        <div style="font-size: 24px; font-weight: 600; color: var(--heading);">142</div>
                        <div style="font-size: 12px; color: var(--subtext);">in period</div>
                    </div>
                </div>
                
                <div class="col-3">
                    <div class="card" style="margin-bottom: 0; padding: var(--spacing-md);">
                        <div style="font-size: 12px; color: var(--subtext);">Accounts Active</div>
                        <div style="font-size: 24px; font-weight: 600; color: var(--heading);">28</div>
                        <div style="font-size: 12px; color: var(--subtext);">of 35 total</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- General Ledger Table -->
        <div class="card">
            <div class="d-flex justify-between align-center">
                <h3 class="card-title"><i class="fas fa-book"></i> General Ledger Entries</h3>
                <div style="font-size: 13px; color: var(--subtext);">
                    Showing 1-20 of 142 entries
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account #</th>
                            <th>Account Name</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Sample data rows -->
                        <tr>
                            <td>2023-01-15</td>
                            <td>1010</td>
                            <td>Cash - Operating</td>
                            <td>Initial business investment</td>
                            <td>INV-001</td>
                            <td class="text-right">$25,000.00</td>
                            <td class="text-right"></td>
                            <td class="text-right">$25,000.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-15</td>
                            <td>3010</td>
                            <td>Owner's Capital</td>
                            <td>Initial business investment</td>
                            <td>INV-001</td>
                            <td class="text-right"></td>
                            <td class="text-right">$25,000.00</td>
                            <td class="text-right">$25,000.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-20</td>
                            <td>1200</td>
                            <td>Inventory</td>
                            <td>Purchase of fuel inventory</td>
                            <td>INV-045</td>
                            <td class="text-right">$8,500.00</td>
                            <td class="text-right"></td>
                            <td class="text-right">$8,500.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-20</td>
                            <td>1010</td>
                            <td>Cash - Operating</td>
                            <td>Payment for fuel inventory</td>
                            <td>CHK-1023</td>
                            <td class="text-right"></td>
                            <td class="text-right">$8,500.00</td>
                            <td class="text-right">$16,500.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-25</td>
                            <td>5010</td>
                            <td>Equipment Rental</td>
                            <td>Monthly equipment lease</td>
                            <td>INV-112</td>
                            <td class="text-right">$1,200.00</td>
                            <td class="text-right"></td>
                            <td class="text-right">$1,200.00</td>
                            <td><span class="badge badge-warning">Review</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-25</td>
                            <td>1010</td>
                            <td>Cash - Operating</td>
                            <td>Payment for equipment lease</td>
                            <td>CHK-1024</td>
                            <td class="text-right"></td>
                            <td class="text-right">$1,200.00</td>
                            <td class="text-right">$15,300.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-31</td>
                            <td>4010</td>
                            <td>Fuel Sales</td>
                            <td>January fuel sales revenue</td>
                            <td>SALES-0123</td>
                            <td class="text-right"></td>
                            <td class="text-right">$42,150.00</td>
                            <td class="text-right">$42,150.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                        <tr>
                            <td>2023-01-31</td>
                            <td>1010</td>
                            <td>Cash - Operating</td>
                            <td>Receipts from January sales</td>
                            <td>DEP-0123</td>
                            <td class="text-right">$42,150.00</td>
                            <td class="text-right"></td>
                            <td class="text-right">$57,450.00</td>
                            <td><span class="badge badge-balanced">Valid</span></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: var(--surface-1); font-weight: 600;">
                            <td colspan="5" class="text-right">Period Totals:</td>
                            <td class="text-right">$76,850.00</td>
                            <td class="text-right">$76,850.00</td>
                            <td colspan="2" class="text-center">
                                <span class="badge badge-balanced">DEBITS = CREDITS ✓</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="d-flex justify-between align-center mt-1">
                <div style="font-size: 13px; color: var(--subtext);">
                    <i class="fas fa-info-circle"></i> Double-click any entry for detailed view
                </div>
                
                <div class="d-flex gap-1">
                    <button class="btn btn-ghost">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="btn btn-ghost">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Footer Note -->
        <div style="text-align: center; color: var(--subtext); font-size: 13px; margin-top: var(--spacing-xl); padding: var(--spacing-md);">
            <p>This General Ledger report follows GAAP (Generally Accepted Accounting Principles) standards.</p>
            <p>Report generated on <span id="currentDate"></span> | FuelingSys ERP Accounting Module v2.4</p>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/general_ledger/general-ledger.js"></script>
</body>
</html>