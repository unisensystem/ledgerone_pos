<?php
require_once '../../../../includes/dashboard.php';
require_once '../../../../includes/connection.php';
require_once '../../../../includes/role-helper.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
// Employee location data — gate the page itself, not just its API calls, so
// a non-admin who knows/guesses this URL sees the Admin Panel home instead
// of a broken page full of failed requests.
if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    header('Location: panel.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP | Live Tracking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" integrity="sha384-c6Rcwz4e4CITMbu/NBmnNS8yN2sC3cUElMEMfP3vqqKFp7GOYaaBBCqmaWBjmkjb" crossorigin="anonymous">
    <link rel="stylesheet" href="../../../assets/css/system_setup/admin_panel/panel.css">
    <link rel="stylesheet" href="../../../assets/css/system_setup/admin_panel/officer-tracking.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="app-container">
        <main class="main-content">
            <div class="content-area">
                <a href="panel.php" class="ot-back-link"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a>

                <div class="ot-header">
                    <div>
                        <h1 class="ot-title">Live Tracking</h1>
                        <p class="ot-subtitle">Officers currently using the mobile app, and where they've been today</p>
                    </div>
                    <div class="ot-header-right">
                        <span class="ot-updated" id="otLastUpdated">—</span>
                        <button class="btn btn-secondary btn-sm" id="otManageAccessBtn"><i class="fas fa-user-gear"></i> Manage Access</button>
                        <button class="btn btn-secondary btn-sm" id="otRefreshBtn"><i class="fas fa-rotate"></i> Refresh</button>
                    </div>
                </div>

                <div class="ot-tabs">
                    <button class="ot-tab active" data-tab="map"><i class="fas fa-map-location-dot"></i> Live Map</button>
                    <button class="ot-tab" data-tab="report"><i class="fas fa-table-list"></i> History Report</button>
                </div>

                <div class="ot-panel active" id="otMapPanel">
                    <div class="ot-layout">
                        <div class="ot-sidebar">
                            <div class="ot-sidebar-header">
                                <span>Online Officers</span>
                                <span class="ot-count-badge" id="otOnlineCount">0</span>
                            </div>
                            <div class="ot-officer-list" id="otOfficerList">
                                <div class="ot-empty"><i class="fas fa-location-dot"></i> No officers online right now</div>
                            </div>
                        </div>
                        <div class="ot-map-wrap">
                            <div id="otMap"></div>
                            <div class="ot-map-legend">
                                <span><i class="fas fa-circle" style="color:#2fbf71;"></i> Moving</span>
                                <span><i class="fas fa-circle" style="color:#d97706;"></i> Stopped</span>
                                <span><i class="fas fa-circle" style="color:#9ca3af;"></i> Offline</span>
                                <span><i class="fas fa-minus" style="color:#1f7bff;"></i> Today's route</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ot-panel" id="otReportPanel">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Location History Report</div>
                        </div>
                        <div class="ot-report-filters">
                            <div class="form-group">
                                <label class="form-label">From</label>
                                <input type="date" class="form-input" id="otReportFrom">
                            </div>
                            <div class="form-group">
                                <label class="form-label">To</label>
                                <input type="date" class="form-input" id="otReportTo">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Officer</label>
                                <select class="form-select" id="otReportUserFilter">
                                    <option value="">All Officers</option>
                                </select>
                            </div>
                            <button class="btn btn-primary" id="otReportLoadBtn"><i class="fas fa-magnifying-glass"></i> Load</button>
                        </div>
                        <div class="table-container">
                            <table id="otReportTable">
                                <thead>
                                    <tr>
                                        <th>Officer</th>
                                        <th>Date</th>
                                        <th>First Seen</th>
                                        <th>Last Seen</th>
                                        <th>Distance (km)</th>
                                        <th>Stops</th>
                                    </tr>
                                </thead>
                                <tbody id="otReportTableBody">
                                    <tr><td colspan="6" class="ot-empty">Pick a date range and click Load</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MANAGE ACCESS MODAL -->
    <div id="otAccessModal" class="modal-overlay">
        <div class="modal" style="max-width: 560px;">
            <div class="modal-header">
                <div class="modal-title">Manage Tracking Access</div>
                <button class="modal-close" id="otAccessModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 16px; font-size: 13px;">
                    Turn tracking off for anyone who shouldn't be tracked. They'll see this in their app
                    immediately and won't be able to turn it back on themselves.
                </p>
                <div id="otAccessList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="otAccessModalClose2">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" integrity="sha384-NElt3Op+9NBMCYaef5HxeJmU4Xeard/Lku8ek6hoPTvYkQPh3zLIrJP7KiRocsxO" crossorigin="anonymous"></script>
    <script src="../../../assets/js/system_setup/admin_panel/officer-tracking.js?v=<?php echo time(); ?>"></script>
</body>

</html>
