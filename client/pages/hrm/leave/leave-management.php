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
// This page is wired to server/api/hrm/leave/leave-request.php:
//   - GET  ?action=bootstrap  -> employees, leave types (Annual/Casual/Sick/Over Time),
//                                 daily/hourly rates
//   - POST action=submit      -> creates the record. Payment is decided up front via
//                                 a Paid / Unpaid / Split dropdown, sent as pay_mode
//                                 ('paid' | 'unpaid' | 'split' + unpaid_days) — no quota
//                                 tracking, no over-quota approval round trip.
//   - POST action=edit        -> updates a pending record in place
//   - POST action=delete / update_status -> remove / approve-reject a record
// Leave types are fixed (seeded once by ensureBuiltinTypes()) — there is no
// "add custom leave type" feature.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/hrm/leave/leave-management.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Leave Management</h1>
                <p>Select an employee, pick a leave type, and set the dates</p>
            </div>
        </div>

        <div class="layout">
            <!-- Sidebar: employee + leave type -->
            <div class="sidebar">
                <div class="card">
                    <div class="card-head">
                        <h2>1. Employee</h2>
                    </div>
                    <div class="card-body">
                        <div class="combo" id="empCombo">
                            <button type="button" class="combo-trigger" id="empComboTrigger">
                                <span class="placeholder" id="empComboLabel">Select employee…</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="combo-panel" id="empComboPanel">
                                <div class="emp-search">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="empSearch" placeholder="Search name, ID or dept...">
                                </div>
                                <div class="emp-list" id="empList"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2>2. Leave Type</h2>
                    </div>
                    <div class="card-body">
                        <div class="type-list" id="typeList"></div>

                        <!-- Payment: chosen right after picking a type, no quota calculation -->
                        <div class="field" id="payModeRow" style="margin-top:12px;">
                            <label>Payment <span class="req">*</span></label>
                            <select class="text-input" id="payModeSelect">
                                <option value="paid">Paid — all days paid</option>
                                <option value="unpaid">Unpaid — all days unpaid</option>
                                <option value="split">Split — some paid, some unpaid</option>
                            </select>
                            <div class="split-box" id="splitBox" style="display:none;">
                                <label for="splitUnpaidInput">Unpaid days (out of <span id="splitTotalDays">0</span>)</label>
                                <input type="number" class="text-input" id="splitUnpaidInput" min="0" step="0.5" value="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Request details -->
            <div class="card">
                <div class="card-head">
                    <h2>3. Request Details</h2>
                </div>
                <div class="card-body">
                    <span class="mode-tag" id="modeTag"><i class="fas fa-sun"></i> Annual Leave</span>
                    <div class="rate-info" id="rateInfo" style="display:none;"></div>

                    <div class="details-grid">
                        <div>
                            <!-- Date-range calendar mode -->
                            <div id="rangeMode">
                                <div class="field">
                                    <label>Dates <span class="req">*</span></label>
                                    <div class="cal">
                                        <div class="cal-head">
                                            <button id="calPrev"><i class="fas fa-chevron-left"></i></button>
                                            <span id="calLabel"></span>
                                            <button id="calNext"><i class="fas fa-chevron-right"></i></button>
                                        </div>
                                        <div class="cal-grid" id="calGrid"></div>
                                        <div class="cal-help">Click a start date, then an end date.</div>
                                    </div>
                                    <div class="halfday-row">
                                        <input type="checkbox" id="halfDay"> <label for="halfDay">Half-day leave</label>
                                    </div>
                                    <div class="days-out" id="daysOut">Pick a date to see the total</div>
                                </div>
                            </div>

                            <!-- Overtime mode -->
                            <div id="otMode" style="display:none;">
                                <div class="field">
                                    <label>Date <span class="req">*</span></label>
                                    <input type="date" class="text-input" id="otDate">
                                </div>
                                <div class="row2">
                                    <div class="field">
                                        <label>Hours <span class="req">*</span></label>
                                        <input type="number" step="0.5" min="0" class="text-input" id="otHours" placeholder="e.g. 3">
                                    </div>
                                    <div class="field">
                                        <label>Rate</label>
                                        <select class="text-input" id="otRate">
                                            <option value="1x">1x (Standard)</option>
                                            <option value="1.5x">1.5x</option>
                                            <option value="2x">2x (Holiday/Sunday)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="field">
                                <label>Reason <span class="req">*</span></label>
                                <textarea id="reason" placeholder="Enter reason..."></textarea>
                            </div>
                            <div class="row2">
                                <div class="field">
                                    <label>Contact during leave</label>
                                    <input type="text" class="text-input" id="contact" placeholder="Emergency contact">
                                </div>
                                <div class="field">
                                    <label>Approval status</label>
                                    <select class="text-input" id="approvalStatus">
                                        <option value="pending">Pending approval</option>
                                        <option value="approved">Auto-approve</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-primary btn-full" id="submitBtn" disabled style="margin-top:8px;">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent requests -->
        <div class="card table-card">
            <div class="card-head req-head" id="reqHead">
                <h2>Recent Requests</h2>
                <div class="req-head-actions">
                    <!-- Per-column show/hide panel -->
                    <div class="col-toggle" id="colToggle">
                        <button type="button" class="btn" id="colToggleBtn"><i class="fas fa-columns"></i> Columns</button>
                        <div class="col-panel" id="colPanel">
                            <label><input type="checkbox" class="col-check" data-col="1" checked> Employee</label>
                            <label><input type="checkbox" class="col-check" data-col="2" checked> Type</label>
                            <label><input type="checkbox" class="col-check" data-col="3" checked> Paid</label>
                            <label><input type="checkbox" class="col-check" data-col="4" checked> Dates</label>
                            <label><input type="checkbox" class="col-check" data-col="5" checked> Total</label>
                            <label><input type="checkbox" class="col-check" data-col="6" checked> Amount</label>
                            <label><input type="checkbox" class="col-check" data-col="7" checked> Status</label>
                            <label><input type="checkbox" class="col-check" data-col="8" checked> Actions</label>
                        </div>
                    </div>
                    <button type="button" class="btn" id="reqToggleBtn"><i class="fas fa-eye-slash"></i> Hide</button>
                </div>
            </div>
            <div class="req-filters" id="reqFilters">
                <div class="field">
                    <label>Employee Name</label>
                    <input type="text" class="text-input" id="reqFilterName" placeholder="Search by name...">
                </div>
                <div class="field">
                    <label>From</label>
                    <input type="date" class="text-input" id="reqFilterFrom">
                </div>
                <div class="field">
                    <label>To</label>
                    <input type="date" class="text-input" id="reqFilterTo">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select class="text-input" id="reqFilterStatus">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <button type="button" class="btn" id="reqFilterClear">Clear Filters</button>
            </div>
            <div class="table-wrap" id="reqTableWrap">
                <table id="reqTable" class="leave-table">
                    <thead>
                        <tr><th>Employee</th><th>Type</th><th>Paid</th><th>Dates</th><th>Total</th><th>Amount</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody id="reqBody">
                        <tr><td colspan="8" class="empty-row">No requests submitted yet</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/hrm/leave/leave-management.js"></script>
</body>
</html>