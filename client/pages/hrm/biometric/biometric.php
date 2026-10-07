<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Biometric Devices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .biometric-wrapper { padding: 24px; max-width: 1100px; }
        .page-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
        .page-header h1 { font-size:22px; font-weight:600; margin:0; color:var(--text-primary); }
        .page-header .subtitle { font-size:13px; color:var(--text-secondary); margin-top:2px; }

        /* Tabs */
        .tab-bar { display:flex; gap:4px; border-bottom:2px solid var(--border); margin-bottom:24px; }
        .tab-btn { padding:10px 20px; background:none; border:none; cursor:pointer; font-size:14px;
                   font-weight:500; color:var(--text-secondary); border-bottom:2px solid transparent;
                   margin-bottom:-2px; transition:all .2s; }
        .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
        .tab-btn:hover:not(.active) { color:var(--text-primary); }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }

        /* Cards */
        .card { background:var(--surface); border:1px solid var(--border); border-radius:8px;
                padding:20px; margin-bottom:20px; }
        .card-title { font-size:15px; font-weight:600; margin:0 0 16px; color:var(--text-primary);
                      display:flex; align-items:center; gap:8px; }

        /* Forms */
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .form-row.single { grid-template-columns:1fr; }
        .form-row.triple { grid-template-columns:1fr 1fr 1fr; }
        .field label { display:block; font-size:12px; font-weight:500; color:var(--text-secondary);
                       margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
        .field input, .field select {
            width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
            font-size:14px; background:var(--bg); color:var(--text-primary);
            box-sizing:border-box; transition:border-color .2s;
        }
        .field input:focus, .field select:focus { outline:none; border-color:var(--primary); }

        /* Buttons */
        .btn { padding:9px 18px; border:none; border-radius:6px; cursor:pointer;
               font-size:13px; font-weight:500; transition:all .2s; display:inline-flex;
               align-items:center; gap:6px; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { filter:brightness(1.1); }
        .btn-secondary { background:var(--surface); border:1px solid var(--border);
                         color:var(--text-primary); }
        .btn-secondary:hover { background:var(--hover); }
        .btn-danger { background:#e74c3c; color:#fff; }
        .btn-danger:hover { filter:brightness(1.1); }
        .btn-sm { padding:5px 10px; font-size:12px; }
        .btn-icon { background:none; border:none; cursor:pointer; padding:5px 8px;
                    border-radius:4px; font-size:14px; transition:all .2s; }
        .btn-edit  { color:var(--primary); }
        .btn-edit:hover  { background:rgba(52,152,219,.1); }
        .btn-del   { color:#e74c3c; }
        .btn-del:hover   { background:rgba(231,76,60,.1); }
        .btn-copy  { color:var(--text-secondary); }
        .btn-copy:hover  { background:var(--hover); }

        /* Tables */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { padding:10px 12px; text-align:left; font-weight:600; font-size:11px;
             text-transform:uppercase; letter-spacing:.4px; color:var(--text-secondary);
             border-bottom:2px solid var(--border); white-space:nowrap; }
        td { padding:11px 12px; border-bottom:1px solid var(--border); color:var(--text-primary);
             vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:var(--hover); }
        .no-data { text-align:center; color:var(--text-secondary); padding:32px !important; }

        /* Badges */
        .badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px;
                 font-weight:500; }
        .badge-active   { background:rgba(39,174,96,.15); color:#27ae60; }
        .badge-inactive { background:rgba(149,165,166,.15); color:#7f8c8d; }

        /* Token display */
        .token-box { font-family:monospace; font-size:12px; background:var(--hover);
                     padding:8px 12px; border-radius:6px; word-break:break-all;
                     color:var(--text-secondary); border:1px solid var(--border); }
        .token-row { display:flex; align-items:center; gap:8px; }

        /* Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
                         z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:var(--surface); border-radius:10px; padding:24px; width:520px;
                 max-width:90vw; box-shadow:0 8px 32px rgba(0,0,0,.2); }
        .modal-header { display:flex; align-items:center; justify-content:space-between;
                        margin-bottom:20px; }
        .modal-header h3 { margin:0; font-size:16px; font-weight:600; color:var(--text-primary); }
        .modal-close { background:none; border:none; cursor:pointer; font-size:18px;
                       color:var(--text-secondary); padding:4px; line-height:1; }
        .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:20px; }

        /* Push log section */
        .log-controls { display:flex; gap:12px; align-items:center; margin-bottom:16px; }
        .log-controls select, .log-controls input { padding:7px 10px; border:1px solid var(--border);
            border-radius:6px; font-size:13px; background:var(--bg); color:var(--text-primary); }
        .status-processed { color:#27ae60; }
        .status-unmapped  { color:#e67e22; }
        .status-error     { color:#e74c3c; }

        /* Toast */
        #toast { position:fixed; bottom:24px; right:24px; padding:12px 20px; border-radius:8px;
                 font-size:13px; font-weight:500; color:#fff; z-index:9999;
                 transform:translateY(80px); opacity:0; transition:all .3s; pointer-events:none; }
        #toast.show { transform:translateY(0); opacity:1; }
        #toast.success { background:#27ae60; }
        #toast.error   { background:#e74c3c; }
    </style>
</head>
<body>

<div class="biometric-wrapper">

    <div class="page-header">
        <i class="fas fa-fingerprint" style="font-size:28px;color:var(--primary)"></i>
        <div>
            <h1>Biometric Devices</h1>
            <div class="subtitle">Connect fingerprint / face-recognition machines to the attendance system</div>
        </div>
    </div>

    <div class="tab-bar">
        <button class="tab-btn active" data-tab="devices">
            <i class="fas fa-server"></i> Devices
        </button>
        <button class="tab-btn" data-tab="mappings">
            <i class="fas fa-users"></i> Employee Mapping
        </button>
        <button class="tab-btn" data-tab="logs">
            <i class="fas fa-list-alt"></i> Push Log
        </button>
    </div>

    <!-- ═══════════════════════ DEVICES TAB ═══════════════════════ -->
    <div class="tab-pane active" id="tab-devices">

        <div class="card">
            <div class="card-title"><i class="fas fa-plus-circle"></i> Register New Device</div>
            <div class="form-row">
                <div class="field">
                    <label>Device Name *</label>
                    <input type="text" id="dev-name" placeholder="e.g. Main Entrance Scanner">
                </div>
                <div class="field">
                    <label>Location</label>
                    <input type="text" id="dev-location" placeholder="e.g. Ground Floor Lobby">
                </div>
            </div>
            <button class="btn btn-primary" id="btn-add-device">
                <i class="fas fa-plus"></i> Register Device
            </button>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-server"></i> Registered Devices</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Device Name</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th>API Token</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="devices-tbody">
                        <tr><td colspan="7" class="no-data"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ MAPPINGS TAB ═══════════════════════ -->
    <div class="tab-pane" id="tab-mappings">

        <div class="card">
            <div class="card-title"><i class="fas fa-link"></i> Add Employee Mapping</div>
            <p style="font-size:13px;color:var(--text-secondary);margin:0 0 16px;">
                Link the number assigned to an employee in the fingerprint machine to their ERP profile.
            </p>
            <div class="form-row triple">
                <div class="field">
                    <label>Machine Employee ID *</label>
                    <input type="text" id="map-machine-id" placeholder="e.g. 1, 2, 3…">
                </div>
                <div class="field">
                    <label>Employee *</label>
                    <select id="map-employee">
                        <option value="">— Select employee —</option>
                    </select>
                </div>
                <div class="field" style="display:flex;align-items:flex-end;">
                    <button class="btn btn-primary" id="btn-add-mapping" style="width:100%">
                        <i class="fas fa-plus"></i> Add Mapping
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-users"></i> Employee Mappings</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Machine ID</th>
                            <th>Employee</th>
                            <th>Employee ID</th>
                            <th>Department</th>
                            <th>Mapped On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="mappings-tbody">
                        <tr><td colspan="6" class="no-data"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ LOGS TAB ═══════════════════════════ -->
    <div class="tab-pane" id="tab-logs">

        <div class="card">
            <div class="card-title"><i class="fas fa-list-alt"></i> Recent Push Log</div>
            <div class="log-controls">
                <select id="log-status-filter">
                    <option value="">All statuses</option>
                    <option value="processed">Processed</option>
                    <option value="unmapped">Unmapped</option>
                    <option value="error">Error</option>
                </select>
                <input type="date" id="log-date-filter">
                <button class="btn btn-secondary btn-sm" id="btn-load-logs">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Device</th>
                            <th>Machine ID</th>
                            <th>Employee ID</th>
                            <th>Punch Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody id="logs-tbody">
                        <tr><td colspan="8" class="no-data">Select a date and click Refresh</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top:0">
            <div class="card-title"><i class="fas fa-plug"></i> Push Endpoint</div>
            <p style="font-size:13px;color:var(--text-secondary);margin:0 0 12px;">
                Configure your fingerprint machine's HTTP push URL. Include the device token as a query parameter or <code>X-Device-Token</code> header.
            </p>
            <div class="token-row">
                <div class="token-box" id="push-url-display" style="flex:1">
                    <?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']); ?>/server/api/hrm/attendance/biometric-push.php?token=<YOUR_TOKEN>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="copyPushUrl()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════ EDIT DEVICE MODAL ═══════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-device">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Device</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-device')">&times;</button>
        </div>
        <input type="hidden" id="edit-dev-id">
        <div class="form-row">
            <div class="field">
                <label>Device Name *</label>
                <input type="text" id="edit-dev-name">
            </div>
            <div class="field">
                <label>Location</label>
                <input type="text" id="edit-dev-location">
            </div>
        </div>
        <div class="form-row single">
            <div class="field">
                <label>Status</label>
                <select id="edit-dev-active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-edit-device')">Cancel</button>
            <button class="btn btn-danger btn-sm" id="btn-regen-token">
                <i class="fas fa-key"></i> Regenerate Token
            </button>
            <button class="btn btn-primary" id="btn-save-device">Save Changes</button>
        </div>
    </div>
</div>

<!-- ═══════════════ NEW TOKEN MODAL ════════════════════════════════ -->
<div class="modal-overlay" id="modal-token">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> API Token</h3>
            <button class="modal-close" onclick="closeModal('modal-token')">&times;</button>
        </div>
        <p style="font-size:13px;color:var(--text-secondary);margin:0 0 12px;">
            Copy this token now — it will not be shown again in full after you leave this dialog.
            Paste it into your fingerprint machine's HTTP push settings.
        </p>
        <div class="token-row">
            <div class="token-box" id="new-token-display" style="flex:1"></div>
            <button class="btn btn-secondary btn-sm" onclick="copyToken()">
                <i class="fas fa-copy"></i> Copy
            </button>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeModal('modal-token')">Done</button>
        </div>
    </div>
</div>

<!-- ═══════════════ EDIT MAPPING MODAL ══════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-mapping">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Mapping</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-mapping')">&times;</button>
        </div>
        <input type="hidden" id="edit-map-id">
        <div class="form-row">
            <div class="field">
                <label>Machine Employee ID *</label>
                <input type="text" id="edit-map-machine-id">
            </div>
            <div class="field">
                <label>Employee *</label>
                <select id="edit-map-employee"></select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-edit-mapping')">Cancel</button>
            <button class="btn btn-primary" id="btn-save-mapping">Save Changes</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script src="../../../assets/js/hrm/biometric/biometric.js"></script>
</body>
</html>
