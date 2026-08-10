-- Migration: Add Country ID to Companies Table
-- Description: Adds country_id column to companies table to link companies with specific countries
-- Date: 2024

-- Add country_id column to companies table if it doesn't exist
ALTER TABLE `companies` ADD COLUMN IF NOT EXISTS `country_id` INT NULL COMMENT 'Foreign key to countries table' AFTER `country`;

-- Add foreign key constraint for country_id
ALTER TABLE `companies` ADD CONSTRAINT `fk_companies_country` 
FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Create index for better query performance on country_id
CREATE INDEX IF NOT EXISTS `idx_companies_country_id` ON `companies` (`country_id`);

-- Create composite index for tenant + country
CREATE INDEX IF NOT EXISTS `idx_companies_tenant_country` ON `companies` (`tenant_id`, `country_id`);

-- Note: This migration enables:
-- 1. Country-specific company management
-- 2. Efficient querying by country
-- 3. Proper referential integrity with foreign keys
