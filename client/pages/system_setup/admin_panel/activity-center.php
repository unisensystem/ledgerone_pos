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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP | Activity & Audit Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/system_setup/admin_panel/panel.css">
</head>
<style>
.ac-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--subtext);
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 12px;
}
.ac-back-link:hover { color: var(--primary); }

.ac-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.ac-header h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--heading);
    margin: 0 0 4px 0;
}
.ac-header p {
    color: var(--subtext);
    font-size: 13px;
    margin: 0;
    max-width: 640px;
}

.ac-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 900px) {
    .ac-stats { grid-template-columns: repeat(2, 1fr); }
}
.ac-stat-card {
    background: var(--surface-card, #fff);
    border: 1px solid var(--border-default);
    border-radius: var(--radius);
    padding: 16px;
}
.ac-stat-value {
    font-size: 26px;
    font-weight: 700;
    color: var(--heading);
    line-height: 1.2;
}
.ac-stat-label {
    color: var(--subtext);
    font-size: 12px;
    margin-top: 4px;
}

.ac-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 4px;
}
.ac-tabgroup {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 6px;
    background: rgba(31, 123, 255, 0.05);
    border-radius: var(--radius);
}
.ac-tabgroup-label {
    display: flex;
    align-items: center;
    padding: 0 8px 0 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--subtext);
}
.ac-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: var(--radius);
    border: 1px solid transparent;
    background: transparent;
    color: var(--subtext);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.ac-tab:hover {
    color: var(--primary);
    background: rgba(31, 123, 255, 0.08);
}
.ac-tab.active {
    background: var(--primary);
    color: #fff;
}
.ac-tab-primary {
    border: 1px solid var(--border-default);
    padding: 10px 18px;
}
.ac-tab-primary.active {
    border-color: var(--primary);
}
.ac-tab-danger {
    border: 1px solid var(--error);
    color: var(--error);
    padding: 10px 18px;
    margin-left: auto;
}
.ac-tab-danger:hover {
    background: rgba(227, 79, 79, 0.08);
    color: var(--error);
}
.ac-tab-danger.active {
    background: var(--error);
    color: #fff;
}

.ac-panel { display: none; }
.ac-panel.active { display: block; }

.ac-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

/* Searchable module dropdown - matches the Customer Code dropdown pattern used on
   client/pages/sale/pos_invoice/pos-add.php, instead of a native <select>/<datalist>. */
.searchable-dropdown {
    position: relative;
}
.searchable-dropdown .search-input {
    cursor: text;
}
.searchable-dropdown .dropdown-options {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 4px;
    background: var(--surface-0, #fff);
    border: 1px solid var(--border-default);
    border-radius: var(--radius);
    max-height: 220px;
    overflow-y: auto;
    z-index: 500;
    display: none;
    box-shadow: var(--shadow-lg, 0 4px 12px rgba(0, 0, 0, 0.08));
}
.searchable-dropdown .dropdown-option {
    padding: 9px 12px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-default);
    color: var(--body, var(--heading));
    font-size: 13px;
}
.searchable-dropdown .dropdown-option:last-child {
    border-bottom: none;
}
.searchable-dropdown .dropdown-option:hover {
    background: rgba(31, 123, 255, 0.08);
}
.searchable-dropdown .dropdown-option.selected {
    background: var(--primary);
    color: #fff;
}
.searchable-dropdown .dropdown-option.ac-option-all {
    font-weight: 600;
    color: var(--primary);
}

.ac-empty {
    text-align: center;
    padding: 48px 16px;
    color: var(--subtext);
}
.ac-empty i { font-size: 28px; margin-bottom: 10px; display: block; opacity: 0.5; }

.ac-loading { text-align: center; padding: 40px; color: var(--subtext); }

.ac-scroll-table {
    max-height: 320px;
    overflow-y: auto;
}
.ac-scroll-table thead th {
    position: sticky;
    top: 0;
    background: var(--surface-card, #fff);
    z-index: 1;
}

.ac-subsection-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--heading);
    margin: 24px 0 12px 0;
    padding-top: 20px;
    border-top: 1px solid var(--border-default);
}

.ac-report-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 24px;
}
@media (max-width: 900px) {
    .ac-report-grid { grid-template-columns: 1fr; }
}

