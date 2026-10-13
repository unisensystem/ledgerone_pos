-- ============================================================
-- Add POS counter support
-- Run against: ledgerone_tenant
-- ============================================================

-- 1. Counters master table (per branch)
CREATE TABLE IF NOT EXISTS `counters` (
  `id`          int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id`   int(11) NOT NULL,
  `company_id`  int(11) DEFAULT NULL,
  `branch_id`   int(11) NOT NULL,
  `counter_name` varchar(100) NOT NULL,
  `is_active`   tinyint(1) NOT NULL DEFAULT 1,
  `created_by`  int(11) DEFAULT NULL,
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_counters_branch` (`branch_id`),
  KEY `idx_counters_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Link counter to each POS sale invoice
ALTER TABLE `sale_invoice`
  ADD COLUMN `counter_id`     int(11)       DEFAULT NULL AFTER `branch_id`,
  ADD COLUMN `payment_method` varchar(50)   DEFAULT NULL AFTER `amount_paid_auto_fill`,
  ADD COLUMN `amount_paid`    decimal(15,2) DEFAULT 0.00 AFTER `payment_method`,
  ADD KEY `idx_si_counter` (`counter_id`);

-- 3. Link counter to each receive voucher created by the POS
ALTER TABLE `receive_voucher`
  ADD COLUMN `counter_id` int(11) DEFAULT NULL AFTER `dsr_no`,
  ADD KEY `idx_rv_counter` (`counter_id`);

-- 4. Extend opening-cash tracking to counter level
ALTER TABLE `cash_opening`
  ADD COLUMN `counter_id` int(11) DEFAULT NULL AFTER `branch_id`,
  ADD KEY `idx_co_counter` (`counter_id`);
