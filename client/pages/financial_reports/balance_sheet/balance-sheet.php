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
    <title>LedgerOne ERP - Balance Sheet Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/balance_sheet/balance-sheet.css">
</head>
<body>
    <div class="container">
        <!-- Header with title and actions -->
        <div class="header-actions">
            <div>
                <h1>Balance Sheet Report</h1>
                <div class="subtitle">As of <span id="report-date"><?php echo date('F j, Y'); ?></span></div>
            </div>
            <div class="action-buttons">
                <button class="btn btn-secondary" id="print-btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <div class="dropdown" style="position: relative; display: inline-block;">
                    <button class="btn btn-secondary" id="export-btn">
                        <i class="fas fa-file-export"></i> Export <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
                    </button>
                    <div class="dropdown-menu" id="export-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); min-width: 180px; margin-top: 4px; z-index: 1000;">
                        <button class="dropdown-item" id="export-excel-btn" style="display: block; width: 100%; padding: 10px 16px; border: none; background: none; text-align: left; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-file-excel" style="color: #217346; margin-right: 8px;"></i> Export to Excel
                        </button>
                        <button class="dropdown-item" id="export-json-btn" style="display: block; width: 100%; padding: 10px 16px; border: none; background: none; text-align: left; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-file-code" style="color: #f39c12; margin-right: 8px;"></i> Export JSON
                        </button>
                    </div>
                </div>
                <button class="btn btn-secondary" id="close-period-btn">
                    <i class="fas fa-calendar-check"></i> Close Period
                </button>
                <button class="btn btn-primary" id="validate-btn">
                    <i class="fas fa-check-circle"></i> Validate Report
                </button>
            </div>
        </div>
        
        <!-- Report period selector -->
        <div class="card">
            <h3>Report Period</h3>
            <div class="report-period">
                <div class="period-selector">
                    <label for="report-period">Select Period</label>
                    <select id="report-period" class="form-control">
                        <option value="as-of-date">As Of Date</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="period-selector" id="date-to-container">
                    <label for="date-to">As Of Date</label>
                    <input type="date" id="date-to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="period-selector" id="date-from-container" style="display: none;">
                    <label for="date-from">Date From</label>
                    <input type="date" id="date-from" class="form-control">
                </div>
            </div>
        </div>
        
        <!-- Help section for non-accountants -->
        <div class="help-section">
            <div class="help-title">
                <i class="fas fa-life-ring"></i>
                <h3>Understanding Your Balance Sheet</h3>
            </div>
            <div class="help-content">
                <p><strong>What is a Balance Sheet?</strong> A balance sheet is a financial statement that shows what your company owns (assets), what it owes (liabilities), and the value left for owners (equity) at a specific point in time.</p>
                
                <div class="help-tip">
                    <strong>Key Insight:</strong> The balance sheet must always "balance" according to the fundamental accounting equation: <strong>Assets = Liabilities + Equity</strong>. If this equation doesn't balance, there's likely an error in your financial records.
                </div>
                
                <p><strong>Assets</strong> are resources your company owns that have economic value (cash, inventory, equipment).</p>
                <p><strong>Liabilities</strong> are what your company owes to others (loans, accounts payable).</p>
                <p><strong>Equity</strong> is the owner's stake in the company after all liabilities are paid.</p>
            </div>
        </div>
        
        <!-- Validation message area -->
        <div id="validation-message" class="validation-message">
            <!-- Validation messages will appear here -->
        </div>
        
        <!-- Balance Sheet Content -->
        <div class="balance-sheet-container">
            <!-- Assets Column -->
            <div class="card">
                <div class="card-header">
                    <h2>Assets</h2>
                    <div class="amount" id="total-assets">$0.00</div>
                </div>
                
                <div class="account-row account-total">
                    <div>TOTAL ASSETS</div>
                    <div class="amount" id="assets-grand-total">$0.00</div>
                </div>
            </div>
            
            <!-- Liabilities & Equity Column -->
            <div class="card">
                <div class="card-header">
                    <h2>Liabilities & Equity</h2>
                    <div class="amount" id="total-liabilities-equity">$0.00</div>
                </div>
                
                <!-- Liabilities sections will be inserted here by JS -->
                
                <div class="account-row account-total">
                    <div>TOTAL LIABILITIES</div>
                    <div class="amount" id="liabilities-total">$0.00</div>
                </div>
                
                <div class="balance-sheet-section mt-4">
                    <h4 class="section-title">Equity</h4>
                    
                    <div class="account-row account-subtotal">
                        <div>Total Retained Earnings</div>
                        <div class="amount" id="retained-earnings-total">$0.00</div>
                    </div>
                    
                    <div class="account-row account-total">
                        <div>TOTAL EQUITY</div>
                        <div class="amount" id="equity-total">$0.00</div>
                    </div>
                </div>
                
                <div class="account-row account-grand-total mt-3">
                    <div>TOTAL LIABILITIES & EQUITY</div>
                    <div class="amount" id="liabilities-equity-grand-total">$0.00</div>
                </div>
            </div>
        </div>
        
        <!-- Additional Help Section -->
        <div class="help-section mt-4">
            <div class="help-title">
                <i class="fas fa-chart-line"></i>
                <h3>Interpreting Your Balance Sheet</h3>
            </div>
            <div class="help-content">
                <p><strong>What to look for in a healthy balance sheet:</strong></p>
                <ul style="margin-left: 20px; margin-bottom: var(--spacing-md);">
                    <li><strong>Current Ratio (Current Assets ÷ Current Liabilities):</strong> Should be above 1.0, ideally between 1.5-3.0.</li>
                    <li><strong>Debt-to-Equity Ratio (Total Liabilities ÷ Total Equity):</strong> Lower ratios indicate less risk. Under 2.0 is generally healthy.</li>
                    <li><strong>Working Capital (Current Assets - Current Liabilities):</strong> Positive working capital means you can cover short-term obligations.</li>
                </ul>
                
                <div class="help-tip">
                    <strong>Pro Tip:</strong> Regularly compare balance sheets over time. Growth in retained earnings typically indicates profitability, while increasing debt without corresponding asset growth may signal financial stress.
                </div>
            </div>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/balance_sheet/balance-sheet.js"></script>
</body>
</html>