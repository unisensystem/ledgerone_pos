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
    <title>LedgerOne ERP | Employee Ledger Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/hrm/employees_ledger/employee-ledger.css">
</head>
<body>
    <script>
        window.currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Employee Ledger Report</h1>
            <div class="btn-group">
                <button class="btn btn-secondary" id="scanBarcodeBtn">
                    <i class="fas fa-barcode"></i> Scan Barcode
                </button>
                <button class="btn btn-secondary" id="printBtn">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button class="btn btn-secondary" id="exportBtn">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="card">
            <div class="card-header">
                <h2>Report Filters</h2>
            </div>
            <div class="card-body">
                <div class="filters">
                    <div class="form-group">
                        <label class="form-label">Date Range</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="date" class="form-control" id="startDate" value="2023-01-01">
                            <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select class="form-control" id="employeeSelect">
                            <option value="all">All Employees</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select class="form-control" id="departmentSelect">
                            <option value="all">All Departments</option>
                            <option value="sales">Sales</option>
                            <option value="finance">Finance</option>
                            <option value="hr">Human Resources</option>
                            <option value="it">IT</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="display: block;">&nbsp;</label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="showZeroBalance" style="width: auto;">
                            <span>Show Zero Balance Records</span>
                        </label>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-primary" id="generateReport">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                    <button class="btn btn-secondary" id="resetFilters">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Barcode Scanner Modal -->
        <div id="barcodeScannerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
            <div style="background: white; padding: 20px; border-radius: 12px; max-width: 500px; width: 90%; text-align: center;">
                <h3 style="margin-bottom: 20px;">Scan Employee Barcode</h3>
                <video id="barcodeScannerVideo" style="width: 100%; max-width: 400px; border-radius: 8px; margin-bottom: 20px;"></video>
                <div id="scannerStatus" style="margin-bottom: 20px; color: var(--body);">Initializing camera...</div>
                <button class="btn btn-secondary" id="closeScannerBtn">Close Scanner</button>
            </div>
        </div>
        
        <!-- View Toggle -->
        <div class="view-toggle">
            <button class="view-toggle-btn active" id="summaryViewBtn">
                <i class="fas fa-list"></i> Summary View
            </button>
            <button class="view-toggle-btn" id="individualViewBtn">
                <i class="fas fa-user"></i> Individual View
            </button>
        </div>
        
        <!-- Report Data Card -->
        <div class="card">
            <div class="card-header">
                <h2 id="reportTitle">Employee Ledger Summary</h2>
            </div>
            <div class="card-body">
                <!-- Summary View Table -->
                <div id="summaryView" class="table-container">
                    <table id="summaryTable">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Opening Balance</th>
                                <th>Total Debit (Dr)</th>
                                <th>Total Credit (Cr)</th>
                                <th>Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody id="summaryTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Individual View Table -->
                <div id="individualView" class="table-container" style="display: none;">
                    <table id="individualTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher No.</th>
                                <th>Particulars</th>
                                <th>Debit Amount (Dr)</th>
                                <th>Credit Amount (Cr)</th>
                                <th>Balance</th>
                                <th>Balance Type</th>
                            </tr>
                        </thead>
                        <tbody id="individualTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Loading Indicator -->
                <div class="loading" id="loadingIndicator" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Generating report...
                </div>
                
                <!-- No Data Message -->
                <div class="no-data" id="noDataMessage" style="display: none;">
                    <i class="fas fa-chart-bar"></i>
                    <p>No data available for the selected filters. Try adjusting your criteria.</p>
                </div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="help-section">
            <h3><i class="fas fa-info-circle"></i> Understanding the Employee Ledger Report</h3>
            <p>This report shows the financial transactions for employees during the selected period.</p>
            
            <ul class="help-list">
                <li><strong>Opening Balance:</strong> The balance at the start of the selected period.</li>
                <li><strong>Closing Balance:</strong> The balance at the end of the selected period (Opening Balance + Total Debit - Total Credit).</li>
                <li><strong>Dr (Debit):</strong> Advances given to employee (employee owes company).</li>
                <li><strong>Cr (Credit):</strong> Advance returns from employee (employee pays back) or Salary Adjustments (deducted from salary).</li>
                <li><strong>Note:</strong> Salary and other payments are shown for record but don't affect the balance.</li>
                <li><strong>Click on a row</strong> in the Summary View to see individual transactions for that employee.</li>
            </ul>
        </div>
        
        <!-- Footer Actions -->
        <div class="footer-actions">
            <div class="btn-group">
                <button class="btn btn-secondary" id="showHelp">
                    <i class="fas fa-question-circle"></i> Show Help
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script src="../../../assets/js/hrm/employees_ledger/employee-ledger.js"></script>
</body>
</html>