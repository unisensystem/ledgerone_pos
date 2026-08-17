-- Make tax_regime_id required (NOT NULL) in tax_rates table
-- All tax rates must be linked to a tax regime
ALTER TABLE `tax_rates` 
MODIFY COLUMN `tax_regime_id` INT UNSIGNED NOT NULL,
ADD CONSTRAINT `fk_tax_rates_regime` 
FOREIGN KEY (`tax_regime_id`) REFERENCES `tax_regimes`(`id`) ON DELETE RESTRICT;

-- Verify the change
DESCRIBE tax_rates;
SHOW CREATE TABLE tax_rates\G
