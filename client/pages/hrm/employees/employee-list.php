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
    <title>Employee Management - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/employees/employee-list.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Employee Management</h1>
                <p>Manage employee status, leaves, suspensions, and terminations</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="showAddEmployeeModal()">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
                <button class="btn btn-primary" onclick="printWithFilters()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card active">
                <div class="stat-header">
                    <div class="stat-value" id="activeCount">24</div>
                    <i class="fas fa-user-check" style="color: #2FBF71; font-size: 24px;"></i>
                </div>
                <div class="stat-label">Active Employees</div>
                <div class="stat-change" id="activeChange"></div>
            </div>
            <div class="stat-card on-leave">
                <div class="stat-header">
                    <div class="stat-value" id="onLeaveCount">3</div>
                    <i class="fas fa-umbrella-beach" style="color: #E8B23F; font-size: 24px;"></i>
                </div>
                <div class="stat-label">On Leave</div>
                <div class="stat-change" id="leaveChange"></div>
            </div>
            <div class="stat-card suspended">
                <div class="stat-header">
                    <div class="stat-value" id="suspendedCount">1</div>
                    <i class="fas fa-ban" style="color: #E34F4F; font-size: 24px;"></i>
                </div>
                <div class="stat-label">Suspended</div>
                <div class="stat-change" id="suspendedChange"></div>
            </div>
            <div class="stat-card terminated">
                <div class="stat-header">
                    <div class="stat-value" id="terminatedCount">2</div>
                    <i class="fas fa-user-slash" style="color: #6B7280; font-size: 24px;"></i>
                </div>
                <div class="stat-label">Terminated</div>
                <div class="stat-change" id="terminatedChange"></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-row">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                   <input type="text" id="searchInput" placeholder="Search employees by name, ID, or department..." 
       value="<?= htmlspecialchars($initialSearch) ?>"
       oninput="filterEmployees()">
                </div>
                <div class="filter-group">
                    <label>Department</label>
                    <select id="departmentFilter" onchange="filterEmployees()">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Position</label>
                    <select id="positionFilter" onchange="filterEmployees()">
                        <option value="">All Positions</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter" onchange="filterEmployees()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="on-leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                        <option value="terminated">Terminated</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="quick-filters">
                <div class="filter-chip active" onclick="setQuickFilter('all')">All Employees</div>
                <div class="filter-chip" onclick="setQuickFilter('probation')">On Probation</div>
                <div class="filter-chip" onclick="setQuickFilter('contract')">Contract Staff</div>
                <div class="filter-chip" onclick="setQuickFilter('remote')">Remote Workers</div>
                <div class="filter-chip" onclick="setQuickFilter('leaves')">Upcoming Leaves</div>
            </div>
        </div>

        <!-- Employee Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>Employee List</h3>
                <div class="table-actions">
                    <span class="pagination-info" id="tableInfo">Showing 1-10 of 30 employees</span>
                    <select id="rowsPerPage" onchange="changeRowsPerPage()" style="width: auto;">
                        <option value="10">10 rows</option>
                        <option value="25">25 rows</option>
                        <option value="50">50 rows</option>
                    </select>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Employee</th>
                        <th>ID</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="employeeTableBody">
                    <!-- Employee rows will be inserted here by JavaScript -->
                </tbody>
            </table>

            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Page 1 of 3</div>
                <div class="pagination-controls">
                    <button class="page-btn" onclick="changePage(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn" onclick="changePage(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Leave Management Modal -->
    <div class="modal" id="leaveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Leave</h3>
                <button class="modal-close" onclick="closeModal('leaveModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="employee-details" id="leaveEmployeeDetails">
                    <div class="employee-details-avatar" id="leaveEmployeeAvatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-details-info">
                        <h4 id="leaveEmployeeName">John Doe</h4>
                        <p id="leaveEmployeeInfo">ID: FUEL-00123 • Department: IT</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">Leave Type</label>
                    <div class="leave-type-selector" id="leaveTypeSelector">
                        <!-- Populated from leave-types.php: types + live balances are tenant-configurable, not hardcoded -->
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">Date Range</label>
                    <div class="date-range">
                        <div>
                            <label>Start Date</label>
                            <input type="date" id="leaveStartDate" class="leave-date" onchange="calculateLeaveDays()">
                        </div>
                        <div>
                            <label>End Date</label>
                            <input type="date" id="leaveEndDate" class="leave-date" onchange="calculateLeaveDays()">
                        </div>
                    </div>
                    <div class="checkbox-group" style="margin-top: 8px;">
                        <input type="checkbox" id="leaveIsHalfDay" onchange="toggleHalfDay()">
                        <label for="leaveIsHalfDay">Half-day leave</label>
                        <select id="leaveHalfDayPeriod" style="display:none; margin-left: 10px; width: auto;">
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                        </select>
                    </div>
                    <div class="helper-text" id="leaveDaysCount">Total days: 0 (weekends/holidays excluded)</div>
                </div>

                <div class="form-group">
                    <label class="required">Reason for Leave</label>
                    <textarea id="leaveReason" placeholder="Enter reason for leave..."></textarea>
                </div>

                <div class="form-group">
                    <label>Contact During Leave</label>
                    <input type="text" id="leaveContact" placeholder="Emergency contact number">
                </div>

                <div class="form-group" id="leaveOverrideBox" style="display:none;">
                    <div class="checkbox-group">
                        <input type="checkbox" id="leaveOverride">
                        <label for="leaveOverride">Override the warning above and submit anyway</label>
                    </div>
                    <input type="text" id="leaveOverrideReason" placeholder="Reason for override (required)" style="margin-top:6px;">
                </div>

                <div class="warning-box" id="leaveValidationWarning" style="display:none;"></div>

                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Employee will be marked as "On Leave" during this period. If the leave type requires approval it stays Pending until approved.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('leaveModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitLeaveRequest()">
                    <i class="fas fa-paper-plane"></i> Submit Leave Request
                </button>
            </div>
        </div>
    </div>

    <!-- Suspension Modal -->
    <div class="modal" id="suspendModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Suspend Employee</h3>
                <button class="modal-close" onclick="closeModal('suspendModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="employee-details">
                    <div class="employee-details-avatar" id="suspendEmployeeAvatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-details-info">
                        <h4 id="suspendEmployeeName">John Doe</h4>
                        <p id="suspendEmployeeInfo">ID: FUEL-00123 • Department: IT</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">Suspension Duration</label>
                    <div class="date-range">
                        <div>
                            <label>Start Date</label>
                            <input type="date" id="suspendStartDate">
                        </div>
                        <div>
                            <label>End Date</label>
                            <input type="date" id="suspendEndDate">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">Suspension Type</label>
                    <select id="suspensionType">
                        <option value="">Select type</option>
                        <option value="with-pay">With Pay</option>
                        <option value="without-pay">Without Pay</option>
                        <option value="investigation">Pending Investigation</option>
                        <option value="disciplinary">Disciplinary Action</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Reason for Suspension</label>
                    <textarea id="suspendReason" placeholder="Enter detailed reason for suspension..."></textarea>
                </div>

                <div class="danger-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Warning: Suspended employees cannot access company systems or premises. All benefits will be frozen during suspension period.</span>
                </div>

                <div class="form-group">
                    <label>Notes for HR</label>
                    <textarea id="suspendNotes" placeholder="Additional notes for HR department..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('suspendModal')">Cancel</button>
                <button class="btn btn-danger" onclick="submitSuspension()">
                    <i class="fas fa-ban"></i> Confirm Suspension
                </button>
            </div>
        </div>
    </div>

    <!-- Termination Modal -->
    <div class="modal" id="terminateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Terminate Employment</h3>
                <button class="modal-close" onclick="closeModal('terminateModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="employee-details">
                    <div class="employee-details-avatar" id="terminateEmployeeAvatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-details-info">
                        <h4 id="terminateEmployeeName">John Doe</h4>
                        <p id="terminateEmployeeInfo">ID: FUEL-00123 • Department: IT</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">Termination Type</label>
                    <select id="terminationType">
                        <option value="">Select type</option>
                        <option value="voluntary">Voluntary Resignation</option>
                        <option value="retirement">Retirement</option>
                        <option value="redundancy">Redundancy/Layoff</option>
                        <option value="performance">Performance Issues</option>
                        <option value="misconduct">Misconduct</option>
                        <option value="probation-fail">Probation Failure</option>
                        <option value="end-contract">End of Contract</option>
                        <option value="mutual">Mutual Agreement</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Termination Date</label>
                    <input type="date" id="terminationDate">
                </div>

                <div class="form-group">
                    <label class="required">Notice Period</label>
                    <select id="noticePeriod">
                        <option value="">Select notice period</option>
                        <option value="immediate">Immediate</option>
                        <option value="1-week">1 Week</option>
                        <option value="2-weeks">2 Weeks</option>
                        <option value="1-month">1 Month</option>
                        <option value="2-months">2 Months</option>
                        <option value="3-months">3 Months</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Reason for Termination</label>
                    <textarea id="terminationReason" placeholder="Enter detailed reason for termination..."></textarea>
                </div>

                <div class="form-group">
                    <label>Exit Interview Notes</label>
                    <textarea id="exitInterview" placeholder="Notes from exit interview..."></textarea>
                </div>

                <div class="form-group">
                    <label>Final Settlement Details</label>
                    <div class="checkbox-group" style="margin-top: 10px;">
                        <input type="checkbox" id="finalPay" checked>
                        <label for="finalPay">Process final salary payment</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="gratuityPayment" checked>
                        <label for="gratuityPayment">Include gratuity payment</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="returnAssets">
                        <label for="returnAssets">Assets to be returned</label>
                    </div>
                </div>

                <div class="danger-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Warning: This action is irreversible. Terminated employees will lose all system access immediately.</span>
                </div>

                <div class="status-history">
                    <h4>Employee History</h4>
                    <div class="history-item">
                        <span class="status status-active">Active</span>
                        <span class="reason">Hired as Software Engineer</span>
                        <span class="date">2023-01-15</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('terminateModal')">Cancel</button>
                <button class="btn btn-danger" onclick="submitTermination()">
                    <i class="fas fa-user-slash"></i> Confirm Termination
                </button>
            </div>
        </div>
    </div>

    <!-- Leave Balance Modal -->
    <div class="modal" id="leaveBalanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Leave Balance</h3>
                <button class="modal-close" onclick="closeModal('leaveBalanceModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="employee-details">
                    <div class="employee-details-avatar" id="balanceEmployeeAvatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-details-info">
                        <h4 id="balanceEmployeeName">John Doe</h4>
                        <p id="balanceEmployeeInfo">ID: FUEL-00123 • Department: IT</p>
                    </div>
                </div>

                <div class="form-group">
                    <table class="data-table" id="balanceBreakdownTable" style="width:100%; font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Leave Type</th><th>Opening</th><th>Allocated</th><th>Carry Fwd</th>
                                <th>Used</th><th>Pending</th><th>Adjustment</th><th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody id="balanceBreakdownBody"><!-- filled by JS --></tbody>
                    </table>
                </div>

                <div class="form-group">
                    <label class="required">Adjust Leave Type</label>
                    <select id="balanceLeaveType"><!-- filled by JS --></select>
                </div>

                <div class="form-group">
                    <label class="required">Operation</label>
                    <select id="balanceOperation">
                        <option value="add">Add/Subtract Days (+/-)</option>
                        <option value="set">Set Remaining Balance To</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Days</label>
                    <input type="number" id="balanceAmount" step="0.5" placeholder="e.g. 5 or -2">
                </div>

                <div class="form-group">
                    <label class="required">Reason</label>
                    <input type="text" id="balanceReason" placeholder="Reason for this adjustment">
                </div>

                <div class="form-group">
                    <label>Recent Transactions (this leave type)</label>
                    <table class="data-table" style="width:100%; font-size: 12px;">
                        <thead><tr><th>Date</th><th>Type</th><th>Days</th><th>Reason</th></tr></thead>
                        <tbody id="balanceHistoryBody"><!-- filled by JS --></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('leaveBalanceModal')">Cancel</button>
                <button class="btn btn-primary" onclick="updateLeaveBalance()">
                    <i class="fas fa-save"></i> Save Adjustment
                </button>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Employee Status</h3>
                <button class="modal-close" onclick="closeModal('statusModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="employee-details">
                    <div class="employee-details-avatar" id="statusEmployeeAvatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="employee-details-info">
                        <h4 id="statusEmployeeName">John Doe</h4>
                        <p id="statusEmployeeInfo">ID: FUEL-00123 • Current Status: <span id="currentStatus">Active</span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">New Status</label>
                    <select id="newStatus">
                        <option value="">Select status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on-leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                        <option value="terminated">Terminated</option>
                        <option value="probation">Probation</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Effective Date</label>
                    <input type="date" id="statusEffectiveDate">
                </div>

                <div class="form-group">
                    <label>Reason for Status Change</label>
                    <textarea id="statusChangeReason" placeholder="Enter reason for status change..."></textarea>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="statusNotes" placeholder="Additional notes..."></textarea>
                </div>

                <div class="status-history">
                    <h4>Status History</h4>
                    <div id="statusHistoryList">
                        <!-- Status history will be inserted here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitStatusChange()">
                    <i class="fas fa-sync-alt"></i> Update Status
                </button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/hrm/employees/employee-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        if (typeof filterEmployees === 'function') {
            filterEmployees();
        }
    });
</script>
<?php endif; ?>
</body>
</html>