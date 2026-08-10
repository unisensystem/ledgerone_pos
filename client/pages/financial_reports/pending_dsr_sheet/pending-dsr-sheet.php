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
    <title>Pending DSR Sheet - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/pending_dsr_sheet/pending-dsr-sheet.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Pending DSR Sheet</h1>
            <p>View and manage all pending DSR (Daily Sales Report) entries with recovery status</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">Search & Filter</div>
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">Sales Officer</label>
                    <select id="filter-sales-officer" class="filter-select">
                        <option value="">All Sales Officers</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Company</label>
                    <select id="filter-company" class="filter-select">
                        <option value="">All Companies</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">DSR Reference #</label>
                    <input type="text" id="filter-reference" class="filter-input"
                        placeholder="Enter DSR reference number">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Total Sheet Amount</label>
                    <select id="filter-amount" class="filter-select">
                        <option value="">All amounts</option>
                        <option value="low">Less than $1,000</option>
                        <option value="medium">$1,000 - $5,000</option>
                        <option value="high">More than $5,000</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select id="filter-status" class="filter-select">
                        <option value="">All statuses</option>
                        <option value="no-recovery">No Recovery</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-secondary" id="reset-btn">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
                <button class="btn btn-primary" id="apply-btn">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-section">
            <div class="table-header">
                <div class="table-title">DSR Entries</div>
                <div class="table-actions">
                    <button class="btn btn-ghost">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-primary" id="refresh-btn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table id="dsr-table">
                    <thead>
                        <tr>
                            <th>S#</th>
                            <th>Sales Officer</th>
                            <th>DSR Reference #</th>
                            <th>Total Sheet Amount</th>
                            <th>Total Recovery Amount</th>
                            <th>Remaining Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <!-- Table rows will be generated by JavaScript -->
                    </tbody>
                </table>

                <!-- Empty state (hidden by default) -->
                <div id="empty-state" class="empty-state" style="display: none;">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No DSR entries found</h3>
                    <p>Try adjusting your filters or check back later</p>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info" id="pagination-info">Showing 1-10 of 50 entries</div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prev-btn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">4</button>
                    <button class="pagination-btn">5</button>
                    <button class="pagination-btn" id="next-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/pending_dsr_sheet/pending-dsr-sheet.js"></script>
</body>

</html>