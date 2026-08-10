-- Migration: Add Country ID to Tax Regimes Table
-- Description: Adds country_id column to tax_regimes table to link tax regimes with specific countries
-- Date: 2024
-- Purpose: Enable country-specific tax regime management

-- Add country_id column to tax_regimes table if it doesn't exist
ALTER TABLE `tax_regimes` ADD COLUMN IF NOT EXISTS `country_id` INT NULL COMMENT 'Foreign key to countries table' AFTER `regime_code`;
