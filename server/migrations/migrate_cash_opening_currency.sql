-- ============================================================================
-- MIGRATION: Update cash_opening table to use currency_id with proper joins
-- ============================================================================

-- Step 1: Add currency_id column if it doesn't exist
ALTER TABLE `cash_opening` ADD COLUMN `currency_id` INT NULL AFTER `branch_id`;

-- Step 2: Create mapping table for currency codes to IDs (for reference)
-- This helps us map existing currency codes to proper currency IDs

-- Step 3: Map existing currency codes to currency IDs
-- Handle all variants: USD, PKR, Pak, EUR, GBP, AED, UAE
UPDATE `cash_opening` SET `currency_id` = 1 WHERE `currency` IN ('USD', 'usd');
UPDATE `cash_opening` SET `currency_id` = 2 WHERE `currency` IN ('PKR', 'Pak', 'pak', 'pkr');
UPDATE `cash_opening` SET `currency_id` = 3 WHERE `currency` IN ('EUR', 'eur');
UPDATE `cash_opening` SET `currency_id` = 4 WHERE `currency` IN ('GBP', 'gbp');
UPDATE `cash_opening` SET `currency_id` = 5 WHERE `currency` IN ('AED', 'UAE', 'uae', 'aed');

-- Step 4: Verify all records have been mapped
-- SELECT COUNT(*) as unmapped FROM cash_opening WHERE currency_id IS NULL;

-- Step 5: Add NOT NULL constraint after verification
ALTER TABLE `cash_opening` MODIFY COLUMN `currency_id` INT NOT NULL;

-- Step 6: Add foreign key constraint
ALTER TABLE `cash_opening` ADD CONSTRAINT `fk_cash_opening_currency_id` 
FOREIGN KEY (`currency_id`) REFERENCES `ledgerone_public`.`currencies`(`id`) 
ON DELETE RESTRICT ON UPDATE CASCADE;

-- Step 7: Drop old currency column
ALTER TABLE `cash_opening` DROP COLUMN `currency`;

-- ============================================================================
-- VERIFICATION QUERIES (Run these to verify the migration)
-- ============================================================================

-- Check table structure
-- DESCRIBE cash_opening;

-- Check data with proper joins
-- SELECT 
--     co.id,
--     co.tenant_id,
--     co.branch_id,
--     co.as_of_date,
--     co.opening_amount,
--     co.currency_id,
--     cur.code,
--     cur.symbol,
--     cur.name
-- FROM cash_opening co
-- LEFT JOIN ledgerone_public.currencies cur ON co.currency_id = cur.id
-- ORDER BY co.tenant_id, co.as_of_date DESC;
