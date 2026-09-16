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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Leave Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/leave/leave-management.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Leave Management</h1>
            <div class="header-stats" id="headerStats">
                <div class="stat-chip stat-pending"><span id="statPending">0</span> Pending</div>
                <div class="stat-chip stat-approved"><span id="statApproved">0</span> Approved</div>
                <div class="stat-chip stat-rejected"><span id="statRejected">0</span> Rejected</div>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="card">
            <div class="filters-row">
                <input type="text" id="filterEmployee" placeholder="Employee code (e.g. SALES-0002)">
                <select id="filterLeaveType"><option value="">All Leave Types</option></select>
                <select id="filterStatus">
                    <option value="">All Statuses</option>
                    <option value="pending" selected>Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input type="date" id="filterStartDate">
                <input type="date" id="filterEndDate">
                <button class="btn btn-secondary" onclick="loadLeaveRequests()"><i class="fas fa-filter"></i> Filter</button>
                <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Employee</th><th>Leave Type</th><th>Dates</th><th>Days</th>
                            <th>Reason</th><th>Status</th><th>Returned</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leaveTableBody">
                        <tr><td colspan="9" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Leave Request</h3>
                <button class="modal-close" onclick="closeModal('rejectModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="required">Reason for rejection</label>
                    <textarea id="rejectReason" placeholder="Why is this request being rejected?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmReject()"><i class="fas fa-times-circle"></i> Reject</button>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Leave</h3>
                <button class="modal-close" onclick="closeModal('cancelModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="required">Reason for cancellation</label>
                    <textarea id="cancelReason" placeholder="Why is this leave being cancelled?"></textarea>
                </div>
                <div class="warning-box" id="cancelWarning" style="display:none;"></div>
                <div class="form-group" id="cancelAckBox" style="display:none;">
                    <div class="checkbox-group">
                        <input type="checkbox" id="cancelAcknowledge">
                        <label for="cancelAcknowledge">I understand and want to cancel anyway</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('cancelModal')">Close</button>
                <button class="btn btn-danger" onclick="confirmCancel()"><i class="fas fa-ban"></i> Cancel Leave</button>
            </div>
        </div>
    </div>

    <!-- Return Modal -->
    <div class="modal" id="returnModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Employee Returned From Leave</h3>
                <button class="modal-close" onclick="closeModal('returnModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p id="returnLeaveInfo"></p>
                <div class="form-group">
                    <label class="required">Actual return date</label>
                    <input type="date" id="returnActualDate">
                </div>
                <div class="helper-text">If this is after the approved end date, the extra days are automatically recorded as Unpaid Leave. If it's before, the unused days are refunded to the balance.</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('returnModal')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmReturn()"><i class="fas fa-door-open"></i> Confirm Return</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/hrm/leave/leave-management.js?v=<?php echo time(); ?>"></script>
</body>
</html>
