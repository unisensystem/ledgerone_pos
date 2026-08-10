-- Migration: Tax Regimes Integration
-- Description: Update products table to store tax regime ID
-- Date: 2024

-- Add tax_regime_id column if it doesn't exist (stores tax regime ID as foreign key)
ALTER TABLE products ADD COLUMN IF NOT EXISTS tax_regime_id INT NULL COMMENT 'Foreign key to tax_regimes table';

-- Add foreign key constraint
ALTER TABLE products ADD CONSTRAINT fk_products_tax_regimes 
FOREIGN KEY (tax_regime_id) REFERENCES tax_regimes(id) ON DELETE SET NULL;

-- Create index for better query performance
CREATE INDEX IF NOT EXISTS idx_products_tax_regime_id ON products(tax_regime_id);

-- Note: The system now uses tax_regime_id (tax regime ID) for tax calculations
-- This replaces the previous hardcoded sales_tax and further_tax percentages
