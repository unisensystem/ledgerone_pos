-- Migration: Add tax_percent and tax_amount columns to sale_invoice_items table
-- Purpose: Support flexible taxation system (not just GST)
-- These columns will store tax percentage and amount for any tax type

ALTER TABLE `sale_invoice_items` 
ADD COLUMN `tax_percent` DECIMAL(10,2) DEFAULT 0.00 AFTER `gst_amount`,
ADD COLUMN `tax_amount` DECIMAL(15,2) DEFAULT 0.00 AFTER `tax_percent`;

-- Optional: Add tax type column to support different tax regimes
ALTER TABLE `sale_invoice_items`
ADD COLUMN `tax_type` VARCHAR(50) DEFAULT 'standard_gst' AFTER `tax_amount`;
