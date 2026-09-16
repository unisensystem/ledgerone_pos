<?php
// Shared helpers for the leave management module. Included (not executed
// standalone) by the action endpoints in this directory.

/**
 * hrm_settings row for a tenant, with defaults if the tenant somehow has
 * none (shouldn't happen post-migration, but keeps every caller safe).
 */
function getHrmSettings(PDO $pdo, int $tenantId): array {
    $stmt = $pdo->prepare("SELECT * FROM hrm_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;
    return [
        'tenant_id' => $tenantId,
        'weekly_off_days' => '0',
        'payroll_divisor_basis' => 'fixed',
        'payroll_divisor_days' => 26,
    ];
}

/**
 * Every calendar date in [start, end] inclusive that is NOT a configured
 * weekly-off day and NOT a company holiday — i.e. the actual working days
 * a leave request should be charged for.
 *
 * Returns a list of 'Y-m-d' strings.
 */
function getWorkingDaysInRange(PDO $pdo, int $tenantId, string $start, string $end): array {
    $settings = getHrmSettings($pdo, $tenantId);
    $weeklyOff = array_filter(array_map('intval', explode(',', $settings['weekly_off_days'])), fn($d) => $d >= 0 && $d <= 6);
    $weeklyOff = array_flip($weeklyOff);

    $holidayStmt = $pdo->prepare("SELECT holiday_date FROM company_holidays WHERE tenant_id = ? AND holiday_date BETWEEN ? AND ?");
    $holidayStmt->execute([$tenantId, $start, $end]);
    $holidays = array_flip($holidayStmt->fetchAll(PDO::FETCH_COLUMN));

    $days = [];
    $cursor = new DateTime($start);
    $endDate = new DateTime($end);
    while ($cursor <= $endDate) {
        $dateStr = $cursor->format('Y-m-d');
        $dow = (int) $cursor->format('w'); // 0=Sunday..6=Saturday, matches weekly_off_days convention
        if (!isset($weeklyOff[$dow]) && !isset($holidays[$dateStr])) {
            $days[] = $dateStr;
        }
        $cursor->modify('+1 day');
    }
    return $days;
}

/**
 * Total leave days to charge for a request: count of working days in the
 * range (weekends/holidays excluded), minus 0.5 if it's a half-day leave.
 * A half-day request must be a single-day request (validated by the caller).
 */
function calculateLeaveDays(PDO $pdo, int $tenantId, string $start, string $end, bool $isHalfDay): float {
    $workingDays = getWorkingDaysInRange($pdo, $tenantId, $start, $end);
    $count = count($workingDays);
    if ($isHalfDay) {
        return $count > 0 ? 0.5 : 0.0;
    }
    return (float) $count;
}

/**
 * Records one leave-balance ledger transaction and applies it to the
 * employee_leave_balances cache row for transaction_date's year (creating
 * the balance row if this is the first transaction this year for that
 * employee+leave type). Returns the new remaining balance (only meaningful
 * for track_balance leave types).
 */
function applyLeaveBalanceTransaction(
    PDO $pdo, int $tenantId, string $employeeId, int $leaveTypeId,
    string $date, string $type, float $days, ?string $refTable, ?int $refId,
    ?string $reason, ?int $userId
): void {
    $year = (int) date('Y', strtotime($date));

    $stmt = $pdo->prepare("
        INSERT INTO employee_leave_balance_transactions
            (tenant_id, employee_id, leave_type_id, transaction_date, transaction_type, days, reference_table, reference_id, reason, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tenantId, $employeeId, $leaveTypeId, $date, $type, $days, $refTable, $refId, $reason, $userId]);

    // Ensure a balance row exists for this employee/type/year.
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO employee_leave_balances (tenant_id, employee_id, leave_type_id, year)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$tenantId, $employeeId, $leaveTypeId, $year]);

    // 'used'/'pending' are tracked as a positive magnitude in the cache row.
    // leave_used arrives negative (a debit) and leave_reversed arrives
    // positive (a credit / refund) — both apply to the SAME 'used' column
    // via -$days, which correctly increases it for a debit and decreases
    // it for a credit.
    $column = match ($type) {
        'leave_used', 'leave_reversed' => 'used',
        'adjustment' => 'adjustment',
        'allocation' => 'allocated',
        'carry_forward' => 'carry_forward',
        default => null,
    };
    if ($column === 'used') {
        $stmt = $pdo->prepare("UPDATE employee_leave_balances SET used = used + ? WHERE tenant_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?");
        $stmt->execute([-$days, $tenantId, $employeeId, $leaveTypeId, $year]);
    } elseif ($column) {
        $stmt = $pdo->prepare("UPDATE employee_leave_balances SET {$column} = {$column} + ? WHERE tenant_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?");
        $stmt->execute([$days, $tenantId, $employeeId, $leaveTypeId, $year]);
    }
}

