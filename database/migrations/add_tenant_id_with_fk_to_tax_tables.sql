-- Migration: Add tenant_id with Foreign Key to tax_regimes and tax_rates
-- Description: Adds tenant_id column to tax_regimes and tax_rates tables with
--              a foreign key constraint referencing ledgerone_public.tenants(id).
-- Note: Uses IF NOT EXISTS guards so this is safe to re-run.

-- ─── tax_regimes ─────────────────────────────────────────────────────────────

ALTER TABLE `tax_regimes`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT NOT NULL DEFAULT 0 AFTER `id`;

ALTER TABLE `tax_regimes`
    ADD INDEX IF NOT EXISTS `idx_tr_tenant_id` (`tenant_id`);

ALTER TABLE `tax_regimes`
    ADD INDEX IF NOT EXISTS `idx_tr_tenant_active` (`tenant_id`, `is_active`);

ALTER TABLE `tax_regimes`
    ADD CONSTRAINT `fk_tax_regimes_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

-- ─── tax_rates ────────────────────────────────────────────────────────────────

ALTER TABLE `tax_rates`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT NOT NULL DEFAULT 0 AFTER `id`;

ALTER TABLE `tax_rates`
    ADD INDEX IF NOT EXISTS `idx_txr_tenant_id` (`tenant_id`);

ALTER TABLE `tax_rates`
    ADD INDEX IF NOT EXISTS `idx_txr_tenant_active` (`tenant_id`, `is_active`);

ALTER TABLE `tax_rates`
    ADD CONSTRAINT `fk_tax_rates_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;
