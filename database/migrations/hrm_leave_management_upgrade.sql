-- ============================================================================
-- HRM Leave Management / Attendance / Payroll integration upgrade.
--
-- Extends the EXISTING leave_records table (does not replace it) and adds
-- the normalized tables needed for a configurable leave-type system, a
-- proper leave balance ledger, attendance<->leave linkage, and payroll
-- period/status + per-employee attendance breakdown.
--
-- Safe to run multiple times (every statement checks before acting) and
-- safe to run against a DB that already has real leave_records data.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. leave_types — configurable leave types per tenant (replaces the
--    hardcoded 'paid'/'sick'/'unpaid' strings used until now).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    requires_approval TINYINT(1) NOT NULL DEFAULT 1,
    allow_carry_forward TINYINT(1) NOT NULL DEFAULT 0,
    max_carry_forward_days DECIMAL(6,2) DEFAULT NULL,
    max_days_per_year DECIMAL(6,2) DEFAULT NULL,
    track_balance TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT DEFAULT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_leave_types_tenant_code (tenant_id, code),
    KEY idx_leave_types_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed the 4 default leave types for every tenant that doesn't have them yet
-- (mirrors the 3 legacy employees.leave_balance_* columns + adds Casual Leave).
INSERT INTO leave_types (tenant_id, name, code, is_paid, requires_approval, allow_carry_forward, track_balance, display_order)
SELECT t.tenant_id, 'Annual Leave', 'ANNUAL', 1, 1, 1, 1, 1
FROM (SELECT DISTINCT tenant_id FROM employees) t
WHERE NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.tenant_id = t.tenant_id AND lt.code = 'ANNUAL');

INSERT INTO leave_types (tenant_id, name, code, is_paid, requires_approval, allow_carry_forward, track_balance, display_order)
SELECT t.tenant_id, 'Sick Leave', 'SICK', 1, 1, 0, 1, 2
FROM (SELECT DISTINCT tenant_id FROM employees) t
WHERE NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.tenant_id = t.tenant_id AND lt.code = 'SICK');

INSERT INTO leave_types (tenant_id, name, code, is_paid, requires_approval, allow_carry_forward, track_balance, display_order)
SELECT t.tenant_id, 'Casual Leave', 'CASUAL', 1, 1, 0, 1, 3
FROM (SELECT DISTINCT tenant_id FROM employees) t
WHERE NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.tenant_id = t.tenant_id AND lt.code = 'CASUAL');

INSERT INTO leave_types (tenant_id, name, code, is_paid, requires_approval, allow_carry_forward, track_balance, max_days_per_year, display_order)
SELECT t.tenant_id, 'Unpaid Leave', 'UNPAID', 0, 1, 0, 0, NULL, 4
FROM (SELECT DISTINCT tenant_id FROM employees) t
WHERE NOT EXISTS (SELECT 1 FROM leave_types lt WHERE lt.tenant_id = t.tenant_id AND lt.code = 'UNPAID');

