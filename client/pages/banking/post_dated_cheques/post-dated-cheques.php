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
    <title>PDCs Report | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../../../assets/css/banking/post_dated_cheques/post-dated-cheques.css">
</head>

<body>
    <div class="container">

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h1 class="page-title">Post Dated Cheques (PDCs) Report</h1>
            </div>

            <!-- Ledger Settings -->
            <div class="filters-container" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" id="pending-pdc-ledger-toggle" style="width: auto; cursor: pointer;">
                    <span style="font-weight: 600;">Pending Cheques Affect Customer Ledger Balance</span>
                </label>
                <small style="color: var(--subtext, #6b7280); display: block; margin-top: 6px;">
                    By default a Pending cheque is shown on the Customer Ledger for reference only and does not count toward the Dr/Cr totals or closing balance — it starts affecting the ledger once Approved. Turn this on if you want Pending cheques to count immediately.
                </small>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total PDCs</div>
                        <div class="stat-icon" style="background-color: #1F7BFF;">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="total-pdcs">124</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        12% from last month
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Pending Approval</div>
                        <div class="stat-icon" style="background-color: #E8B23F;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="pending-pdcs">42</div>
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-down"></i>
                        5% from last week
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Amount</div>
                        <div class="stat-icon" style="background-color: #2FBF71;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="total-amount">$245,850</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        8% from last month
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Avg. Cheque Value</div>
                        <div class="stat-icon" style="background-color: #E34F4F;">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="avg-amount">$1,983</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        3% from last month
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-container">
                <h3 class="filters-title">Search & Filter PDCs</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Cheque No</label>
<input type="text" class="form-input" id="cheque-no-filter" placeholder="Enter cheque number" value="<?= htmlspecialchars($initialSearch) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account</label>
                        <input type="text" class="form-input" id="account-filter" placeholder="Filter by account">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bank Name</label>
                        <select class="form-select" id="bank-filter">
                            <option value="">All Banks</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status-filter">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Transaction Type</label>
                        <select class="form-select" id="type-filter">
                            <option value="">All Types</option>
                            <option value="Received">Received</option>
                            <option value="Paid">Paid</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Company</label>
                        <select class="form-select" id="company-filter">
                            <option value="">All Companies</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="date-filter">
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="quarter">This Quarter</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Min Amount</label>
                        <input type="number" class="form-input" id="min-amount" placeholder="$0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Max Amount</label>
                        <input type="number" class="form-input" id="max-amount" placeholder="$10,000">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-secondary" id="reset-filters">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button class="btn btn-primary" id="apply-filters">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">PDCs List</h3>
                    <div class="table-actions">
                        <button class="btn btn-ghost">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-primary" id="add-pdc">
                            <i class="fas fa-plus"></i> Add PDC
                        </button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="pdcs-table">
                        <thead>
                            <tr>
                                <th>Cheque No</th>
                                <th>Account</th>
                                <th>Bank Name</th>
                                <th>Transaction Type</th>
                                <th>Amount</th>
                                <th>Cheque Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pdcs-table-body">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <div class="pagination-info" id="pagination-info">Showing 1-10 of 124 PDCs</div>
                    <div class="pagination-controls" id="pagination-controls">
                        <button class="pagination-btn" id="prev-page">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="pagination-btn" id="next-page">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="chart-container">
                <h3 class="chart-title">PDCs Overview</h3>
                <div class="chart-wrapper">
                    <canvas id="pdcs-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmation-modal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Confirm Status Change</h3>
            <p class="modal-message" id="modal-message"></p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="modal-cancel">Cancel</button>
                <button class="btn btn-primary" id="modal-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Approve Modal with Bank Selection -->
    <div id="approve-modal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title"><i class="fas fa-check-circle" style="color:#2FBF71;margin-right:8px;"></i>Approve PDC</h3>
            <p style="margin-bottom:16px;color:var(--subtext,#6b7280);font-size:14px;">Cheque <strong id="approve-cheque-no"></strong> — Amount: <strong id="approve-amount"></strong></p>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label" for="approve-bank-select">Deposit to Bank Account</label>
                <select class="form-select" id="approve-bank-select">
                    <option value="">Loading...</option>
                </select>
                <small style="color:var(--subtext,#6b7280);font-size:12px;margin-top:4px;display:block;">Original bank shown by default. Change if depositing to a different bank.</small>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="approve-modal-cancel">Cancel</button>
                <button class="btn btn-primary" id="approve-modal-confirm" style="background:#2FBF71;border-color:#2FBF71;">
                    <i class="fas fa-check"></i> Approve & Post
                </button>
            </div>
        </div>
    </div>

    <!-- PDC Payments View Modal -->
    <div id="pdc-payments-modal" class="modal">
        <div class="modal-content" style="max-width:560px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 class="modal-title" style="margin:0;"><i class="fas fa-eye" style="color:#1f7bff;margin-right:8px;"></i>PDC Payment History</h3>
                <button id="pdc-payments-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#6b7280;">&times;</button>
            </div>
            <div id="pdc-payments-body"></div>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
<script src="../../../assets/js/banking/post_dated_cheques/post-dated-cheques.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const applyBtn = document.getElementById('apply-filters');
        if (applyBtn) applyBtn.click();
    });
</script>
<?php endif; ?>
</body>

</html>