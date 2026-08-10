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

// Fetch sales officers
$stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
$stmt->execute([$_SESSION['tenant_id']]);
$sales_officers = $stmt->fetchAll();

// Fetch customers
$stmt = $pdo->prepare("SELECT id, customer_name FROM customers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY customer_name");
$stmt->execute([$_SESSION['tenant_id']]);
$customers = $stmt->fetchAll();

// Fetch distributions (branches/suppliers)
$stmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers WHERE tenant_id = ? ORDER BY supplier_name");
$stmt->execute([$_SESSION['tenant_id']]);
$distributions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP | Sales Officer Recovery Report (Hierarchical)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/sales_officer_recovery/sales-officer-recovery.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-sitemap"></i> Sales Officer-wise Recovery Report (Hierarchical)</h1>
            <div>
                <button class="btn btn-secondary"><i class="fas fa-download"></i> Export</button>
                <button class="btn btn-secondary" id="print-btn"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-card-title">Total Sales Officers</div>
                <div class="summary-card-value">4</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Bills</div>
                <div class="summary-card-value">147</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Recovered</div>
                <div class="summary-card-value summary-card-positive">₹ 1,92,15,000</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Remaining Balance</div>
                <div class="summary-card-value summary-card-negative">₹ 93,27,500</div>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="card">
            <div class="card-title">Search Filters</div>
            
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="date-from">Date From</label>
                        <input type="date" id="date-from" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="date-to">Date To</label>
                        <input type="date" id="date-to" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="sales-officer">Sales Officer</label>
                        <select id="sales-officer" class="form-input">
                            <option value="">All Sales Officers</option>
                            <?php foreach ($sales_officers as $officer): ?>
                                <option value="<?php echo $officer['id']; ?>"><?php echo htmlspecialchars($officer['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="company">Company</label>
                        <select id="company" class="form-input">
                            <option value="">All Companies</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="customer">Customer</label>
                        <select id="customer" class="form-input">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['customer_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="distribution">Distribution</label>
                        <select id="distribution" class="form-input">
                            <option value="">All Distributions</option>
                            <?php foreach ($distributions as $dist): ?>
                                <option value="<?php echo $dist['id']; ?>"><?php echo htmlspecialchars($dist['supplier_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Overdue Status</label>
                        <select id="status" class="form-input">
                            <option value="">All Status</option>
                            <option value="overdue">Overdue Only</option>
                            <option value="recovered">Fully Recovered</option>
                            <option value="partial">Partially Recovered</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="include-zero-balance" style="margin-right: 8px;">
                            Include invoices with zero remaining balance
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <div class="left-actions">
                    <button id="reset-filters-btn" class="btn btn-ghost"><i class="fas fa-redo"></i> Reset Filters</button>
                </div>
                <div class="right-actions">
                    <button id="apply-filters-btn" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                </div>
            </div>
        </div>
        
        <!-- Report Card -->
        <div class="card">
            <div class="card-title">Recovery Report by Sales Officer</div>
            
            <div class="action-buttons">
                <div class="left-actions">
                    <button id="expand-all-btn" class="btn btn-secondary">
                        <i class="fas fa-expand-alt"></i> Expand All Officers
                    </button>
                    <button id="collapse-all-btn" class="btn btn-secondary">
                        <i class="fas fa-compress-alt"></i> Collapse All Officers
                    </button>
                </div>
                <div class="right-actions">
                    <button class="btn btn-primary" id="view-analytics-btn"><i class="fas fa-chart-pie"></i> View Analytics</button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>S#</th>
                            <th>Bill Date</th>
                            <th>Overdue Days</th>
                            <th>Bill No</th>
                            <th>Return Amount</th>
                            <th>Customer Name</th>
                            <th>Bill Amount</th>
                            <th>Total Recovery</th>
                            <th>Remaining Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="report-table-body">
                        <!-- Sales officer groups and transactions will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="footer">
                <div>Showing 4 Sales Officers with 147 total transactions</div>
                <div style="margin-top: var(--spacing-sm);">
                    <button class="btn btn-secondary btn-icon"><i class="fas fa-chevron-left"></i></button>
                    <button class="btn btn-ghost">1</button>
                    <button class="btn btn-ghost">2</button>
                    <button class="btn btn-ghost">3</button>
                    <button class="btn btn-ghost">...</button>
                    <button class="btn btn-ghost">15</button>
                    <button class="btn btn-secondary btn-icon"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>LedgerOne ERP • Hierarchical Sales Officer Recovery Report • Generated on: 15 Jan 2024, 10:30 AM</p>
        </div>
    </div>

    <!-- Analytics Modal -->
    <div id="analytics-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; width: 90%; max-width: 1200px; margin: 50px auto; border-radius: 12px; padding: 30px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;"><i class="fas fa-chart-line"></i> Recovery Analytics</h2>
                <button id="close-analytics-btn" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div class="card" style="padding: 20px;">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Recovery by Sales Officer</h3>
                    <canvas id="officerChart"></canvas>
                </div>
                <div class="card" style="padding: 20px;">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Recovery Status Distribution</h3>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div class="card" style="padding: 20px;">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Invoice Distribution by Sales Officer</h3>
                    <canvas id="invoiceDistributionChart"></canvas>
                </div>
                <div class="card" style="padding: 20px;">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Recovery Performance Comparison</h3>
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/financial_reports/sales_officer_recovery/sales-officer-recovery.js"></script>
</body>
</html>