-- ----------------------------------------------------------------------------
-- 2. employee_leave_balances — current-year balance snapshot per
--    employee + leave type (derived/reconciled from the transaction ledger
--    below, kept as a fast-read cache).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    employee_id VARCHAR(50) NOT NULL COMMENT 'employees.employee_id (business code)',
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    opening_balance DECIMAL(6,2) NOT NULL DEFAULT 0,
    allocated DECIMAL(6,2) NOT NULL DEFAULT 0,
    carry_forward DECIMAL(6,2) NOT NULL DEFAULT 0,
    used DECIMAL(6,2) NOT NULL DEFAULT 0,
    pending DECIMAL(6,2) NOT NULL DEFAULT 0,
    adjustment DECIMAL(6,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_elb_emp_type_year (tenant_id, employee_id, leave_type_id, year),
    KEY idx_elb_tenant_emp (tenant_id, employee_id),
    CONSTRAINT fk_elb_leave_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 3. employee_leave_balance_transactions — immutable ledger. Every balance
--    change (opening, allocation, carry-forward, leave used, reversal,
--    manual adjustment) is recorded here; employee_leave_balances is a
--    cache that must always be reconcilable from this table.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_leave_balance_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    employee_id VARCHAR(50) NOT NULL,
    leave_type_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type ENUM('opening','allocation','carry_forward','leave_used','leave_reversed','adjustment') NOT NULL,
    days DECIMAL(6,2) NOT NULL COMMENT 'signed: positive = credit, negative = debit',
    reference_table VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_elbt_tenant_emp_type (tenant_id, employee_id, leave_type_id),
    KEY idx_elbt_reference (reference_table, reference_id),
    CONSTRAINT fk_elbt_leave_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 4. hrm_settings — one row per tenant: working-week + payroll divisor
--    policy. No equivalent config existed anywhere in the schema before.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hrm_settings (
    tenant_id INT NOT NULL PRIMARY KEY,
    weekly_off_days VARCHAR(20) NOT NULL DEFAULT '0' COMMENT 'comma-separated day-of-week, 0=Sunday..6=Saturday',
    payroll_divisor_basis ENUM('fixed','calendar_days','working_days') NOT NULL DEFAULT 'fixed',
    payroll_divisor_days INT NOT NULL DEFAULT 26,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO hrm_settings (tenant_id)
SELECT t.tenant_id FROM (SELECT DISTINCT tenant_id FROM employees) t
WHERE NOT EXISTS (SELECT 1 FROM hrm_settings hs WHERE hs.tenant_id = t.tenant_id);

-- ----------------------------------------------------------------------------
-- 5. company_holidays — no holiday calendar existed before.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS company_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    UNIQUE KEY uk_holiday_tenant_date (tenant_id, holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 6. Extend leave_records (the EXISTING table) with approval-workflow,
--    half-day, cancellation and return-reconciliation columns. Nothing
--    existing is removed or renamed.
-- ----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'leave_type_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN leave_type_id INT NULL AFTER leave_type', 'SELECT ''leave_type_id already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'is_half_day');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN is_half_day TINYINT(1) NOT NULL DEFAULT 0 AFTER total_days', 'SELECT ''is_half_day already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'half_day_period');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN half_day_period ENUM(''morning'',''afternoon'') NULL AFTER is_half_day', 'SELECT ''half_day_period already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN approved_by INT NULL AFTER status', 'SELECT ''approved_by already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by', 'SELECT ''approved_at already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'rejected_reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN rejected_reason TEXT NULL AFTER approved_at', 'SELECT ''rejected_reason already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'cancelled_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN cancelled_by INT NULL AFTER rejected_reason', 'SELECT ''cancelled_by already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'cancelled_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN cancelled_at TIMESTAMP NULL AFTER cancelled_by', 'SELECT ''cancelled_at already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'cancellation_reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_at', 'SELECT ''cancellation_reason already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'actual_return_date');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN actual_return_date DATE NULL AFTER cancellation_reason', 'SELECT ''actual_return_date already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'overstay_days');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN overstay_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER actual_return_date', 'SELECT ''overstay_days already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'override_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN override_by INT NULL AFTER overstay_days', 'SELECT ''override_by already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_records' AND COLUMN_NAME = 'override_reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE leave_records ADD COLUMN override_reason TEXT NULL AFTER override_by', 'SELECT ''override_reason already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 7. Fix leave_records.employee_id convention. Every other HR table
--    (attendance, payroll_entries_items, employees_ledger queries) keys
--    employee_id off employees.employee_id (the VARCHAR business code,
--    e.g. "ALUM-0001"). leave_records was the one exception — it was
--    storing employees.id (the INT primary key) as a string instead.
--    Normalize the resolvable rows; rows pointing at an employee id that
--    no longer exists are left untouched (nothing to resolve them to) and
--    will be visible in the report as pre-existing orphaned data.
-- ----------------------------------------------------------------------------
UPDATE leave_records lr
JOIN employees e ON e.id = CAST(lr.employee_id AS UNSIGNED) AND e.tenant_id = lr.tenant_id
SET lr.employee_id = e.employee_id
WHERE lr.employee_id REGEXP '^[0-9]+$';

-- ----------------------------------------------------------------------------
-- 8. Backfill leave_type_id on existing leave_records rows from the old
--    free-text leave_type column, and normalize legacy status values.
-- ----------------------------------------------------------------------------
UPDATE leave_records lr
JOIN leave_types lt ON lt.tenant_id = lr.tenant_id
    AND lt.code = CASE LOWER(lr.leave_type)
        WHEN 'paid' THEN 'ANNUAL'
        WHEN 'annual' THEN 'ANNUAL'
        WHEN 'sick' THEN 'SICK'
        WHEN 'casual' THEN 'CASUAL'
        WHEN 'unpaid' THEN 'UNPAID'
        ELSE '___NO_MATCH___'
    END
SET lr.leave_type_id = lt.id
WHERE lr.leave_type_id IS NULL;

-- ----------------------------------------------------------------------------
-- 9. Seed employee_leave_balances + an 'opening' ledger transaction from
--    the existing employees.leave_balance_paid/sick/unpaid columns (those
--    columns are left in place, untouched, for backward compatibility —
--    this just gives the new normalized system a correct starting point).
-- ----------------------------------------------------------------------------
INSERT INTO employee_leave_balances (tenant_id, employee_id, leave_type_id, year, opening_balance)
SELECT e.tenant_id, e.employee_id, lt.id, YEAR(CURDATE()), e.leave_balance_paid
FROM employees e
JOIN leave_types lt ON lt.tenant_id = e.tenant_id AND lt.code = 'ANNUAL'
WHERE e.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM employee_leave_balances elb
      WHERE elb.tenant_id = e.tenant_id AND elb.employee_id = e.employee_id
        AND elb.leave_type_id = lt.id AND elb.year = YEAR(CURDATE())
  );

INSERT INTO employee_leave_balances (tenant_id, employee_id, leave_type_id, year, opening_balance)
SELECT e.tenant_id, e.employee_id, lt.id, YEAR(CURDATE()), e.leave_balance_sick
FROM employees e
JOIN leave_types lt ON lt.tenant_id = e.tenant_id AND lt.code = 'SICK'
WHERE e.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM employee_leave_balances elb
      WHERE elb.tenant_id = e.tenant_id AND elb.employee_id = e.employee_id
        AND elb.leave_type_id = lt.id AND elb.year = YEAR(CURDATE())
  );

INSERT INTO employee_leave_balances (tenant_id, employee_id, leave_type_id, year, opening_balance)
SELECT e.tenant_id, e.employee_id, lt.id, YEAR(CURDATE()), 0
FROM employees e
JOIN leave_types lt ON lt.tenant_id = e.tenant_id AND lt.code = 'CASUAL'
WHERE e.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM employee_leave_balances elb
      WHERE elb.tenant_id = e.tenant_id AND elb.employee_id = e.employee_id
        AND elb.leave_type_id = lt.id AND elb.year = YEAR(CURDATE())
  );

