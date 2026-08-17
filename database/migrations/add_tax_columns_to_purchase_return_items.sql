-- Migration: Add tax_percent and tax_amount columns to purchase_return_items table
-- Purpose: Code references tax_percent/tax_amount but table only has gst_percent/gst_amount

ALTER TABLE `purchase_return_items`
ADD COLUMN `tax_percent` DECIMAL(5,2) DEFAULT 0.00 AFTER `gst_amount`,
ADD COLUMN `tax_amount` DECIMAL(15,2) DEFAULT 0.00 AFTER `tax_percent`;
