-- Per-tenant toggle: whether Pending post-dated cheques (received from customers)
-- should count towards the customer ledger balance/totals, or stay informational-only
-- (bracketed, not counted) until Approved. Default is 0 (existing behavior unchanged).
CREATE TABLE `pdc_ledger_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `pending_pdc_affects_ledger` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_pdc_ledger_settings_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
