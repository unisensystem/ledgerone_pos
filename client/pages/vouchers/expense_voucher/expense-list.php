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
$currency_symbol = $currency['symbol'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Expense Entry List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/expense_voucher/expense-list.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<style>
    
</style>

<body>
    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Expense Entry List</h1>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
<input type="text" id="globalSearch" placeholder="Search vouchers, descriptions..." value="<?= htmlspecialchars($initialSearch) ?>">
                </div>
                <button class="btn btn-primary" id="newEntryBtn">
                    <i class="fas fa-plus"></i>
                    New Expense Entry
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Vouchers</h3>
                    <div class="stat-value">0</div>
                </div>
                <div class="stat-icon total">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Amount</h3>
                    <div class="stat-value" id="totalAmountStat">0.00</div>
                </div>
                <div class="stat-icon approved">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-title">Expense Trend (Last 30 Days)</div>
                <div id="expenseTrendChart"></div>
            </div>
            <div class="chart-card">
                <div class="chart-title">Top Expense Accounts</div>
                <div id="topAccountsChart"></div>
            </div>
            <div class="chart-card">
                <div class="chart-title">Top Cost Centers</div>
                <div id="topCostCentersChart"></div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="filters-card">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filter Expenses
            </div>

            <div class="filters-grid">
                <div class="form-group">
                    <label for="fromDate">From Date</label>
                    <input type="date" id="fromDate">
                </div>

                <div class="form-group">
                    <label for="toDate">To Date</label>
                    <input type="date" id="toDate">
                </div>

                <div class="form-group">
                    <label for="filterCompany">Company</label>
                    <select id="filterCompany">
                        <option value="">All Companies</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterExpenseAccount">Expense Account</label>
                    <div class="searchable-dropdown">
                        <input type="text" id="filterExpenseAccount" placeholder="Search expense account...">
                        <div class="dropdown-options" id="filterExpenseAccountOptions"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="filterProject">Project</label>
                    <select id="filterProject">
                        <option value="">All Projects</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterCostCenter">Cost Center</label>
                    <div class="searchable-dropdown">
                        <input type="text" id="filterCostCenter" placeholder="Search cost center...">
                        <div class="dropdown-options" id="filterCostCenterOptions"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="minAmount">Minimum Amount</label>
                    <input type="number" id="minAmount" placeholder="0.00" min="0" step="0.01">
                </div>

                <div class="form-group">
                    <label for="maxAmount">Maximum Amount</label>
                    <input type="number" id="maxAmount" placeholder="0.00" min="0" step="0.01">
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-ghost" id="resetFiltersBtn">
                    <i class="fas fa-redo"></i>
                    Reset Filters
                </button>
                <button class="btn btn-primary" id="applyFiltersBtn">
                    <i class="fas fa-check"></i>
                    Apply Filters
                </button>
            </div>
        </div>

        <!-- List Card -->
        <div class="list-card">
            <div class="list-header">
                <div class="list-title">Expense Vouchers</div>
                <div class="list-actions">
                    <div class="dropdown-wrapper">
                        <button class="btn btn-secondary" id="exportBtn">
                            <i class="fas fa-download"></i>
                            Export
                            <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 10px;"></i>
                        </button>
                        <div class="dropdown-menu" id="exportDropdown">
                            <button class="dropdown-item" id="exportExcel">
                                <i class="fas fa-file-excel"></i>
                                Export to Excel
                            </button>
                            <button class="dropdown-item" id="exportJSON">
                                <i class="fas fa-file-code"></i>
                                Export JSON
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table" id="expenseTable">
                    <thead>
                        <tr>
                            <th>Voucher #</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Project</th>
                            <th>Expense Account</th>
                            <th>Cost Center</th>
                            <th>Amount</th>
                            <th>Cash Flow</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 1 to 10 of 42 entries</div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevPage" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">4</button>
                    <button class="pagination-btn">5</button>
                    <button class="pagination-btn" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Confirm Deletion</div>
                <button class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete voucher <strong id="voucherToDelete">VCH-2023-00127</strong>?</p>
                <p class="subtext" style="color: var(--subtext); margin-top: 8px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDelete">Cancel</button>
                <button class="btn btn-primary" id="confirmDelete" style="background-color: var(--error);">Delete
                    Voucher</button>
            </div>
        </div>
    </div>

    <!-- View Voucher Modal -->
    <div class="modal" id="viewVoucherModal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <div class="modal-title">Voucher Details</div>
                <button class="modal-close" id="closeViewModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px;">
                    <div>
                        <strong>Voucher #:</strong>
                        <p id="viewVoucherNo"></p>
                    </div>
                    <div>
                        <strong>Date:</strong>
                        <p id="viewDate"></p>
                    </div>
                    <div>
                        <strong>Company:</strong>
                        <p id="viewCompany"></p>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <strong>Description:</strong>
                        <p id="viewDescription"></p>
                    </div>
                    <div>
                        <strong>Vendor:</strong>
                        <p id="viewVendor"></p>
                    </div>
                    <div>
                        <strong>Type:</strong>
                        <p id="viewType"></p>
                    </div>
                    <div>
                        <strong>Effect on Cash Flow:</strong>
                        <p id="viewCashFlow"></p>
                    </div>
                </div>
                <h4 style="margin-bottom: 12px;">Expense Lines</h4>
                <div class="table-wrapper">
                    <table class="entries-table">
                        <thead>
                            <tr>
                                <th>Expense Account</th>
                                <th>Cost Center</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Bank Account</th>
                                <th>Cheque No</th>
                                <th>Cheque Date</th>
                            </tr>
                        </thead>
                        <tbody id="viewLinesBody">
                        </tbody>
                    </table>
                </div>
                <div style="text-align: right; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-default);">
                    <strong>Total Amount: <span id="viewTotalAmount"></span></strong>
                </div>
            </div>
        </div>
    </div>

   <script src="../../../assets/js/vouchers/expense_voucher/expense-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('globalSearch');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
</body>

</html>