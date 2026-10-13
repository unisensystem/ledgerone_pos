<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency        = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Sales Man Wise Recovery Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/financial_reports/salesman_recovery/salesman-recovery.css?v=<?php echo filemtime(dirname(__FILE__) . '/../../../assets/css/financial_reports/salesman_recovery/salesman-recovery.css'); ?>">
</head>

<body>
    <div class="app-container">
        <div class="main-content" style="margin-left: 6px;">
            <!-- Topbar -->
            <div class="topbar">
                <div class="page-title">Sales Man Wise Recovery</div>
                <div class="actions">
                    <button class="btn btn-secondary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Report Container -->
            <div class="report-container">
                <div class="report-header">
                    <div class="report-header-top">
                        <div class="report-title">Sales Man Wise Recovery Report</div>
                        <div class="view-toggle-wrapper">
                            <span class="view-toggle-label">Bill View</span>
                            <label class="toggle-switch" title="Switch between per-bill and per-customer (auto-clear) view">
                                <input type="checkbox" id="customerViewToggle">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="view-toggle-label active-label" id="customerViewLabel">Customer View</span>
                        </div>
                    </div>
                    <div class="report-filters">
                        <div class="filter-item">
                            <label class="filter-label">Company</label>
                            <select class="filter-input" id="companyFilter">
                                <option value="">All Companies</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Date Range</label>
                            <input type="date" class="filter-input">
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">To</label>
                            <input type="date" class="filter-input">
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Sales Man</label>
                            <select class="filter-input" id="salesmanFilter">
                                <option value="all">All Sales Men</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
                                $stmt->execute([$_SESSION['tenant_id']]);
                                foreach ($stmt->fetchAll() as $emp) {
                                    echo '<option value="' . $emp['id'] . '">' . htmlspecialchars($emp['full_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Country</label>
                            <select class="filter-input" id="countryFilter">
                                <option value="all">All Countries</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, country_name FROM countries WHERE tenant_id = ? ORDER BY country_name");
                                $stmt->execute([$_SESSION['tenant_id']]);
                                foreach ($stmt->fetchAll() as $country) {
                                    echo '<option value="' . $country['id'] . '">' . htmlspecialchars($country['country_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Region</label>
                            <select class="filter-input" id="regionFilter" disabled>
                                <option value="all">All Regions</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">City</label>
                            <select class="filter-input" id="cityFilter" disabled>
                                <option value="all">All Cities</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">City Zone</label>
                            <select class="filter-input" id="cityZoneFilter" disabled>
                                <option value="all">All Zones</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Area</label>
                            <select class="filter-input" id="areaFilter" disabled>
                                <option value="all">All Areas</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table id="salesmanRecoveryTable">
                        <thead>
                            <tr>
                                <th>S#</th>
                                <th>Bill No</th>
                                <th>Invoice Date</th>
                                <th>Customer Name</th>
                                <th>Address</th>
                                <th>Primary Phone No</th>
                                <th>Balance</th>
                                <th>Received Amount</th>
                                <th>Sales Return</th>
                                <th>Days Overdue</th>
                                <th>Signature</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="report-footer">
                    <div class="summary">
                        <div class="summary-item">
                            <div class="summary-label">Total Balance</div>
                            <div class="summary-value balance-positive" id="totalBalanceDisplay"><?php echo $currency_symbol; ?>0.00</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Total Received</div>
                            <div class="summary-value" id="totalReceivedDisplay"><?php echo $currency_symbol; ?>0.00</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Total Returns</div>
                            <div class="summary-value" id="totalReturnsDisplay"><?php echo $currency_symbol; ?>0.00</div>
                        </div>
                    </div>
                    <div class="actions">
                        <button class="btn btn-ghost" id="resetFiltersBtn">
                            <i class="fas fa-redo"></i> Reset Filters
                        </button>
                        <button class="btn btn-ghost">
                            <i class="fas fa-file-export"></i> Export CSV
                        </button>
                        <button class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/financial_reports/salesman_recovery/salesman-recovery.js?v=<?php echo filemtime(dirname(__FILE__) . '/../../../assets/js/financial_reports/salesman_recovery/salesman-recovery.js'); ?>"></script>
</body>
</html>
