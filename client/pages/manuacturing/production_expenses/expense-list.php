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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>LedgerOne · Production Expenses List</title>
    <!-- Google Fonts: Inter + IBM Plex Mono -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=IBM+Plex+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_expenses/expense-list.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="app-container">

        <div class="page">
            <div class="header-actions">
                <div class="page-title">
                    <h1>Production Expenses</h1>
                    <p>Manage and review all manufacturing cost entries</p>
                </div>
                <button class="btn btn-secondary" onclick="window.location.href='expense-add.php'" style="margin-left:auto;"><i class="fas fa-plus"></i> Add Expense</button>
                <button class="btn btn-secondary" onclick="window.location.href='unit-cost-analysis.php'"><i class="fas fa-chart-line"></i> Unit Costs</button>
                <button class="btn btn-secondary" onclick="window.location.href='costing-config.php'"><i class="fas fa-cog"></i> Costing Config</button>
                <button class="btn btn-secondary" onclick="adjustPeriodCosts()"><i class="fas fa-calculator"></i> Adjust Period Costs</button>
                <button class="btn btn-secondary" onclick="reverseAdjustment()"><i class="fas fa-undo"></i> Reverse Adjustment</button>
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> Date From</label>
                    <input type="date" id="filterDateFrom">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> Date To</label>
                    <input type="date" id="filterDateTo">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Production Order</label>
                    <input type="text" id="filterPo" placeholder="PO-XXXXX">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-flag"></i> Status</label>
                    <select id="filterStatus">
                        <option value="">All</option>
                        <option value="Posted">Posted</option>
                        <option value="Draft">Draft</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" onclick="applyFilters()" style="width:100%;"><i
                            class="fas fa-search"></i> Apply</button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table" id="expenseTable">
                        <thead>
                            <tr>
                                <th>Entry No.</th>
                                <th>Date</th>
                                <th>Production Order</th>
                                <th>Finished Good</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- dynamic rows -->
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <button id="prevPageBtn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i> Prev</button>
                    <span class="page-info" id="pageInfo">Page 1 of 1</span>
                    <button id="nextPageBtn" onclick="changePage(1)">Next <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div class="toast" id="toast"><i class="fas fa-check-circle" id="toastIcon"></i>
            <div><strong id="toastTitle"></strong>
                <div id="toastMsg"></div>
            </div>
        </div>

        <!-- Reverse Adjustment Modal -->
        <div class="modal-overlay" id="reverseModal" style="display:none;">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-undo"></i> Reverse Cost Adjustment</h3>
                    <button class="modal-close" onclick="closeReverseModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="color:var(--subtext); font-size:13px; margin-bottom:20px;">
                        This will restore original costs before adjustment for the selected period.
                    </p>
                    <div class="field">
                        <label>Period From <span class="req">*</span></label>
                        <input type="date" id="reverseDateFrom" class="modal-input">
                    </div>
                    <div class="field" style="margin-top:16px;">
                        <label>Period To <span class="req">*</span></label>
                        <input type="date" id="reverseDateTo" class="modal-input">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeReverseModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="confirmReverseAdjustment()"><i class="fas fa-undo"></i> Reverse Adjustment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/manufacturing/production_expenses/expense-list.js?v=<?php echo time(); ?>"></script>
</body>

</html>