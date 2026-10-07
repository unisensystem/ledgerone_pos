<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Biometric Devices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/attendance/attendance.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/biometric/biometric.css">
</head>
<body>

<div class="biometric-wrapper">

    <div class="page-header">
        <i class="fas fa-fingerprint icon"></i>
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

    <!-- ═══════════════════════════════ DEVICES TAB ══════════════════════════════ -->
    <div class="tab-pane active" id="tab-devices">

        <div class="card">
            <div class="card-title"><i class="fas fa-plus-circle"></i> Register New Device</div>
            <div class="form-row">
                <div class="field">
                    <label>Device Name <span style="color:var(--error)">*</span></label>
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
                        <tr><td colspan="7" class="no-data"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════ MAPPINGS TAB ══════════════════════════════ -->
    <div class="tab-pane" id="tab-mappings">

        <div class="card">
            <div class="card-title"><i class="fas fa-link"></i> Add Employee Mapping</div>
            <p class="card-hint">
                Enter the number the fingerprint machine uses for each employee
                (shown on the device's user list) and link it to their ERP profile.
            </p>
            <div class="form-row triple">
                <div class="field">
                    <label>Machine Employee ID <span style="color:var(--error)">*</span></label>
                    <input type="text" id="map-machine-id" placeholder="e.g. 1, 2, 3…">
                </div>
                <div class="field">
                    <label>Employee <span style="color:var(--error)">*</span></label>
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
                        <tr><td colspan="6" class="no-data"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════════ LOGS TAB ════════════════════════════════ -->
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
                            <th>Received At</th>
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

        <div class="card">
            <div class="card-title"><i class="fas fa-plug"></i> Push Endpoint</div>
            <p class="card-hint">
                Paste this URL into your fingerprint machine's HTTP push settings.
                Replace <code>&lt;YOUR_TOKEN&gt;</code> with the device's token, or pass it
                as the <code>X-Device-Token</code> request header.
            </p>
            <div class="token-row">
                <div class="token-box" id="push-url-display" style="flex:1">
                    <?= htmlspecialchars($baseUrl) ?>/server/api/hrm/attendance/biometric-push.php?token=&lt;YOUR_TOKEN&gt;
                </div>
                <button class="btn btn-secondary btn-sm" onclick="copyPushUrl()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
        </div>

    </div>

</div><!-- /.biometric-wrapper -->


<!-- ═════════════════════════ EDIT DEVICE MODAL ═════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-device">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Device</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-device')">&times;</button>
        </div>
        <input type="hidden" id="edit-dev-id">
        <div class="form-row">
            <div class="field">
                <label>Device Name <span style="color:var(--error)">*</span></label>
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

<!-- ═════════════════════════ NEW TOKEN MODAL ═══════════════════════════════ -->
<div class="modal-overlay" id="modal-token">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> API Token</h3>
            <button class="modal-close" onclick="closeModal('modal-token')">&times;</button>
        </div>
        <p class="card-hint">
            Copy this token now — it will not be shown in full again after you close this dialog.
            Paste it into your fingerprint machine's HTTP push configuration.
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

<!-- ═════════════════════════ EDIT MAPPING MODAL ════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-mapping">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Mapping</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-mapping')">&times;</button>
        </div>
        <input type="hidden" id="edit-map-id">
        <div class="form-row">
            <div class="field">
                <label>Machine Employee ID <span style="color:var(--error)">*</span></label>
                <input type="text" id="edit-map-machine-id">
            </div>
            <div class="field">
                <label>Employee <span style="color:var(--error)">*</span></label>
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
