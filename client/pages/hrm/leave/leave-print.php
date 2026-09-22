<?php
// ============================================
// Leave Approval Letter — Print/PDF
// ============================================

require_once '../../../../includes/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

// Get Leave ID
$leave_id = (int)($_GET['id'] ?? 0);
if (!$leave_id) die('No leave ID provided.');

// Fetch Leave Record
$stmt = $pdo->prepare("
    SELECT lr.*, lt.name AS type_name, lt.kind AS type_kind
    FROM leave_records lr
    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.id = ? AND lr.tenant_id = ?
");
$stmt->execute([$leave_id, $tenant_id]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leave) die('Leave record not found.');

// Fetch Employee Details
$stmt = $pdo->prepare("
    SELECT e.*, d.department_name, p.position_title
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN positions p ON p.id = e.position_id
    WHERE e.employee_id = ? AND e.tenant_id = ?
");
$stmt->execute([$leave['employee_id'], $tenant_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) die('Employee not found.');

// Fetch Approver Name
$stmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
$stmt->execute([$user_id, $tenant_id]);
$approver = $stmt->fetch(PDO::FETCH_ASSOC);
$approverName = $approver['full_name'] ?? 'HR / Manager';

// Fetch Manager Name
$mgrName = '—';
if ($emp['reporting_manager_id']) {
    $sm = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
    $sm->execute([$emp['reporting_manager_id'], $tenant_id]);
    $mgr = $sm->fetch(PDO::FETCH_ASSOC);
    if ($mgr) $mgrName = htmlspecialchars($mgr['full_name']);
}

// ============ HELPER FUNCTIONS ============

function safe($value, $default = '—') {
    return (isset($value) && $value !== '' && $value !== null) 
        ? htmlspecialchars($value) 
        : $default;
}

function formatDate($date) {
    return $date ? date('d M Y', strtotime($date)) : '—';
}

function formatMoney($amount, $decimals = 2) {
    return number_format((float)$amount, $decimals);
}

// ============ CALCULATIONS ============

$isOvertime   = !empty($leave['ot_hours']);
$statusMap    = ['approved' => 'APPROVED', 'rejected' => 'REJECTED', 'pending' => 'PENDING'];
$statusLabel  = $statusMap[$leave['status']] ?? strtoupper($leave['status']);
$printDate    = date('d M Y, h:i A');
$currency     = 'PKR';

// Financial calculations
$dailyRate    = (float)($leave['daily_rate']  ?? 0);
$paidDays     = (float)($leave['paid_days']   ?? 0);
$unpaidDays   = (float)($leave['unpaid_days'] ?? 0);
$unpaidAmount = $dailyRate * $unpaidDays;

// Overtime calculations
$workDaysPerWeek   = (float)($emp['work_days_per_week']  ?? 5);
$workHoursPerWeek  = (float)($emp['work_hours_per_week'] ?? 40);
$hoursPerDay       = ($workDaysPerWeek > 0) ? $workHoursPerWeek / $workDaysPerWeek : 8;
$hourlyRate        = ($hoursPerDay > 0) ? $dailyRate / $hoursPerDay : 0;
$otMultiplier      = (float)($leave['ot_rate_multiplier'] ?? 1);

// Determine payment type
$paymentType = '—';
if (!$isOvertime) {
    if ($unpaidDays > 0 && $paidDays > 0)       $paymentType = 'Split (Partial Unpaid)';
    elseif ($unpaidDays > 0 && $paidDays == 0)  $paymentType = 'Unpaid';
    else                                         $paymentType = 'Paid';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Letter — <?= safe($emp['full_name']) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            background: #f5f5f5;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            background: #2d3748;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 99;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .toolbar span {
            font-size: 14px;
            font-weight: 600;
        }

        .toolbar-btns {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-print {
            background: #fff;
            color: #2d3748;
        }

        .btn-print:hover {
            background: #f0f0f0;
        }

        .btn-back {
            background: #4a5568;
            color: #fff;
        }

        .btn-back:hover {
            background: #2d3748;
        }

        /* A4 Page */
        .page {
            width: 210mm;
            min-height: 297mm;
            max-height: 297mm;
            overflow: hidden;
            margin: 20px auto;
            background: #fff;
            padding: 14mm 16mm 12mm;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
        }

        /* Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a202c;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .doc-header .company {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #1a202c;
        }

        .doc-header .company-sub {
            font-size: 10px;
            color: #718096;
            margin-top: 2px;
        }

        .doc-header .right {
            text-align: right;
            font-size: 10px;
            line-height: 1.6;
            color: #4a5568;
        }

        .doc-header .ref {
            font-size: 12px;
            font-weight: 700;
            color: #1a202c;
        }

        .status-stamp {
            display: inline-block;
            border: 2px solid #1a202c;
            padding: 3px 12px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-left: 6px;
            vertical-align: middle;
            background: #f7fafc;
        }

        /* Title */
        .doc-title {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 10px 0 12px;
            text-decoration: underline;
            color: #1a202c;
        }

        /* Sections */
        .section {
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #cbd5e0;
            padding-bottom: 4px;
            margin-bottom: 8px;
            color: #2d3748;
        }

        /* Grid rows */
        .row {
            display: flex;
            gap: 0;
            margin-bottom: 5px;
        }

        .cell {
            flex: 1;
        }

        .cell label {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            margin-bottom: 2px;
            font-weight: 600;
        }

        .cell span {
            font-size: 11px;
            font-weight: 600;
            color: #2d3748;
        }

        .cell span.normal {
            font-weight: normal;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 5px;
        }

        table thead th {
            background: #1a202c;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
        }

        table tbody tr:last-child td {
            border-bottom: 2px solid #1a202c;
            font-weight: 700;
            background: #f7fafc;
        }

        table .right {
            text-align: right;
        }

        /* Reason box */
        .reason-box {
            border: 1px solid #cbd5e0;
            padding: 8px 10px;
            font-size: 11px;
            line-height: 1.5;
            min-height: 30px;
            background: #f7fafc;
            color: #2d3748;
        }

        /* Signatures */
        .sig-strip {
            display: flex;
            gap: 0;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed #cbd5e0;
        }

        .sig-box {
            flex: 1;
            text-align: center;
            padding: 0 10px;
        }

        .sig-space {
            height: 36px;
        }

        .sig-line {
            border-top: 1px solid #1a202c;
            margin: 0 12px 6px;
        }

        .sig-name {
            font-size: 11px;
            font-weight: 700;
            color: #1a202c;
        }

        .sig-role {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            margin-top: 2px;
        }

        .sig-date {
            font-size: 10px;
            margin-top: 4px;
            color: #4a5568;
        }

        /* Footer */
        .doc-footer {
            border-top: 1px solid #e2e8f0;
            margin-top: 10px;
            padding-top: 6px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #718096;
        }

        /* Print styles */
        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                min-height: auto;
                max-height: none;
                padding: 10mm 14mm 8mm;
            }

            @page {
                size: A4;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <span>Leave Approval Letter — <?= safe($emp['full_name']) ?></span>
    <div class="toolbar-btns">
        <a href="javascript:history.back()" class="btn btn-back">← Back</a>
        <button class="btn btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
    </div>
</div>

<div class="page">

    <!-- Header -->
    <div class="doc-header">
        <div>
            <div class="company">LedgerOne ERP</div>
            <div class="company-sub">Human Resource Management</div>
        </div>
        <div class="right">
            <div class="ref">Ref: LV-<?= str_pad($leave['id'], 5, '0', STR_PAD_LEFT) ?></div>
            <div>Print Date: <?= $printDate ?></div>
            <div>
                Status:
                <span class="status-stamp"><?= $statusLabel ?></span>
            </div>
        </div>
    </div>

    <div class="doc-title">Leave <?= $isOvertime ? 'Overtime' : 'Approval' ?> Letter</div>

    <!-- Employee Information -->
    <div class="section">
        <div class="section-title">Employee Information</div>
        <div class="row">
            <div class="cell"><label>Full Name</label><span><?= safe($emp['full_name']) ?></span></div>
            <div class="cell"><label>Employee ID</label><span><?= safe($emp['employee_id']) ?></span></div>
            <div class="cell"><label>Department</label><span><?= safe($emp['department_name']) ?></span></div>
            <div class="cell"><label>Position</label><span><?= safe($emp['position_title']) ?></span></div>
        </div>
        <div class="row">
            <div class="cell"><label>Date of Birth</label><span class="normal"><?= formatDate($emp['date_of_birth']) ?></span></div>
            <div class="cell"><label>Gender</label><span class="normal"><?= safe(ucfirst($emp['gender'] ?? '')) ?></span></div>
            <div class="cell"><label>Hire Date</label><span class="normal"><?= formatDate($emp['hire_date']) ?></span></div>
            <div class="cell"><label>Employment Type</label><span class="normal"><?= safe(ucfirst($emp['employment_type'] ?? '')) ?></span></div>
        </div>
        <div class="row">
            <div class="cell"><label>Phone</label><span class="normal"><?= safe($emp['phone_number']) ?></span></div>
            <div class="cell"><label>Email</label><span class="normal"><?= safe($emp['email'] ?: $emp['personal_email']) ?></span></div>
            <div class="cell"><label>Reporting Manager</label><span class="normal"><?= $mgrName ?></span></div>
            <div class="cell"><label>Location / Shift</label><span class="normal"><?= safe($emp['work_location']) ?> / <?= safe($emp['shift_timing']) ?></span></div>
        </div>
        <div class="row">
            <div class="cell"><label>Emergency Contact</label><span class="normal"><?= safe($emp['emergency_contact_name']) ?><?= $emp['emergency_contact_phone'] ? ' — ' . safe($emp['emergency_contact_phone']) : '' ?></span></div>
            <div class="cell"><label>Address / City</label><span class="normal"><?= safe(trim(($emp['address'] ?? '') . ($emp['city'] ? ', ' . $emp['city'] : ''), ', ')) ?></span></div>
            <div class="cell"><label>Marital Status</label><span class="normal"><?= safe(ucfirst($emp['marital_status'] ?? '')) ?></span></div>
            <div class="cell"><label>Nationality</label><span class="normal"><?= safe($emp['nationality']) ?></span></div>
        </div>
    </div>

    <!-- Leave Details -->
    <div class="section">
        <div class="section-title">Leave Details</div>
        <?php if ($isOvertime): ?>
            <div class="row">
                <div class="cell"><label>Leave Type</label><span><?= safe($leave['type_name']) ?></span></div>
                <div class="cell"><label>Date</label><span><?= formatDate($leave['start_date']) ?></span></div>
                <div class="cell"><label>OT Hours</label><span><?= formatMoney($leave['ot_hours'], 1) ?> hrs</span></div>
                <div class="cell"><label>Rate Multiplier</label><span><?= formatMoney($otMultiplier, 1) ?>x</span></div>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="cell"><label>Leave Type</label><span><?= safe($leave['type_name']) ?></span></div>
                <div class="cell"><label>From</label><span><?= formatDate($leave['start_date']) ?></span></div>
                <div class="cell"><label>To</label><span><?= formatDate($leave['end_date']) ?></span></div>
                <div class="cell"><label>Total Days</label><span><?= formatMoney($leave['total_days'], 1) ?><?= !empty($leave['is_half_day']) ? ' (Half Day)' : '' ?></span></div>
            </div>
            <div class="row">
                <div class="cell"><label>Payment Type</label><span><?= $paymentType ?></span></div>
                <div class="cell"><label>Paid Days</label><span><?= formatMoney($paidDays, 1) ?></span></div>
                <div class="cell"><label>Unpaid Days</label><span><?= formatMoney($unpaidDays, 1) ?></span></div>
                <div class="cell"><label>Daily Rate (PKR)</label><span><?= formatMoney($dailyRate) ?></span></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reason & Contact -->
    <div class="section">
        <div class="section-title">Reason & Contact During Leave</div>
        <div class="row" style="margin-bottom: 5px;">
            <div class="cell"><label>Contact During Leave</label><span class="normal"><?= safe($leave['contact_during_leave']) ?></span></div>
        </div>
        <div class="reason-box"><?= safe($leave['reason']) ?></div>
    </div>

    <!-- Financial Summary -->
    <div class="section">
        <div class="section-title"><?= $isOvertime ? 'Overtime' : 'Financial' ?> Summary</div>
        <?php if ($isOvertime): ?>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Hours</th>
                        <th>Hourly Rate</th>
                        <th>Multiplier</th>
                        <th class="right">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Overtime Pay</td>
                        <td><?= formatMoney($leave['ot_hours'], 1) ?></td>
                        <td><?= formatMoney($hourlyRate) ?></td>
                        <td><?= formatMoney($otMultiplier, 1) ?>x</td>
                        <td class="right"><?= formatMoney($leave['ot_amount']) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4">Total OT Payable</td>
                        <td class="right"><?= formatMoney($leave['ot_amount']) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Days</th>
                        <th>Daily Rate (PKR)</th>
                        <th class="right">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($unpaidDays > 0): ?>
                        <tr>
                            <td>Unpaid Leave Deduction</td>
                            <td><?= formatMoney($unpaidDays, 1) ?></td>
                            <td><?= formatMoney($dailyRate) ?></td>
                            <td class="right">- <?= formatMoney($unpaidAmount) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Net Salary Deduction</td>
                        <td><?= formatMoney($unpaidDays, 1) ?></td>
                        <td>—</td>
                        <td class="right">- <?= formatMoney($unpaidAmount) ?></td>
                    </tr>
                </tbody>
            </table>
            <?php if ($unpaidDays == 0): ?>
                <div style="font-size: 11px; margin-top: 5px; font-style: italic; color: #718096;">
                    No salary deduction — all days are paid.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Compensation Overview -->
    <div class="section">
        <div class="section-title">Compensation Overview</div>
        <div class="row">
            <div class="cell"><label>Base Salary (PKR)</label><span><?= formatMoney($emp['base_salary'] ?? 0) ?></span></div>
            <div class="cell"><label>Frequency</label><span class="normal"><?= safe(ucfirst($emp['salary_frequency'] ?? '')) ?></span></div>
            <div class="cell"><label>Work Hrs / Week</label><span class="normal"><?= safe($emp['work_hours_per_week']) ?> hrs</span></div>
            <div class="cell"><label>Work Days / Week</label><span class="normal"><?= safe($emp['work_days_per_week']) ?> days</span></div>
        </div>
        <div class="row">
            <div class="cell"><label>Leave Balance (Paid)</label><span class="normal"><?= safe($emp['leave_balance_paid']) ?> days</span></div>
            <div class="cell"><label>Leave Balance (Sick)</label><span class="normal"><?= safe($emp['leave_balance_sick']) ?> days</span></div>
            <?php if ((float)($emp['housing_allowance'] ?? 0) > 0): ?>
                <div class="cell"><label>Housing Allow. (PKR)</label><span class="normal"><?= formatMoney($emp['housing_allowance']) ?></span></div>
            <?php else: ?>
                <div class="cell"></div>
            <?php endif; ?>
            <?php if ((float)($emp['transport_allowance'] ?? 0) > 0): ?>
                <div class="cell"><label>Transport Allow. (PKR)</label><span class="normal"><?= formatMoney($emp['transport_allowance']) ?></span></div>
            <?php else: ?>
                <div class="cell"></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Signatures -->
    <div class="sig-strip">
        <div class="sig-box">
            <div class="sig-space"></div>
            <div class="sig-line"></div>
            <div class="sig-name"><?= safe($emp['full_name']) ?></div>
            <div class="sig-role">Employee</div>
            <div class="sig-date">Date: _______________</div>
        </div>
        <div class="sig-box">
            <div class="sig-space"></div>
            <div class="sig-line"></div>
            <div class="sig-name"><?= safe($approverName) ?></div>
            <div class="sig-role">HR / Manager</div>
            <div class="sig-date">Date: <?= date('d M Y') ?></div>
        </div>
        <div class="sig-box">
            <div class="sig-space"></div>
            <div class="sig-line"></div>
            <div class="sig-name">Director / CEO</div>
            <div class="sig-role">Final Authority</div>
            <div class="sig-date">Date: _______________</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <span>Ref: LV-<?= str_pad($leave['id'], 5, '0', STR_PAD_LEFT) ?> | Generated: <?= $printDate ?></span>
        <span>System generated document — LedgerOne ERP</span>
    </div>

</div>

</body>
</html>