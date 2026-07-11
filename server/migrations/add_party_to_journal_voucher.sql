-- Add party_type and party_id to journal_voucher for balance adjustment tracking
ALTER TABLE `journal_voucher`
    ADD COLUMN `party_type` VARCHAR(20) NULL DEFAULT NULL COMMENT 'customer or supplier' AFTER `status`,
    ADD COLUMN `party_id`   INT          NULL DEFAULT NULL COMMENT 'customers.id or suppliers.id' AFTER `party_type`;

-- Index for fast ledger lookups
CREATE INDEX `idx_jv_party` ON `journal_voucher` (`tenant_id`, `party_type`, `party_id`);