-- Opening ledger transactions matching the balances just seeded (only for
-- non-zero openings, so we don't clutter the ledger with 0-day noise).
INSERT INTO employee_leave_balance_transactions (tenant_id, employee_id, leave_type_id, transaction_date, transaction_type, days, reference_table, reason)
SELECT elb.tenant_id, elb.employee_id, elb.leave_type_id, CURDATE(), 'opening', elb.opening_balance, 'employees', 'Migrated from employees.leave_balance_* columns'
FROM employee_leave_balances elb
WHERE elb.opening_balance != 0
  AND NOT EXISTS (
      SELECT 1 FROM employee_leave_balance_transactions t
      WHERE t.tenant_id = elb.tenant_id AND t.employee_id = elb.employee_id
        AND t.leave_type_id = elb.leave_type_id AND t.transaction_type = 'opening'
  );

-- Replay each existing APPROVED leave_records row as a 'leave_used' ledger
-- transaction and reflect it in the 'used' column, so the new balance
-- system starts in sync with what the old system had already deducted
-- (only for leave types that track balance, e.g. not Unpaid).
INSERT INTO employee_leave_balance_transactions (tenant_id, employee_id, leave_type_id, transaction_date, transaction_type, days, reference_table, reference_id, reason)
SELECT lr.tenant_id, lr.employee_id, lr.leave_type_id, lr.start_date, 'leave_used', -lr.total_days, 'leave_records', lr.id, CONCAT('Migrated existing leave record #', lr.id)
FROM leave_records lr
JOIN leave_types lt ON lt.id = lr.leave_type_id AND lt.track_balance = 1
WHERE lr.status = 'approved' AND lr.leave_type_id IS NOT NULL
  AND lr.employee_id NOT REGEXP '^[0-9]+$'
  AND NOT EXISTS (
      SELECT 1 FROM employee_leave_balance_transactions t
      WHERE t.reference_table = 'leave_records' AND t.reference_id = lr.id
  );

UPDATE employee_leave_balances elb
JOIN (
    SELECT tenant_id, employee_id, leave_type_id, SUM(-days) AS used_days
    FROM employee_leave_balance_transactions
    WHERE transaction_type = 'leave_used'
    GROUP BY tenant_id, employee_id, leave_type_id
) u ON u.tenant_id = elb.tenant_id AND u.employee_id = elb.employee_id AND u.leave_type_id = elb.leave_type_id
SET elb.used = u.used_days
WHERE elb.year = YEAR(CURDATE());

-- ----------------------------------------------------------------------------
-- 10. attendance — link to the leave that generated an auto-marked row.
-- ----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'leave_request_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE attendance ADD COLUMN leave_request_id INT NULL AFTER status', 'SELECT ''leave_request_id already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'leave_type_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE attendance ADD COLUMN leave_type_id INT NULL AFTER leave_request_id', 'SELECT ''leave_type_id already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 11. payroll_entries — period + finalization state. Existing rows default
--     to 'finalized' (they predate the lock concept and were always
--     treated as final), so nothing already-saved silently becomes
--     editable/draft.
-- ----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'status');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries ADD COLUMN status ENUM(''draft'',''finalized'') NOT NULL DEFAULT ''finalized'' AFTER total_amount', 'SELECT ''status already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'period_start');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries ADD COLUMN period_start DATE NULL AFTER status', 'SELECT ''period_start already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'period_end');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries ADD COLUMN period_end DATE NULL AFTER period_start', 'SELECT ''period_end already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'finalized_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries ADD COLUMN finalized_at TIMESTAMP NULL AFTER period_end', 'SELECT ''finalized_at already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'finalized_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries ADD COLUMN finalized_by INT NULL AFTER finalized_at', 'SELECT ''finalized_by already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 12. payroll_entries_items — per-item attendance/leave breakdown, so every
--     deduction shown in payroll is traceable back to specific day counts.
--     All nullable: existing rows (Advance/Bonus/old Salary entries) stay
--     valid with NULLs, nothing is recalculated retroactively.
-- ----------------------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'present_days');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN present_days DECIMAL(5,2) NULL AFTER amount', 'SELECT ''present_days already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'paid_leave_days');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN paid_leave_days DECIMAL(5,2) NULL AFTER present_days', 'SELECT ''paid_leave_days already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'unpaid_leave_days');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN unpaid_leave_days DECIMAL(5,2) NULL AFTER paid_leave_days', 'SELECT ''unpaid_leave_days already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'absent_days');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN absent_days DECIMAL(5,2) NULL AFTER unpaid_leave_days', 'SELECT ''absent_days already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'daily_rate');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN daily_rate DECIMAL(12,2) NULL AFTER absent_days', 'SELECT ''daily_rate already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'gross_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN gross_amount DECIMAL(15,2) NULL AFTER daily_rate', 'SELECT ''gross_amount already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'deduction_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN deduction_amount DECIMAL(15,2) NULL AFTER gross_amount', 'SELECT ''deduction_amount already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'period_start');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN period_start DATE NULL AFTER deduction_amount', 'SELECT ''period_start already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_entries_items' AND COLUMN_NAME = 'period_end');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payroll_entries_items ADD COLUMN period_end DATE NULL AFTER period_start', 'SELECT ''period_end already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Additive-only enum extension (existing values untouched) so a payroll
-- line item can explicitly be typed as a leave/absence deduction instead
-- of being folded anonymously into a plain "Salary" amount.
ALTER TABLE payroll_entries_items
    MODIFY COLUMN type ENUM('Salary','Salary Adjustment','Advance','Advance Return','Commission','Bonus','Allowance','Daily Wages','Unpaid Leave Deduction','Absence Deduction') NOT NULL;
