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
$currency_symbol = $currency['symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Entry - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/payroll/payroll-add.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Payroll Entry</h1>
            <p>Enter payroll information for employees. Fields marked with * are required.</p>
        </div>

        <!-- Main Content Grid -->
        <div class="main-content">
            <!-- Notification for Opening Balance -->
            <div class="notification" id="balanceNotification">
                <div class="notification-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="notification-content">
                    <h4>Employee Opening Balance</h4>
                    <p>Selected employee has an opening balance of <span class="notification-balance" id="balanceAmount"><?php echo $currency_symbol; ?>0.00</span></p>
                </div>
                <button class="close-notification" id="closeNotification">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Payroll Entry Section -->
            <div class="form-section payroll-entry-section">
                <h2 class="section-title">Payroll Entry</h2>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="date" class="required">Date</label>
                        <input type="date" id="date" required>
                        <div class="helper-text">Defaults to current date</div>
                    </div>
                    
                    <div class="form-field">
                        <label for="payrollId" class="required">Payroll ID</label>
                        <input type="text" id="payrollId" readonly>
                        <div class="helper-text">Auto-generated</div>
                    </div>
                </div>
            </div>

            <!-- Payroll Table Section -->
            <div class="form-section payroll-table-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 class="section-title">Payroll Table</h2>
                    <button class="btn btn-primary" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add Row
                    </button>
                </div>
                
                <div class="table-container">
                    <table id="payrollTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Payment Method</th>
                                <th>Bank Account</th>
                                <th>Cheque Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Rows will be added dynamically -->
                        </tbody>
                    </table>
                </div>
                
                <div class="form-actions">
                    <div>
                        <button class="btn btn-ghost" id="cancelBtn">
                            Cancel
                        </button>
                    </div>
                    <div class="form-actions-right">
                        <button class="btn btn-primary" id="submitBtn">
                            Submit Payroll
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dropdown templates (hidden) -->
    <div id="employeeDropdown" class="dropdown-content">
        <div class="dropdown-search">
            <input type="text" placeholder="Search employees..." id="employeeSearch">
        </div>
        <div class="dropdown-items" id="employeeItems">
            <!-- Employee items will be added dynamically -->
        </div>
    </div>

    <div id="typeDropdown" class="dropdown-content">
        <div class="dropdown-items">
            <div class="dropdown-item" data-value="Salary">Salary</div>
            <div class="dropdown-item" data-value="Salary Adjustment">Salary Adjustment</div>
            <div class="dropdown-item" data-value="Advance">Advance</div>
            <div class="dropdown-item" data-value="Advance Return">Advance Return</div>
            <div class="dropdown-item" data-value="Commission">Commission</div>
            <div class="dropdown-item" data-value="Bonus">Bonus</div>
            <div class="dropdown-item" data-value="Allowance">Allowance</div>
            <div class="dropdown-item" data-value="Daily Wages">Daily Wages</div>
        </div>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/hrm/payroll/payroll-add.js"></script>
</body>
</html>