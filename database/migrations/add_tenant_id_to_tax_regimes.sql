-- Add tenant_id column to tax_regimes table
ALTER TABLE `tax_regimes` ADD COLUMN `tenant_id` INT NOT NULL DEFAULT 0 AFTER `id`;

-- Add index on tenant_id for better query performance
ALTER TABLE `tax_regimes` ADD INDEX `idx_tenant_id` (`tenant_id`);

-- Add composite index for common queries
ALTER TABLE `tax_regimes` ADD INDEX `idx_tenant_active` (`tenant_id`, `is_active`);
