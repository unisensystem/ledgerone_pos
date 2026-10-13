-- Add country_id column to tax_regimes table
ALTER TABLE `tax_regimes` ADD COLUMN `country_id` INT(11) DEFAULT NULL AFTER `tenant_id`;

-- Add foreign key constraint
ALTER TABLE `tax_regimes` ADD CONSTRAINT `fk_tax_regimes_country` 
FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE SET NULL;

-- Add index for better query performance
ALTER TABLE `tax_regimes` ADD INDEX `idx_country_id` (`country_id`);
