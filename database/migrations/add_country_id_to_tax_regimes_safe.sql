-- Migration: Add country_id to tax_regimes (idempotent)
-- Description: Adds country_id column to tax_regimes with a foreign key
--              referencing the countries table. Safe to re-run.

ALTER TABLE `tax_regimes`
    ADD COLUMN IF NOT EXISTS `country_id` INT(11) DEFAULT NULL AFTER `tenant_id`;

ALTER TABLE `tax_regimes`
    ADD INDEX IF NOT EXISTS `idx_tr_country_id` (`country_id`);

ALTER TABLE `tax_regimes`
    ADD CONSTRAINT `fk_tax_regimes_country`
    FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE SET NULL;
