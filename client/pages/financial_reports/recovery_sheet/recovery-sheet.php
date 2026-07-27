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
    <title>LedgerOne ERP - Recovery Sheet Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/financial_reports/recovery_sheet/recovery-sheet.css">
</head>
<body>
    <div class="app-container">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="page-title">Recovery Management</div>
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
                    <div class="report-title">Recovery Sheet Report</div>
                    <div class="report-filters">
                        <div class="filter-item">
                            <label class="filter-label">Company</label>
                            <select class="filter-input" id="companyFilter">
                                <option value="">All Companies</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Date Range</label>
                            <input type="date" class="filter-input" value="2023-10-01">
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">To</label>
                            <input type="date" class="filter-input" value="2023-10-31">
                        </div>
                        <div class="filter-item">
                            <label class="filter-label">Recovery Agent</label>
                            <select class="filter-input" id="recoveryOfficer">
                                <option value="all">All Agents</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
                                $stmt->execute([$_SESSION['tenant_id']]);
                                $employees = $stmt->fetchAll();
                                foreach ($employees as $employee) {
                                    echo '<option value="' . $employee['id'] . '">' . htmlspecialchars($employee['full_name']) . '</option>';
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
                                $countries = $stmt->fetchAll();
                                foreach ($countries as $country) {
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
                    <table>
                        <thead>
                            <tr>
                                <th>S#</th>
                                <th>Bill No</th>
                                <th>Customer Name</th>
                                <th>Address</th>
                                <th>Primary Phone No</th>
                                <th>Balance</th>
                                <th>Received Amount</th>
                                <th>Sales Return</th>
                                <th>Signature</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <div class="report-footer">
                    <div class="summary">
                        <div class="summary-item">
                            <div class="summary-label">Total Balance</div>
                            <div class="summary-value balance-positive"><?php echo $currency_symbol; ?>0.00</div>
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
    <script src="../../../assets/js/financial_reports/recovery_sheet/recovery-sheet.js"></script>
</body>
</html>