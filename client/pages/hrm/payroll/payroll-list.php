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
// 👇 ADD KARO
$initialSearch = $_GET['search'] ?? '';
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
    <title>Payroll List - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/payroll/payroll-list.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Payroll Management</h1>
            <p>View, manage, and process payroll records</p>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Filters Card -->
            <div class="filters-card">
                <div class="filters-title">
                    <i class="fas fa-filter"></i> Filter Payroll Records
                </div>
                
                <div class="filters-form">
                    <div class="filter-field">
                        <label for="dateRange">Date Range</label>
                        <select id="dateRange">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month" selected>This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="filter-field" id="customDateFields" style="display: none;">
                        <label for="startDate">Start Date</label>
                        <input type="date" id="startDate">
                    </div>
                    
                    <div class="filter-field" id="customDateFieldsEnd" style="display: none;">
                        <label for="endDate">End Date</label>
                        <input type="date" id="endDate">
                    </div>
                    
                    <div class="filter-field">
                        <label for="employeeFilter">Employee</label>
                        <input type="text" id="employeeFilter" placeholder="Search employees..." list="employeeList">
                        <datalist id="employeeList">
                            <option value="">All Employees</option>
                        </datalist>
                    </div>
                    
                    <div class="filter-actions">
                        <button class="btn btn-secondary" id="resetFiltersBtn">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button class="btn btn-primary" id="applyFiltersBtn">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div class="stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Records</span>
                        <span class="stat-value" id="totalRecords">8</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Amount</span>
                        <span class="stat-value" id="totalAmount">$42,850.40</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Pending Approval</span>
                        <span class="stat-value" id="pendingCount">3</span>
                    </div>
                </div>
                
                <div>
                    <button class="btn btn-primary" id="newPayrollBtn" onclick="window.location.href='payroll-add.php'">
                        <i class="fas fa-plus"></i> New Payroll Entry
                    </button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Payroll Records</h3>
                    <div class="table-actions">
                        <div class="search-box">
                           <input type="text" placeholder="Search payroll..." id="searchInput"
       value="<?= htmlspecialchars($initialSearch) ?>">
                        </div>
                        <button class="btn btn-icon btn-secondary" id="exportBtn" title="Export">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="btn btn-icon btn-secondary" id="refreshBtn" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                
                <table id="payrollTable">
                    <thead>
                        <tr>
                            <th>Payroll ID</th>
                            <th>Date</th>
                            <th>Company</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
                
                <!-- Empty State (hidden by default) -->
                <div class="empty-state" id="emptyState" style="display: none;">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h4>No payroll records found</h4>
                    <p>Try adjusting your filters or create a new payroll entry</p>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <span id="startRow">1</span> to <span id="endRow">8</span> of <span id="totalRows">8</span> entries
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="firstPage" disabled>
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button class="pagination-btn" id="prevPage" disabled>
                            <i class="fas fa-angle-left"></i>
                        </button>
                        <div id="pageNumbers"></div>
                        <button class="pagination-btn" id="nextPage" disabled>
                            <i class="fas fa-angle-right"></i>
                        </button>
                        <button class="pagination-btn" id="lastPage" disabled>
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal (simplified) -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 24px; border-radius: 12px; max-width: 400px; width: 90%;">
            <h3 style="margin-bottom: 12px; color: #0E1A2B;">Confirm Delete</h3>
            <p style="margin-bottom: 24px; color: #2F3B4C;">Are you sure you want to delete this payroll record? This action cannot be undone.</p>
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button class="btn btn-secondary" id="cancelDelete">Cancel</button>
                <button class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
<script src="../../../assets/js/hrm/payroll/payroll-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
</body>
</html>