.ac-module-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 13px;
}
.ac-module-name {
    width: 130px;
    flex-shrink: 0;
    color: var(--heading);
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ac-module-bar-track {
    flex: 1;
    background: rgba(31, 123, 255, 0.08);
    border-radius: 4px;
    height: 10px;
    overflow: hidden;
}
.ac-module-bar-fill {
    background: var(--primary);
    height: 100%;
    border-radius: 4px;
}
.ac-module-count {
    width: 36px;
    text-align: right;
    color: var(--subtext);
    font-weight: 600;
    flex-shrink: 0;
}

.btn-danger {
    background-color: var(--error);
    color: #fff;
    border: 1px solid var(--error);
}
.btn-danger:hover:not(:disabled) { background-color: #cc3f3f; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }

.ac-danger-card {
    border: 1px solid rgba(227, 79, 79, 0.3);
}
.ac-danger-summary {
    background: rgba(227, 79, 79, 0.08);
    border: 1px solid rgba(227, 79, 79, 0.3);
    border-radius: var(--radius);
    padding: 12px 16px;
    font-size: 13px;
    color: var(--heading);
}
.ac-danger-summary b { color: var(--error); }

.ac-snapshot-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ac-snapshot-table td { padding: 6px 8px; border-bottom: 1px solid var(--border-default); vertical-align: top; }
.ac-snapshot-table td:first-child { font-weight: 600; color: var(--heading); width: 40%; white-space: nowrap; }
.ac-snapshot-table td:last-child { color: var(--subtext); word-break: break-word; }
</style>

<body>
    <div class="app-container">
        <main class="main-content">
            <div class="content-area">
                <a href="panel.php" class="ac-back-link"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a>

                <div class="ac-header">
                    <div>
                        <h1>Activity & Audit Center</h1>
                        <p>One place for everything about who did what: the full business audit trail (created, updated, deleted records with before/after details), sign-in activity (history, failures, locations), and reports.</p>
                    </div>
                </div>

                <div class="ac-stats">
                    <div class="ac-stat-card">
                        <div class="ac-stat-value" id="statTodayActivities">-</div>
                        <div class="ac-stat-label">Activities Today</div>
                    </div>
                    <div class="ac-stat-card">
                        <div class="ac-stat-value" id="statTodayLogins">-</div>
                        <div class="ac-stat-label">Successful Logins Today</div>
                    </div>
                    <div class="ac-stat-card">
                        <div class="ac-stat-value" id="statTodayFailedLogins">-</div>
                        <div class="ac-stat-label">Failed Logins Today</div>
                    </div>
                    <div class="ac-stat-card">
                        <div class="ac-stat-value" id="statDeletions30d">-</div>
                        <div class="ac-stat-label">Deletions (Last 30 Days)</div>
                    </div>
                </div>

                <div class="card">
                    <div class="ac-tabs">
                        <button class="ac-tab ac-tab-primary active" data-tab="audit-trail" onclick="acSwitchTab('audit-trail')">
                            <i class="fas fa-clipboard-list"></i> Audit Trail
                        </button>
                        <button class="ac-tab ac-tab-primary" data-tab="login-activity" onclick="acSwitchTab('login-activity')">
                            <i class="fas fa-user-shield"></i> Login Activity
                        </button>
                        <div class="ac-tabgroup">
                            <span class="ac-tabgroup-label">Reports</span>
                            <button class="ac-tab" data-tab="report-overview" onclick="acSwitchTab('report-overview')">
                                <i class="fas fa-chart-bar"></i> Summary
                            </button>
                            <button class="ac-tab" data-tab="deleted-records" onclick="acSwitchTab('deleted-records')">
                                <i class="fas fa-trash-restore"></i> Deleted Records
                            </button>
                            <button class="ac-tab" data-tab="user-activity" onclick="acSwitchTab('user-activity')">
                                <i class="fas fa-user-clock"></i> User Activity
                            </button>
                        </div>
                        <button class="ac-tab ac-tab-danger" id="manageLogsTabBtn" data-tab="manage-logs" onclick="acSwitchTab('manage-logs')" hidden>
                            <i class="fas fa-user-shield"></i> Manage Logs
                        </button>
                    </div>
                </div>

                <!-- LOGIN ACTIVITY PANEL (History + Failed + Locations combined in one view) -->
                <div class="card ac-panel" id="panel-login-activity">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history"></i> Login History</div>
                        <label style="font-size:13px; color:var(--subtext); display:flex; align-items:center; gap:6px;">Show
                            <select id="loginActivityPageSize" class="form-input" style="max-width: 90px;" onchange="acChangeLoginActivityPageSize()">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>
                    </div>
                    <div class="table-container ac-scroll-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Login Time</th>
                                    <th>IP Address</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="loginHistoryBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex" style="justify-content: space-between; align-items: center; margin: 10px 0;">
                        <div id="loginHistoryInfo" style="color: var(--subtext); font-size: 13px;"></div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-secondary btn-sm" id="loginHistoryPrevBtn" onclick="loginHistoryGoToPage(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="btn btn-secondary btn-sm" id="loginHistoryNextBtn" onclick="loginHistoryGoToPage(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="ac-subsection-header"><i class="fas fa-exclamation-triangle"></i> Failed Login Attempts</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Attempt Time</th>
                                    <th>IP Address</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody id="failedLoginsBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex" style="justify-content: space-between; align-items: center; margin: 10px 0;">
                        <div id="failedLoginsInfo" style="color: var(--subtext); font-size: 13px;"></div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-secondary btn-sm" id="failedLoginsPrevBtn" onclick="failedLoginsGoToPage(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="btn btn-secondary btn-sm" id="failedLoginsNextBtn" onclick="failedLoginsGoToPage(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="ac-subsection-header"><i class="fas fa-map-marker-alt"></i> Login Locations</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Location</th>
                                    <th>IP Address</th>
                                    <th>Last Login</th>
                                    <th>Login Count</th>
                                </tr>
                            </thead>
                            <tbody id="loginLocationsBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex" style="justify-content: space-between; align-items: center; margin: 10px 0;">
                        <div id="loginLocationsInfo" style="color: var(--subtext); font-size: 13px;"></div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-secondary btn-sm" id="loginLocationsPrevBtn" onclick="loginLocationsGoToPage(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="btn btn-secondary btn-sm" id="loginLocationsNextBtn" onclick="loginLocationsGoToPage(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <!-- AUDIT TRAIL PANEL -->
                <div class="card ac-panel" id="panel-audit-trail">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-clipboard-list"></i> Activity Log</div>
                    </div>
                    <div class="ac-filters">
                        <div class="searchable-dropdown" style="max-width: 200px;">
                            <input type="text" class="form-input search-input" id="activityLogModuleFilter" placeholder="All Modules" autocomplete="off">
                            <div class="dropdown-options" id="activityLogModuleOptions"></div>
                        </div>
                        <select id="activityLogActionFilter" class="form-input" style="max-width: 160px;" onchange="fetchActivityLog(1)">
                            <option value="">All Actions</option>
                            <option value="create">Create</option>
                            <option value="update">Update</option>
                            <option value="delete">Delete</option>
                        </select>
                        <input type="date" id="activityLogFromDate" class="form-input" style="max-width: 160px;" onchange="fetchActivityLog(1)">
                        <input type="date" id="activityLogToDate" class="form-input" style="max-width: 160px;" onchange="fetchActivityLog(1)">
                        <input type="text" id="activityLogSearch" class="form-input" placeholder="Search user, record, description..." style="max-width: 260px;" onkeyup="if(event.key==='Enter') fetchActivityLog(1)">
                        <select id="activityLogPageSize" class="form-input" style="max-width: 130px;" onchange="fetchActivityLog(1)">
                            <option value="10">Show 10</option>
                            <option value="25" selected>Show 25</option>
                            <option value="50">Show 50</option>
                            <option value="100">Show 100</option>
                        </select>
                        <button class="btn btn-secondary" onclick="fetchActivityLog(1)"><i class="fas fa-search"></i> Search</button>
                        <button class="btn btn-secondary" onclick="acClearFilters()"><i class="fas fa-times"></i> Clear</button>
                        <button class="btn btn-secondary" onclick="exportActivityLogCsv(event)"><i class="fas fa-file-csv"></i> Export CSV</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Record</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody id="activityLogBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex" style="justify-content: space-between; align-items: center; margin-top: 12px;">
                        <div id="activityLogInfo" style="color: var(--subtext); font-size: 13px;"></div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-secondary" id="activityLogPrevBtn" onclick="activityLogGoToPage(-1)"><i class="fas fa-chevron-left"></i> Previous</button>
                            <button class="btn btn-secondary" id="activityLogNextBtn" onclick="activityLogGoToPage(1)">Next <i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <!-- SUMMARY REPORT PANEL -->
                <div class="card ac-panel" id="panel-report-overview">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-chart-bar"></i> Activity Summary</div>
                    </div>
                    <div class="ac-report-grid">
                        <div>
                            <h4 style="margin: 0 0 12px 0; color: var(--heading); font-size: 14px;">Daily Activity (Last 7 Days)</h4>
                            <canvas id="dailyActivityChart" width="600" height="220" style="width:100%; max-width:600px; height:220px;"></canvas>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 12px 0; color: var(--heading); font-size: 14px;">Module-wise Activity (Last 30 Days)</h4>
                            <div id="moduleBreakdownList"></div>
                        </div>
                    </div>
                </div>

                <!-- DELETED RECORDS REPORT PANEL -->
                <div class="card ac-panel" id="panel-deleted-records">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-trash-restore"></i> Deleted Records</div>
                    </div>
                    <div class="ac-filters">
                        <div class="searchable-dropdown" style="max-width: 200px;">
                            <input type="text" class="form-input search-input" id="deletedModuleFilter" placeholder="All Modules" autocomplete="off">
                            <div class="dropdown-options" id="deletedModuleOptions"></div>
                        </div>
                        <input type="date" id="deletedFromDate" class="form-input" style="max-width: 160px;" onchange="fetchDeletedRecords(1)">
                        <input type="date" id="deletedToDate" class="form-input" style="max-width: 160px;" onchange="fetchDeletedRecords(1)">
                        <input type="text" id="deletedSearch" class="form-input" placeholder="Search user, record, description..." style="max-width: 260px;" onkeyup="if(event.key==='Enter') fetchDeletedRecords(1)">
                        <select id="deletedPageSize" class="form-input" style="max-width: 130px;" onchange="fetchDeletedRecords(1)">
                            <option value="10">Show 10</option>
                            <option value="25" selected>Show 25</option>
                            <option value="50">Show 50</option>
                            <option value="100">Show 100</option>
                        </select>
                        <button class="btn btn-secondary" onclick="fetchDeletedRecords(1)"><i class="fas fa-search"></i> Search</button>
                        <button class="btn btn-secondary" onclick="acClearDeletedFilters()"><i class="fas fa-times"></i> Clear</button>
                        <button class="btn btn-secondary" onclick="exportDeletedRecordsCsv(event)"><i class="fas fa-file-csv"></i> Export CSV</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Deleted By</th>
                                    <th>Module</th>
                                    <th>Record</th>
                                    <th>Description</th>
                                    <th>Snapshot</th>
                                </tr>
                            </thead>
                            <tbody id="deletedRecordsBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex" style="justify-content: space-between; align-items: center; margin-top: 12px;">
                        <div id="deletedRecordsInfo" style="color: var(--subtext); font-size: 13px;"></div>
                        <div class="d-flex gap-12">
                            <button class="btn btn-secondary" id="deletedRecordsPrevBtn" onclick="deletedRecordsGoToPage(-1)"><i class="fas fa-chevron-left"></i> Previous</button>
                            <button class="btn btn-secondary" id="deletedRecordsNextBtn" onclick="deletedRecordsGoToPage(1)">Next <i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <!-- USER ACTIVITY REPORT PANEL -->
                <div class="card ac-panel" id="panel-user-activity">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-user-clock"></i> User Activity Report</div>
                    </div>
                    <div class="ac-filters">
                        <label style="font-size:13px; color:var(--subtext); display:flex; align-items:center; gap:6px;">From
                            <input type="date" id="userActivityFromDate" class="form-input" style="max-width: 160px;" onchange="fetchUserActivityReport()">
                        </label>
                        <label style="font-size:13px; color:var(--subtext); display:flex; align-items:center; gap:6px;">To
                            <input type="date" id="userActivityToDate" class="form-input" style="max-width: 160px;" onchange="fetchUserActivityReport()">
                        </label>
                        <button class="btn btn-secondary" onclick="fetchUserActivityReport()"><i class="fas fa-sync"></i> Refresh</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Logins</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                    <th>Deleted</th>
                                    <th>Total Activities</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="userActivityBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- MANAGE LOGS PANEL (Super Admin only - server-enforced on every request) -->
                <div class="card ac-panel ac-danger-card" id="panel-manage-logs">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-user-shield"></i> Delete Log Entries</div>
                    </div>
                    <p style="color:var(--subtext); font-size:13px; margin:-8px 0 16px 0;">
                        Permanently deletes matching log entries. This cannot be undone - download a backup first. Only a Super Admin can do this.
                    </p>

                    <div class="ac-filters">
                        <div class="ac-tabgroup" style="padding:4px;">
                            <button class="ac-tab active" id="mlTypeAudit" onclick="acSetManageLogsType('audit')"><i class="fas fa-clipboard-list"></i> Audit Trail</button>
                            <button class="ac-tab" id="mlTypeLogin" onclick="acSetManageLogsType('login')"><i class="fas fa-user-shield"></i> Login Activity</button>
                        </div>
                    </div>

                    <div class="ac-filters">
                        <div class="searchable-dropdown" style="max-width: 200px;">
                            <input type="text" class="form-input search-input" id="mlModule" placeholder="All Modules" autocomplete="off">
                            <div class="dropdown-options" id="mlModuleOptions"></div>
                        </div>
                        <select id="mlAction" class="form-input" style="max-width: 180px;">
                            <option value="">All Actions</option>
                            <option value="create">Create</option>
                            <option value="update">Update</option>
                            <option value="delete">Delete</option>
                        </select>
                        <select id="mlStatus" class="form-input" style="max-width: 180px; display:none;">
                            <option value="">All Statuses</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                            <option value="locked">Locked</option>
                        </select>
                        <label style="font-size:13px; color:var(--subtext); display:flex; align-items:center; gap:6px;">Delete up to
                            <input type="date" id="mlToDate" class="form-input" style="max-width: 160px;">
                        </label>
                        <button class="btn btn-secondary" onclick="acPreviewLogsToDelete()"><i class="fas fa-search"></i> Preview</button>
                    </div>

                    <div id="mlPreviewSection" hidden>
                        <div class="ac-danger-summary" id="mlCountSummary"></div>

                        <div style="margin: 16px 0;">
                            <div style="font-size:13px; font-weight:600; color:var(--heading); margin-bottom:8px;">Step 1 - Download a backup</div>
                            <div class="d-flex gap-12" style="flex-wrap:wrap;">
                                <button class="btn btn-secondary" onclick="acDownloadLogsBackup('csv')"><i class="fas fa-file-csv"></i> Backup as CSV</button>
                                <button class="btn btn-secondary" onclick="acDownloadLogsBackup('xlsx')"><i class="fas fa-file-excel"></i> Backup as Excel</button>
                                <button class="btn btn-secondary" onclick="acDownloadLogsBackup('pdf')"><i class="fas fa-file-pdf"></i> Backup as PDF</button>
                            </div>
                        </div>

                        <div style="margin: 16px 0;">
                            <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--heading);">
                                <input type="checkbox" id="mlBackedUpCheck" onchange="acUpdateDeleteButtonState()">
                                I have downloaded a backup of these records
                            </label>
                        </div>

                        <div id="mlAllScopeWarning" style="margin: 16px 0;" hidden>
                            <div class="ac-danger-summary" style="margin-bottom:10px;">
                                <i class="fas fa-exclamation-triangle"></i> You're about to delete <b>every</b> matching record with no module/status filter. Type <b>DELETE ALL</b> below to confirm.
                            </div>
                            <input type="text" id="mlConfirmAllInput" class="form-input" placeholder="Type DELETE ALL" style="max-width: 240px;" oninput="acUpdateDeleteButtonState()">
                        </div>

                        <div style="margin: 16px 0;">
                            <div style="font-size:13px; font-weight:600; color:var(--heading); margin-bottom:8px;">Step 2 - Delete</div>
                            <button class="btn btn-danger" id="mlDeleteBtn" onclick="acDeleteLogs()" disabled><i class="fas fa-trash"></i> Delete Matching Records</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- DELETED RECORD SNAPSHOT MODAL -->
    <div id="snapshotModal" class="modal-overlay">
        <div class="modal" style="max-width: 560px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-camera"></i> Deleted Record Snapshot</div>
                <button class="modal-close" onclick="closeSnapshotModal()">&times;</button>
            </div>
            <div class="modal-body" id="snapshotBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSnapshotModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/admin_panel/activity-center.js?v=<?php echo time(); ?>"></script>
</body>
</html>
