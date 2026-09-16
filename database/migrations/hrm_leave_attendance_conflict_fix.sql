-- ============================================================================
-- HRM leave/attendance follow-up fix.
--
-- Found during the full HRM audit: when an approved leave overwrites a day
-- that already had REAL attendance (present/absent/late/half_day — entered
-- via the normal Attendance module before the leave existed), the original
-- status/check-in/check-out was silently lost — cancelling the leave or an
-- early return afterward hardcoded the day back to 'present' regardless of
-- what it actually was. This adds a snapshot column so the original
-- attendance can be restored exactly, and leave-request.php/leave-approve.php
-- now require an explicit override before clobbering real attendance at all.
--
-- Safe to run multiple times; safe against existing data.
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'pre_leave_snapshot');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE attendance ADD COLUMN pre_leave_snapshot TEXT NULL AFTER leave_type_id', 'SELECT ''pre_leave_snapshot already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Also found during the audit: a half-day leave marked the whole attendance day
-- as 'on_leave' with no record that it was only half a day, so
-- calculate-suggested.php counted it as a FULL leave day (1.0) instead of 0.5 —
-- for unpaid half-day leave this doubled the deduction. This column lets
-- payroll calculation sum the real fraction instead of counting rows.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'leave_fraction');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE attendance ADD COLUMN leave_fraction DECIMAL(3,2) NULL AFTER pre_leave_snapshot', 'SELECT ''leave_fraction already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CRITICAL: leave_records.total_days was still an INT column (pre-dates the
-- half-day feature) — every half-day request (0.5) was silently rounded to a
-- whole day (1) the moment it hit the database, corrupting the leave balance
-- deduction, the leave register display, and payroll's half-day math. Widen
-- it to a real decimal; existing whole-day values (the vast majority of rows)
-- are unaffected by this widening.
SET @col_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'total_days');
SET @sql = IF(@col_type = 'int', 'ALTER TABLE leave_records MODIFY COLUMN total_days DECIMAL(6,2) NOT NULL', 'SELECT ''total_days already widened''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