/** Adjusts the 'pending' column on the balance cache (no ledger row — pending is a hold, not a committed transaction). */
function adjustPendingBalance(PDO $pdo, int $tenantId, string $employeeId, int $leaveTypeId, string $date, float $delta): void {
    $year = (int) date('Y', strtotime($date));
    $stmt = $pdo->prepare("INSERT IGNORE INTO employee_leave_balances (tenant_id, employee_id, leave_type_id, year) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tenantId, $employeeId, $leaveTypeId, $year]);
    $stmt = $pdo->prepare("UPDATE employee_leave_balances SET pending = pending + ? WHERE tenant_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?");
    $stmt->execute([$delta, $tenantId, $employeeId, $leaveTypeId, $year]);
}

/** Remaining balance = opening + allocated + carry_forward + adjustment - used - pending, for the current year. */
function getRemainingBalance(PDO $pdo, int $tenantId, string $employeeId, int $leaveTypeId, ?int $year = null): float {
    $year = $year ?? (int) date('Y');
    $stmt = $pdo->prepare("SELECT opening_balance, allocated, carry_forward, used, pending, adjustment FROM employee_leave_balances WHERE tenant_id = ? AND employee_id = ? AND leave_type_id = ? AND year = ?");
    $stmt->execute([$tenantId, $employeeId, $leaveTypeId, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return 0.0;
    return (float) $row['opening_balance'] + (float) $row['allocated'] + (float) $row['carry_forward'] + (float) $row['adjustment'] - (float) $row['used'] - (float) $row['pending'];
}

/**
 * Any working day in the range that already has a REAL attendance record
 * (present/absent/late/half_day — entered via the normal Attendance module,
 * not already 'on_leave') — i.e. a day that approving this leave would
 * silently overwrite. Callers use this to block/require override, per the
 * rule "must not silently create duplicate/conflicting attendance".
 * Returns a list of ['date' => ..., 'status' => ...].
 */
function getAttendanceConflicts(PDO $pdo, int $tenantId, string $employeeId, array $workingDays): array {
    if (empty($workingDays)) return [];
    $placeholders = implode(',', array_fill(0, count($workingDays), '?'));
    $stmt = $pdo->prepare("
        SELECT attendance_date, status FROM attendance
        WHERE tenant_id = ? AND employee_id = ? AND attendance_date IN ($placeholders) AND status != 'on_leave'
    ");
    $stmt->execute([$tenantId, $employeeId, ...$workingDays]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marks/updates attendance rows for every working day in [start,end] as
 * 'on_leave' for this leave request, without duplicating rows that already
 * exist for that employee+date (updates them in place instead, preserving
 * whatever check-in/out data might already be there while overwriting the
 * status — matches spec: "do not create duplicate attendance records").
 *
 * If an existing row had REAL attendance (not already on_leave), its
 * status/check-in/check-out/notes are snapshotted first so a later
 * cancel/early-return can restore the exact original state instead of
 * guessing 'present'. Callers are expected to have already blocked/gotten
 * an override for this via getAttendanceConflicts() before calling here.
 */
function syncAttendanceForLeave(PDO $pdo, int $tenantId, string $employeeId, int $leaveRequestId, int $leaveTypeId, array $workingDays, ?int $userId, float $fraction = 1.0): void {
    foreach ($workingDays as $date) {
        $stmt = $pdo->prepare("SELECT id, status, check_in_time, check_out_time, notes FROM attendance WHERE tenant_id = ? AND employee_id = ? AND attendance_date = ?");
        $stmt->execute([$tenantId, $employeeId, $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Only snapshot if this row isn't already on_leave (don't overwrite an
            // earlier, more accurate snapshot with an intermediate on_leave state).
            $snapshot = null;
            if ($existing['status'] !== 'on_leave') {
                $snapshot = json_encode([
                    'status' => $existing['status'],
                    'check_in_time' => $existing['check_in_time'],
                    'check_out_time' => $existing['check_out_time'],
                    'notes' => $existing['notes'],
                ]);
            }
            $stmt = $pdo->prepare("
                UPDATE attendance SET status = 'on_leave', leave_request_id = ?, leave_type_id = ?, leave_fraction = ?, pre_leave_snapshot = COALESCE(?, pre_leave_snapshot)
                WHERE id = ?
            ");
            $stmt->execute([$leaveRequestId, $leaveTypeId, $fraction, $snapshot, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance (tenant_id, employee_id, check_in_time, attendance_date, status, leave_request_id, leave_type_id, leave_fraction)
                VALUES (?, ?, ?, ?, 'on_leave', ?, ?, ?)
            ");
            // check_in_time is NOT NULL in the existing schema; use midnight of the
            // leave date as a harmless placeholder for a day with no actual check-in.
            $stmt->execute([$tenantId, $employeeId, $date . ' 00:00:00', $date, $leaveRequestId, $leaveTypeId, $fraction]);
        }
    }
}

/**
 * Reverts attendance rows this leave request created/marked back to a clean
 * state (used on reject/cancel). Rows this leave created outright (fake
 * midnight check-in, no real attendance behind it) are removed. Rows that
 * had real attendance before being overridden into on_leave are restored
 * from their pre_leave_snapshot exactly, rather than guessed as 'present'.
 */
function clearAttendanceForLeave(PDO $pdo, int $tenantId, int $leaveRequestId): void {
    // Rows this leave request created outright (no real check-in ever happened) — remove them.
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE tenant_id = ? AND leave_request_id = ? AND check_in_time = DATE_FORMAT(attendance_date, '%Y-%m-%d 00:00:00') AND pre_leave_snapshot IS NULL");
    $stmt->execute([$tenantId, $leaveRequestId]);

    // Rows that had a real pre-leave snapshot — restore it exactly.
    $stmt = $pdo->prepare("SELECT id, pre_leave_snapshot FROM attendance WHERE tenant_id = ? AND leave_request_id = ? AND pre_leave_snapshot IS NOT NULL");
    $stmt->execute([$tenantId, $leaveRequestId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $snap = json_decode($row['pre_leave_snapshot'], true);
        $upd = $pdo->prepare("UPDATE attendance SET status = ?, check_in_time = ?, check_out_time = ?, notes = ?, leave_request_id = NULL, leave_type_id = NULL, leave_fraction = NULL, pre_leave_snapshot = NULL WHERE id = ?");
        $upd->execute([$snap['status'], $snap['check_in_time'], $snap['check_out_time'], $snap['notes'], $row['id']]);
    }

    // Any remaining rows still linked to this leave (no snapshot, not the fake-row
    // pattern above — e.g. a row synced before this fix shipped) fall back to 'present'.
    $stmt = $pdo->prepare("UPDATE attendance SET status = 'present', leave_request_id = NULL, leave_type_id = NULL, leave_fraction = NULL WHERE tenant_id = ? AND leave_request_id = ?");
    $stmt->execute([$tenantId, $leaveRequestId]);
}
